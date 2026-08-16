<?php
// DESCRIPTION: Unit tests for the dashboard count bucketing.
// Covers the tile each tracked data_type lands in, including unknown post types.

namespace ContentForge\Tests\Api;

use ContentForge\Api\Dashboard;
use PHPUnit\Framework\TestCase;

final class Dashboard_CountsTest extends TestCase {

	/**
	 * Stand-in for get_taxonomies( [], 'names' ), which is keyed by name.
	 *
	 * @var array<string, string>
	 */
	private const TAXONOMIES = [
		'category' => 'category',
		'post_tag' => 'post_tag',
	];

	public function test_buckets_each_data_type_into_its_tile() {
		$rows = [
			[ 'data_type' => 'post', 'total' => 10 ],
			[ 'data_type' => 'page', 'total' => 5 ],
			[ 'data_type' => 'user', 'total' => 3 ],
			[ 'data_type' => 'comment', 'total' => 7 ],
			[ 'data_type' => 'category', 'total' => 2 ],
			[ 'data_type' => 'post_tag', 'total' => 4 ],
			[ 'data_type' => 'product', 'total' => 6 ],
		];

		$this->assertSame(
			[
				'posts'    => 15,
				'cpt'      => 6,
				'users'    => 3,
				'comments' => 7,
				'terms'    => 6,
			],
			Dashboard::bucket_rows( $rows, self::TAXONOMIES )
		);
	}

	public function test_unknown_post_type_falls_into_the_cpt_tile() {
		// A CPT whose plugin is deactivated is no longer a registered taxonomy
		// either, so it must not silently vanish from the totals.
		$rows = [ [ 'data_type' => 'tribe_events', 'total' => 9 ] ];

		$counts = Dashboard::bucket_rows( $rows, self::TAXONOMIES );

		$this->assertSame( 9, $counts['cpt'] );
		$this->assertSame( 9, array_sum( $counts ) );
	}

	public function test_no_rows_yields_all_zeroes() {
		$counts = Dashboard::bucket_rows( [], self::TAXONOMIES );

		$this->assertSame( 0, array_sum( $counts ) );
		$this->assertSame( [ 'posts', 'cpt', 'users', 'comments', 'terms' ], array_keys( $counts ) );
	}

	public function test_string_totals_from_wpdb_are_cast_to_int() {
		// $wpdb->get_results( ..., ARRAY_A ) returns COUNT(*) as a string.
		$counts = Dashboard::bucket_rows( [ [ 'data_type' => 'post', 'total' => '12' ] ], self::TAXONOMIES );

		$this->assertSame( 12, $counts['posts'] );
	}
}
