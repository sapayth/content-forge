# Brand Voice Profile for AI Generation - Implementation Plan

**Target Release:** TBD (v1.9.0+ candidate)
**Status:** Planning Phase — spec for issue #14
**Focus:** Let a site owner describe audience, persona and phrases to avoid once, so every AI-generated post follows it

---

## Overview

Autopilot's Tone select (Professional, Casual, Technical, Conversational) and Approximate
length now work (see `docs/bug.md`, "Tone and Approximate length"). A single tone word is
still too thin: two sites on "Professional" get the same generic AI voice.

This plan adds a **brand voice profile**: a few short text fields (audience, persona,
phrases to avoid, optional sample) that are folded into the AI prompt. It is prompt
text only. No new provider call, no new model, no training.

How the prompt is built today:

- `AI_Content_Generator` holds `$tone`, `$words` and `$topic`
  (`includes/Generator/AI_Content_Generator.php:69-90`).
- `set_style()` (`:118`) and `set_topic()` (`:134`) are the only setters. Both are called
  from `Schedule_Runner` (`includes/Autopilot/Schedule_Runner.php:445-446`) and
  `Preview_Generator` (`includes/Autopilot/Preview_Generator.php:69-70`).
- `build_prompt()` (`:301`) emits `Tone: %s` at `:321-323`, then the custom prompt at
  `:325-327`, then the editor-format block, then runs the `cforge_ai_generation_prompt`
  filter at `:366`.
- The schedule's `ai` config defaults live in `Schedule::default_config()`
  (`includes/Autopilot/Schedule.php:104-110`) and are sanitized in
  `Autopilot_Schedules::sanitize_config()` (`includes/Api/Autopilot_Schedules.php:509-511`).
- The form renders them at `src/js/autopilot/ScheduleForm.jsx:290-320`, with state
  defaults at `:42-48`.

The AI Generate tab, the bulk generator and the scheduled-AI generator build
`AI_Content_Generator` without calling either setter
(`includes/Api/AI.php:494`, `includes/Generator/Post.php:73`,
`includes/Generator/AI_Scheduled_Generator.php:257`), so they get no tone today either.

---

## Problem Statement

Autopilot is marketed as hands-off publishing. The posts it writes all sound the same:
neutral, hedged, full of "In today's fast-paced world". The only lever is the free-text
**Custom prompt** field, which each schedule has to re-type, which has no structure, and
whose help text ("Use to set tone, length, style", `ScheduleForm.jsx:296`) invites people
to cram a whole style guide into one box.

A site owner who runs two Autopilot schedules (a news blog and a how-to blog) has to
maintain the voice in two places and remember to keep them in sync.

**The feature is not "more prompt fields." It is "describe the voice once, and every post
follows it."**

Content Forge's core job is fake content for builders (see `release-plan.md`). Voice is an
Autopilot/AI extra, so the design stays small: no new screen, no new table.

---

## Target User

**The Autopilot operator** — a site owner running scheduled AI posts who wants them to
sound like their site.

Secondary: **an agency** running several sites, who wants to paste the client's style
notes in once.

Not the target: a builder filling a demo site with lorem ipsum. They never see this.

---

## Open questions

The issue asks four questions that only the owner can decide. The rest of this spec
assumes the recommendations.

### Q1 — Where does the voice live?

| Option | Notes |
|---|---|
| A. Site-wide only | One option. Simplest. Two schedules on different topics cannot differ. |
| B. Per-Autopilot only | Fits the existing `ai` config. Re-typed per schedule; no shared voice for AI Generate. |
| C. Site-wide default with a per-Autopilot override | One place to set it; an override when a schedule needs a different voice. |

**Recommendation: C.** The site-wide profile covers the "once" in the issue; the override
costs one boolean in the schedule config. **Unconfirmed — needs owner decision.**
If the owner picks A, drop the override slice (slice 4). If B, drop the site-wide option
and the Settings UI (slices 2 and 3), and the AI Generate tab gets no voice.

### Q2 — Which fields?

| Option | Notes |
|---|---|
| Audience, persona, banned phrases | Short, each maps to one prompt line. |
| + example post | Strongest signal for style, but long: it can add hundreds of tokens to every call. |
| + reading level | Overlaps with Tone and with the audience text. |

**Recommendation: audience, persona, phrases to avoid, and a short writing sample capped
at 1,000 characters. No reading level.** Audience already implies reading level, and a
separate select would fight the Tone select. The sample is capped so it cannot dominate
the prompt. **Unconfirmed — needs owner decision.** If the owner drops the sample, delete
one field and one prompt line; nothing else changes.

### Q3 — Does the AI Generate tab use it too?

| Option | Notes |
|---|---|
| Autopilot only | Smallest change. |
| Autopilot and every AI path | Voice is applied wherever `AI_Content_Generator` runs. |

**Recommendation: every AI path, via the site-wide profile only** (the per-Autopilot
override has no meaning outside a schedule). The profile is applied inside
`AI_Content_Generator`, so no caller changes are needed beyond Autopilot's override.
A site that sets a brand voice and then gets generic text from the AI Generate tab would
read it as a bug. The AI Generate tab gets a visible "Using your brand voice" note and a
per-request opt-out (US-4). **Unconfirmed — needs owner decision.** If the owner picks
"Autopilot only," slice 3 is dropped and the generator applies the voice only when
Autopilot calls `set_voice()`.

### Q4 — Is there a prompt-length limit we must stay under?

Input is not the constraint; output is. Findings:

- Every provider sends a hard-coded `max_tokens` of **2000** for generation
  (`AI_Provider_OpenAI.php:89`, `AI_Provider_Anthropic.php:71`,
  `AI_Provider_DeepSeek.php:81`, `AI_Provider_Mistral.php:81`). That is the cap on the
  *reply*, not the prompt. A "Long (~1500 words)" post is already close to 2000 tokens
  (about 1,100 words at a typical 1.35 tokens/word fits; 1,500 words does not), so
  long posts can be cut off today. This is a **pre-existing issue, separate from this
  feature**, and it should be tracked on its own, not fixed here.
- Input context windows on all five providers are tens of thousands of tokens or more,
  so a few hundred words of voice text is nowhere near a limit. The real cost is money
  and prompt dilution, not a hard cap.

**Recommendation: cap the fields by characters, not tokens**: audience 300, persona 300,
phrases to avoid 500 (up to 20 entries), sample 1,000. Worst case is about 2,100
characters, roughly 500 tokens. Enforce in the REST sanitizer and truncate again in the
generator in case a filter or CLI bypasses REST. **Unconfirmed — needs owner decision**
on the exact numbers; they are constants in one place.

---

## User Stories

### US-1 — Set the voice once for the site (primary)

As a site owner, I want to describe my audience, persona and phrases to avoid in one
place, so every AI post follows it.

**Acceptance criteria:**

- A "Brand voice" panel on the AI settings screen has fields for audience, persona,
  phrases to avoid and an optional writing sample
- Saving stores the profile in one option (see Data Model)
- An empty profile produces **exactly today's prompt**, byte for byte
- Each field has a character limit shown beside it, enforced on save
- The profile never contains or leaks an API key; it is plain text

### US-2 — Autopilot runs follow the voice

As an Autopilot operator, I want scheduled posts to follow the saved voice without
touching each schedule.

**Acceptance criteria:**

- `Schedule_Runner::generate_content()` (the `AI_Content_Generator` call at
  `Schedule_Runner.php:444-447`) produces a prompt containing the voice lines
- The Tone select still works and is sent alongside the voice
- Existing schedules keep working after upgrade with no migration: a missing
  `ai.voice` key means "use the site-wide profile"

### US-3 — Preview matches the real run

As an operator, I want the Preview button to show output written in the same voice a real
run would use.

**Acceptance criteria:**

- `Preview_Generator::generate()` resolves the voice through the same code path as
  `Schedule_Runner`, using the unsaved form config (including an unsaved override)
- A test asserts both builders produce the same voice lines for the same config

### US-4 — The AI Generate tab uses it, and can opt out

As a user generating a one-off post on the AI Generate tab, I want it to match my site's
voice, and I want to turn that off when I don't.

**Acceptance criteria:**

- `POST /ai/generate` applies the site-wide profile by default
- The request accepts `use_brand_voice` (bool, default `true`); `false` skips it
- The AI Generate tab shows a checkbox, checked by default, only when a profile exists
- The bulk generator (`Post.php`) and the scheduled AI generator
  (`AI_Scheduled_Generator.php`) also pick up the profile

### US-5 — Per-Autopilot override

As an agency running a news schedule and a how-to schedule, I want one schedule to use a
different voice.

**Acceptance criteria:**

- The schedule form has "Use a different voice for this Autopilot" (off by default)
- When on, the schedule's own voice fields replace the site-wide profile **as a whole**;
  fields are not merged field by field (merging makes it impossible to clear a field)
- Turning it off keeps the override text saved but unused, so toggling is not destructive

### US-6 — Banned phrases are respected, with a safety net

As an owner, I want phrases I list as banned not to appear.

**Acceptance criteria:**

- The prompt tells the model to avoid each phrase
- A prompt instruction is not a guarantee. After generation, a check flags any banned
  phrase found (case-insensitive) in the title or body, and records it in the run's
  `error_message` so the run is `PARTIAL`
- The post is still created. The check never rewrites or drops content
- The check is a pure function and unit-tested

---

## UX / UI

### AI settings screen (site-wide profile)

A "Brand voice" section below the provider/model fields in `src/js/settings.jsx`:

- **Audience** — single-line text. Help: "Who you write for, e.g. first-time home bakers."
- **Persona** — single-line text. Help: "Who is speaking, e.g. a friendly neighbour who bakes daily."
- **Phrases to avoid** — textarea, one phrase per line.
- **Writing sample (optional)** — textarea. Help: "A short paragraph in your voice. The AI imitates its style, not its subject."
- Live character counter per field, announced with `aria-live="polite"` when a limit is reached.

It saves with the same Save button as the rest of the screen. Components come from
`@wordpress/components` (`TextControl`, `TextareaControl`), as `ScheduleForm.jsx` uses.

### Schedule form

In the existing "AI" section (`ScheduleForm.jsx:290`):

- A `ToggleControl` "Use a different voice for this Autopilot"
- When on, the same four fields appear beneath it. When off, a read-only line reads
  "Using the site-wide brand voice" with a link to the settings screen, or "No brand voice
  set" if the profile is empty
- The **Custom prompt** help text changes from "Use to set tone, length, style" to
  "Extra instructions for this Autopilot. Set your brand voice above." so the two do not
  compete

### Accessibility

- Every field has a visible `<label>`; help text is tied with `aria-describedby`
  (the `@wordpress/components` controls do this)
- Character counters are text, not color alone
- The override toggle reveals fields in DOM order directly after the toggle, so keyboard
  and screen-reader order follows visual order
- Run `node tests/js/contrast.test.js` for any new color use; reuse existing tokens

---

## Data Model

**Site-wide profile**: one option, `cforge_brand_voice`, autoload `no`:

```php
[
    'audience' => '',
    'persona'  => '',
    'avoid'    => [],   // list of phrases
    'sample'   => '',
]
```

**Per-Autopilot override**: a new key in the schedule `ai` config, which is already a
JSON blob read through `Schedule::config_get()`, so there is no schema change:

```php
'ai' => [
    // existing keys unchanged
    'voice' => [
        'override' => false,
        'audience' => '',
        'persona'  => '',
        'avoid'    => [],
        'sample'   => '',
    ],
],
```

`Schedule::default_config()` (`Schedule.php:104`) gains the `voice` default and
`ScheduleForm.jsx:42` mirrors it. `Autopilot_Schedules::sanitize_config()`
(`:509`) merges incoming config over defaults with `array_replace_recursive()`, so an old
saved schedule lacking `voice` gets the defaults, with no migration.

**Why an option, not a custom table or postmeta:** it is site-level settings data of a few
hundred bytes; the plugin already stores AI settings in options
(`AI_Settings_Manager::OPTION_*`). The profile is deleted on uninstall along with the
other `cforge_*` options.

---

## Technical Design

### Backend (PHP)

**New `includes/Settings/Brand_Voice.php`** (namespace `ContentForge\Settings`), static
helpers, the only owner of the option and the limits:

- `get()` — the stored profile with defaults
- `save( array $profile )` — sanitizes and stores; returns the clean profile
- `sanitize( array $profile )` — `sanitize_text_field()` on audience/persona,
  `sanitize_textarea_field()` on the sample, `avoid` split on newlines, trimmed, deduped,
  capped to 20 entries; every field truncated to its limit with `mb_substr()`
- `resolve( $schedule_voice )` — returns the override when `override` is true, otherwise
  `get()`. Shared by `Schedule_Runner` and `Preview_Generator` so Preview and real runs
  cannot drift
- `find_banned( string $text, array $avoid )` — returns the banned phrases found

**`AI_Content_Generator`**

- New property `protected array $voice = []` and `set_voice( array $voice )`, beside
  `set_style()`. Setters stay optional so existing callers keep working
- When no caller sets a voice, the constructor path loads `Brand_Voice::get()` through
  an `apply_filters( 'cforge_brand_voice_profile', ... )`, **unless**
  `set_voice()` was called (including with an empty array, which means "no voice").
  That gives US-4's default without editing four call sites
- `build_prompt()` appends one block after the `Tone:` line (`:321-323`) and before the
  custom prompt:

```
Brand voice:
- Audience: {audience}
- Persona: {persona}
- Never use these phrases: {a}; {b}
- Match the style of this sample, not its subject:
"""
{sample}
"""
```

  Lines for empty fields are omitted; with all empty the block is omitted, so an empty
  profile leaves the prompt unchanged. The voice sits before "Additional Instructions" so
  the per-schedule custom prompt can still refine it.
- Voice text is user-controlled and goes into the prompt, so the sample is fenced in
  triple quotes and any `"""` inside it is removed

**`Schedule_Runner`** and **`Preview_Generator`**: add
`$generator->set_voice( Brand_Voice::resolve( $config['ai']['voice'] ?? [] ) )` next to
the `set_style()` call. After a successful generation, `Schedule_Runner` runs
`Brand_Voice::find_banned()` and appends any hits to the run's errors (US-6), following the
non-fatal pattern already used for the featured image step.

### REST API

| Route | Change |
|---|---|
| `GET /ai/settings` | Also returns `brand_voice` |
| `POST /ai/settings` | Accepts optional `brand_voice`; sanitized by `Brand_Voice::sanitize()`. Same capability as the existing route (`permission_check_settings`) |
| `POST /ai/generate` | Accepts optional `use_brand_voice` (bool, default `true`) |
| Autopilot schedule routes | No new route. `ai.voice` flows through `sanitize_config()`, which gains the voice sanitization call |

All under the existing `cforge/v1` namespace. No new endpoint.

### Frontend (React)

- `src/js/settings.jsx`: the Brand voice section
- `src/js/autopilot/ScheduleForm.jsx`: toggle, override fields, state default
- AI Generate tab: the opt-out checkbox (file confirmed during slice 3)
- Strings in the `content-forge` text domain

---

## Hooks and Filters

| Hook | Type | Use case |
|---|---|---|
| `cforge_brand_voice_profile` | filter (`$profile, $context`) | Supply the voice from code, e.g. an agency plugin that keeps client style guides elsewhere, or return `[]` to switch the voice off in one context. `$context` is `autopilot`, `preview`, `ai_generate`, `bulk` or `scheduled` |
| `cforge_brand_voice_prompt` | filter (`$block, $profile`) | Change the wording of the voice block for a particular provider that follows instructions differently |
| `cforge_brand_voice_banned_found` | action (`$phrases, $post_id, $schedule`) | Run a custom action when a banned phrase slips through, e.g. notify an editor or tag the post for review |
| `cforge_ai_generation_prompt` | existing filter | Still runs last, so it sees the voice block |

---

## Block Editor and Classic Editor

No difference in the feature. The voice is prompt text, and the prompt's editor-format
instructions (`build_prompt()` `:329-350`) are independent of it. Output is formatted by
`$this->editor_type` exactly as now. The settings screen and schedule form are React admin
pages, not editor screens, so neither editor loads them.

Release-plan check: generate one Autopilot post with a voice set on a block theme, and one
on a classic theme with the Classic Editor plugin. Confirm the block markup (or plain HTML)
is intact and no `Brand voice:` text leaks into the post body.

---

## Free vs Pro

Everything is free. Content Forge has no Pro tier. If one is added later, a single
site-wide voice stays free; multiple saved named voices would be the natural paid
extension, and this design leaves room for it (the `cforge_brand_voice_profile` filter and
the `resolve()` seam).

---

## Security

- Capability: saving the profile uses the existing `permission_check_settings` callback
  (`manage_options` class); the REST permission callback already guards `/ai/settings`
- All fields sanitized on input (`sanitize_text_field`, `sanitize_textarea_field`) and
  escaped on output by React; no raw HTML is stored or rendered
- **Prompt injection:** the voice is entered by an admin, so it carries the same trust as
  the existing Custom prompt. It still goes in the user prompt, not the system role, and
  the sample is fenced so it reads as data. Banned-phrase checking is a quality net, not a
  security control
- Character limits are enforced server-side, not just in the form
- The option contains no secrets; it is exported and removed like any other plain option

---

## Edge Cases

| Case | Behavior |
|---|---|
| Empty profile | Prompt identical to today's |
| Old schedule with no `ai.voice` | Defaults fill in; site-wide profile used |
| Override on, all override fields empty | Voice off for that schedule; the site-wide profile is **not** used. That is what "override" means, and the form says so |
| Banned phrase appears anyway | Post kept; run marked `PARTIAL` with the phrase named |
| Sample contains `"""` or prompt-like text | Triple quotes stripped; text treated as a style sample |
| Oversized input via REST or CLI | Truncated server-side to the field limits |
| Provider `max_tokens` (2000) cuts a long post short | Pre-existing, unrelated; tracked separately (see Q4) |
| Multilingual content (see `multilingual-plan.md`) | Voice fields are written in the owner's language; the language line from that plan is appended after the voice block |
| Voice text in a different language than the post | The model decides; documented as a known limitation |

---

## Testing

**Unit (PHPUnit, `tests/unit/`):**

- `Brand_Voice::sanitize()` — limits, newline splitting, dedupe, 20-entry cap
- `Brand_Voice::resolve()` — override on, off, and empty-override cases
- `Brand_Voice::find_banned()` — case-insensitive, multi-word, no false positive on substrings of other words
- `AI_Content_Generator::build_prompt()` — empty voice leaves the prompt unchanged
  (assert equality with the pre-change prompt); each field adds exactly one line; the
  filter runs after the block
- Preview/real-run parity (US-3)
- `Autopilot_Schedules::sanitize_config()` — old config without `voice` is filled in

**E2E (`tests/e2e/`):** save a voice on the settings screen, reload, see it persisted;
toggle the override on a schedule and see the override fields appear.

**Manual:** run Preview with a distinctive persona and banned phrase on one provider;
confirm the output reflects the persona.

---

## Proposed Build Slices

One PR each, in order:

| # | Slice | Depends on |
|---|---|---|
| 1 | `Brand_Voice` class (option, sanitize, resolve, find_banned) + unit tests | — |
| 2 | `AI_Content_Generator::set_voice()` + prompt block + `cforge_brand_voice_*` filters + tests | 1 |
| 3 | `GET/POST /ai/settings` `brand_voice`, Settings screen panel, AI Generate tab opt-out, `use_brand_voice`; bulk and scheduled generators pick it up | 2 |
| 4 | Autopilot: `ai.voice` default and sanitization, `Schedule_Runner` + `Preview_Generator` wiring with parity test, form toggle and override fields | 2 (and 3 for the shared Save/section styling) |
| 5 | Banned-phrase check in `Schedule_Runner` + `cforge_brand_voice_banned_found` action | 2 |
| 6 | readme.txt features, user guide, release-plan update, e2e spec | all |

Slices 1–3 make a releasable feature without Autopilot changes; slice 4 adds the override
(drop it if the owner picks Q1 option A).

---

## Out of Scope

- **Multiple named voices** or a voice library. One site-wide profile plus one per-schedule
  override
- **Fine-tuning or training** on the owner's existing posts, and **auto-importing** a voice
  from existing site content
- **Rewriting** posts after generation to remove banned phrases. We flag; we do not edit
- **Voice for non-AI generators.** The bulk generator's lorem-style text is unaffected;
  only AI paths use the profile
- **Per-provider prompt tuning** beyond the `cforge_brand_voice_prompt` filter
- **Raising or exposing the 2,000 `max_tokens` cap.** Worth its own issue
- **Reading-level control**, and **WP-CLI flags** for voice (CLI inherits the site-wide
  profile via the generator; a flag can follow on demand)

---

## Sizing

| Piece | Size |
|---|---|
| `Brand_Voice` class + tests | Small |
| Generator prompt block + filters | Small |
| REST settings + Settings panel + AI Generate opt-out | Medium |
| Autopilot override (config, runner, preview, form) | Medium |
| Banned-phrase check | Small |
| Docs + e2e | Small |

Mostly wiring text fields into one prompt. The risk is not code size; it is prompt
quality, which only manual checks on real providers can judge.

---

## Rollout

Ships dormant: an empty profile changes nothing. No migration, no new table. Announce with
a readme note that the voice adds a few hundred input tokens per generated post, billed to
the user's own API key.
