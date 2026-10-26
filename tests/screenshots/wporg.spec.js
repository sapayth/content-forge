// DESCRIPTION: Takes the WordPress.org screenshots: seeds the tests site with `wp cforge`, captures each admin screen,
// DESCRIPTION: and frames it like the original artwork (title, caption, diagonal background) into .wordpress-org/.

const { execSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );
const { test } = require( '@wordpress/e2e-test-utils-playwright' );
const { hideNotices } = require( '../howto/recorder' );

const NS = '/cforge/v1';
const OUT = path.join( __dirname, '../../.wordpress-org' );
// Unframed captures, kept out of .wordpress-org because the deploy mirrors that folder to SVN.
const RAW = path.join( __dirname, '../../artifacts/screenshots' );

// Order and captions match `== Screenshots ==` in readme.txt.
const SHOTS = [
	{ page: 'cforge', caption: 'Dashboard: what you have generated, and one-click cleanup' },
	{ page: 'cforge-pages-posts', caption: 'Every generated post in one list, with edit and view links' },
	{ page: 'cforge-pages-posts', caption: 'Generate posts and pages, with or without AI', addNew: true },
	{ page: 'cforge-cpt', caption: 'Generate WooCommerce products and other custom post types' },
	{ page: 'cforge-users', caption: 'Generate users with any role' },
	{ page: 'cforge-comments', caption: 'Generate comments and reply threads' },
	// The setup notices only show because the tests site has no AI provider configured.
	{ page: 'cforge-autopilot', caption: 'Autopilot: schedule AI posts', hide: [ 'Autopilot is turned off', 'Autopilot needs an AI provider' ] },
];

const cli = ( args ) => execSync( `npx wp-env run tests-cli wp cforge ${ args } --user=admin`, { stdio: 'pipe' } );

// Everything Content Forge generated, so the run starts and ends clean.
function clearGenerated() {
	for ( const type of [ 'posts', 'comments', 'users', 'terms' ] ) {
		cli( `delete ${ type } --all --yes` );
	}
}

let scheduleIds = [];

test.describe.configure( { mode: 'serial' } );

test.describe( 'WordPress.org screenshots', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'woocommerce' );
		clearGenerated();
		cli( 'generate terms --count=6 --taxonomy=category' );
		cli( 'generate terms --count=10 --taxonomy=post_tag' );
		cli( 'generate users --count=6 --roles=author,editor,subscriber' );
		cli( 'generate posts --count=12 --random-terms=category:1,post_tag:2-3 --featured-image=placehold' );
		cli( 'generate posts --count=6 --post_type=product --featured-image=placehold' );
		cli( 'generate comments --count=15 --replies=40' );

		for ( const [ name, mode ] of [ [ 'Gardening tips', 'daily' ], [ 'Weekly product roundup', 'weekly' ] ] ) {
			const schedule = await requestUtils.rest( {
				method: 'POST',
				path: `${ NS }/autopilot/schedules`,
				data: { name, config: { topic: { mode: 'list_cycle', list: [ name ] }, frequency: { mode } } },
			} );
			scheduleIds.push( schedule.id );
		}
		fs.mkdirSync( OUT, { recursive: true } );
		fs.mkdirSync( RAW, { recursive: true } );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		for ( const id of scheduleIds ) {
			await requestUtils.rest( { method: 'DELETE', path: `${ NS }/autopilot/schedules/${ id }`, params: { force: true } } ).catch( () => {} );
		}
		scheduleIds = [];
		clearGenerated();
		await requestUtils.deactivatePlugin( 'woocommerce' );
	} );

	SHOTS.forEach( ( shot, i ) => {
		test( `screenshot-${ i + 1 }: ${ shot.caption }`, async ( { page } ) => {
			await page.setViewportSize( { width: 1280, height: 800 } );
			await page.goto( `/wp-admin/admin.php?page=${ shot.page }` );
			await hideNotices( page );
			const app = page.locator( '[id^="cforge-"][id$="-app"]' );
			await app.waitFor();
			if ( shot.addNew ) {
				await page.getByRole( 'button', { name: 'Add New' } ).first().click();
			}
			await page.waitForLoadState( 'networkidle' );
			for ( const text of shot.hide || [] ) {
				await page.locator( 'div', { hasText: text } ).last().evaluate( ( el ) => el.remove() );
			}
			await page.waitForTimeout( 800 );

			const capture = await app.screenshot( { animations: 'disabled' } );
			fs.writeFileSync( path.join( RAW, `raw-${ i + 1 }.png` ), capture );
			await frame( page, capture, shot.caption, path.join( OUT, `screenshot-${ i + 1 }.png` ) );
		} );
	} );
} );

// The original artwork: light grey canvas, navy lower-right half, title and caption top-left,
// the screen as a white card that runs off the bottom edge.
async function frame( page, capture, caption, file ) {
	await page.setViewportSize( { width: 1982, height: 1076 } );
	await page.setContent( `<!doctype html><html><head><style>
		* { margin: 0; box-sizing: border-box; }
		html { overflow: hidden; }
		body { width: 1982px; height: 1076px; overflow: hidden; position: relative; background: #dcdde0;
			font-family: Inter, -apple-system, "Segoe UI", Roboto, sans-serif; }
		.navy { position: absolute; inset: 0; background: #0a1323; clip-path: polygon(0 100%, 100% 0, 100% 100%); }
		h1 { position: absolute; left: 97px; top: 84px; font-size: 52px; font-weight: 400; color: #334155; }
		p { position: absolute; left: 97px; top: 156px; font-size: 34px; color: #0b0f19; }
		.card { position: absolute; left: 240px; top: 300px; width: 1500px; height: 776px; overflow: hidden; background: #fff; padding: 24px 24px 0;
			box-shadow: 0 20px 60px rgba(10, 19, 35, .35); }
		.card img { display: block; width: 100%; }
	</style></head><body>
		<div class="navy"></div>
		<h1>Content Forge</h1>
		<p>${ caption }</p>
		<div class="card"><img src="data:image/png;base64,${ capture.toString( 'base64' ) }"></div>
	</body></html>` );
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	// Captures are taken at 2x for sharp text; the framed file stays at the original 1982x1076.
	await page.screenshot( { path: file, scale: 'css' } );
}
