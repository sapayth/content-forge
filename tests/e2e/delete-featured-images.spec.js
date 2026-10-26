// DESCRIPTION: Deleting generated posts also deletes the featured images Content Forge downloaded for them,
// DESCRIPTION: and leaves an image the user uploaded alone (docs/bug.md, 2026-10-24).

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const NS = '/cforge/v1';

const attachmentIds = async ( requestUtils ) =>
	( await requestUtils.rest( { path: '/wp/v2/media', params: { per_page: 100, _fields: 'id' } } ) ).map( ( m ) => m.id );

test.describe( 'Delete generated posts', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.rest( { method: 'DELETE', path: `${ NS }/posts/bulk` } );
		await requestUtils.deleteAllMedia();
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( { method: 'DELETE', path: `${ NS }/posts/bulk` } );
		await requestUtils.deleteAllMedia();
	} );

	test( 'Delete All removes their generated featured images and the dashboard is empty again', async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'POST',
			path: `${ NS }/posts/bulk`,
			data: { post_type: 'post', post_status: 'publish', post_number: 2, generate_image: true, image_sources: [ 'placehold' ] },
		} );
		expect( await attachmentIds( requestUtils ) ).toHaveLength( 2 );

		await requestUtils.rest( { method: 'DELETE', path: `${ NS }/posts/bulk` } );

		expect( await attachmentIds( requestUtils ) ).toHaveLength( 0 );
		const stats = await requestUtils.rest( { path: `${ NS }/dashboard` } );
		expect( stats.total ).toBe( 0 );
	} );

	test( "deleting a post keeps a featured image the user uploaded", async ( { requestUtils } ) => {
		// A 1x1 PNG the user "uploaded": not generated, so not Content Forge's to delete.
		const file = path.join( os.tmpdir(), 'cforge-own-image.png' );
		fs.writeFileSync( file, Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64' ) );
		const own = await requestUtils.uploadMedia( file );
		await requestUtils.rest( {
			method: 'POST',
			path: `${ NS }/posts/bulk`,
			data: { post_type: 'post', post_status: 'publish', post_number: 1 },
		} );
		const { items } = await requestUtils.rest( { path: `${ NS }/posts/list`, params: { post_types: 'post' } } );
		await requestUtils.rest( { method: 'POST', path: `/wp/v2/posts/${ items[ 0 ].ID }`, data: { featured_media: own.id } } );

		await requestUtils.rest( { method: 'DELETE', path: `${ NS }/posts/${ items[ 0 ].ID }` } );

		expect( await attachmentIds( requestUtils ) ).toEqual( [ own.id ] );
	} );
} );
