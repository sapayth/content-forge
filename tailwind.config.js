module.exports = {
    prefix: 'cforge-',
    content: ['./**/*.php', './src/**/*.{js,jsx,ts,tsx}'],
    theme: {
        extend: {
            colors: {
                'primary': '#62748E',
                'primaryHover': '#45556C',
                'secondary': '#475569',
                'tertiary': '#F1F5F9',
                'accent': '#F59E42',
                'success': '#22C55E',
                'warning': '#FACC15',
                'error': '#EF4444',
                'border': '#CBD5E1',
                'text-primary': '#1E293B',
                'text-secondary': '#64748B',
            },
        },
    },
    plugins: [],
};