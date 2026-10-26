# Multilingual Generation (WPML, Polylang) - Implementation Plan

**Target Release:** TBD (v1.8.0 candidate)
**Status:** Planning Phase — spec for issue #27
**Focus:** Generate posts, pages, CPTs and terms in specific languages, linked
as translations, on sites running WPML or Polylang

---

## Overview

Content Forge ignores the multilingual plugin. Every generated post and term
gets whatever language WPML or Polylang assigns on save — usually the default
or the current admin language — and nothing is linked as a translation.

This plan teaches the bulk generator, the term generator and the WP-CLI
commands to place generated content in chosen languages and link it into
translation groups. Autopilot is out of scope (see "Out of Scope").

---

## Problem Statement

A developer building a multilingual theme or plugin generates 30 posts on a
Polylang site with English, French and Arabic, and finds:

- All 30 posts are English; the French and Arabic front ends are empty
- The language switcher has nothing to switch to, so it can't be tested
- There's no way to check how "translation missing" is handled (switcher
  fallback, hreflang output, redirect to the home page)
- The RTL language never renders any content, so RTL layout bugs stay hidden
- Categories exist in one language only, so translated archives are empty

The workaround is to create translations by hand, one post at a time, in the
WPML or Polylang editor.

**The feature isn't "write good French." It's "make a multilingual site
exercise its language switching, translation links and per-language
archives."** That defines done: after generating, every configured language
has content, translation groups are linked, and some translations are missing
on purpose.

---

## Target User

**The Builder** — theme or plugin developer, agency dev building a
multilingual client site. Uses the bulk generator or WP-CLI, usually without
an API key, and wants a multilingual site that looks populated so switcher,
fallback and RTL bugs show up early.

The AI path gets one extra prompt line (US-4) so users with an API key get
text in the right language. Real translated publishing for **The Publisher**
is a different job and isn't covered here.

---

## Decisions (proposed answers to the issue's open questions)

| Question | Proposed answer | Why |
|---|---|---|
| Is demand enough to justify this now? | **Unverified.** Check the wp.org support forum and reviews before building. Spec it now; build after the demand check. | Issue #27 raised this; nothing in the repo answers it. |
| Site language only, or linked translations? | **Both.** Three modes: one language, spread across languages, linked translations. | "One language" is close to free once the adapter exists; linked translations is the actual testing need. |
| WPML, Polylang or TranslatePress first? | **Polylang and WPML together, behind one small adapter.** TranslatePress is out of scope. | Both store translations as separate posts linked by a group, so they share one code path. TranslatePress translates one post on the fly and has no per-language posts to generate. Polylang is free, so CI and manual checks can use it. |
| Translate an existing post, or generate each language separately? | **Generate each language separately.** | Fake content doesn't need to be a faithful translation. Separate generation reuses the current generator unchanged. Translating with AI is a second prompt and a second cost for no testing benefit. |

---

## User Stories

### US-1 — Linked translations (primary)

> As a theme developer on a multilingual site, I want each generated post
> created in all of my selected languages and linked as translations, so the
> language switcher, hreflang tags and translated archives have something to
> show.

**Acceptance criteria:**

- [ ] When WPML or Polylang is active and the selected post type is
      translatable, the generation form shows a **Languages** section
- [ ] Language mode **Linked translations** creates one translation group
      per requested post: a source post in the source language, plus one
      post per other selected language, all linked as translations
- [ ] The form says how many posts will be created
      (`count × selected languages`), before submit
- [ ] **Translation coverage** (0–100%, default 100%) controls how often each
      non-source translation is created, so some groups have missing
      translations on purpose
- [ ] Every created post is tracked, so Cleanup removes all of them
- [ ] Translations in a group share author, post date and featured-image
      setting; title and content are generated separately per post
- [ ] Each non-source post's title starts with a language tag (`[FR] `)
      when generated without AI, so the language is visible at a glance
- [ ] With no multilingual plugin active, the section is hidden and the
      behaviour is exactly as it is today

### US-2 — One language, or spread across languages

> As a developer, I want to generate posts in one chosen language, or spread
> randomly across several, without translation links.

**Acceptance criteria:**

- [ ] Mode **Default** (initial value): today's behaviour; the multilingual
      plugin assigns the language
- [ ] Mode **One language**: every post is set to the chosen language
- [ ] Mode **Spread**: each post is set to a random language from the
      selection, with no translation links
- [ ] These modes don't change the number of posts created

### US-3 — Terms per language

> As a developer, I want generated categories and tags created per language
> and linked, and I want generated posts to get terms in their own language.

**Acceptance criteria:**

- [ ] The Taxonomy screen offers the same three non-default modes when the
      taxonomy is translatable
- [ ] Linked term translations share a name, and get a unique slug per
      language (`technology-fr`), so `wp_insert_term()` doesn't reject them
      as duplicates
- [ ] Term assignment (`taxonomy_options`, v1.7.0) only picks terms in the
      post's language. In **Specific terms** mode, each picked term is mapped
      to its translation in the post's language and dropped if it has none
- [ ] Posts are never given a term in another language

### US-4 — AI text in the right language

> As a user with an API key, I want AI-generated posts written in the post's
> language.

**Acceptance criteria:**

- [ ] When a language is set for a post, the AI prompt includes
      `Language: write the title and content in <Language name> (<locale>).`
- [ ] Linked translations make one AI call per post. The form warns that
      AI cost multiplies by the number of languages
- [ ] The language tag prefix from US-1 is not added to AI titles

### US-5 — WP-CLI

> As a developer scripting a test site, I want the same options in
> `wp content-forge generate posts` and `generate terms`.

**Acceptance criteria:**

- [ ] `--language=<code>` — one language
- [ ] `--languages=<codes>` — comma-separated; spread mode by default
- [ ] `--translations` — with `--languages`: linked translations, the first
      code is the source language
- [ ] `--coverage=<percent>` — translation coverage, default 100
- [ ] An unknown language code fails before anything is created, and the
      error lists the valid codes
- [ ] Using these flags with no multilingual plugin active is an error,
      not a silent no-op

---

## UX / UI

**Languages section** (Pages/Posts, Custom Post Types and Taxonomy screens),
placed after the taxonomy section:

- Radio group, `<fieldset>` with a `<legend>`: **Default** / **One
  language** / **Spread** / **Linked translations**. Same pattern as
  `AuthorSelect.jsx`
- **One language**: a `<select>` of active languages, defaulting to the
  site's default language
- **Spread** / **Linked translations**: checkbox list of active languages,
  all checked by default. Linked translations adds a **Source language**
  select (limited to the checked languages) and a **Coverage** number input
  (0–100, step 10, `%` suffix in the label)
- A live summary below the controls: "Creates 30 posts: 10 in English,
  up to 10 in French, up to 10 in Arabic." It is an `aria-live="polite"`
  region so screen readers hear the total when it changes
- When AI is on and mode is Linked translations, an inline notice under the
  summary: "AI generation runs once per post: 30 calls."
- RTL languages are marked in the list ("Arabic — RTL") so it's clear the
  run will exercise RTL layout
- Section hidden when no multilingual plugin is active or the post type /
  taxonomy isn't translatable. No disabled-control placeholder

All controls use visible labels, keep the existing focus order and Tailwind
conventions, and meet the contrast check in `tests/js/contrast.test.js`.

---

## Data Model

No new table, option or postmeta. Language and translation links are stored
by WPML or Polylang in their own tables and taxonomies. Content Forge's
tracking table already records every created post and term, which is all
Cleanup needs.

Languages are read live from the active plugin on every page load and
request; nothing is cached across requests.

---

## Technical Design

### Backend (PHP)

**New: `includes/Integration/Multilingual.php`**

One static class, matching the other files in `includes/Integration/`.
Each method switches internally on the detected plugin; there is no
interface, because there are only two implementations and both are tiny.

| Method | Polylang | WPML |
|---|---|---|
| `active_plugin()` | `function_exists( 'pll_languages_list' )` → `'polylang'` | `defined( 'ICL_SITEPRESS_VERSION' )` → `'wpml'` |
| `languages()` | `pll_languages_list( [ 'fields' => '' ] )` | `apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] )` |
| `default_language()` | `pll_default_language()` | `apply_filters( 'wpml_default_language', null )` |
| `is_translated_post_type( $pt )` | `pll_is_translated_post_type()` | `apply_filters( 'wpml_is_translated_post_type', null, $pt )` |
| `is_translated_taxonomy( $tax )` | `pll_is_translated_taxonomy()` | `apply_filters( 'wpml_is_translated_taxonomy', null, $tax )` |
| `set_post_language( $id, $lang )` | `pll_set_post_language()` | `wpml_set_element_language_details`, element type `post_{$post_type}` |
| `link_posts( array $lang_to_id )` | `pll_save_post_translations()` | same action with the source post's `trid` |
| `set_term_language` / `link_terms` | `pll_set_term_language()` / `pll_save_term_translations()` | same action, element type `tax_{$taxonomy}`, keyed by **term_taxonomy_id**, not term ID |
| `term_ids_in_language( $tax, $lang )` | `get_terms( [ 'lang' => $lang, ... ] )` | `wpml_switch_language` around `get_terms()`, then restore |
| `translate_term( $term_id, $tax, $lang )` | `pll_get_term()` | `apply_filters( 'wpml_object_id', $id, $tax, false, $lang )` |

`languages()` returns a normalised list:
`[ { code, name, locale, rtl, is_default } ]`.

If both plugins are somehow active, Polylang wins and the admin screen shows
a notice. This is not a supported setup for either plugin.

**`includes/Generator/Post.php`**

- Read `$args['language_options']`, remove it from `$post_data`, same as
  `authors` is handled today
- **one** / **spread**: after `wp_insert_post()`, call
  `Multilingual::set_post_language()` beside the existing post-insert block
- **translations**: the loop creates one group per `$count`: the source post,
  then each other language (skipped with probability `1 - coverage`), then
  `Multilingual::link_posts()`. Generation is grouped so a group never splits
  across REST batches or AI queue jobs
- Pass the resolved `language` into `$args` before the existing
  `cforge_generate_post_title` / `_content` / `_excerpt` filters run, so
  integrations can return per-language text with no new filter
- `get_taxonomy_term_pool()` caches by `"{$taxonomy}|{$lang}"` and asks
  `Multilingual::term_ids_in_language()` when a language is set.
  `assign_terms()` gets the post's language and maps specific terms through
  `translate_term()`

**`includes/Generator/Taxonomy.php`**

Same three modes. For linked term translations, insert the source term,
then each language with `slug => sanitize_title( "$name-$lang" )`, then
`Multilingual::link_terms()`.

**`includes/Generator/AI_Content_Generator.php`**

Add `set_language( string $name, string $locale )` beside `set_topic()`.
`build_prompt()` appends the language line after the tone line when set.

**`includes/Generator/AI_Scheduled_Generator.php`**

The queue runs one post per Action Scheduler job, so linked translations
expand to `count × languages` jobs. Each job's index maps to
`( group, language )`. The source post ID for each group is stored in the
existing batch data, so later jobs in the group can link to it. Coverage is
rolled when the job runs, and a skipped translation completes as a no-op.
`language_options` is added as a trailing optional parameter to
`handle_sequential_generation()` and `schedule_next_or_complete()`, so
existing scheduled jobs still run.

### REST API

**`POST /cforge/v1/posts/bulk`** and **`POST /cforge/v1/taxonomy/bulk`** —
new optional `language_options` parameter:

```json
{
  "language_options": {
    "mode": "translations",
    "languages": [ "en", "fr", "ar" ],
    "source": "en",
    "coverage": 70
  }
}
```

`mode` is `default` | `one` | `spread` | `translations`. Omitting the key keeps
today's behaviour. Like `taxonomy_options`, it's sanitised **once** before the
AI / manual / auto branches in `handle_bulk_create()` and merged into the
shared args. It is not threaded through each branch.

The response shape doesn't change. `created` already lists the new post IDs,
so the UI reads the actual total from its length. That total can be lower
than `count × languages` when coverage is under 100.

**No new endpoint.** The language list is small (rarely more than 30 rows),
so it is localized into `window.cforge.multilingual` by `Admin.php`, along
with the translatable post types and taxonomies:

```json
{
  "plugin": "polylang",
  "default": "en",
  "languages": [ { "code": "en", "name": "English", "locale": "en_US", "rtl": false } ],
  "post_types": [ "post", "page" ],
  "taxonomies": [ "category", "post_tag" ]
}
```

This is the opposite of the v1.7.0 decision to load terms on demand, and the
reason is size: terms can number thousands, languages can't. When no plugin
is active, `multilingual` is `null`.

### Frontend (React)

**New: `src/js/components/LanguageSelect.jsx`** — the Languages section
above. Props: `config` (`window.cforge.multilingual`), `value`
(`language_options`), `onChange`, `count`, `useAi`.

Rendered from `pages-posts.jsx`, `cpt.jsx` and `taxonomy.jsx` when
`config` is set and the selected post type or taxonomy is in its list.
The payload omits `language_options` in Default mode.

### WP-CLI

`includes/CLI/Generate_Command.php` — flags from US-5. A new
`parse_languages()` helper sits beside `parse_authors()` and
`parse_random_terms()`. In `run_batches()`, `--count` counts **groups**,
so every batch request creates whole groups.

---

## Hooks and Filters

| Hook | Type | Use case |
|---|---|---|
| `cforge_multilingual_title_prefix` | filter (`$prefix, $lang, $post_type`) | Change or remove the `[FR] ` tag, for example to use the native language name, or `''` when a filter already returns real translated titles |
| `cforge_multilingual_plugin` | filter (`$plugin`) | Force `'wpml'`, `'polylang'` or `null`, for setups where detection gets it wrong, and for tests |
| `cforge_generate_post_title` / `_content` / `_excerpt` | existing filters, `$args['language']` now set | Supply real per-language text from your own fixtures without patching Content Forge |
| `cforge_ai_generation_prompt` | existing filter | Adjust the language instruction per provider |

No new actions. WPML and Polylang already fire their own hooks when a
language is set or a translation is linked.

---

## Block Editor and Classic Editor

No difference. Language assignment happens after `wp_insert_post()` and
doesn't touch `post_content`. `cforge_detect_editor_type()` is still
resolved per post type, and every translation in a group uses the same
format. The editor matrix in `release-plan.md` gets one extra case: Polylang
with the Classic Editor plugin, because Polylang's metabox on the classic
editor screen reads the language differently from the block editor sidebar.

---

## Free vs Pro

Everything is free. Content Forge has no Pro tier. If one is added later,
this feature stays free: it's core dummy-data behaviour for a common site
setup.

---

## Security

- Every language code is checked against `Multilingual::languages()`.
  Unknown codes return a 400 and nothing is created
- `coverage` is clamped to 0–100 with `absint()`
- The bulk endpoint has no per-request cap on `post_number` today. Because
  translations mode multiplies it by the number of languages, this mode adds
  a cap: `count × languages ≤ 1000` per request, counting **posts created**,
  not groups. The cap lives in a class constant beside
  `MAX_TERMS_PER_TAXONOMY`. WP-CLI already splits large runs into
  batches, so it stays under the cap
- Translatability is checked server-side (`is_translated_post_type` /
  `is_translated_taxonomy`). A non-translatable type with language options
  returns a 400
- No new capability. The existing `permission_check` gates both routes

---

## Edge Cases

| Case | Behaviour |
|---|---|
| Multilingual plugin deactivated after generating | Posts stay; Cleanup still deletes them from the tracking table |
| Cleanup deletes one post in a group | WPML / Polylang drop it from the group on `delete_post`; the rest stay linked |
| Coverage 0% | Only source posts are created, each in its own group. Valid, tests the "no translations" state |
| Source language not among selected languages | 400 |
| Post type is translatable in WPML but set to "display as translated" | Treated as translatable; generated posts are real translations |
| WooCommerce products | Translatable only with WooCommerce Multilingual (WPML) or Polylang for WooCommerce. If the type isn't translatable, the section is hidden |
| `post_parent` set (pages) | Each translation gets the parent's translation in its language, or no parent if none exists |
| AI call fails for one translation | Falls back to non-AI text for that post (today's behaviour); the group stays linked |
| AI queue job for a translation runs before its source exists | Can't happen: jobs run in order and the source is index 0 of each group. If the source failed, the remaining jobs in the group are skipped and recorded as errors |
| Both WPML and Polylang active | Polylang is used; admin notice shown |

---

## Testing

**Unit** (`tests/unit/`, following the existing structure):

- `Multilingual` detection and language normalisation, using the
  `cforge_multilingual_plugin` filter and stubbed `pll_*` functions
- `Post::generate()` in translations mode: group count, coverage at 0 / 100,
  `language` passed to the title / content filters
- `sanitize_language_options()`: unknown codes, missing source, clamp
- `Generate_Command::parse_languages()`

**Manual** (`tests/manual/multilingual-check.php`, run via `wp eval-file`
on a site with Polylang and three languages, one RTL):

- 10 groups, coverage 100 → 30 posts, all linked, 10 per language
- Coverage 50 → source posts all present, translations roughly half
- Terms in translations mode, then posts with `category` random: every
  post's category is in its own language
- Cleanup removes everything and leaves no orphan translation rows

**Browser:** language switcher on a block theme and a classic theme, an RTL
language archive, and the "translation missing" switcher state.

WPML is paid, so it is checked by hand on a licensed site before release.
CI covers Polylang only.

---

## Proposed Build Slices

One PR each, in order:

| # | Slice | Depends on |
|---|---|---|
| 1 | `Multilingual` adapter + unit tests + `window.cforge.multilingual` localization | — |
| 2 | Posts: one / spread / translations in `Post.php` and `POST /posts/bulk`, plus the manual check script | 1 |
| 3 | `LanguageSelect.jsx` on Pages/Posts and Custom Post Types | 2 |
| 4 | Terms: `Taxonomy.php`, `POST /taxonomy/bulk`, Taxonomy screen, language-aware term assignment | 1, 3 |
| 5 | AI: prompt language line + queue grouping in `AI_Scheduled_Generator` | 2 |
| 6 | WP-CLI flags (needs PR #32 merged) | 2, 4 |
| 7 | readme.txt features, user guide, release-plan update | all |

Slices 2–4 make a releasable feature without AI or CLI.

---

## Out of Scope

| Excluded | Reason |
|---|---|
| TranslatePress, Weglot, GTranslate | They translate a single post at render time; there are no per-language posts to generate |
| Translating a source post with AI | Second prompt and a second cost, with no testing benefit for fake content |
| Real non-English text without AI (sentence banks per language) | Large content effort. The `cforge_generate_post_*` filters with `$args['language']` let users bring their own |
| Pseudo-localization (accented, ~30% longer text to test string expansion) | Real testing value, but a separate feature. Revisit after this ships |
| Autopilot language settings | Autopilot is not where Content Forge is heading for this feature. Revisit only if users ask |
| Multilingual comments and users | Comments follow their post's language already; users have no language |
| String translation (WPML String Translation, Polylang strings) | Not content |
| Multisite language-per-site setups (MultilingualPress) | Different model; multisite support itself is still a candidate |

---

## Sizing

Medium — estimated 4–6 days across the slices. The adapter and the post
generator are most of it. Terms and the AI queue grouping are the fiddly
parts. Nothing needs a schema change or a migration.

---

## Rollout

Default mode is **Default** everywhere, so sites with WPML or Polylang see a
new section but no change in behaviour until they choose a mode. Sites
without either plugin see nothing new.
