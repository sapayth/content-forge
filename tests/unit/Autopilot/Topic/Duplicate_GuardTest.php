<?php
// DESCRIPTION: Unit tests for Duplicate_Guard pure helpers (tokenize_for_similarity + jaccard).
// is_duplicate() touches WP_Query and is covered separately by integration tests later.

namespace ContentForge\Tests\Autopilot\Topic;

use ContentForge\Autopilot\Topic\Duplicate_Guard;
use PHPUnit\Framework\TestCase;

final class Duplicate_GuardTest extends TestCase {

	public function test_jaccard_full_overlap_is_one() {
		$guard = new Duplicate_Guard();
		$this->assertSame( 1.0, $guard->jaccard( [ 'a', 'b' ], [ 'a', 'b' ] ) );
	}

	public function test_jaccard_no_overlap_is_zero() {
		$guard = new Duplicate_Guard();
		$this->assertSame( 0.0, $guard->jaccard( [ 'a', 'b' ], [ 'c', 'd' ] ) );
	}

	public function test_jaccard_partial_overlap() {
		$guard = new Duplicate_Guard();
		// 1 intersection ("a"), 3 union ("a","b","c") -> 1/3.
		$this->assertEqualsWithDelta( 1 / 3, $guard->jaccard( [ 'a', 'b' ], [ 'a', 'c' ] ), 0.0001 );
	}

	public function test_jaccard_empty_sets_are_treated_as_identical() {
		$guard = new Duplicate_Guard();
		$this->assertSame( 1.0, $guard->jaccard( [], [] ) );
	}

	public function test_tokenize_drops_short_tokens_and_punctuation() {
		$guard  = new Duplicate_Guard();
		$tokens = $guard->tokenize_for_similarity( "5 SEO Tips at 2026!" );

		// "5", "at" filtered out (sub-3-char). "seo", "tips", "2026" remain (lowercased).
		sort( $tokens );
		$this->assertSame( [ '2026', 'seo', 'tips' ], $tokens );
	}

	public function test_tokenize_deduplicates() {
		$guard  = new Duplicate_Guard();
		$tokens = $guard->tokenize_for_similarity( 'WordPress WordPress wordpress' );

		$this->assertSame( [ 'wordpress' ], $tokens );
	}

	public function test_tokenize_handles_empty_string() {
		$guard = new Duplicate_Guard();
		$this->assertSame( [], $guard->tokenize_for_similarity( '' ) );
	}

	public function test_tokenize_handles_punctuation_only() {
		$guard = new Duplicate_Guard();
		$this->assertSame( [], $guard->tokenize_for_similarity( '!!!---???' ) );
	}

	public function test_near_duplicate_titles_score_above_default_threshold() {
		$guard = new Duplicate_Guard();
		$a     = $guard->tokenize_for_similarity( '10 SEO Tips for WordPress' );
		$b     = $guard->tokenize_for_similarity( '10 SEO Tips on WordPress' );

		$this->assertGreaterThanOrEqual( Duplicate_Guard::DEFAULT_THRESHOLD, $guard->jaccard( $a, $b ) );
	}

	public function test_distinct_titles_score_below_default_threshold() {
		$guard = new Duplicate_Guard();
		$a     = $guard->tokenize_for_similarity( 'How to Bake Bread' );
		$b     = $guard->tokenize_for_similarity( '10 SEO Tips for WordPress' );

		$this->assertLessThan( Duplicate_Guard::DEFAULT_THRESHOLD, $guard->jaccard( $a, $b ) );
	}
}
