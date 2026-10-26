<?php
// DESCRIPTION: Unit tests for the WP-CLI flag parsers in Generate_Command.
// Covers --authors and --random-terms: valid input, bad input and edge cases.

namespace ContentForge\Tests\CLI;

use ContentForge\CLI\Generate_Command;
use PHPUnit\Framework\TestCase;

final class Generate_Command_ParsersTest extends TestCase {

	public function test_authors_me_and_random_pass_through() {
		$this->assertSame(
			[
				'mode' => 'me',
				'ids'  => [],
			],
			Generate_Command::parse_authors( 'me' )
		);
		$this->assertSame(
			[
				'mode' => 'random',
				'ids'  => [],
			],
			Generate_Command::parse_authors( ' random ' )
		);
	}

	public function test_authors_ids_become_specific_and_dedupe() {
		$this->assertSame(
			[
				'mode' => 'specific',
				'ids'  => [ 3, 7, 9 ],
			],
			Generate_Command::parse_authors( '3, 7,9,3' )
		);
	}

	/**
	 * Values that are not me, random or a list of user IDs.
	 *
	 * @dataProvider bad_authors
	 *
	 * @param string $value --authors value.
	 */
	public function test_authors_rejects_bad_input( $value ) {
		$this->expectException( \InvalidArgumentException::class );
		Generate_Command::parse_authors( $value );
	}

	public function bad_authors() {
		return [ [ '' ], [ 'admin' ], [ '3,' ], [ '3,x' ], [ '-1' ] ];
	}

	public function test_random_terms_ranges_single_counts_and_defaults() {
		$this->assertSame(
			[
				'category' => [
					'mode' => 'random',
					'min'  => 1,
					'max'  => 2,
				],
				'post_tag' => [
					'mode' => 'random',
					'min'  => 3,
					'max'  => 3,
				],
				'genre'    => [
					'mode' => 'random',
					'min'  => 1,
					'max'  => 1,
				],
			],
			Generate_Command::parse_random_terms( 'category:1-2, post_tag:3,genre' )
		);
	}

	public function test_random_terms_empty_is_no_options() {
		$this->assertSame( [], Generate_Command::parse_random_terms( '' ) );
	}

	/**
	 * Malformed entries and impossible ranges.
	 *
	 * @dataProvider bad_random_terms
	 *
	 * @param string $value --random-terms value.
	 */
	public function test_random_terms_rejects_bad_input( $value ) {
		$this->expectException( \InvalidArgumentException::class );
		Generate_Command::parse_random_terms( $value );
	}

	public function bad_random_terms() {
		return [ [ 'category:' ], [ 'category:a-b' ], [ 'category:3-1' ], [ 'category:0' ], [ 'Category:1' ], [ 'cat:1-2-3' ] ];
	}
}
