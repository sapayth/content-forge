// DESCRIPTION: Records footage for the "Fill a WooCommerce test store" how-to video (product categories, products
// DESCRIPTION: with photos, prices, sales, SKUs and stock, the shop page, Delete All): one webm per segment plus a log.

const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const { test } = require( '@wordpress/e2e-test-utils-playwright' );
const { howto, hideNotices } = require( './recorder' );

const NS = '/cforge/v1';
const ADMIN = process.env.STORAGE_STATE_PATH;
const PLUGIN = path.join( __dirname, '..', '..' );

// WP-CLI on the tests site, for state the REST API doesn't expose.
const wpcli = ( ...args ) =>
	execFileSync( 'npx', [ 'wp-env', 'run', 'tests-cli', 'wp', ...args ], { cwd: PLUGIN, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } );

// The smallest box holding both elements: the panel to outline, rather than just its heading text.
const section = ( page, a, b ) => page.locator( 'div' ).filter( { has: a } ).filter( { has: b } ).last();

let recorder;
let save;

// Remove every product, image and term Content Forge generated, so each take starts (and the site ends) clean.
async function clearGenerated( requestUtils ) {
	await requestUtils.rest( { method: 'DELETE', path: `${ NS }/posts/bulk` } );
	await requestUtils.rest( { method: 'DELETE', path: `${ NS }/taxonomy/bulk` } );
}

test.describe.configure( { mode: 'serial' } );

test.describe( 'How-to: Fill a WooCommerce test store', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		( { recorder, save } = howto( 'woocommerce' ) );
		await requestUtils.activatePlugin( 'woocommerce' );
		// A fresh store is in "coming soon" mode and nags about setup; keep both off camera.
		await requestUtils.rest( { method: 'POST', path: '/wp/v2/settings', data: { woocommerce_coming_soon: 'no' } } ).catch( () => {} );
		// Off camera: the review prompt (shown a day after install) and Action Scheduler's past-due warning.
		// wp-env's loopback never runs the queue, so WooCommerce's housekeeping jobs pile up; run them here.
		wpcli( 'option', 'update', 'cforge_review_notice_dismissed', '1' );
		wpcli( 'action-scheduler', 'run' );
		await clearGenerated( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		save();
		await clearGenerated( requestUtils );
		await requestUtils.deactivatePlugin( 'woocommerce' );
		wpcli( 'option', 'delete', 'cforge_review_notice_dismissed' );
	} );

	test( 'admin: generate product categories', async ( { browser } ) => {
		const r = await recorder( browser, 'categories', ADMIN );
		const { page } = r;
		await page.goto( '/wp-admin/admin.php?page=cforge-taxonomies' );
		await hideNotices( page );

		r.step( 'categories' );
		await page.waitForTimeout( 1500 );
		await r.click( page.getByRole( 'button', { name: 'Add New' } ).first() );
		await page.waitForTimeout( 1200 );
		// The narration explains the list of taxonomies before one is picked.
		await r.focus( page.locator( '#cforge-taxonomy-type' ) );
		await page.waitForTimeout( 1500 );
		await r.select( page.locator( '#cforge-taxonomy-type' ), 'Product categories' );
		await page.waitForTimeout( 1200 );
		await r.fill( page.locator( '#cforge-taxonomy-count' ), '4' );
		await page.waitForTimeout( 1200 );
		await r.click( page.getByRole( 'button', { name: 'Generate Terms' } ) );
		await page.getByRole( 'button', { name: 'Delete All' } ).waitFor( { timeout: 60_000 } );
		r.wide();
		await page.waitForTimeout( 1500 );
		await r.focus( page.locator( 'table' ) );
		await page.waitForTimeout( 2000 );
		await r.close();
	} );

	test( 'admin: generate products', async ( { browser } ) => {
		const r = await recorder( browser, 'products', ADMIN );
		const { page } = r;
		await page.goto( '/wp-admin/admin.php?page=cforge-cpt' );
		await hideNotices( page );

		r.step( 'products-basics' );
		await page.waitForTimeout( 1500 );
		await r.click( page.getByRole( 'button', { name: 'Add New' } ).first() );
		await page.waitForTimeout( 1200 );
		await r.select( page.locator( '#cforge-cpt-post-type' ), 'Product' );
		await page.waitForTimeout( 1200 );
		await r.fill( page.locator( '#cforge-cpt-number-to-generate' ), '12' );
		await page.waitForTimeout( 1200 );
		await r.focus( page.locator( '#cforge-cpt-status' ) );
		await page.waitForTimeout( 1500 );

		r.step( 'products-categories' );
		const cats = page.locator( '.cforge-border' ).filter( { has: page.getByText( 'Product categories', { exact: true } ) } ).last();
		await r.focus( cats );
		await page.waitForTimeout( 1500 );
		await r.click( cats.getByText( 'Specific terms' ) );
		await page.waitForTimeout( 1200 );
		// The four categories just generated: every term but WooCommerce's own Uncategorized.
		const list = cats.locator( 'select[multiple]' );
		const generated = ( await list.locator( 'option' ).allTextContents() ).filter( ( name ) => name !== 'Uncategorized' );
		await r.focus( list );
		await list.selectOption( generated.map( ( label ) => ( { label } ) ) );
		await page.waitForTimeout( 1500 );

		r.step( 'products-image' );
		await r.click( page.getByLabel( 'Generate Featured Image' ) );
		await page.waitForTimeout( 1500 );
		await r.focus( page.locator( '.cforge-bg-gray-50' ).filter( { hasText: 'Image Sources:' } ) );
		await page.waitForTimeout( 1500 );

		r.step( 'products-prices' );
		const settings = page.locator( 'div' ).filter( { has: page.getByText( 'WooCommerce product settings' ) } ).last();
		await r.focus( settings );
		await page.waitForTimeout( 1500 );
		await r.fill( page.locator( '#cforge-cpt-price-min' ), '15' );
		await page.waitForTimeout( 800 );
		await r.fill( page.locator( '#cforge-cpt-price-max' ), '120' );
		await page.waitForTimeout( 1200 );
		await r.fill( page.locator( '#cforge-cpt-sale-price-min-optional' ), '10' );
		await page.waitForTimeout( 800 );
		await r.fill( page.locator( '#cforge-cpt-sale-price-max-optional' ), '60' );
		await page.waitForTimeout( 1200 );

		r.step( 'products-stock' );
		await r.fill( page.locator( '#cforge-cpt-sku-prefix' ), 'DEMO-' );
		await page.waitForTimeout( 1200 );
		const stock = page.locator( '#cforge-multiselect' );
		await r.focus( stock );
		await stock.selectOption( [ 'instock', 'onbackorder' ] );
		await page.waitForTimeout( 1500 );
		await r.click( page.getByRole( 'button', { name: 'Generate', exact: true } ) );
		// Twelve Picsum downloads; the form returns to the list when it's done.
		await page.getByRole( 'button', { name: 'Delete All' } ).waitFor( { timeout: 180_000 } );
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		r.wide();
		await page.waitForTimeout( 1500 );

		r.step( 'products-list' );
		await r.focus( page.locator( 'table' ) );
		await page.waitForTimeout( 2000 );
		await r.close();
	} );

	test( 'site: the shop page and one product', async ( { browser } ) => {
		const r = await recorder( browser, 'shop', ADMIN );
		const { page } = r;
		r.step( 'shop' );
		await page.goto( '/?post_type=product' );
		await page.waitForLoadState( 'networkidle' );
		await page.waitForTimeout( 2000 );
		const cards = page.locator( 'li.product, .wc-block-product' );
		await r.focus( cards.first() );
		await page.waitForTimeout( 1500 );
		await r.scrollTo( cards.nth( 3 ) );
		await page.waitForTimeout( 1200 );
		// Open a product that is on sale, so its page shows both prices.
		const onSale = cards.filter( { has: page.locator( 'del' ) } ).first();
		await r.click( onSale.locator( 'a' ).first() );
		await page.waitForLoadState( 'networkidle' );
		r.wide();
		await page.waitForTimeout( 1500 );

		r.step( 'product' );
		await r.focus( page.locator( '.price, .wc-block-components-product-price' ).first() );
		await page.waitForTimeout( 1500 );
		// SKU and category sit together; in-stock products show no stock line unless stock is managed.
		await r.focus( page.locator( 'div' ).filter( { has: page.getByText( /^SKU:/ ) } ).filter( { hasText: 'Category' } ).last() );
		await page.waitForTimeout( 1500 );
		await r.focus( page.getByRole( 'tabpanel', { name: 'Description' } ) );
		await page.waitForTimeout( 1500 );
		await r.close();
	} );

	test( 'admin: delete everything', async ( { browser } ) => {
		const r = await recorder( browser, 'cleanup', ADMIN );
		const { page } = r;
		await page.goto( '/wp-admin/admin.php?page=cforge-cpt' );
		await page.getByRole( 'button', { name: 'Delete All' } ).waitFor();

		r.step( 'cleanup' );
		await page.waitForTimeout( 1500 );
		// The confirm() never paints in a headless recording; accept it and let the list empty on camera.
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await r.click( page.getByRole( 'button', { name: 'Delete All' } ) );
		await page.getByText( 'Nothing here yet' ).waitFor( { timeout: 60_000 } );
		await page.waitForTimeout( 1500 );
		await r.focus( section( page, page.getByText( 'Nothing here yet' ), page.getByRole( 'button', { name: 'Add New' } ).last() ) );
		await page.waitForTimeout( 2000 );
		await r.close();
	} );
} );
