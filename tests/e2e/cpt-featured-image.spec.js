// DESCRIPTION: The Custom Post Types form can give generated WooCommerce products a featured image
// DESCRIPTION: (docs/bug.md, 2026-10-24): the switch is on the form and the product gets a thumbnail.

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const NS = '/cforge/v1';

test.describe( 'Custom Post Types featured image', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'woocommerce' );
		await requestUtils.rest( { method: 'DELETE', path: `${ NS }/posts/bulk` } );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( { method: 'DELETE', path: `${ NS }/posts/bulk` } );
		await requestUtils.deactivatePlugin( 'woocommerce' );
	} );

	test( 'a product generated with Generate Featured Image on has a thumbnail', async ( { page, admin, requestUtils } ) => {
		await admin.visitAdminPage( 'admin.php', 'page=cforge-cpt' );
		await page.getByRole( 'button', { name: 'Add New' } ).first().click();
		await page.locator( '#cforge-cpt-post-type' ).selectOption( 'product' );
		await page.getByLabel( 'Generate Featured Image' ).click();
		await page.locator( '#cforge-cpt-source-picsum' ).uncheck();
		await page.locator( '#cforge-cpt-source-placehold' ).check();
		await page.getByRole( 'button', { name: 'Generate', exact: true } ).click();
		await page.getByRole( 'button', { name: 'Delete All' } ).waitFor( { timeout: 60_000 } );

		const products = await requestUtils.rest( { path: '/wp/v2/product', params: { _fields: 'id,featured_media' } } );
		expect( products ).toHaveLength( 1 );
		expect( products[ 0 ].featured_media ).toBeGreaterThan( 0 );
	} );
} );
