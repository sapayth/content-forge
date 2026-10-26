// DESCRIPTION: Records footage for the "Content Forge quick start" how-to video (generate posts with featured images,
// DESCRIPTION: see them on the site, generate users, delete it all): one webm per segment plus how-to-quickstart.json.

const { test } = require( '@wordpress/e2e-test-utils-playwright' );
const { howto, hideNotices } = require( './recorder' );

const NS = '/cforge/v1';
const ADMIN = process.env.STORAGE_STATE_PATH;

// The smallest box holding both elements: the panel to outline, rather than just its heading text.
const section = ( page, a, b ) => page.locator( 'div' ).filter( { has: a } ).filter( { has: b } ).last();
// A switch's whole column: the switch, its label and its help text.
const toggle = ( page, label ) => page.locator( '.cforge-flex-1' ).filter( { has: page.getByText( label, { exact: true } ) } ).last();

let recorder;
let save;

// Remove everything Content Forge generated, so each take starts (and the site ends) clean.
async function clearGenerated( requestUtils ) {
	await requestUtils.rest( { method: 'DELETE', path: `${ NS }/posts/bulk` } );
	await requestUtils.rest( { method: 'DELETE', path: `${ NS }/users/bulk` } );
}

test.describe.configure( { mode: 'serial' } );

test.describe( 'How-to: Content Forge quick start', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		( { recorder, save } = howto( 'quickstart' ) );
		await clearGenerated( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		save();
		await clearGenerated( requestUtils );
	} );

	test( 'admin: generate posts', async ( { browser } ) => {
		const r = await recorder( browser, 'posts', ADMIN );
		const { page } = r;
		await page.goto( '/wp-admin/admin.php?page=cforge' );
		await hideNotices( page );

		r.step( 'dashboard' );
		await page.waitForTimeout( 1500 );
		await r.focus( section( page, page.getByRole( 'heading', { name: 'Start here' } ), page.getByRole( 'link', { name: 'Posts & pages Generate' } ) ) );
		await page.waitForTimeout( 1500 );
		await r.click( page.getByRole( 'link', { name: 'Posts & pages Generate' } ) );
		await page.waitForLoadState( 'networkidle' );
		r.wide();
		await page.waitForTimeout( 1200 );

		r.step( 'posts-modes' );
		await r.click( page.getByRole( 'button', { name: 'Add New' } ).first() );
		await page.waitForTimeout( 1500 );
		await r.focus( section( page, page.getByText( 'Auto Generate', { exact: true } ), page.getByText( 'AI Generate', { exact: true } ) ) );
		await page.waitForTimeout( 1500 );
		// Each mode card as the narration names it, then pick Auto Generate.
		const mode = ( name ) => page.locator( 'label' ).filter( { hasText: name } );
		await r.focus( mode( 'Auto Generate' ) );
		await page.waitForTimeout( 1500 );
		await r.focus( mode( 'Manual' ) );
		await page.waitForTimeout( 1500 );
		await r.focus( mode( 'AI Generate' ) );
		await page.waitForTimeout( 1500 );
		await r.click( mode( 'Auto Generate' ) );
		await page.waitForTimeout( 1500 );

		r.step( 'posts-basics' );
		await r.select( page.locator( '#cforge-pp-type' ), 'Post' );
		await page.waitForTimeout( 1200 );
		await r.fill( page.locator( '#cforge-pp-number-of-pages-posts' ), '6' );
		await page.waitForTimeout( 1200 );
		await r.select( page.locator( '#cforge-pp-pages-posts-status' ), 'Publish' );
		await page.waitForTimeout( 1200 );
		await r.focus( page.locator( '#cforge-pp-comment-status' ) );
		await page.waitForTimeout( 1500 );

		r.step( 'posts-options' );
		await r.click( page.getByLabel( 'Generate Featured Image' ) );
		await page.waitForTimeout( 1500 );
		// The grey panel, now holding the image sources the switch revealed.
		await r.focus( page.locator( '.cforge-bg-gray-50' ).filter( { hasText: 'Image Sources:' } ) );
		await page.waitForTimeout( 1500 );
		await r.focus( toggle( page, 'Generate Post Excerpt' ) );
		await page.waitForTimeout( 1500 );
		await r.focus( toggle( page, 'Randomize Post Dates' ) );
		await page.waitForTimeout( 1500 );

		r.step( 'posts-more' );
		await r.focus( section( page, page.getByText( 'Assign Author', { exact: true } ), page.getByText( 'Random authors' ) ) );
		await page.waitForTimeout( 2000 );
		await r.focus( section( page, page.getByText( 'Taxonomies', { exact: true } ), page.getByText( 'Do not assign' ).first() ) );
		await page.waitForTimeout( 2000 );
		await r.click( page.getByRole( 'button', { name: 'Generate', exact: true } ) );
		// Six Picsum downloads; the form returns to the list when it's done.
		await page.getByRole( 'button', { name: 'Delete All' } ).waitFor( { timeout: 120_000 } );
		// The Generate click left the page scrolled down; show the list from its header.
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		r.wide();
		await page.waitForTimeout( 1500 );

		r.step( 'posts-list' );
		await r.focus( page.locator( 'table' ) );
		await page.waitForTimeout( 1500 );
		await r.focus( page.locator( 'table tbody tr' ).first() );
		await page.waitForTimeout( 2000 );
		await r.close();
	} );

	test( 'site: the posts on the front end', async ( { browser } ) => {
		const r = await recorder( browser, 'site', ADMIN );
		const { page } = r;
		r.step( 'site' );
		await page.goto( '/' );
		await page.waitForLoadState( 'networkidle' );
		await page.waitForTimeout( 2000 );
		await r.scrollTo( page.locator( 'main .wp-block-post' ).nth( 1 ) );
		await page.waitForTimeout( 1500 );
		await r.close();
	} );

	test( 'admin: generate users, then delete everything', async ( { browser } ) => {
		const r = await recorder( browser, 'users', ADMIN );
		const { page } = r;
		await page.goto( '/wp-admin/admin.php?page=cforge-users' );
		await hideNotices( page );

		r.step( 'users' );
		await page.waitForTimeout( 1500 );
		await r.click( page.getByRole( 'button', { name: 'Add New' } ).first() );
		await page.waitForTimeout( 1200 );
		await r.fill( page.locator( '#cforge-user-number' ), '5' );
		await page.waitForTimeout( 1000 );
		await r.click( page.locator( '#cforge-user-roles option', { hasText: 'Author' } ) );
		await page.waitForTimeout( 1200 );
		await r.click( page.getByRole( 'button', { name: 'Generate Users' } ) );
		await page.getByRole( 'button', { name: 'Delete All' } ).waitFor( { timeout: 60_000 } );
		r.wide();
		await page.waitForTimeout( 1500 );
		await r.focus( page.locator( 'table' ) );
		await page.waitForTimeout( 2000 );

		r.step( 'cleanup' );
		await page.goto( '/wp-admin/admin.php?page=cforge-pages-posts' );
		await page.getByRole( 'button', { name: 'Delete All' } ).waitFor();
		r.wide();
		await page.waitForTimeout( 1500 );
		// The confirm() never paints in a headless recording; accept it and let the list empty on camera.
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await r.click( page.getByRole( 'button', { name: 'Delete All' } ) );
		await page.getByText( 'Nothing here yet' ).waitFor();
		await page.waitForTimeout( 1500 );
		await r.focus( section( page, page.getByText( 'Nothing here yet' ), page.getByRole( 'button', { name: 'Add New' } ).last() ) );
		await page.waitForTimeout( 2000 );
		await r.close();
	} );
} );
