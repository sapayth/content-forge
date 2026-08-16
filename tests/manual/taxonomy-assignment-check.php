<?php
// Self-check for taxonomy assignment on generated posts. Run via `wp eval-file`.
// Creates its own terms, generates posts, asserts assignment, then cleans up.

use ContentForge\Generator\Post as GeneratorPost;

$fail = function ( $msg ) {
	exit( "FAIL: {$msg}\n" );
};

$user_id   = 1;
$generator = new GeneratorPost( $user_id );
$cleanup   = [ 'posts' => [], 'terms' => [] ];

// Build a known term pool.
$cat_ids = [];
for ( $i = 0; $i < 5; $i++ ) {
	$t = wp_insert_term( 'CF Check Cat ' . $i . ' ' . wp_rand(), 'category' );
	if ( is_wp_error( $t ) ) {
		$fail( 'could not create category: ' . $t->get_error_message() );
	}
	$cat_ids[]          = (int) $t['term_id'];
	$cleanup['terms'][] = [ (int) $t['term_id'], 'category' ];
}
$tag_ids = [];
for ( $i = 0; $i < 8; $i++ ) {
	$t = wp_insert_term( 'CF Check Tag ' . $i . ' ' . wp_rand(), 'post_tag' );
	$tag_ids[]          = (int) $t['term_id'];
	$cleanup['terms'][] = [ (int) $t['term_id'], 'post_tag' ];
}

// 1. Specific categories (1 per post) + random tags (2-4 per post).
$ids = $generator->generate(
	20,
	[
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'taxonomy_options' => [
			'category' => [ 'mode' => 'specific', 'terms' => $cat_ids, 'min' => 1, 'max' => 1 ],
			'post_tag' => [ 'mode' => 'random', 'min' => 2, 'max' => 4 ],
		],
	]
);
$cleanup['posts'] = array_merge( $cleanup['posts'], $ids );

if ( count( $ids ) !== 20 ) {
	$fail( 'expected 20 posts, got ' . count( $ids ) );
}

$cat_hits = [];
foreach ( $ids as $post_id ) {
	$cats = wp_get_object_terms( $post_id, 'category', [ 'fields' => 'ids' ] );
	$tags = wp_get_object_terms( $post_id, 'post_tag', [ 'fields' => 'ids' ] );

	if ( 1 !== count( $cats ) ) {
		$fail( "post {$post_id} has " . count( $cats ) . ' categories, expected exactly 1' );
	}
	if ( ! in_array( (int) $cats[0], $cat_ids, true ) ) {
		$fail( "post {$post_id} got a category outside the requested set" );
	}
	if ( count( $tags ) < 2 || count( $tags ) > 4 ) {
		$fail( "post {$post_id} has " . count( $tags ) . ' tags, expected 2-4' );
	}

	$cat_hits[ (int) $cats[0] ] = ( $cat_hits[ (int) $cats[0] ] ?? 0 ) + 1;
}

// 2. Distribution must be uneven — even spread would hide pagination bugs.
if ( count( array_unique( array_values( $cat_hits ) ) ) < 2 ) {
	$fail( 'category distribution is perfectly even across 20 posts, expected variation' );
}

// 3. A taxonomy not registered for the post type is ignored.
$ids = $generator->generate(
	1,
	[
		'post_type'        => 'page',
		'post_status'      => 'draft',
		'taxonomy_options' => [
			'category' => [ 'mode' => 'specific', 'terms' => $cat_ids, 'min' => 1, 'max' => 1 ],
		],
	]
);
$cleanup['posts'] = array_merge( $cleanup['posts'], $ids );
if ( ! empty( wp_get_object_terms( $ids[0], 'category', [ 'fields' => 'ids' ] ) ) ) {
	$fail( 'category was assigned to a page, which is not in that taxonomy' );
}

// 4. Regression guard: no taxonomy_options means no terms assigned.
$ids = $generator->generate( 3, [ 'post_type' => 'post', 'post_status' => 'draft' ] );
$cleanup['posts'] = array_merge( $cleanup['posts'], $ids );
foreach ( $ids as $post_id ) {
	$cats = wp_get_object_terms( $post_id, 'category', [ 'fields' => 'ids' ] );
	$default = (int) get_option( 'default_category' );
	$extra   = array_diff( array_map( 'intval', $cats ), [ $default ] );
	if ( ! empty( $extra ) ) {
		$fail( "post {$post_id} got terms without taxonomy_options" );
	}
}

// 5. min > max is tolerated (swapped, not fatal).
$ids = $generator->generate(
	2,
	[
		'post_type'        => 'post',
		'post_status'      => 'draft',
		'taxonomy_options' => [
			'post_tag' => [ 'mode' => 'random', 'min' => 4, 'max' => 2 ],
		],
	]
);
$cleanup['posts'] = array_merge( $cleanup['posts'], $ids );
foreach ( $ids as $post_id ) {
	$n = count( wp_get_object_terms( $post_id, 'post_tag', [ 'fields' => 'ids' ] ) );
	if ( $n < 2 || $n > 4 ) {
		$fail( "post {$post_id} got {$n} tags with swapped min/max, expected 2-4" );
	}
}

// 6. max above pool size caps at the pool.
$ids = $generator->generate(
	2,
	[
		'post_type'        => 'post',
		'post_status'      => 'draft',
		'taxonomy_options' => [
			'category' => [ 'mode' => 'specific', 'terms' => [ $cat_ids[0] ], 'min' => 5, 'max' => 5 ],
		],
	]
);
$cleanup['posts'] = array_merge( $cleanup['posts'], $ids );
foreach ( $ids as $post_id ) {
	$n = count( wp_get_object_terms( $post_id, 'category', [ 'fields' => 'ids' ] ) );
	if ( 1 !== $n ) {
		$fail( "post {$post_id} got {$n} categories from a 1-term pool, expected 1" );
	}
}

// Cleanup.
foreach ( $cleanup['posts'] as $post_id ) {
	wp_delete_post( $post_id, true );
}
foreach ( $cleanup['terms'] as $term ) {
	wp_delete_term( $term[0], $term[1] );
}

echo "all taxonomy assignment checks passed\n";
