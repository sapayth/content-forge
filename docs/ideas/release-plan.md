# Content Forge - Release Plan

This document outlines the release schedule for Content Forge, targeting a moderate cadence of 1-2 releases per month. The goal is to deliver high-value features incrementally while ensuring stability and support for both Block (Gutenberg) and Classic editors.

**Release Cadence:** Monthly / Bi-monthly
**Core Requirement:** All content generation features must support both **Block Editor** and **Classic Editor** environments.

---

## Phase 1: Core Content & Intelligence (Month 1-2)

### v1.1.0 - The Visual Update (Month 1)
**Focus:** Media generation and basic post enhancements.
- **Feature:** Featured Image Generation (Placeholders).
  - *Classic:* Set `_thumbnail_id`.
  - *Block:* Set `_thumbnail_id` (compatible with all block themes).
- **Feature:** Basic Post Excerpts.
- **Enhancement:** Dashboard Statistics Widget (Basic counts).
- **Fix:** Ensure all current generators respect the active editor (wrap content in blocks if Block Editor is active, plain HTML if Classic).

### v1.2.0 - The AI & Smart Update (Month 2)
**Focus:** AI Integration & Smart Context.
- **Feature:** **AI Content Generation (Beta)**.
  - Integration with OpenAI API (User provides key).
  - Generate realistic titles and body content based on prompts.
  - *Block:* Generate content as Paragraph/Heading blocks.
  - *Classic:* Generate content as HTML.
- **Feature:** Smart Context (Auto-detect theme/niche for better random data).
- **Feature:** Post Status Variations.
  - Generate Drafts, Pending, Private, Future (Scheduled).

---

## Phase 2: E-Commerce & Integration (Month 3-4)

### v1.3.0 - The Commerce Update (Month 3)
**Focus:** WooCommerce Integration (Basic).
- **Feature:** Product Generation (Simple Products).
  - Title, Description, Price, SKU.
  - *Block/Classic:* Product description compatibility.
- **Feature:** Product Categories & Tags.
- **Feature:** Product Images (Featured + Gallery placeholders).
- **Feature:** Custom Post Type (CPT) Support (Moved from v1.2.0).

### v1.4.0 - The Commerce Enhanced Update (Month 4)
**Focus:** Advanced WooCommerce & Third-Party.
- **Feature:** Product Variations (Variable Products).
- **Feature:** Customer Generation (Users with 'customer' role + billing/shipping data).
- **Feature:** Order Generation (Simulate sales for reporting).
- **Feature:** **Third-Party Integration API** (Foundation for future integrations).

---

## Phase 3: Efficiency & Scale (Month 5-6)

### v1.5.0 - The Bulk Update (Month 5)
**Focus:** Bulk Operations & Scheduling.
- **Feature:** Scheduled Generation (Cron).
  - "Generate 5 posts every day".
- **Feature:** Export/Import Generated Content.
- **Enhancement:** Bulk Delete/Cleanup Tools (by Date, Type).

### v1.6.0 - The Performance Update (Month 6)
**Focus:** Performance & Multisite.
- **Feature:** Performance Stress Test Mode (10k+ items).
- **Feature:** Multisite Support (Network-wide generation).
- **Enhancement:** Advanced Filtering in Admin List.

---

## Phase 4: Future Roadmap (Low Priority / Long Term)

### v1.7.0+
- **Advanced Analytics** (Detailed usage reports).
- **GDPR Compliance Tools** (Anonymized data).
- **Video Tutorials & In-App Help**.

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
