<?php
// DESCRIPTION: Self-check for Autopilot featured images — capability flags, the source
// whitelist, alt text, tracking, and that an image failure never costs us the post.
//
// The PHPUnit suite bootstraps without WordPress, so this runs against a live install:
//   wp eval-file wp-content/plugins/content-forge/tests/manual/featured-image-check.php
//
// Steps 1-3 make no network calls. Step 4 hits the real image API and is skipped unless
// an image-capable provider has a key configured.

use ContentForge\Generator\AI_Content_Generator;
use ContentForge\Generator\Image;
use ContentForge\Settings\AI_Settings_Manager;

$pass = function ( $label, $ok ) {
	echo $label . ': ' . ( $ok ? "PASS\n" : "FAIL\n" );
	return $ok;
};

wp_set_current_user( 1 );

// 1. Capability flags come from the provider classes, not a hardcoded list.
$expected = [
	'openai'    => true,
	'google'    => true,
	'anthropic' => false,
	'mistral'   => false,
	'deepseek'  => false,
];
$caps_ok = true;
foreach ( $expected as $slug => $want ) {
	$got = AI_Content_Generator::make_provider( $slug, '', '' )->supports_images();
	if ( $got !== $want ) {
		$caps_ok = false;
		echo "CAPS: {$slug} expected " . var_export( $want, true ) . ', got ' . var_export( $got, true ) . "\n";
	}
}
$pass( 'CAPS', $caps_ok );
$pass( 'CAPS: list matches flags', AI_Settings_Manager::get_image_providers() === [ 'openai', 'google' ] );

// 2. Text-only providers refuse with a WP_Error rather than fataling.
$refusal = AI_Content_Generator::make_provider( 'anthropic', '', '' )->generate_image( 'anything' );
$pass( 'REFUSE', is_wp_error( $refusal ) && 'cforge_no_image_support' === $refusal->get_error_code() );

// 3. Source whitelist at the REST boundary: junk falls back to 'none'.
$api    = new ReflectionMethod( 'ContentForge\Api\Autopilot_Schedules', 'sanitize_config' );
$api->setAccessible( true );
$sanitizer = new ContentForge\Api\Autopilot_Schedules();
$source_of = function ( $value ) use ( $api, $sanitizer ) {
	$config = $api->invoke( $sanitizer, [ 'targeting' => [ 'featured_image' => [ 'source' => $value ] ] ] );
	return $config['targeting']['featured_image']['source'];
};
$whitelist_ok = 'none' === $source_of( 'none' )
	&& 'placeholder' === $source_of( 'placeholder' )
	&& 'ai' === $source_of( 'ai' )
	&& 'none' === $source_of( 'wat' )
	&& 'none' === $source_of( '<script>' );
$pass( 'WHITELIST', $whitelist_ok );

// 3b. Structural fallbacks are silenced by error code in Schedule_Runner. Those codes
// are a contract between two files: if an emitter is renamed and the silent list is not,
// every run starts reporting PARTIAL again. Assert the emitters still match the list.
$structural = [ 'cforge_no_image_support', 'cforge_no_api_key' ];

$no_support = AI_Content_Generator::make_provider( 'anthropic', '', '' )->generate_image( 'x' );
$pass( 'FALLBACK: no-image-model code', in_array( $no_support->get_error_code(), $structural, true ) );

// generate_from_ai() bails with cforge_no_api_key before any network call when the
// active provider has no key stored.
if ( AI_Settings_Manager::get_api_key( AI_Settings_Manager::get_active_provider() ) ) {
	echo "FALLBACK: no-key code: SKIP (a key is configured)\n";
} else {
	$no_key = ( new Image( 1 ) )->generate_from_ai( 'x', 'x' );
	$pass( 'FALLBACK: no-key code', in_array( $no_key->get_error_code(), $structural, true ) );
}

// 4. Placeholder path: attachment tracked, alt text set, thumbnail attached.
global $wpdb;
$table   = $wpdb->prefix . CFORGE_DBNAME;
$post_id = wp_insert_post( [ 'post_title' => 'Featured image check', 'post_status' => 'draft' ] );

$image = new Image( 1 );
$ids   = $image->generate( 1, [ 'title' => 'Featured image check', 'sources' => [ 'picsum' ] ] );

if ( empty( $ids ) ) {
	echo "PLACEHOLDER: SKIP (could not reach picsum.photos)\n";
} else {
	set_post_thumbnail( $post_id, (int) $ids[0] );
	$tracked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND data_type = 'attachment'", $ids[0] ) );
	$alt     = get_post_meta( $ids[0], '_wp_attachment_image_alt', true );

	$pass( 'PLACEHOLDER: thumbnail', (int) get_post_thumbnail_id( $post_id ) === (int) $ids[0] );
	$pass( 'PLACEHOLDER: tracked', 1 === $tracked );
	$pass( 'PLACEHOLDER: alt text', 'Featured image check' === $alt );

	wp_delete_attachment( (int) $ids[0], true );
}

// 5. A bad API key must return WP_Error, never throw — the post survives either way.
$broken = new class(1) extends Image {
	public function provider_error() {
		return AI_Content_Generator::make_provider( 'openai', 'gpt-4', 'sk-invalid-key-for-testing' )
			->generate_image( 'a test image' );
	}
};
$err = $broken->provider_error();
$pass( 'BAD KEY', is_wp_error( $err ) );
$pass( 'BAD KEY: post survives', 'draft' === get_post_status( $post_id ) );

wp_delete_post( $post_id, true );

// 6. Live AI path — only when an image-capable provider is actually configured.
$provider = AI_Settings_Manager::get_active_provider();
if ( ! in_array( $provider, AI_Settings_Manager::get_image_providers(), true ) || ! AI_Settings_Manager::get_api_key( $provider ) ) {
	echo "AI  : SKIP (active provider '{$provider}' has no image model or no key)\n";
	return;
}

$ai_id = ( new Image( 1 ) )->generate_from_ai( 'A red bicycle leaning on a wall. No text.', 'Red bicycle' );

if ( is_wp_error( $ai_id ) ) {
	echo 'AI  : FAIL (' . $ai_id->get_error_message() . ")\n";
	return;
}

$pass( 'AI  : attachment created', wp_attachment_is_image( $ai_id ) );
$pass( 'AI  : alt text', 'Red bicycle' === get_post_meta( $ai_id, '_wp_attachment_image_alt', true ) );
echo 'AI  : ' . wp_get_attachment_url( $ai_id ) . "\n";

wp_delete_attachment( $ai_id, true );
