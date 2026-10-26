// DESCRIPTION: Shared recorder for how-to video specs: one webm per segment plus a JSON log of each
// DESCRIPTION: narration step's start/end and click boxes (drives the edit's zooms and cursor).

const fs = require( 'fs' );
const path = require( 'path' );

const LOGGED_OUT = { cookies: [], origins: [] };
const VIEWPORT = { width: 1280, height: 800 };

/**
 * Start a fresh footage folder and return a recorder bound to it.
 *
 * @param {string} name Folder under artifacts/media/ and the log's base name (how-to-<name>.json).
 */
function howto( name ) {
	const out = path.join(
		process.cwd(),
		'artifacts',
		'media',
		`howto-${ name }`
	);
	const log = { viewport: VIEWPORT, segments: {}, steps: [] };
	fs.rmSync( out, { recursive: true, force: true } );
	fs.mkdirSync( out, { recursive: true } );

	/**
	 * Open a recorded browser context and return helpers that log steps and clicks
	 * against the video clock (seconds since the context opened).
	 *
	 * @param {import('@playwright/test').Browser} browser      Browser.
	 * @param {string}                             segment      Segment name; also the webm file name.
	 * @param {Object|string}                      storageState Session to record with.
	 */
	async function recorder( browser, segment, storageState ) {
		const context = await browser.newContext( {
			viewport: VIEWPORT,
			timezoneId: 'UTC',
			storageState,
			// Playwright records at the CSS viewport size; a deviceScaleFactor only pads the frame.
			recordVideo: { dir: out, size: VIEWPORT },
		} );
		const page = await context.newPage();
		const t0 = Date.now();
		const now = () => ( Date.now() - t0 ) / 1000;
		let current = null;

		const end = () => current && ( current.end = now() );
		const mark = async ( locator, kind, extra = {} ) => {
			await locator.scrollIntoViewIfNeeded();
			const box = await locator.boundingBox();
			current.marks.push( { t: now(), kind, ...box, ...extra } );
		};

		return {
			page,
			step( id ) {
				end();
				current = { id, segment, start: now(), marks: [], wides: [] };
				log.steps.push( current );
			},
			async click( locator, options ) {
				await mark( locator, 'click' );
				await locator.click( options );
			},
			// Native <select> popups never reach the recording, so log the options for the edit to draw.
			async select( locator, label ) {
				const options = await locator
					.locator( 'option' )
					.allTextContents();
				await mark( locator, 'select', { options, chosen: label } );
				await locator.selectOption( { label } );
			},
			async fill( locator, value ) {
				await mark( locator, 'click' );
				await locator.fill( value );
			},
			async type( locator, text ) {
				await mark( locator, 'click' );
				await locator.click();
				await locator.pressSequentially( text, { delay: 120 } );
			},
			// Point the viewer at something without clicking it.
			async focus( locator ) {
				await mark( locator, 'focus' );
			},
			// A new page or view just appeared: the edit opens it wide before zooming back in.
			wide() {
				current.wides.push( now() );
			},
			async scrollTo( locator ) {
				await locator.evaluate( ( el ) =>
					el.scrollIntoView( { behavior: 'smooth', block: 'start' } )
				);
				await page.waitForTimeout( 1200 );
			},
			async close() {
				await page.waitForTimeout( 1500 );
				end();
				const video = page.video();
				await context.close();
				fs.renameSync(
					await video.path(),
					path.join( out, `${ segment }.webm` )
				);
				log.segments[ segment ] = { video: `${ segment }.webm` };
			},
		};
	}

	const save = () =>
		fs.writeFileSync(
			path.join( out, `how-to-${ name }.json` ),
			JSON.stringify( log, null, 1 )
		);

	return { recorder, save };
}

/**
 * Dismiss the usage-tracking opt-in notice so it stays out of the footage.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function hideNotices( page ) {
	const noThanks = page.getByRole( 'link', { name: 'No thanks' } );
	if ( await noThanks.isVisible() ) {
		await noThanks.click();
	}
}

module.exports = { howto, hideNotices, LOGGED_OUT, VIEWPORT };
