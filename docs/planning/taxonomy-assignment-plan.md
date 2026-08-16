# Taxonomy Assignment for Generated Content - Implementation Plan

**Target Release:** v1.7.0
**Status:** Planning Phase
**Focus:** Assign taxonomy terms to bulk-generated posts, pages, CPTs and products

---

## Overview

Content Forge can generate posts and it can generate terms, but the two halves
never meet. Every post the bulk generator creates lands in `Uncategorized` with
no tags. This plan closes that gap for the traditional (non-AI) bulk generator
across all post types and all registered taxonomies.

Autopilot already assigns a single fixed category and a fixed tag list
(`includes/Autopilot/Publishing/Post_Builder.php`). That path is out of scope
here — see "Out of Scope" below for why.

---

## Problem Statement

The WordPress template hierarchy requires a theme to handle `category.php`,
`tag.php`, `taxonomy-{tax}.php` and `archive.php`. Content Forge currently
cannot populate a single one of them.

A developer generates 50 posts and finds:

- Every category and tag archive is empty
- The category widget reads "Uncategorized (50)"
- Breadcrumbs render one level deep
- Term-based "related posts" queries return nothing
- A category-filtered nav menu item leads to a blank page

The workaround is to open the posts list and hand-assign terms to 50 posts —
the exact tedium the plugin exists to remove.

**The feature is not "assign categories." It is "make the generated site
exercise the template hierarchy."** That framing defines done: if a theme's
archive templates still cannot be reviewed after generating content, the
feature is not finished.

This is table stakes for a dummy-data plugin, not a differentiator. It is a
hole to close, not a bet to place.

---

## Target User

**The Builder** — theme developer, plugin developer, agency dev, freelancer.
Uses the bulk generator, no API key, wants a site that looks and behaves real
so client-facing bugs surface before the client finds them.

This is distinct from **The Publisher** (solo creator using AI + Autopilot),
who is served by the existing Autopilot targeting settings.

---

## User Stories

### US-1 — Distribute posts across terms (primary)

> As a theme developer, I want generated posts distributed across existing
> categories and tags, so that category and tag archives have content and I can
> verify my archive templates.

**Acceptance criteria:**

- [ ] The generation form shows a taxonomy section listing every taxonomy
      registered for the selected post type
- [ ] Each taxonomy offers three modes: **None**, **Specific terms**,
      **Random from existing**
- [ ] Terms-per-post is configurable per taxonomy as a min/max range, so a post
      can get 1 category and 2-4 tags
- [ ] Distribution across terms is uneven — some terms receive many posts, some
      receive one. Even distribution is unrealistic and hides pagination bugs
- [ ] Works for any taxonomy on any post type, not only `category` and
      `post_tag`
- [ ] Hierarchical taxonomies assign the selected term only; no automatic
      parent assignment (see Out of Scope)
- [ ] Selecting no taxonomy options preserves today's behaviour exactly

### US-2 — Product categories for demo stores

> As an agency dev prepping a client demo store, I want generated products
> assigned to product categories, so the shop page filters and "shop by
> category" blocks are not empty.

**Acceptance criteria:**

- [ ] `product_cat` and `product_tag` appear in the taxonomy section when the
      Custom Post Types screen targets `product`
- [ ] Generated products appear under their assigned categories on the shop
      page, in the product category widget, and in breadcrumbs
- [ ] No WooCommerce-specific code path — this falls out of US-1 for free

This is the story that loses deals. A demo store with no `product_cat` terms
renders as visibly broken: no sidebar filters, no category grid, no
breadcrumbs.

### US-3 — Chain term generation into post generation

> As a developer setting up a fresh site, I want to generate 8 categories and
> 30 tags, then generate 50 posts that use them, in one sitting.

**Acceptance criteria:**

- [ ] The taxonomy term list reflects all existing terms, including ones
      Content Forge created moments earlier
- [ ] No new plumbing — the picker reads live term data on each page load
- [ ] When a taxonomy has no terms, the UI says so and points at the Taxonomy
      generation screen rather than showing an empty dropdown

---

## Out of Scope

Deliberately excluded, with the reason for each:

| Excluded | Reason |
|---|---|
| Auto-creating terms during post generation | Term generation already exists as its own screen. Two creation paths is confusion, not value. |
| Hierarchical parent auto-assignment | Real but narrow edge case. Revisit if requested. |
| AI-chosen categories for Autopilot posts | Different job: *correct* categorization of *real* content vs *varied* categorization of fake content. Different ICP, different feature. Do not bundle. |
| Per-term post count targets ("exactly 10 posts in News") | Speculative. The random distribution covers the testing need. |

---

## Technical Design

### Backend (PHP)

**`includes/Generator/Post.php`**

The generator loop already has a post-insert hook block (product options, EDD
download defaults, TEC event defaults). Term assignment slots in beside them:

```php
if ( ! empty( $args['taxonomy_options'] ) ) {
    $this->assign_terms( $post_id, $post_type, $args['taxonomy_options'] );
}
```

New protected method `assign_terms( $post_id, $post_type, $options )`:

- Iterate the requested taxonomies
- Skip any taxonomy not registered for `$post_type`
  (`is_object_in_taxonomy()`) — never trust the client
- Resolve the term pool: explicit IDs, or all existing terms for random mode
- Pick a random count within the configured min/max, capped at pool size
- Call `wp_set_object_terms( $post_id, $term_ids, $taxonomy, false )`

Uneven distribution comes free from independent random picks per post; no
weighting logic needed.

**`includes/functions/general.php`**

New helper `cforge_get_assignable_taxonomies( $post_type )` returning public
taxonomies registered for the post type, excluding internal ones
(`post_format`, `wp_theme`, and anything with `public === false`). Filterable
via `cforge_assignable_taxonomies` so integrations can adjust.

### REST API

**`includes/Api/Post.php` — `POST /cforge/v1/posts/bulk`**

Add an optional `taxonomy_options` parameter:

```json
{
  "taxonomy_options": {
    "category":  { "mode": "random",   "min": 1, "max": 1 },
    "post_tag":  { "mode": "specific", "terms": [4, 9, 17], "min": 2, "max": 4 }
  }
}
```

`mode` is one of `none` | `specific` | `random`. Omitting the key entirely
preserves current behaviour.

**Refactor note:** the `$args` assembly is currently duplicated across three
branches in `handle_bulk_create()` — the AI path, manual mode, and auto mode.
Rather than threading a fourth option through all three, build the sanitized
`taxonomy_options` array **once before the branch** and merge it into the
shared base args. This shrinks the diff and stops the next option from adding a
fourth copy.

**New endpoint: `GET /cforge/v1/taxonomies`**

Query param `post_type`. Returns each assignable taxonomy with its label,
hierarchical flag, term count, and up to 200 terms (`id`, `name`, `count`).

Why a dedicated endpoint rather than core's `/wp/v2/categories`: custom
taxonomies are only exposed there when registered with `show_in_rest`, which is
not guaranteed for the CPTs this plugin targets. One endpoint covers every
taxonomy uniformly.

### Frontend (React)

**New component: `src/js/components/TermSelect.jsx`**

Mirror `AuthorSelect.jsx` closely — same three-mode radio pattern
(None / Specific / Random), same prop shape, same Tailwind class conventions.
Reuse `MultiSelect.jsx` for the specific-terms picker.

Props:

```
taxonomy         { slug, label, hierarchical, terms: [] }
mode             'none' | 'specific' | 'random'
onModeChange     (mode) => void
selected         Array<number>
onSelectedChange (ids) => void
range            { min, max }
onRangeChange    ({ min, max }) => void
```

**`src/js/pages-posts.jsx` and `src/js/cpt.jsx`**

- Fetch assignable taxonomies from `GET /cforge/v1/taxonomies` when the
  selected post type changes
- Render one `TermSelect` per returned taxonomy
- Build `payload.taxonomy_options` in the existing payload assembly, omitting
  taxonomies left in `none` mode

**Data loading note:** authors are currently localized into `window.cforge` by
`Admin::get_authors_for_select()`. Terms should **not** follow that pattern — a
site can have thousands of tags, and localizing them all bloats every admin
page load. Fetch on demand instead. The common case (random mode) needs no term
list client-side at all, since the server resolves the pool.

---

## Security

- Validate every taxonomy against `is_object_in_taxonomy( $post_type, $tax )`
  server-side. A client-supplied taxonomy that is not registered for the post
  type is dropped silently.
- Sanitize term IDs with `absint()`; drop IDs that do not resolve to a real
  term in that taxonomy via `term_exists()`.
- Clamp min/max to a sane ceiling (proposed: 20 terms per taxonomy per post) so
  a crafted request cannot force thousands of `wp_set_object_terms` writes.
- Existing `permission_check` on the bulk endpoint continues to gate the route;
  no new capability needed.

---

## Edge Cases

| Case | Behaviour |
|---|---|
| Taxonomy has zero terms | UI shows an empty-state pointing at the Taxonomy screen; server treats as `none` |
| `max` exceeds available terms | Cap at pool size |
| `min` greater than `max` | Swap them server-side rather than erroring |
| Non-hierarchical taxonomy | Same code path; `wp_set_object_terms` handles both |
| Post type registered with no taxonomies | Section hidden entirely |
| Term deleted between page load and submit | `term_exists()` check drops it; generation continues |
| AI generation path | Term options apply identically — the background queue receives the same args |

---

## Testing

**Manual check** (`tests/manual/`, following the existing script pattern, run
via `wp eval-file`):

- Generate 20 posts with `category` random 1-1 and `post_tag` random 2-4;
  assert every post has exactly one category and 2-4 tags
- Assert term distribution is uneven — at least one term has a different post
  count than another
- Assert a taxonomy not registered for the post type is rejected
- Assert omitting `taxonomy_options` leaves posts untouched (regression guard)
- Generate products with `product_cat` and assert shop-page term counts update

**Browser verification:**

- Category archive renders generated posts on a block theme and a classic theme
- WooCommerce shop page category filter lists generated products

---

## Sizing

Small — estimated 1-2 days.

No schema change, no migration, no new table. The generator already has the
post-insert hook block, `AuthorSelect.jsx` supplies the UI pattern to copy, and
`MultiSelect.jsx` supplies the picker. The bulk of the work is the taxonomies
endpoint and threading the payload through the two generation screens.

---

## Rollout

1. Backend + REST, verified by the manual check script
2. `TermSelect.jsx` and the Pages/Posts screen
3. Custom Post Types screen (picks up products for free)
4. Update readme.txt features list and the user documentation

Default state is `none` for every taxonomy, so existing users see no behaviour
change until they opt in.
