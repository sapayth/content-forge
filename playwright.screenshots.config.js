// DESCRIPTION: Playwright config for the WordPress.org screenshots, run against the wp-env "tests" site (port 8893).
// DESCRIPTION: Reuses the how-to config's base URL and browser; writes .wordpress-org/screenshot-N.png. Run: npm run screenshots

const howtoConfig = require( './playwright.howto.config.js' );

module.exports = {
	...howtoConfig,
	testDir: './tests/screenshots',
	use: { ...howtoConfig.use, deviceScaleFactor: 2 },
};
