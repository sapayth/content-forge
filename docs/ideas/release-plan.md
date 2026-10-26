# Content Forge - Release Plan

Release schedule for Content Forge, at a cadence of roughly one release per month.

**Core Requirement:** All content generation features must support both **Block Editor** and **Classic Editor** environments.

**Positioning note:** Content Forge is a fake content generator. It fills WordPress sites
with realistic posts, pages, users, terms, comments and products, so builders can develop,
test and demo themes and plugins. AI generation and Autopilot are optional extras on top of
that core, not where the product is heading. Roadmap items are weighed against "does this
help a builder fill and test a site faster?" SEO and live-publishing features (SEO meta,
internal linking, content refresh) come last.

---

## Shipped

| Version | Date | Highlights |
|---|---|---|
| v1.1.0 | 2025-12-05 | Featured image generation (placeholders), post excerpts, Block/Classic content formatting |
| v1.2.0 | 2025-12-19 | AI content generation (OpenAI, Anthropic, Google), Picsum + Placehold.co images, excerpts |
| v1.3.0 | 2026-02-12 | WooCommerce integration, weDocs integration |
| v1.4.x | 2026-03 | Random date ranges, EDD / The Events Calendar / WP User Frontend support, deactivation cleanup, CPT fixes |
| v1.5.0 | 2026-05-14 | **Autopilot** — scheduled AI post generation |
| v1.6.0 | 2026-06-14 | Author modes (fixed or random pool), DeepSeek + Mistral providers, review notice |
| v1.7.0 | 2026-08-17 | Taxonomy assignment, AI featured images for Autopilot, Dashboard page, edit/view row links, orphaned tracking-row cleanup |

---

## Next

### Candidates for v1.8.0+
- **Autopilot for other post types** — pages, CPTs, products.
- **Advanced filtering & search** in the generated-content list views.
- **Multisite support** (network-wide generation).
- **Performance stress-test mode** (10k+ items).
- **Advanced analytics**, **GDPR tooling**, **video tutorials & in-app help**.
- **Internal linking** — cross-link Autopilot posts to existing site content. *(Blocked: SEO-driven, comes last.)*
- **SEO meta for Autopilot posts** — populate Yoast / Rank Math title and description fields. *(Blocked: the very last feature.)*

Longer, unfiltered idea list: `docs/ideas/feature-ideas.md`.

---

## Development Guidelines for Editor Support

To ensure full compatibility:
1.  **Check Environment:** Detect if the site uses Classic Editor plugin or supports Block Editor.
2.  **Content Formatting:**
    *   **Classic:** Generate standard HTML (`<p>`, `<h2>`, `<ul>`).
    *   **Block:** Generate Block Grammar (`<!-- wp:paragraph -->...<!-- /wp:paragraph -->`).
3.  **Testing:** Every release must be tested on:
    *   Latest WP + Block Theme (e.g., Twenty Twenty-Four).
    *   Latest WP + Classic Theme + Classic Editor Plugin.
