# Bugs

## Deleting generated posts leaves their featured images behind

- **Found:** 2026-10-24, scripting the quick-start how-to video.
- **Symptom:** Generate posts with "Generate Featured Image" on, then Pages/Posts > Delete All. The posts go, but every
  Picsum/Placehold image stays in the Media Library, and the dashboard then shows "Custom post types: N generated"
  (N = the leftover images) with no posts or CPTs left.
- **Root cause:** `Generator\Image::sideload()` tracks each image as its own `attachment` row in `wp_cforge`.
  `Api\Post::get_all_tracked_posts_ids()` only selects `cforge_get_allowed_post_types()` (post, page, ...), and
  `Generator\Post::delete()` deletes only the post, so the attachment rows and files are never removed.
  `Api\Dashboard::bucket_rows()` then files the leftover `attachment` rows under "cpt".
- **Fix:** in `Generator\Post::delete()` (both bulk and single delete route through it), delete the post's
  featured image too when it is a tracked `attachment`, and untrack it. In `bucket_rows()`, don't count `attachment`
  rows as CPTs (they belong to their posts).
- **Regression tests:** `tests/e2e/delete-featured-images.spec.js` (`npm run test:e2e`; fails with the delete fix
  reverted) and `Dashboard_CountsTest::test_featured_images_are_not_counted_as_cpts` (fails with the bucket fix reverted).
- **Status:** fixed.

## Autopilot's Tone and Approximate length settings do nothing

- **Found:** 2026-10-24, reading the code before scripting the Autopilot how-to video.
- **Symptom:** The Autopilot form offers Tone (Professional, Casual, Technical, Conversational) and Approximate length
  (Short ~300, Medium ~800, Long ~1500 words), and `readme.txt` advertises "Per-Autopilot tone ... and length
  controls". Changing either changes nothing: every post comes out with the same voice and at least 500 words.
- **Root cause:** `ai.tone` and `ai.length` are saved in the schedule config (`Autopilot\Schedule` defaults) but never
  read. `Schedule_Runner::generate_ai_content()` and `Preview_Generator::generate()` build the prompt from the topic
  and custom prompt only, and `AI_Content_Generator::build_prompt()` hard-codes "minimum 500 words". Unwired since
  745e87e, which added Autopilot.
- **Fix:** Give `AI_Content_Generator` an optional tone and word target. When set, the prompt asks for that
  tone and "about N words" in place of "minimum 500 words"; when not set, the prompt stays as it is, so the AI Generate tab
  is unchanged. Pass them from both the runner and the preview.
- **Regression test:** `tests/unit/Generator/AI_Content_Generator_PromptTest.php` (fails with the fix reverted).
- **Status:** fixed.

## Google AI provider: every model in the dropdown fails for a new API key

- **Found:** 2026-10-24, setting up Google for the Autopilot how-to video.
- **Symptom:** With a newly created Google AI Studio key, Test Connection, AI Generate, Autopilot runs and previews all fail.
  Google answers 404, either "This model ... is no longer available to new users" (2.5 / 2.0) or "not found for
  API version v1" (3 previews, `-latest` aliases, 1.x).
- **Root cause:** `AI_Settings_Manager::get_models()` lists only models Google has since retired for new keys, and
  `DEFAULT_MODEL_GOOGLE` is `gemini-2.5-flash`.
- **Fix:** added `gemini-3.6-flash`, `gemini-3.5-flash`, `gemini-3.5-flash-lite` and `gemini-3.1-flash-lite` (each
  checked against the v1 endpoint the provider uses: 3–13 s for a 300-word post), and made `gemini-3.6-flash` the
  default. The older entries stay, because keys created before Google retired them can still use them. `gemini-3.7-flash` and
  `3.8-flash` are left out: both timed out after 90 s, longer than the provider's 60 s timeout.
- **Also seen:** the free tier has no quota for Gemini image models (429), so "AI-generated" featured images fall back
  to Picsum on a free key, as designed.
- **Status:** fixed.

## Autopilot "AI-suggested from theme" can ignore the theme

- **Found:** 2026-10-24, test runs for the Autopilot how-to video.
- **Symptom:** With theme "beginner home gardening tips" on `gemini-3.5-flash-lite`, a run picked the topic "Mastering the
  Art of Digital Content Writing" and wrote that post into the Gardening category. `gemini-3.6-flash` stayed on theme.
- **Root cause:** `Topic\Strategies\AI_Theme::resolve()` (and `Preview_Generator::resolve_ai_theme_topic()`) ask for the
  topic through `AI_Content_Generator::generate( 'general', ... )`, which wraps it in the full blog-post prompt
  ("Generate a WordPress blog post ... minimum 500 words ... Keywords: article, blog, news, writing, content"), with the
  theme only as "Additional Instructions". Smaller models follow the post prompt and its keywords. It also writes
  a whole post just to keep its title, which makes each run slower and spends more tokens.
- **Fix:** `AI_Theme::propose()` sends just the topic request to the provider (no blog-post prompt), asking for the
  topic as the JSON title; the preview uses it too instead of its own copy. For the post itself, Autopilot now passes the
  topic with `AI_Content_Generator::set_topic()`: the prompt names it as the subject and leaves out the content type's
  generic keywords. Those keywords had also leaked into post text ("plant writing, blogging"). The AI Generate tab doesn't
  set a topic and is unchanged.
- **Regression tests:** `tests/unit/Autopilot/Topic/AI_ThemeTest.php`,
  `AI_Content_Generator_PromptTest::test_topic_is_the_subject_without_generic_keywords`. Verified in WordPress on
  `gemini-3.5-flash-lite`: the preview topic was "Easy Veggies for Beginners: Start Your First Garden", a run wrote "Easy
  Beginner Home Gardening Tips for First-Timers", and neither post mentioned writing or blogging.
- **Status:** fixed.

## AI settings: a model with a dot in its name can't be tested or saved

- **Found:** 2026-10-24, setting up Google for the Autopilot how-to video.
- **Symptom:** Pick "Gemini 3.5 Flash-Lite" (or GPT-3.5 Turbo) in Settings. Test Connection fails with "Invalid model for
  provider", and after Save Changes the dropdown and every generation use the provider's first listed model instead.
- **Root cause:** `Api\AI` (save and test routes) and `AI_Settings_Manager::save_settings()` run the model through
  `sanitize_key()`, which drops dots, so `gemini-3.5-flash-lite` is stored as `gemini-35-flash-lite`.
  `get_stored_model()` finds no such model and returns the first one. Its typo fixer only repairs `gemini-20-` and `gemini-25-`.
- **Fix:** sanitize the model with `sanitize_text_field()` instead of `sanitize_key()` (both REST arg schemas, the save
  and test-connection handlers, and `save_settings()`), so Test Connection no longer says "Invalid model for provider"
  and the model saves as picked. Values already saved without their dots are repaired when read: `get_stored_model()`
  maps them back to the listed model (`match_undotted_model()`) and re-saves it.
- **Regression test:** `tests/unit/Settings/AI_Settings_ManagerTest.php`; verified in WordPress through the Settings
  page (Test Connection, Save Changes).
- **Status:** fixed.

## Autopilot "Run now" on a draft or paused Autopilot is always skipped

- **Found:** 2026-10-24, test runs for the Autopilot how-to video.
- **Symptom:** The Autopilot card offers Run now for drafts and paused Autopilots, and the user guide suggests it for
  testing "without waiting", but the run ends as Skipped: "Schedule no longer active". Retry behaves the same way.
- **Root cause:** `Schedule_Runner::handle()` skips any run whose schedule isn't active. Run now and Retry enqueue the
  same action as the scheduled dispatcher, so the runner can't tell a manual run from a stale scheduled one.
- **Fix:** Run now and Retry enqueue the action with a third argument, `true`. The runner then also runs draft, paused and
  auto-paused schedules for these runs (archived and completed ones are still skipped). Scheduled runs keep 2 arguments,
  so they still need an active schedule. A failed run never pauses a schedule that isn't active, whether through the failure streak
  or a preflight failure, so a test run can't flip a draft to "Auto-paused".
- **Verified in WordPress:** Run now on a draft made a post and left the draft a draft; a scheduled run on the draft was
  still skipped; a failed manual run on a draft with "auto-pause after 1 failure" left it a draft.
- **Status:** fixed.

## Generated WooCommerce products can't get a featured image

- **Found:** 2026-10-24, scripting the WooCommerce test-store how-to video.
- **Symptom:** Custom Post Types > Add New has no "Generate Featured Image" switch, so every generated product shows
  WooCommerce's grey placeholder on the shop page.
- **Root cause:** `src/js/cpt.jsx` never sends `generate_image` / `image_sources`. The server already supports them for
  any post type (`Api\Post` passes them to `Generator\Post`); only the form was missing the switch.
- **Fix:** add the same "Generate Featured Image" switch and image-source checkboxes as the Pages/Posts form to
  `cpt.jsx`, and send them in the payload.
- **Regression test:** `tests/e2e/cpt-featured-image.spec.js` (`npm run test:e2e`; fails with the form change reverted).
- **Status:** fixed.

## Product sale prices are silently skipped when the sale range overlaps the price

- **Found:** 2026-10-24, scripting the WooCommerce test-store how-to video.
- **Symptom:** Price 15-120 with sale 10-40: any product whose random regular price comes out at or under 40 gets no
  sale price at all, with no message. Prices are also stored unrounded (e.g. `36.9963`).
- **Root cause:** `WooCommerce_Product::apply_product_options()` only set a sale when the sale range's *max* was below
  that product's regular price, and never rounded either price.
- **Fix:** `WooCommerce_Product::sale_price()` caps the sale range just under each product's regular price (no sale only
  when even the sale minimum is at or above it); both prices round to cents.
- **Regression test:** `tests/unit/Integration/WooCommerce_ProductTest.php`.
- **Status:** fixed.
