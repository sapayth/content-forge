# OpenAI-Compatible Provider (OpenRouter, Ollama, Custom Endpoints) - Implementation Plan

**Target Release:** v1.9.0 (candidate)
**Status:** Draft spec, awaiting owner review. Open questions below are **unconfirmed**.
**Focus:** One provider that talks to any `/chat/completions` endpoint, so users are not limited to models we list by hand

---

## Open questions (owner decisions)

Each is **Unconfirmed — needs owner decision**. The rest of this spec assumes the recommendation. The slice
table at the end says which slices change if the owner picks differently.

### Q1. One generic provider, or a dedicated OpenRouter provider as well?

| Option | Notes |
|---|---|
| A. One generic "OpenAI-compatible" provider with a base URL | OpenRouter, Ollama, LM Studio, vLLM, LiteLLM and Azure-style gateways all speak the same wire format. One class, one settings form. |
| B. Generic provider plus a dedicated OpenRouter provider | Fixed base URL and a nicer picker, but a second class that is a base-URL preset of the first. |

**Recommendation: A, with presets.** The settings form offers "OpenRouter", "Ollama" and "LM Studio" as
one-click presets that only pre-fill the base URL. That gives OpenRouter users a one-step setup without a second
provider class to maintain. **Unconfirmed — needs owner decision.**
*If B:* slice 1 gains a thin `AI_Provider_OpenRouter` subclass and a fixed base URL; nothing else changes.

### Q2. Model as free text, or loaded from the endpoint's `/models`?

| Option | Notes |
|---|---|
| A. Free text only | Always works. User must know the model id. |
| B. Load from `GET {base}/models` only | Nice, but not every compatible server implements it, and it needs the key before the form is complete. |
| C. Free text, plus an optional "Load models" button that fills suggestions | Works everywhere; helps where `/models` exists. |

**Recommendation: C.** It removes the hand-maintained list problem (the Google 404 in `docs/bug.md`) without making
setup depend on `/models`. The model field stays a text input with a `<datalist>` of suggestions.
**Unconfirmed — needs owner decision.**
*If A:* drop slice 3. *If B:* slice 3 grows (blocking load, error states) and the free-text field is removed.

### Q3. Ollama on localhost fails on managed hosts: document it, or detect and warn?

**Recommendation: both, cheaply.** Document it in the settings help text and `readme.txt`; and when the base URL
host is `localhost`, `127.0.0.1` or `[::1]`, show a non-blocking notice: "Localhost endpoints only work when
WordPress runs on the same machine as the model server." Do not try to probe for a managed host (no reliable
signal). **Unconfirmed — needs owner decision.**
*If document-only:* drop the notice from slice 2; the rest is unchanged.

### Q4. SSRF risk from a user-entered base URL?

Only `manage_options` users can set it, and the same capability already lets them install plugins, so the
practical risk is low. It is still a server-side request to an admin-chosen address, so the spec adds guards
(see "Security"). The decision needed: **may the base URL point at private/loopback addresses?**
Ollama and LM Studio need that; blocking it would defeat the main use case.

**Recommendation: allow by default for `http`/`https` URLs, and let a site lock it down with the
`cforge_ai_compatible_allow_private_hosts` filter** (return `false` to reject loopback/private ranges).
Use `wp_http_validate_url()` for the syntax check, but *not* `wp_safe_remote_request()` by default, because that
function rejects exactly the hosts local models run on. **Unconfirmed — needs owner decision.**
*If block-by-default:* flip the filter default and mark Ollama/LM Studio as "needs the filter returning true".

---

## Overview

Every model Content Forge offers is a hand-written list in `AI_Settings_Manager::get_models()`
(`includes/Settings/AI_Settings_Manager.php:100`). The list goes stale: the Google provider returned 404 for every
dropdown entry for new API keys (`docs/bug.md`, "Google AI provider: every model in the dropdown fails for a new
API key"), and the fix was another hand edit. Each endpoint is hard-coded too: OpenAI's is a literal at
`includes/Generator/Providers/AI_Provider_OpenAI.php:37`, Mistral's and DeepSeek's are the same pattern.

This plan adds one provider, **OpenAI-compatible**, with a user-set base URL, an optional API key and a
free-text model. It covers OpenRouter (every model, one key), Ollama and LM Studio (local, free, no key), and any
self-hosted gateway.

---

## Problem Statement

A theme developer wants 200 sample posts without paying per token. They run Ollama locally. Or they have an
OpenRouter key and want a model Content Forge doesn't list. Today neither is possible: the provider list is fixed
(`AI_Settings_Manager::get_providers()`, line 47; slugs are class constants at lines 18-22), the model list is
fixed, and `save_provider_model()` rejects any model not in the list (line 323; Google is the one hand-coded
exception).

**Done means:** an admin picks "OpenAI-compatible", enters a base URL (and a key if the server needs one) and a
model name, hits Test Connection, and AI Generate, Autopilot and the CLI all work with it.

---

## Target User

- Developers who run local models (Ollama, LM Studio) to generate fixtures for free.
- Users with an OpenRouter, LiteLLM or corporate-gateway account.
- Anyone whose preferred model isn't in our lists yet.

---

## User Stories

### US-1 — Use OpenRouter with one key (primary)

As an admin, I want to point Content Forge at OpenRouter, so I can pick any model with one key.

**Acceptance criteria**
- Settings > AI lists "OpenAI-compatible" in the provider dropdown.
- Choosing the "OpenRouter" preset fills the base URL with `https://openrouter.ai/api/v1`; the field stays editable.
- I enter a key and a model such as `anthropic/claude-sonnet-4.5`; Test Connection succeeds; AI Generate produces a post.
- The key is stored encrypted like every other provider's key and shown masked afterwards.

### US-2 — Use a local model with no key

As a developer, I want to use Ollama on my machine without an API key, so generation is free.

**Acceptance criteria**
- With base URL `http://localhost:11434/v1` and a model name, **the API key may be left empty**; Save and Test Connection work.
- AI is reported as configured (`AI_Settings_Manager::is_configured()`) for this provider when a base URL and model are saved, even without a key. Today `is_configured()` returns false for an empty key (line 463), so this is a deliberate change for this provider only.
- When the base URL host is loopback, a notice explains localhost only works if WordPress runs on the same machine as the model server (Q3).
- If the server isn't running, Test Connection shows "Could not reach the endpoint at <host>" rather than a generic error.

### US-3 — Type any model name

As an admin, I want to type a model id instead of choosing from a list, so a new model works the day it ships.

**Acceptance criteria**
- The model field is a text input for this provider; any non-empty value up to 200 characters is accepted on save.
- An optional "Load models" button calls the endpoint's `/models` and offers the ids as suggestions (Q2-C). If `/models` is missing or fails, the user sees a short message and can still type a model.
- Existing providers keep their dropdowns and their validation.

### US-4 — Invalid endpoints are rejected clearly

As an admin, I want a bad URL caught before it is saved.

**Acceptance criteria**
- A base URL that is not `http(s)`, has no host, contains credentials (`user:pass@`), a query string or a fragment, is rejected with an inline error naming the problem.
- A trailing slash is trimmed. A URL already ending in `/chat/completions` is trimmed back to the base.
- Plain `http` to a non-loopback host shows a warning that the key is sent unencrypted; saving is still allowed.

---

## Out of Scope

- Image generation through compatible endpoints. `supports_images()` stays `false` (the base default,
  `AI_Provider_Base.php:98`); Autopilot falls back to placeholders as it does for Anthropic and Mistral today.
- Streaming responses, tool calls, vision input.
- Several compatible endpoints at once (one saved endpoint in v1; see "Data model" for how to extend).
- Per-endpoint custom headers, other than the optional `Authorization` bearer. (OpenRouter's optional attribution
  headers can be added through a filter, below.)
- Auto-discovering Ollama or probing the network.
- Changing the built-in providers, their model lists or their defaults.
- Pricing, token accounting or quota display.

---

## Technical Design

### Backend (PHP)

**New `includes/Generator/Providers/AI_Provider_OpenAI_Compatible.php`**
(`@package ContentForge`, `@since 1.9.0`)

Extends `AI_Provider_Base`. The request/response shape is the same as `AI_Provider_OpenAI`
(`build_request_payload()`, `parse_response()`, `test_connection()` at lines 69-160 and 188-215 of that file).
Two options for the shared code:

- Extend `AI_Provider_OpenAI` and override `get_api_endpoint()`, `get_request_headers()` and `get_provider_slug()`; or
- Extract the chat payload/parse logic into a trait.

**Recommendation:** extend `AI_Provider_OpenAI` and override `supports_images()` to `false`. Smallest diff, no
change to existing classes. (If the OpenAI class later diverges, extract a trait then.)

Differences from OpenAI that the subclass must handle:

| Concern | OpenAI class today | Compatible provider |
|---|---|---|
| Endpoint | Literal at `:37` | `{base_url}/chat/completions`, base read from the option |
| Auth header | Always `Bearer <key>` (`:48`) | Omit `Authorization` when the key is empty |
| `response_format: json_object` | Always sent (`:90`) | Many local servers reject or ignore it. Send it, and on a 400 whose message mentions `response_format`, retry once without it. The parser already falls back to plain text (`parse_text_response()`, base `:294`). |
| `max_tokens` | `2000` | Same. Some newer OpenAI-style servers want `max_completion_tokens`; the existing `cforge_ai_provider_request_payload` filter (base `:188`) covers that. |
| Timeout | 60 s fixed (base `:194`) | Local models can be slow. Add a protected `get_timeout()` to the base returning 60, overridden here as 120 and filterable. Existing providers unchanged. |
| Constructor with empty args | n/a | `AI_Settings_Manager::get_image_providers()` builds every provider with `make_provider( $slug, '', '' )` (lines 77-90) just to read `supports_images()`. The constructor must not throw or make a request when base URL, key and model are empty. |

**`AI_Settings_Manager`** (`includes/Settings/AI_Settings_Manager.php`)

- Add `const PROVIDER_OPENAI_COMPATIBLE = 'openai_compatible';` and register it in `get_providers()` (label "OpenAI-compatible"). The slug uses an underscore; the REST route regex `[a-z-]+` for `/ai/models/(?P<provider>…)` (`includes/Api/AI.php:82`) and `sanitize_key()` both need to accept it. `sanitize_key()` keeps underscores, but the regex does not, so **widen the route regex to `[a-z_-]+`** or use the slug `openai-compatible`. Recommendation: use the hyphenated slug `openai-compatible` to avoid touching the route; option names stay valid (`cforge_ai_openai-compatible_key`).
- Add `OPTION_COMPATIBLE_BASE_URL = 'cforge_ai_compatible_base_url'`, with `get_compatible_base_url()` and a `normalize_base_url()` that applies the US-4 rules and returns `''` when invalid.
- `get_models( 'openai-compatible' )` returns `[]` plus whatever `cforge_ai_models` adds; it is not a fixed list.
- `get_stored_model()` (line 236): for this provider return the stored string as is. The current code drops back to the first listed model when the stored one isn't in the list (lines 270-291), which would blank a free-text model.
- `save_provider_model()` (line 323) and `save_settings()` (line 403) hard-fail on unlisted models. For this provider, accept any `sanitize_text_field()`'d non-empty string up to 200 characters. Other providers' behaviour is untouched.
- `is_configured()` (line 463): for this provider, true when a valid base URL and a model are saved; the key is optional.
- `get_api_key()` already returns `false` for a missing option; callers that pass the key to `make_provider()` (`Generator/Post.php:70`, `Preview_Generator.php:54`, `AI_Theme.php:69`, `Schedule_Runner.php:434`, `Api/AI.php:481`) must pass `''` rather than `false`. Verify each in slice 1; several already guard with `is_configured()` first.

**`AI_Content_Generator::make_provider()`** (`includes/Generator/AI_Content_Generator.php:159`)

Add one `case` returning `AI_Provider_OpenAI_Compatible`. The `default` branch stays OpenAI.

### REST API

All under the existing `cforge/v1/ai` base; capability unchanged (`manage_options` for writes,
`permission_check_settings`, `includes/Api/AI.php:201`).

| Route | Change |
|---|---|
| `GET /ai/settings` (`:213`) | Add `base_url` to the response (empty for other providers). |
| `POST /ai/settings` (`:239`) | Accept `base_url` (new, optional, validated and normalized). Model validation relaxed for this provider only. |
| `POST /ai/test-connection` (`:297`) | Accept `base_url`. Skip the "no API key" rejection (`:324` area) and the "model must be in the list" rejection (`:354` and `:364`) for this provider. Map connection failures to a clear `endpoint_unreachable` code. |
| `GET /ai/models/{provider}` (`:79`) | Unchanged shape. |
| `GET /ai/endpoint-models` (**new**) | Admin-only. Calls `GET {base_url}/models` with the saved (or posted) key and returns `{ models: [id, ...] }`. Powers "Load models". Short timeout (10 s), results not cached. |

The test-connection error mapper (`:372-390`) matches on English substrings ("Invalid", "network"); it will
misclassify some compatible-server messages. Slice 2 adds the `endpoint_unreachable` branch ahead of those.

### Data model

Options only; no tables, no postmeta, no schedule-config change.

| Option | Value | Notes |
|---|---|---|
| `cforge_ai_provider` | `openai-compatible` | Existing option; new allowed value |
| `cforge_ai_openai-compatible_key` | encrypted string or absent | Existing key naming scheme (`OPTION_KEY_PREFIX` + slug + `_key`); may be empty |
| `cforge_ai_openai-compatible_model` | free-text string | Existing naming scheme |
| `cforge_ai_compatible_base_url` | normalized URL string | **New** |

Autopilot schedules read provider and model from these options at run time (`Schedule_Runner.php:434`), so
nothing is stored per schedule. No uninstall routine exists in the repo today (only telemetry deletes options), so the new option follows
the existing options and needs no extra cleanup.

Extending to several endpoints later would make the base URL part of a per-profile record; keeping it a separate
option now makes that migration a straight copy.

### Hooks and filters added

| Hook | Type | Use case |
|---|---|---|
| `cforge_ai_compatible_base_url` | filter | Force the endpoint from code (e.g. a hosting company pointing every site at its own gateway). Receives the normalized URL. |
| `cforge_ai_compatible_allow_private_hosts` | filter (bool, default `true`) | A security-minded site returns `false` to reject loopback/private-range hosts. |
| `cforge_ai_compatible_request_headers` | filter | Add OpenRouter attribution (`HTTP-Referer`, `X-Title`) or a gateway's custom header. |
| `cforge_ai_compatible_timeout` | filter (int seconds, default 120) | Slow local models. |

Existing filters apply unchanged: `cforge_ai_providers`, `cforge_ai_models`,
`cforge_ai_provider_request_payload`, `cforge_ai_provider_response`.

### Frontend (React)

`src/js/components/AISettings.jsx` (the issue names `src/js/settings.jsx`, which only mounts this component).

- Provider `<SelectControl>` gets the new entry automatically from `GET /ai/settings` (`:46`).
- When `provider === 'openai-compatible'`:
  - Show a "Preset" select (OpenRouter, Ollama, LM Studio, Custom) that fills the base URL, and a `TextControl` "Base URL".
  - Replace the model `SelectControl` (`:300`) with a `TextControl` plus `<datalist>` suggestions.
  - Mark the API key field "Optional" and drop the `!apiKey.trim()` disable on Test Connection (`:339`).
  - Hide the "get an API key" link or point it at the preset's docs; `getApiKeyUrl()` (`:245`) gets a case.
- All new strings through `__()` with `content-forge`.

---

## UX / UI and Accessibility

- Fields in order: Provider, Preset, Base URL, Model, API key, then Test Connection and Save. Presets are a convenience, not a gate.
- Every control has a visible `<label>`; helper text is tied with `aria-describedby`.
- Validation errors render inline next to the field and are announced with `role="alert"`; they don't rely on colour.
- The localhost and plain-`http` notices are `Notice` components with `status="warning"`, dismissible, and use text plus an icon.
- "Load models" is a real `<button>`; while loading it sets `aria-busy` and the result count is announced through a polite live region. The datalist is an enhancement; the field works without it.
- Test Connection shows pass/fail in text; focus stays on the button.
- Contrast: reuse existing tokens so `node tests/js/contrast.test.js` keeps passing.

---

## Block Editor and Classic Editor behaviour

This is a settings and generation-transport change; it does not change what content is produced or how it is
formatted. `AI_Content_Generator` still takes the editor type and asks the provider for Block-grammar or HTML
content exactly as it does now, so output is identical in both editors.

- **Block Editor:** generated content from a compatible model is wrapped in block grammar by the existing formatter.
- **Classic Editor:** plain HTML as today.
- Small local models follow the "valid JSON with `title` and `content`" instruction less reliably, so the plain-text fallback in `parse_text_response()` will run more often. The release test pass must generate one post per editor type against a local model and check the post opens without a "block recovery" prompt.

---

## Security

- **Capability:** all writes and `endpoint-models` require `manage_options`. Nonce handling is the existing REST cookie nonce.
- **SSRF:** see Q4. Guards regardless of the answer:
  - scheme must be `http` or `https` (no `file:`, `gopher:`, etc.);
  - reject URLs with userinfo, query or fragment;
  - validate with `wp_http_validate_url()`-style parsing, then apply the private-host filter;
  - never follow redirects to a different host (`'redirection' => 0` for this provider);
  - the response body is only ever parsed as JSON and its `error.message` shown to the admin, never fetched or rendered as HTML; escape on output;
  - `endpoint-models` and Test Connection are the only code paths that send a request with an unsaved URL, and both are admin-only.
- **Key handling:** reuse `encrypt_key()` / `decrypt_key()`; never log the key; never send it to any host other than the configured base URL. Switching the base URL does not silently reuse the key under a different host without the admin seeing the form (the key field shows "saved" masked, as now).
- **Plain `http` + key:** warn, don't block (local servers are legitimately plain HTTP).
- **Output:** escape model ids and error text in the UI; the datalist is built by React, not `innerHTML`.

---

## Edge Cases

| Case | Behaviour |
|---|---|
| Base URL empty | Provider reports not configured; Test Connection says "Enter a base URL". |
| Endpoint returns 200 with non-JSON | Existing `json_error` path (`AI_Provider_Base.php:220`). |
| Server rejects `response_format` | One retry without it (see table above). |
| Server returns `choices[0].message.content` as an array of parts | Out of scope for v1; parsed as empty and surfaced as "no content". Revisit if reported. |
| `/models` returns `{ data: [{ id }] }` (OpenAI/OpenRouter) vs `{ models: [{ name }] }` (some servers) | Accept both shapes; ignore entries without a string id. |
| Very long model list (OpenRouter has hundreds) | Cap suggestions at 500; the field stays free text. |
| Switching from this provider back to OpenAI | Stored values for each provider are separate options, so nothing is lost. |
| Ollama image or embedding models picked as text model | Endpoint error is shown as is. |
| CLI/Autopilot run with a down local server | Normal provider error path; Autopilot logs the run as failed like any provider error. |

---

## Testing

Unit (`tests/unit/`, PHP 7.4 compatible):
- `Generator/AI_Provider_OpenAI_CompatibleTest`: endpoint built from base URL (trailing slash, `/chat/completions` suffix); no `Authorization` header with an empty key; `supports_images()` false; payload filter still applies; constructor with empty args does not error.
- `Settings/AI_Settings_Manager_CompatibleTest`: base URL normalization and rejection (scheme, userinfo, query, fragment); free-text model saved and returned unchanged; `is_configured()` true with no key; other providers' validation unchanged (regression).
- `Api/AI_CompatibleTest`: settings round-trip with `base_url`; test-connection without a key; `endpoint-models` requires `manage_options`; both `/models` response shapes.
- Retry-without-`response_format` behaviour, using a stubbed `pre_http_request` filter (a test seam, not a shipped mock).

E2E (`tests/e2e/`): select the provider, apply the Ollama preset, confirm the key is optional, the localhost notice shows, an invalid URL shows an inline error, and Save persists across reload. No live model call in e2e.

Manual (release checklist): one real generation each against OpenRouter and a local Ollama, in Block and Classic editors.

---

## Free vs Pro boundary

Nothing in the repository defines a paid tier (`readme.txt` and the release plan describe a single plugin), so this
spec treats the whole feature as **Free**. Bring-your-own-endpoint support lowers the cost of using the plugin
and fits its "fill a site faster" positioning. If the owner wants a boundary, the natural line is "Pro: multiple
saved endpoints and per-schedule provider choice", which is already excluded from v1 above. **Unconfirmed — needs owner decision.**

---

## Proposed build slices (one PR each)

| # | Slice | Depends on | Notes |
|---|---|---|---|
| 1 | **Provider core:** `AI_Provider_OpenAI_Compatible`, `make_provider()` case, `AI_Settings_Manager` constant, base-URL option and normalization, free-text model acceptance, optional-key `is_configured()`, timeout hook, unit tests | none | No UI; provider usable via `update_option`/CLI. Verify every `get_api_key()` caller tolerates `false`. |
| 2 | **Settings UI + REST:** `base_url` in settings/test-connection routes, `endpoint_unreachable` code, `AISettings.jsx` fields, presets, localhost and `http` notices, a11y, e2e spec | 1 | Q3 notice lives here. |
| 3 | **Load models:** `GET /ai/endpoint-models`, button and datalist | 2 | Dropped if Q2 = A; larger if Q2 = B. |
| 4 | **Docs and release notes:** `readme.txt` FAQ and provider list (currently names only OpenAI, Anthropic, Google, line 80), `docs/ai-content-generation/` page, hooks reference | 1-3 | If Q1 = B, add `AI_Provider_OpenRouter` to slice 1. |

Slices 1 and 2 are the minimum shippable feature.

---

## Sizing

Roughly 1 (M), 2 (M), 3 (S), 4 (S). The bulk of the risk is in slice 1's settings-manager special cases, because
the existing code assumes every model is in a fixed list in five places (`get_stored_model`, `save_provider_model`,
`save_settings`, and two checks in `Api\AI::test_connection`).

---

## Rollout

- Ships behind no flag; the new provider only appears as another dropdown entry and changes nothing for existing users.
- Existing saved settings are untouched (no migration).
- Mention in the changelog that local and OpenRouter models are supported, with the localhost caveat.
