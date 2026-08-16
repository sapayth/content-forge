# Content Forge Design System

## Overview
This design system provides the visual and interaction guidelines for the Content Forge WordPress plugin. It ensures consistency, accessibility, and maintainability across all plugin UI components.

---

## 1. Color Palette

**Source of truth:** `tailwind.config.js`. The tokens are role-named and carry their
measured contrast ratios as comments — read them there rather than duplicating hex values
in two places that drift apart. Summary:

- **Brand:** `primary` / `primaryHover` for solid fills carrying white labels; `accent` for
  fills only, never text or borders; `brand-50` for icon chips and tinted rows.
- **Text:** `text-primary`, `text-secondary`.
- **Surface and line:** `tertiary`, `border`.
- **Status:** `success`, `warning`, `error`, `errorHover` — all solid fills under white text,
  so they are dark by necessity.

Use the `cforge-` prefixed utility classes; do not hardcode hex values in components.

> **Accessibility:** Every token carrying text meets WCAG AA (4.5:1) against white. Ratios
> are recorded next to each value in `tailwind.config.js`. Verify new colors with the
> [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/) before adding them.

---

## 2. Typography

- **Font Family:** System UI stack
  - `font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";`
- **Font Sizes:**
  - XXS: 11px (`cforge-text-xxs`, badges and micro-labels only)
  - XS: 12px
  - SM: 14px
  - Base: 16px
  - LG: 20px
  - XL: 24px
  - 2XL: 32px
- **Font Weights:** 400 (Regular), 500 (Medium), 700 (Bold)
- **Line Height:** 1.5–1.7 for body text
- **Letter Spacing:** Normal; increase slightly for uppercase or small text

---

## 3. Spacing & Sizing

- **Spacing Scale:** 4, 8, 12, 16, 24, 32px
- **Container Widths:**
  - Content: max-width 800px
  - Sidebar: max-width 320px

---

## 4. Components

Shared React implementations live in `src/js/components/` — `Button.jsx`, `Card.jsx`,
`Field.jsx`, `Badge.jsx`, `Notice.jsx`. Reach for those before writing new markup.

- **Buttons:**
  - Primary, secondary, disabled states
  - Accessible color contrast
  - Clear focus and hover states
- **Inputs:**
  - Text, select, checkbox, radio
  - Focus, active, error states
- **Alerts:**
  - Success, warning, error, info
- **Other:**
  - Cards, modals, tooltips, tabs, etc.

---

## 5. Accessibility

- **Color Contrast:** All UI elements must meet at least WCAG AA
- **Focus States:** Visible outlines for all interactive elements
- **Keyboard Navigation:** All components must be fully keyboard accessible
- **Screen Reader Support:** Use ARIA attributes where needed

---

## 6. Iconography

- **Style:** Use a consistent icon style (line or solid)
- **Accessibility:** Add `aria-label` or `title` for icons

---

## 7. Internationalization (i18n)

- All UI strings must be translatable using WordPress i18n functions in PHP and `@wordpress/i18n` in JS.

---

## 8. Documentation & Maintenance

- **Usage Guidelines:** Document how to use each component, with code examples
- **Design Tokens:** Document color, spacing, and typography variables
- **Update Process:** Review and update the design system as new components or requirements arise

---

## 9. Implementation Notes

- **No custom fonts:** Only use system font stack for maximum compatibility and performance
- **Tailwind CSS:** Use Tailwind utility classes where possible for rapid, consistent styling
- **Dark Mode:** (Optional) Plan for dark mode support in future iterations

---

## References
- [WCAG Contrast Checker](https://webaim.org/resources/contrastchecker/)
- [WordPress Accessibility Guidelines](https://make.wordpress.org/accessibility/handbook/)
- [Tailwind CSS Color Palette](https://tailwindcss.com/docs/customizing-colors) 