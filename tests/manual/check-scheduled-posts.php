<?php
// DESCRIPTION: Check for the "future" post status path — verifies wall-clock->GMT
// conversion, a real scheduled insert, and the rejection cases. Cleans up after itself.
//
// The PHPUnit suite bootstraps without WordPress, so this runs against a live install:
//   wp eval-file wp-content/plugins/content-forge/tests/manual/check-scheduled-posts.php

// 1. Timezone conversion (simulate a UTC+6 site without touching site options).
add_filter( 'option_gmt_offset', fn() => 6 );
$input = '2026-08-20T14:30';
$local = gmdate( 'Y-m-d H:i:s', strtotime( $input ) );
$gmt   = get_gmt_from_date( $local );
echo "TZ  : in={$input} local={$local} gmt={$gmt}\n";
echo ( '2026-08-20 14:30:00' === $local && '2026-08-20 08:30:00' === $gmt ) ? "TZ  : PASS\n" : "TZ  : FAIL\n";
remove_all_filters( 'option_gmt_offset' );

wp_set_current_user( 1 );

$call = function ( array $body ) {
	$req = new WP_REST_Request( 'POST', '/cforge/v1/posts/bulk' );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $body ) );
	return rest_get_server()->dispatch( $req );
};

$future = gmdate( 'Y-m-d\TH:i', time() + 3 * DAY_IN_SECONDS );
$past   = gmdate( 'Y-m-d\TH:i', time() - DAY_IN_SECONDS );

// 2. Happy path: one scheduled post.
$res  = $call( [ 'post_type' => 'post', 'post_status' => 'future', 'post_date' => $future, 'post_number' => 1 ] );
$data = $res->get_data();
echo "OK  : status={$res->get_status()} " . wp_json_encode( $data ) . "\n";

$post_id = $data['created'][0] ?? 0;
if ( $post_id ) {
	$post = get_post( $post_id );
	$cron = wp_next_scheduled( 'publish_future_post', [ $post_id ] );
	echo "POST: status={$post->post_status} date={$post->post_date} gmt={$post->post_date_gmt} cron=" . ( $cron ? gmdate( 'Y-m-d H:i', $cron ) : 'none' ) . "\n";
	echo ( 'future' === $post->post_status && $cron ) ? "POST: PASS\n" : "POST: FAIL\n";
	wp_delete_post( $post_id, true );
	echo "POST: cleaned up\n";
}

// 3. Rejections.
foreach ( [
	'past date'   => [ 'post_status' => 'future', 'post_date' => $past, 'post_number' => 1 ],
	'no date'     => [ 'post_status' => 'future', 'post_number' => 1 ],
	'bad status'  => [ 'post_status' => 'nonsense', 'post_number' => 1 ],
	'ai+future'   => [ 'post_status' => 'future', 'post_date' => $future, 'use_ai' => true, 'content_type' => 'general', 'post_number' => 1 ],
] as $label => $body ) {
	$r = $call( array_merge( [ 'post_type' => 'post' ], $body ) );
	$m = $r->get_data()['message'] ?? '';
	echo sprintf( "REJ : %-10s status=%d %s %s\n", $label, $r->get_status(), 400 === $r->get_status() ? 'PASS' : 'FAIL', $m );
}

// 4. Private still works (the bug that blocked it was client-side).
$r = $call( [ 'post_type' => 'post', 'post_status' => 'private', 'post_number' => 1 ] );
$id = $r->get_data()['created'][0] ?? 0;
echo 'PRIV: status=' . $r->get_status() . ' post_status=' . ( $id ? get_post( $id )->post_status : 'n/a' ) . "\n";
if ( $id ) {
	wp_delete_post( $id, true );
}
