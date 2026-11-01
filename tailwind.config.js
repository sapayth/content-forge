// DESCRIPTION: Tailwind design tokens for the Content Forge admin UI.
// DESCRIPTION: Role-named colors following the header-footer-elementor system; ratios measured against #FFF.
module.exports = {
    prefix: 'cforge-',
    content: ['./**/*.php', './src/**/*.{js,jsx,ts,tsx}'],
    theme: {
        extend: {
            colors: {
                // Brand. Solid fills carrying white labels — buttons, links, focus rings.
                'primary': '#557E36',       // 4.75:1
                'primaryHover': '#4A7130',  // 5.68:1, and text on brand-50
                'accent': '#95BE46',        // 2.16:1 — fills only, never text or borders
                'brand-50': '#F1F7E8',      // icon chips and tinted rows

                // Text
                'text-primary': '#111827',
                'text-secondary': '#4B5563',
                'secondary': '#4B5563',

                // Surface and line
                'tertiary': '#F3F4F6',
                'border': '#E5E7EB',

                // Status. Solid fills under cforge-text-white, so they are dark by necessity.
                'success': '#15803D',       // 5.01:1
                'warning': '#A16207',       // 4.92:1
                'error': '#B91C1C',         // 6.47:1
                'errorHover': '#991B1B',    // 8.31:1
            },
            // Tailwind's default scale, plus the one step it lacks for badges
            // and micro-labels. Font family is inherited from wp-admin.
            fontSize: {
                'xxs': '0.6875rem', // 11px
            },
        },
    },
    plugins: [],
};
