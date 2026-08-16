# AI Featured Images for Autopilot - Implementation Plan

**Target Release:** v1.8.0
**Status:** Implemented — see `tests/manual/featured-image-check.php` for the self-check
**Focus:** Give every Autopilot-generated post a real featured image, generated from the post's own title

---

## Overview

Autopilot generates a title and body, inserts the post, and stops. No featured image is
attached — not an AI one, not a placeholder, not even the Picsum image the traditional
bulk generator has been able to set since v1.1.0.

The class docblock at `includes/Autopilot/Publishing/Post_Builder.php:17` already promises
"post-insert taxonomy/featured-image attachment." Nothing implements it.

The config slot is half-built too. `Schedule::default_config()` has carried
`targeting.featured_image` with `source` and `url` keys since v1.5.0
(`includes/Autopilot/Schedule.php:99`), the REST layer sanitizes both fields
(`includes/Api/Autopilot_Schedules.php:487-488`), and `ScheduleForm.jsx:40` seeds them into
form state. Nothing renders a control for them and nothing reads them at runtime. This
plan fills in the dead slot rather than inventing a new one.

---

## Problem Statement

Content Forge markets Autopilot as a hands-off publishing assistant with an
"immediate publish" mode. Nobody auto-publishes imageless posts to a live site. So the
mode we market hardest is the mode users trust least, and the workaround is to open every
generated draft and set an image by hand — the exact tedium the plugin exists to remove.

Downstream, a missing `_thumbnail_id` also means:

- Block themes render post cards with an empty media slot or a stretched fallback
- Open Graph tags fall back to a site-wide default, so shares look identical
- Archive and related-post grids break their visual rhythm
- Any theme whose card layout assumes a thumbnail gets a layout it was never designed for

**The feature is not "call an image API." It is "make an Autopilot post publishable
without a human touching it."** That framing sets the bar: if a run finishes and the post
still needs manual work before it can go live, the feature has not landed.

---

## Target User

**The Autopilot operator** — solo creator, niche site owner, agency running content for a
client. They configured a schedule once and expect to review a queue, not assemble posts.

Secondary: **the Builder** (theme/plugin developer) populating a demo site, who wants
thumbnails varied enough that grid layouts look real. The existing placeholder path
already serves them; they are not the reason to build this.

---

## User Stories

### US-1 — AI image from the post's own title (primary)

As an Autopilot operator, I want each generated post to get an image that reflects what
the post is actually about, so the post is ready to publish.

**Acceptance criteria:**

- A schedule can be set to `ai` featured-image source
- Each generated post gets a distinct image generated from its title
- The image is sideloaded into the Media Library and set as `_thumbnail_id`
- The attachment is tracked in the `*_cforge` table, so existing cleanup removes it
- Alt text is set from the post title
- An image failure never fails the post — the post is created regardless

### US-2 — Placeholder without an image-capable provider

As a user on Anthropic, Mistral, or DeepSeek — none of which expose an image endpoint —
I want a featured image anyway, so my layout is not the odd one out.

**Acceptance criteria:**

- Selecting `placeholder` source uses the existing `Generator\Image` path (Picsum / Placehold.co)
- Selecting `ai` on a provider with no image model falls back to the placeholder path,
  and the run record notes the fallback
- The UI states which providers support AI images before the user picks

### US-3 — Off by default

As a user who does not want extra API spend or extra Media Library rows, I want the
current behavior preserved.

**Acceptance criteria:**

- `source: 'none'` remains the default and produces exactly today's behavior
- No image API call is made when the source is `none`
- Existing schedules keep working untouched after upgrade — the key already exists in
  their stored config with the value `none`

---

## Out of Scope

- **Image generation for the traditional bulk generator.** It already has the placeholder
  path; adding paid AI calls to a "generate 50 posts" button is a cost trap. Revisit only
  on demand.
- **Inline body images.** One featured image per post. Body images multiply cost per post
  by an unbounded factor and need placement logic in both Block and Classic output.
- **Image editing, cropping, or style presets.** One prompt, one 1024x1024 image.
- **Stable Diffusion / Midjourney / other non-configured providers.** We only call
  providers the user has already configured a key for.
- **The `url` sub-key** in the existing config. It implies "always use this one image,"
  which is a different feature (a fixed fallback image). Leave the key in place, keep
  sanitizing it, do not wire or expose it yet.

---

## Technical Design

### Backend (PHP)

**`includes/Generator/Providers/AI_Provider_Base.php`**

Add two non-abstract methods so the three text-only providers need no changes at all:

```php
public function supports_images() {
    return false;
}

public function generate_image( string $prompt ) {
    return new WP_Error( 'cforge_no_image_support', ... );
}
```

Adding abstract methods here would force empty implementations into all five providers —
a default-returning-false is the smaller diff and the safer one.

**`AI_Provider_OpenAI.php` and `AI_Provider_Google.php`**

Override both. OpenAI posts to `https://api.openai.com/v1/images/generations` with
`gpt-image-1`; Google uses the Gemini image model on the endpoint it already builds.
Each returns a temp file path or `WP_Error`. Both go through the existing
`make_request()` so timeout, error shaping, and header handling stay in one place.

Note both APIs can return base64 rather than a URL, so the return contract is a local
temp file path, not a URL — that keeps the sideload step provider-agnostic.

**`includes/Generator/Image.php`**

Add one method beside the existing `generate()`:

```php
public function generate_from_ai( string $prompt, string $title )
```

It calls the provider, then reuses the sideload block already at
`includes/Generator/Image.php:62-85` — `media_handle_sideload()`, `track_generated()`,
temp-file cleanup. Extract those lines into a private `sideload( $tmp_file, $filename, $title )`
so both paths share them; that is the only refactor this plan asks for. Set
`_wp_attachment_image_alt` from the title in that shared helper, which fixes the missing
alt text on the placeholder path as a side effect.

**`includes/Autopilot/Schedule_Runner.php`**

After a successful `insert_post()` and before `do_action( 'cforge_autopilot_post_created' )`
(around line 238), attach the image. Wrap in its own try/catch: an image error appends to
`$errors` for the run record but must not `continue` past the post.

Prompt: the post title, plus a short style suffix ("editorial photograph, no text"), kept
in one filterable string.

```php
apply_filters( 'cforge_autopilot_image_prompt', $prompt, $post_id, $schedule );
```

**Cost guard.** The daily post cap (`safety.daily_post_cap`) already bounds calls per day,
so one image per post inherits an existing ceiling. No new limiter.

### REST API

`includes/Api/Autopilot_Schedules.php:487` already sanitizes `featured_image.source` with
`sanitize_key()`. Tighten it to a whitelist — `none`, `placeholder`, `ai` — falling back to
`none` on anything else. That is the whole API change; no new endpoint.

For the UI to know which providers support images, expose `supports_images` on the
existing AI settings response rather than hardcoding the provider list in JS.

### Frontend (React)

**`src/js/autopilot/ScheduleForm.jsx`**

Add a "Featured image" control to the targeting section: three radio options (None /
Placeholder / AI-generated), reusing `Field.jsx` and the form's existing patterns. The
state key already exists at line 40. Show a `Notice` when `ai` is selected on a provider
without image support, saying the run will fall back to placeholders.

---

## Security

- Prompt is built from the post title we generated; still pass through
  `sanitize_text_field()` before it goes into the request body
- API keys keep flowing through `AI_Settings_Manager::get_api_key()` — decrypted at call
  time, never logged, never returned in a REST response
- Downloaded images go through `media_handle_sideload()`, which runs WordPress's own MIME
  and extension checks; never trust the provider's declared content type
- Provider endpoints stay hardcoded in the provider classes — no user-supplied URLs
- `source` is whitelisted at the REST boundary, not just sanitized

---

## Edge Cases

| Case | Behavior |
|---|---|
| Provider has no image model | Fall back to placeholder, silently — see "Fallback reporting" below |
| Image API returns an error or times out | Post keeps its content, no thumbnail, error appended to run errors |
| `download_url()` / sideload fails | Same as above; temp file cleaned up |
| Content-policy refusal on a title | Same as above — do not retry with a mangled prompt |
| Post insert succeeded, image call is slow | Image call sits outside the insert; a slow call delays the run, not the post |
| Schedule created before v1.8.0 | Config already contains `source: 'none'`; no migration needed |
| Attachment deleted from Media Library | Existing `Cleanup` (v1.7.0) untracks it |
| Multiple posts in one run | One image call per post, bounded by `posts_per_run` (max 5) |

### Fallback reporting

A fallback to the placeholder image is recorded against the run only when its cause is
transient. The two cases differ in kind:

- **Structural** (`cforge_no_image_support`, `cforge_no_api_key`) — the provider has no
  image model, or no key is stored. True on every run until the user changes settings, and
  the schedule form already warns about it at config time. Silent: recording it would mark
  every run `PARTIAL` forever and train the user to ignore run status.
- **Transient** (API error, timeout, content-policy refusal, malformed response) —
  rare and actionable. Recorded in the run's `error_message`, which makes the run
  `PARTIAL`. That is the honest status: the post is fine, the image degraded.

The silenced codes are a contract between `Schedule_Runner::attach_featured_image()` and
the emitters in `Image::generate_from_ai()` / `AI_Provider_Base::generate_image()`. Rename
an emitter without updating the list and every run silently starts reporting `PARTIAL`
again — `tests/manual/featured-image-check.php` asserts they still match.

---

## Testing

**Manual check** (`tests/manual/`, following the existing script pattern):

1. Schedule with `source: 'none'` → post created, no `_thumbnail_id` (regression guard)
2. Schedule with `source: 'placeholder'` → thumbnail set, attachment tracked, alt text set
3. Schedule with `source: 'ai'` on OpenAI → thumbnail set, image reflects the title
4. Schedule with `source: 'ai'` on Anthropic → placeholder fallback, run notes it
5. Force a provider error → post still created, error recorded, run not failed

**Unit-testable without network:** the fallback decision (`supports_images()` false → placeholder)
and the `source` whitelist. Both are pure branches; cover them when PHPUnit lands
(see `docs/todo.md`).

**Browser verification:** run a schedule from the Autopilot screen, confirm the thumbnail
renders in the posts list and on a block theme's archive.

---

## Sizing

| Piece | Size |
|---|---|
| Base provider defaults | Trivial — two methods |
| OpenAI + Google image calls | Medium — one endpoint each, both reuse `make_request()` |
| `Image::sideload()` extraction + `generate_from_ai()` | Small |
| Runner attachment step | Small |
| REST whitelist + `supports_images` exposure | Trivial |
| ScheduleForm control | Small — state key already exists |

The bulk of the work is two HTTP integrations. Everything else is wiring a config slot
that was left open in v1.5.0.

---

## Rollout

Ships behind the config default: `source: 'none'` means nothing changes for anyone until
they opt in. No migration, no new option, no new table.

Announce alongside a readme note that AI images consume the user's own API credits at
roughly one image per generated post, bounded by their daily post cap.
