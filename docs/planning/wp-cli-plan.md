# WP-CLI Commands - Implementation Plan

**Target Release:** v1.8.0
**Status:** Spec approved
**Focus:** Generate and clean up Content Forge content from the command line

---

## Overview

Everything Content Forge does today starts in wp-admin. Developers seed sites
from shell scripts, CI jobs and `wp-env`, where an admin form is no use. Our own
how-to recordings (`tests/howto/*.spec.js`) already shell out to `wp eval` to
work around this.

This plan adds a `wp cforge` command group that generates, lists and deletes
the same content the admin screens do, with the same validation and the same
tracking, so CLI-made content shows up on the Dashboard and in Delete All.

---

## Problem Statement

A developer building a theme wants a repeatable seed:

```bash
wp-env start
wp plugin activate content-forge
# ...then 40 posts across categories, 10 users, 120 comments, 25 products
```

Today the second half is a manual trip through four admin screens, every time
the environment is reset. CI can't do it at all.

**Done means:** a seed like the one above is a short shell script that runs
unattended, produces tracked content, and fails loudly with a non-zero exit
code when something is wrong.

---

## Design Decision: dispatch through the existing REST routes

The generators share a clean interface (`generate( $count, $args )` /
`delete( $ids )`), but they are not the whole story. The REST handlers do the
real orchestration: `Api\Post::handle_bulk_create()` alone is ~380 lines of
validation, author pools, date ranges, taxonomy options, the docs hierarchy and
the AI queue hand-off. Users, comments and taxonomy have the same split.

| Option | Pros | Cons |
|---|---|---|
| **A. CLI builds a `WP_REST_Request` and runs it with `rest_do_request()`** | Zero duplicated logic. Same validation, messages and tracking as the UI. CLI can't drift from the admin. | CLI output depends on REST response shapes. Needs a user with the right capability. |
| B. Extract a shared service class used by both | Cleaner long-term layering | Refactors working, recently fixed code across 4 controllers. Bigger diff, more risk, no user-facing gain. |
| C. CLI calls the generators directly | Simple-looking | Re-implements or skips all validation; CLI content would differ from UI content |

**Recommendation: A.** It is the smallest change that keeps one source of
truth. B can follow later if the REST layer ever needs untangling for its own
sake; the CLI wouldn't need to change.

### User context

REST permission checks need a user (`edit_posts` via
`cforge_rest_permission_check`, `manage_options` for Autopilot). Rule:

- If the global `--user=<id|login|email>` flag is passed, use it (standard WP-CLI).
- Otherwise use the first administrator and print a one-line notice saying which.
- No administrator found → error, exit 1.

---

## Commands (Phase 1)

All commands live under `wp cforge`. Flag names mirror the admin form labels
where possible.

### `wp cforge generate posts`

Generate posts, pages or any supported post type, including WooCommerce
products, EDD downloads, The Events Calendar events, WPUF subscriptions and
weDocs docs.

| Flag | Default | Maps to REST param |
|---|---|---|
| `--count=<n>` | `10` | `post_number` |
| `--post_type=<type>` | `post` | `post_type` |
| `--status=<status>` | `publish` | `post_status` (`publish`, `draft`, `pending`, `private`, `future`) |
| `--comment_status=<open\|closed>` | `closed` | `comment_status` |
| `--parent=<id>` | `0` | `post_parent` |
| `--from=<Y-m-d>` `--to=<Y-m-d>` | none | `date_from`, `date_to` (random dates within the range) |
| `--authors=<me\|random\|ids>` | `me` | `author_mode` + `authors` (`--authors=3,7,9` means specific) |
| `--featured-image[=<sources>]` | off | `generate_image` + `image_sources` (e.g. `picsum,placehold`) |
| `--random-terms=<tax[:min-max],...>` | none | `taxonomy_options` in `random` mode, e.g. `category:1-2,post_tag:2-5` |
| `--no-excerpt` | excerpts on | `generate_excerpt=false` |
| `--editor=<block\|classic>` | `block` | `editor_type` |
| `--porcelain` | | Print only the created IDs, one per line |

Product, download, event and docs options (price ranges, sale ranges, docs
level counts) use the plugin defaults in Phase 1. See Open Questions.

```bash
wp cforge generate posts --count=40 --random-terms=category:1-2,post_tag:2-4 --featured-image
wp cforge generate posts --post_type=product --count=25 --featured-image=picsum
wp cforge generate posts --post_type=page --count=5 --status=draft --porcelain
```

### `wp cforge generate users`

| Flag | Default | Maps to |
|---|---|---|
| `--count=<n>` | `10` | `user_number` |
| `--roles=<role,...>` | `subscriber` | `roles` |
| `--porcelain` | | IDs only |

### `wp cforge generate comments`

| Flag | Default | Maps to |
|---|---|---|
| `--count=<n>` | `20` | `comment_number` |
| `--status=<approve\|hold\|spam>` | `hold` (same as the admin form) | `comment_status` (`approve` → `1`, `hold` → `0`, `spam` → `spam`) |
| `--replies[=<probability>]` | off | `allow_replies` + `reply_probability` (0–100, default 30) |
| `--porcelain` | | IDs only |

Comments only land on published posts with comments open, and generated posts
default to `--comment_status=closed`. A seed script needs
`generate posts --comment_status=open` first; when no post qualifies, the
error should say exactly that.

### `wp cforge generate terms`

| Flag | Default | Maps to |
|---|---|---|
| `--count=<n>` | `10` | `count` |
| `--taxonomy=<tax>` | `category` | `taxonomy` |
| `--porcelain` | | IDs only |

### `wp cforge list <posts|users|comments|terms>`

Lists tracked (Content Forge-generated) items via the existing `/list` routes.

- `--post_type=<type>` (posts only)
- `--format=<table|csv|json|ids|count>` (default `table`)
- `--fields=<fields>` (standard WP-CLI)

### `wp cforge delete <posts|users|comments|terms>`

Deletes generated content only. Never touches content Content Forge didn't make.

- `--all`: everything of that kind (the admin's Delete All)
- `<id>...`: specific IDs; each must be tracked, otherwise skip with a warning
- `--yes`: skip the confirmation prompt. Required for unattended scripts; without it, `--all` asks first.

```bash
wp cforge delete posts --all --yes
wp cforge delete users 12 13 14
```

### `wp cforge status`

One screen of what the Dashboard shows: tracked counts per type, Autopilot
health (last dispatcher run) and the number of active Autopilots. Supports `--format=json`.

---

## Output and exit codes

- Success: `Success: Created 40 posts.` plus `Warning:` lines for anything skipped.
- `--porcelain`: created IDs only, so `wp cforge generate posts --porcelain | xargs ...` works.
- REST 4xx/5xx → `WP_CLI::error( <the REST message> )`, exit 1. The CLI shows the same wording as the admin.
- Long runs get a WP-CLI progress bar per batch.

---

## Implementation outline

- `includes/CLI/Command.php`: one class, a public method per subcommand, docblock
  synopses (WP-CLI builds `--help` from them).
- Register only when `defined( 'WP_CLI' ) && WP_CLI`, from the main plugin file.
- One private helper turns flags into a `WP_REST_Request`, dispatches it, and maps the response to output or an error.
- No changes to the REST controllers or generators.

---

## Testing

- **Unit (PHPUnit, existing suite):** the flag parsers (`--random-terms`,
  `--authors`, date flags) are pure functions. Cover valid input, bad input and edge cases.
- **Integration (wp-env, CI):** a shell smoke test that generates each type,
  lists it, asserts counts, deletes `--all --yes`, and asserts zero.
- **Manual:** `wp help cforge` reads well; errors carry the REST message.

---

## Phase 2 (separate release)

- `wp cforge generate posts --ai --content-type=<type> [--prompt=<text>]`: AI is
  queued in the background today. The CLI would queue it, print the job ID,
  and offer `--wait` to poll `generation/status`. Spends provider credits, so it
  needs its own UX thought (cost warning, `--yes`).
- `wp cforge autopilot list|run|pause|resume <id>`: wraps the existing Autopilot
  schedule routes (`run-now`, `pause`, `resume`).
- `wp cforge seed <file.json>`: run a whole seed definition in one command.

---

## Out of Scope

- New generation capabilities. The CLI exposes exactly what the admin can do.
- Changing REST routes, params or response shapes.
- Multisite network-wide generation (`--url` per site works as usual).

---

## Decisions

1. **Product / docs / event options in Phase 1:** plugin defaults only. A JSON
   flag (`--product-options='{...}'`) gets added only if someone asks for it.
2. **Default user:** without `--user`, use the first administrator and print a
   notice naming them. Friendlier for wp-env and CI.
3. **Command name:** `wp cforge`, with `wp content-forge` registered as an alias.
