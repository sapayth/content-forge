<?php
// Self-check for ContentForge\Cleanup: run via `wp eval-file`.
global $wpdb;
$table = $wpdb->prefix . CFORGE_DBNAME;

$track = function ( $id, $type ) use ( $wpdb, $table ) {
	$wpdb->insert( $table, [ 'object_id' => $id, 'data_type' => $type, 'created_at' => current_time( 'mysql' ), 'created_by' => 1 ], [ '%d', '%s', '%s', '%d' ] );
};
$rows = function ( $id, $type ) use ( $wpdb, $table ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND data_type = %s", $id, $type ) );
};

// 1. Post.
$post_id = wp_insert_post( [ 'post_title' => 'cforge cleanup check', 'post_status' => 'publish' ] );
$track( $post_id, 'post' );
if ( ! ( 1 === $rows( $post_id, 'post' ) ) ) { exit( "FAIL: " . 'post row not inserted' . "\n" ); }
wp_delete_post( $post_id, true );
if ( ! ( 0 === $rows( $post_id, 'post' ) ) ) { exit( "FAIL: " . 'post row survived deletion' . "\n" ); }

// 2. User.
$user_id = wp_insert_user( [ 'user_login' => 'cforge_check_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'cforge_' . wp_rand() . '@example.test' ] );
$track( $user_id, 'user' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );
if ( ! ( 0 === $rows( $user_id, 'user' ) ) ) { exit( "FAIL: " . 'user row survived deletion' . "\n" ); }

// 3. Comment.
$host_id    = wp_insert_post( [ 'post_title' => 'cforge comment host', 'post_status' => 'publish' ] );
$comment_id = wp_insert_comment( [ 'comment_post_ID' => $host_id, 'comment_content' => 'hi', 'comment_approved' => 1 ] );
$track( $comment_id, 'comment' );
wp_delete_comment( $comment_id, true );
if ( ! ( 0 === $rows( $comment_id, 'comment' ) ) ) { exit( "FAIL: " . 'comment row survived deletion' . "\n" ); }
wp_delete_post( $host_id, true );

// 4. Term.
$term = wp_insert_term( 'cforge check ' . wp_rand(), 'category' );
$track( $term['term_id'], 'category' );
wp_delete_term( $term['term_id'], 'category' );
if ( ! ( 0 === $rows( $term['term_id'], 'category' ) ) ) { exit( "FAIL: " . 'term row survived deletion' . "\n" ); }

// 5. Untracked deletions must not touch other rows.
$other = wp_insert_post( [ 'post_title' => 'cforge unrelated', 'post_status' => 'publish' ] );
$track( $other, 'post' );
$decoy = wp_insert_post( [ 'post_title' => 'cforge decoy', 'post_status' => 'publish' ] );
wp_delete_post( $decoy, true );
if ( ! ( 1 === $rows( $other, 'post' ) ) ) { exit( "FAIL: " . 'unrelated row was removed' . "\n" ); }

// 6. Orphan purge clears a stale row and spares a live one.
$track( 99999999, 'post' );
$track( 99999998, 'user' );
$removed = \ContentForge\Cleanup::purge_orphans();
if ( ! ( 0 === $rows( 99999999, 'post' ) ) ) { exit( "FAIL: " . 'orphan post row survived purge' . "\n" ); }
if ( ! ( 0 === $rows( 99999998, 'user' ) ) ) { exit( "FAIL: " . 'orphan user row survived purge' . "\n" ); }
if ( ! ( 1 === $rows( $other, 'post' ) ) ) { exit( "FAIL: " . 'purge removed a live row' . "\n" ); }
wp_delete_post( $other, true );

echo "all checks passed (purge removed {$removed} rows)\n";
