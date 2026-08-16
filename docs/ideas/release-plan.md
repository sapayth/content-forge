# Content Forge - Release Plan

Release schedule for Content Forge, at a cadence of roughly one release per month.

**Core Requirement:** All content generation features must support both **Block Editor** and **Classic Editor** environments.

**Positioning note:** Content Forge started as a dummy-data generator and now leads with
AI publishing. Autopilot is the headline feature; traditional generation is the no-API-key
fallback that also serves developers and demo sites. Roadmap items are weighed against
"does this make Autopilot output publishable without a human touching it?"

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
- **SEO meta for Autopilot posts** — populate Yoast / Rank Math title and description fields.
- **Autopilot for other post types** — pages, CPTs, products.
- **Internal linking** — cross-link Autopilot posts to existing site content.
- **Advanced filtering & search** in the generated-content list views.
- **Multisite support** (network-wide generation).
- **Performance stress-test mode** (10k+ items).
- **Advanced analytics**, **GDPR tooling**, **video tutorials & in-app help**.

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
