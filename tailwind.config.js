module.exports = {
    prefix: 'fakegen-',
    content: ['./**/*.php', './src/**/*.{js,jsx,ts,tsx}'],
    theme: {
        extend: {
            colors: {
                'primary': '#62748E',
                'primaryHover': '#45556C',
            },
        },
    },
    plugins: [],
    safelist: [
        'fakegen-input',
    ],
};