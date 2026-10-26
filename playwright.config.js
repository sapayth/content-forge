// DESCRIPTION: Playwright config for end-to-end tests run against the wp-env "tests" site (port 8893).
// DESCRIPTION: Reuses the how-to config's base URL and browser; specs live in tests/e2e. Run: npm run test:e2e

const howtoConfig = require( './playwright.howto.config.js' );

module.exports = {
	...howtoConfig,
	testDir: './tests/e2e',
	timeout: 120_000,
};
