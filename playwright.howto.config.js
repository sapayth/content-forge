// DESCRIPTION: Playwright config for recording how-to video footage (one webm per segment + a step/click log).
// DESCRIPTION: Runs against the wp-env "tests" site (port 8893). Run: npm run howto -- tests/howto/<name>.spec.js

// 8888-8891 belong to other wp-env sites on this machine; .wp-env.json pins ours to 8892/8893.
process.env.WP_BASE_URL ??= 'http://localhost:8893';

const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );

module.exports = {
	...baseConfig,
	testDir: './tests/howto',
	workers: 1,
	retries: 0,
	timeout: 300_000,
	reporter: [ [ 'list' ] ],
	use: {
		...baseConfig.use,
		timezoneId: 'UTC',
		trace: 'off',
		screenshot: 'off',
		video: 'off',
	},
	// The base project applies Desktop Chrome's 1280x720; the recorder sets its own viewport.
	projects: [ { name: 'chromium', use: { browserName: 'chromium' } } ],
};
