// DESCRIPTION: Records footage for the "Content Forge Autopilot" how-to video (connect Google AI, create an Autopilot,
// DESCRIPTION: preview it, run it, review the draft): one webm per segment plus how-to-autopilot.json.

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const { test } = require( '@wordpress/e2e-test-utils-playwright' );
const { howto, hideNotices } = require( './recorder' );

const ADMIN = process.env.STORAGE_STATE_PATH;
const PLUGIN = path.join( __dirname, '..', '..' );
// Kept out of the repo and off camera; the settings page only ever shows it masked.
const KEY_FILE = path.join( os.homedir(), '.config', 'cforge-google-key' );
const NAME = 'Weekly gardening tips';
const TOPICS = [
	'Five easy vegetables for beginner gardeners',
	'How often should you water houseplants',
	'Starting a herb garden on your windowsill',
];

// Run PHP in the test site through WP-CLI.
const wp = ( php ) =>
	execFileSync( 'npx', [ 'wp-env', 'run', 'tests-cli', 'wp', 'eval', php ], { cwd: PLUGIN, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } );

// Remove every Autopilot, its runs, and the posts and images they made.
const CLEAR = `
	global $wpdb;
	foreach ( get_posts( [ 'post_type' => 'cforge_autopilot', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $id ) { wp_delete_post( $id, true ); }
	$wpdb->query( "TRUNCATE {$wpdb->prefix}cforge_autopilot_runs" );
	delete_option( 'cforge_autopilot_admin_notices' );
	$cat = term_exists( 'Gardening', 'category' );
	foreach ( $cat ? get_posts( [ 'category' => (int) $cat['term_id'], 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) : [] as $id ) {
		$thumb = get_post_thumbnail_id( $id );
		wp_delete_post( $id, true );
		if ( $thumb ) { wp_delete_attachment( $thumb, true ); }
	}
`;

// The smallest box holding both elements: the panel to outline, rather than just its heading text.
const section = ( page, a, b ) => page.locator( 'div' ).filter( { has: a } ).filter( { has: b } ).last();
// One of the Autopilot form's white panels, by its heading.
const panel = ( page, title ) =>
	page.locator( 'div.cforge-border.cforge-rounded.cforge-p-4' ).filter( { has: page.getByRole( 'heading', { name: title, exact: true } ) } );

let recorder;
let save;
let postId;

test.describe.configure( { mode: 'serial' } );

test.describe( 'How-to: Content Forge Autopilot', () => {
	test.beforeAll( () => {
		( { recorder, save } = howto( 'autopilot' ) );
		const key = fs.readFileSync( KEY_FILE, 'utf8' ).trim();
		// Google's key is stored but OpenAI is active, so choosing Google on camera brings the saved key up masked.
		wp( `
			${ CLEAR }
			term_exists( 'Gardening', 'category' ) || wp_insert_term( 'Gardening', 'category' );
			\\ContentForge\\Settings\\AI_Settings_Manager::save_api_key( 'google', ${ JSON.stringify( key ) } );
			delete_option( 'cforge_ai_google_model' );
			update_option( 'cforge_ai_provider', 'openai' );
			update_option( 'cforge_autopilot_enabled', 0 );
			$prefs = get_user_meta( 1, $wpdb->get_blog_prefix() . 'persisted_preferences', true ) ?: [];
			$prefs['core/edit-post'] = array_merge( $prefs['core/edit-post'] ?? [], [ 'welcomeGuide' => false ] );
			$prefs['core'] = array_merge( $prefs['core'] ?? [], [ 'fullscreenMode' => false ] );
			$prefs['_modified'] = gmdate( 'c' );
			update_user_meta( 1, $wpdb->get_blog_prefix() . 'persisted_preferences', $prefs );
		` );
	} );

	test.afterAll( () => {
		save();
		wp( `${ CLEAR } delete_option( 'cforge_ai_google_key' ); update_option( 'cforge_ai_provider', 'openai' ); update_option( 'cforge_autopilot_enabled', 0 );` );
	} );

	test( 'admin: connect Google AI and turn Autopilot on', async ( { browser } ) => {
		const r = await recorder( browser, 'settings', ADMIN );
		const { page } = r;
		await page.goto( '/wp-admin/admin.php?page=cforge-settings' );
		await hideNotices( page );
		await page.getByRole( 'button', { name: 'Test Connection' } ).waitFor();

		r.step( 'settings' );
		await page.waitForTimeout( 1500 );
		const selects = page.locator( 'select' );
		await r.select( selects.nth( 0 ), 'Google' );
		await page.waitForTimeout( 1500 );
		await r.select( selects.nth( 1 ), 'Gemini 3.5 Flash-Lite' );
		await page.waitForTimeout( 1200 );
		// The saved key, already masked.
		await r.focus( section( page, page.getByText( 'API Key', { exact: true } ), page.getByRole( 'link', { name: 'Get API Key' } ) ) );
		await page.waitForTimeout( 1500 );
		await r.click( page.getByRole( 'button', { name: 'Test Connection' } ) );
		await page.locator( '#cforge-settings-app' ).getByText( 'Connection successful' ).waitFor( { timeout: 60_000 } );
		await page.waitForTimeout( 1500 );
		await r.click( page.getByRole( 'button', { name: 'Save Changes' } ) );
		await page.locator( '#cforge-settings-app' ).getByText( 'Settings saved successfully' ).waitFor();
		await page.waitForTimeout( 1500 );

		r.step( 'enable' );
		await r.click( page.getByLabel( 'Enable Autopilot', { exact: true } ) );
		await page.locator( '#cforge-settings-app' ).getByText( 'Saved.', { exact: true } ).waitFor();
		await page.waitForTimeout( 1500 );
		await r.close();
	} );

	test( 'admin: create an Autopilot, preview it, run it', async ( { browser } ) => {
		const r = await recorder( browser, 'create', ADMIN );
		const { page } = r;
		await page.goto( '/wp-admin/admin.php?page=cforge-autopilot' );
		await hideNotices( page );

		r.step( 'create' );
		await page.waitForTimeout( 1500 );
		await r.click( page.getByRole( 'button', { name: 'Create your first Autopilot' } ) );
		await page.waitForTimeout( 1200 );
		r.wide();
		await r.type( page.getByLabel( 'Name', { exact: true } ), NAME );
		await page.waitForTimeout( 1200 );

		r.step( 'topics' );
		await r.select( page.getByLabel( 'Topic source', { exact: true } ), 'List (cycle in order)' );
		await page.waitForTimeout( 1200 );
		await r.fill( page.getByLabel( 'Topics (one per line)', { exact: true } ), TOPICS.join( '\n' ) );
		// The list is read when the box loses focus.
		await page.getByLabel( 'Topics (one per line)', { exact: true } ).blur();
		await page.waitForTimeout( 1200 );
		await r.focus( page.getByLabel( 'Avoid duplicating recent posts in this category', { exact: true } ).locator( '..' ).locator( '..' ) );
		await page.waitForTimeout( 1500 );

		r.step( 'targeting' );
		await r.select( page.getByLabel( 'Category', { exact: true } ), 'Gardening' );
		await page.waitForTimeout( 1200 );
		await r.focus( page.getByLabel( 'Author', { exact: true } ) );
		await page.waitForTimeout( 1200 );
		await r.select( page.getByLabel( 'Featured image', { exact: true } ), 'Placeholder image' );
		await page.waitForTimeout( 1500 );

		r.step( 'ai' );
		await r.select( page.getByLabel( 'Tone', { exact: true } ), 'Casual' );
		await page.waitForTimeout( 1200 );
		await r.select( page.getByLabel( 'Approximate length', { exact: true } ), 'Short (~300 words)' );
		await page.waitForTimeout( 1500 );

		r.step( 'schedule' );
		await r.select( page.getByLabel( 'Frequency', { exact: true } ), 'Weekly' );
		await page.waitForTimeout( 1200 );
		await r.focus( panel( page, 'Schedule' ) );
		await page.waitForTimeout( 1500 );

		r.step( 'publishing' );
		await r.select( page.getByLabel( 'What to do with generated posts', { exact: true } ), 'Save as draft (recommended)' );
		await page.waitForTimeout( 1500 );

		r.step( 'safety' );
		await r.focus( panel( page, 'Safety' ) );
		await page.waitForTimeout( 1500 );
		await r.focus( panel( page, 'Notifications' ) );
		await page.waitForTimeout( 1500 );

		r.step( 'preview' );
		await r.click( page.getByRole( 'button', { name: 'Preview sample' } ) );
		await page.getByText( 'Preview only — nothing has been saved.' ).waitFor( { timeout: 120_000 } );
		await page.waitForTimeout( 1200 );
		await r.focus( page.locator( '.cforge-bg-blue-50' ).filter( { hasText: 'Sample post' } ) );
		await page.waitForTimeout( 2500 );

		r.step( 'save' );
		await r.click( page.getByLabel( 'Activate immediately after creating', { exact: true } ) );
		await page.waitForTimeout( 1000 );
		await r.click( page.getByRole( 'button', { name: 'Create autopilot' } ) );
		const card = page.locator( 'div.cforge-border.cforge-rounded.cforge-p-4' ).filter( { has: page.getByRole( 'heading', { name: NAME } ) } );
		await card.waitFor();
		r.wide();
		await page.waitForTimeout( 1500 );
		await r.focus( card );
		await page.waitForTimeout( 1500 );
		await r.click( card.getByRole( 'button', { name: 'Run now' } ) );
		await page.waitForTimeout( 2000 );
		await r.close();
	} );

	test( 'admin: the run history and the draft', async ( { browser } ) => {
		// Run now only queues the run; wp-env's loopback never fires Action Scheduler, so run the queue here, off camera.
		// Poll until the run has finished: it may already be under way, started by the Run now request.
		let run = {};
		for ( let i = 0; i < 24 && ! [ 'success', 'partial', 'failed', 'skipped' ].includes( run.status ); i++ ) {
			execFileSync( 'npx', [ 'wp-env', 'run', 'tests-cli', 'wp', 'action-scheduler', 'run', '--hooks=cforge_autopilot_run_schedule' ], { cwd: PLUGIN, stdio: 'ignore' } );
			const out = wp( `global $wpdb; echo wp_json_encode( $wpdb->get_row( "SELECT status, posts_created, error_message FROM {$wpdb->prefix}cforge_autopilot_runs ORDER BY id DESC LIMIT 1" ) );` );
			run = JSON.parse( out.trim().split( '\n' ).pop() || '{}' ) || {};
		}
		// eslint-disable-next-line no-console
		console.log( 'Run:', JSON.stringify( run ) );
		postId = Number( JSON.parse( run.posts_created || '[]' )[ 0 ] || 0 );
		test.expect( postId ).toBeGreaterThan( 0 );

		const r = await recorder( browser, 'history', ADMIN );
		const { page } = r;
		await page.goto( '/wp-admin/admin.php?page=cforge-autopilot' );
		await hideNotices( page );
		const card = page.locator( 'div.cforge-border.cforge-rounded.cforge-p-4' ).filter( { has: page.getByRole( 'heading', { name: NAME } ) } );
		await card.waitFor();

		r.step( 'history' );
		await page.waitForTimeout( 1500 );
		await r.focus( card.getByText( 'Posts created' ).locator( '..' ) );
		await page.waitForTimeout( 1200 );
		await r.click( card.getByRole( 'button', { name: 'History' } ) );
		const row = page.locator( 'div.cforge-border.cforge-rounded.cforge-p-3' ).first();
		await row.waitFor();
		r.wide();
		await page.waitForTimeout( 1500 );
		await r.focus( row );
		await page.waitForTimeout( 2000 );
		await r.close();

		// The post link opens a new tab; start the next segment at its URL instead.
		const d = await recorder( browser, 'draft', ADMIN );
		d.step( 'draft' );
		await d.page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
		await d.page.getByRole( 'region', { name: 'Editor content' } ).waitFor();
		d.wide();
		await d.page.waitForTimeout( 2500 );
		await d.focus( d.page.getByRole( 'region', { name: 'Editor settings' } ) );
		await d.page.waitForTimeout( 2500 );
		await d.close();
	} );
} );
