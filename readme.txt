=== Content Forge - AI & non-AI content generator, scheduled AI posts, dummy data for your site ===
Contributors: sapayth
Tags: ai content generator, autopilot, scheduled posts, dummy content, testing
Requires at least: 5.6
Tested up to: 6.9.1
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate WordPress content with or without AI. Schedule AI posts via Autopilot, or instantly create dummy posts, users, comments, pages.

== Description ==

Content Forge is a free WordPress plugin that works two ways — with AI or without. The built-in traditional generator instantly creates dummy posts, pages, users, comments, and taxonomies for development, testing, and demos, no API key required. Or connect an AI provider (OpenAI, Anthropic, or Google) to generate real, contextually relevant posts on demand — and let Autopilot schedule those AI posts to run automatically on the cadence you choose. Use either mode standalone, or mix both in the same workflow.

= Autopilot: schedule AI-generated WordPress posts =

Autopilot is a free, built-in scheduler that turns Content Forge into a hands-off AI publishing assistant. Define a recurring rule once and it generates posts in the background — daily, weekly, monthly, or any custom interval. Pick your topic strategy (cycling list, random list, one-shot queue, or AI-suggested from a theme), your tone and length, and what should happen to the result: save as draft, mark as pending review, schedule to publish later, or publish immediately. Safety rails are on by default — daily post caps, auto-pause on failure, duplicate guard, and default-to-draft mode — so nothing goes live without your explicit opt-in.

= Features =

* Autopilot: scheduled AI post generation with daily, weekly, monthly, or custom intervals
* Multiple topic strategies for Autopilot: cycling list, random list, one-shot queue, AI-suggested from theme
* Per-Autopilot tone (professional, casual, technical, conversational) and length controls
* Publishing modes for Autopilot: draft, pending review, scheduled, or immediate publish
* Safety controls: daily post caps, auto-pause after consecutive failures, duplicate guard
* Email notifications for Autopilot runs (every run, failures only, or off)
* AI-powered content generation using OpenAI, Anthropic, and Google
* Traditional content generation (no AI required)
* Generate fake posts with realistic titles, content, metadata, and excerpts
* Create dummy pages with hierarchical structure
* Generate featured images using Picsum and Placehold.co
* Generate test users with various roles and capabilities
* Create fake taxonomies (categories and tags)
* Generate realistic comments and comment threads
* Bulk generation capabilities for efficient testing
* Clean and intuitive admin interface
* Follows WordPress coding standards
* Translation ready

= Use Cases =

* Scheduled AI blogging for solo creators and niche site owners
* Editorial workflows where AI produces drafts and humans review before publish
* Filling demo or staging sites with realistic ongoing content
* Theme development and testing
* Plugin development
* Client demonstrations
* Performance testing with large datasets
* Content structure planning
* Training and educational purposes

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/content-forge` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the Content Forge menu in your WordPress admin dashboard.
4. Configure your generation settings and start creating dummy content.

== Frequently Asked Questions ==

= Do I need an AI API key to use this plugin? =

No. Content Forge has two independent modes. The traditional generator creates dummy posts, pages, users, comments, and taxonomies without any AI configuration — install, activate, generate. An AI API key (OpenAI, Anthropic, or Google) is only required if you want AI-generated content on demand or you want to use Autopilot.

= Is the generated content safe for production sites? =

Content Forge is designed for development and testing purposes only. While the generated content is safe, it's recommended to use this plugin only on development, staging, or testing environments.

= Can I customize the type of content generated? =

Yes, Content Forge provides various options to customize the generated content. You can choose between AI-powered generation (with provider selection: OpenAI, Anthropic, or Google) or traditional generation methods. Additional customization options include post types, user roles, taxonomy terms, content length, featured images, and post excerpts.

= Will this plugin slow down my site? =

Content Forge only runs when you actively generate content through the admin interface. It doesn't affect your site's frontend performance.

= Can I delete all generated content at once? =

Yes, Content Forge provides bulk deletion options to easily remove all generated content when you're done testing.

= Is the plugin translation ready? =

Yes, Content Forge is fully translation ready and includes a .pot file for translators.

= What is Autopilot? =

Autopilot is a free, built-in feature that schedules AI-generated posts. You set the topic source, frequency, tone, length, and what happens to the post (draft, pending, scheduled, or immediate publish), and Content Forge runs it in the background using your AI provider. See the full setup guide: https://wp.sapayth.com/schedule-ai-blog-posts-wordpress/

= Is Autopilot free? =

Yes. Autopilot and every other feature of Content Forge are free. You only pay your AI provider (OpenAI, Anthropic, or Google) for their API usage.

= Will Autopilot publish posts without my permission? =

No. Autopilot defaults to saving every generated post as a draft. You explicitly opt into pending review, scheduled, or immediate publish modes per Autopilot.

= What happens if the AI fails repeatedly? =

Autopilot auto-pauses after a configurable number of consecutive failures (default 3) and emails you. You fix the cause (expired API key, rate limit, etc.) and click Resume.

= Can I run multiple Autopilots at the same time? =

Yes. Run one per category, niche, or author — they operate independently.

== Screenshots ==

1. Main dashboard showing generation options
2. Post generation interface with customization options
3. Optional AI-powered content generation using OpenAI, Anthropic, and Google
4. User generation settings
5. Bulk content management tools

== Changelog ==

= 1.5.0 14-05-2026 =
New - Autopilot — set it once, and your posts generate themselves on schedule

= 1.4.2 17-03-2026 =
* New - Random date-range for generated posts — time travel your content
* New - Easy Digital Downloads, The Events Calendar & WP User Frontend support — more post types, more fun
* New - Deactivation cleanup — we tidy up after ourselves
* Fix - CPT page no longer awkwardly shows Post & Page in the dropdown
* Fix - Friendly empty state when no CPT plugins are active

= 1.4.1 16-03-2026 =
* New - Random date-range for generated posts — time travel your content
* New - Easy Digital Downloads, The Events Calendar & WP User Frontend support — more post types, more fun
* New - Deactivation cleanup — we tidy up after ourselves
* Fix - CPT page no longer awkwardly shows Post & Page in the dropdown
* Fix - Friendly empty state when no CPT plugins are active

= 1.4.0 07-03-2026 =
* New - Random date-range for generated posts — time travel your content
* New - Easy Digital Downloads, The Events Calendar & WP User Frontend support — more post types, more fun
* New - Deactivation cleanup — we tidy up after ourselves
* Fix - CPT page no longer awkwardly shows Post & Page in the dropdown
* Fix - Friendly empty state when no CPT plugins are active

= 1.3.0 12-02-2026 =
* New - WooCommerce integration
* New - weDocs integration

= 1.2.0 19-12-2025 =
* New - Optional AI-powered content generation using OpenAI, Anthropic and Google
* New - Add feature image using Picsum and Placehold.co
* New - Generate Post Excerpt

= 1.1.0 05-12-2025 =
* New - Added featured image generation with placeholder support
* New - Added post excerpt generation
* Enhancement – Content formatting for both Block and Classic editors

== Privacy Policy ==

Content Forge uses Feedio to collect some telemetry data upon user’s confirmation. This helps us to troubleshoot problems faster & make product improvements.
Feedio does not gather any data by default. Feedio only starts gathering basic telemetry data when a user allows it via the admin notice. We collect the data to ensure great user experience for all our users.

Integrating Feedio DOES NOT IMMEDIATELY start gathering data, without confirmation from users in any case.

== Support ==

