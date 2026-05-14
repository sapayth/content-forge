<?php
// DESCRIPTION: Detects near-duplicate post titles against the last N posts in a category.
// Uses normalized token Jaccard similarity; threshold defaults to 0.7 and is filterable.

namespace ContentForge\Autopilot\Topic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Heuristic duplicate detector for autopilot output.
 *
 * Pure logic class: tokenize_for_similarity() and jaccard() are unit-testable
 * without WordPress; is_duplicate() does the WP query and applies the filter.
 *
 * @since 1.5.0
 */
class Duplicate_Guard {

	/**
	 * Number of recent posts in the target category to compare against.
	 *
	 * @var int
	 */
	const RECENT_COUNT = 30;

	/**
	 * Default Jaccard similarity threshold (0..1). Filterable.
	 *
	 * @var float
	 */
	const DEFAULT_THRESHOLD = 0.7;

	/**
	 * Determine whether $title is too similar to any recent post in $category_id.
	 *
	 * @since 1.5.0
	 *
	 * @param string $title       Candidate title.
	 * @param int    $category_id Category term ID. 0 means "no category" — always returns false.
	 * @return bool
	 */
	public function is_duplicate( $title, $category_id ) {
		$title       = trim( (string) $title );
		$category_id = (int) $category_id;
		if ( $title === '' || $category_id <= 0 ) {
			return false;
		}

		/**
		 * Filter the Jaccard threshold for duplicate detection.
		 *
		 * @since 1.5.0
		 *
		 * @param float $threshold   Default DEFAULT_THRESHOLD.
		 * @param int   $category_id Target category.
		 */
		$threshold = (float) apply_filters(
			'cforge_autopilot_duplicate_threshold',
			self::DEFAULT_THRESHOLD,
			$category_id
		);

		$tokens_a = $this->tokenize_for_similarity( $title );
		if ( empty( $tokens_a ) ) {
			return false;
		}

		foreach ( $this->fetch_recent_titles( $category_id ) as $recent ) {
			$tokens_b = $this->tokenize_for_similarity( $recent );
			if ( empty( $tokens_b ) ) {
				continue;
			}
			if ( $this->jaccard( $tokens_a, $tokens_b ) >= $threshold ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Tokenize a title into a normalized set for similarity comparison.
	 *
	 * Lowercases, strips punctuation, drops words shorter than 3 chars.
	 *
	 * @since 1.5.0
	 *
	 * @param string $title Title.
	 * @return string[]      De-duplicated tokens.
	 */
	public function tokenize_for_similarity( $title ) {
		$title = strtolower( (string) $title );
		// Replace any non-letter/digit/space with a space, then collapse runs.
		$title = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $title );
		$title = trim( preg_replace( '/\s+/u', ' ', (string) $title ) );
		if ( $title === '' ) {
			return [];
		}

		$tokens = array_filter(
			explode( ' ', $title ),
			static function ( $t ) {
				return strlen( $t ) >= 3;
			}
		);
		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Jaccard similarity between two token sets.
	 *
	 * @since 1.5.0
	 *
	 * @param string[] $a First set.
	 * @param string[] $b Second set.
	 * @return float       Similarity in [0, 1].
	 */
	public function jaccard( array $a, array $b ) {
		if ( empty( $a ) && empty( $b ) ) {
			return 1.0;
		}
		$intersection = count( array_intersect( $a, $b ) );
		$union        = count( array_unique( array_merge( $a, $b ) ) );
		if ( $union === 0 ) {
			return 0.0;
		}
		return (float) ( $intersection / $union );
	}

	/**
	 * Recent post titles in a category.
	 *
	 * @param int $category_id Category term ID.
	 * @return string[]
	 */
	protected function fetch_recent_titles( $category_id ) {
		$ids = get_posts(
			[
				'category__in'           => [ $category_id ],
				'post_status'            => [ 'publish', 'future', 'draft', 'pending' ],
				'posts_per_page'         => self::RECENT_COUNT,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			]
		);

		if ( empty( $ids ) ) {
			return [];
		}

		$titles = [];
		foreach ( $ids as $id ) {
			$title = get_the_title( $id );
			if ( $title ) {
				$titles[] = (string) $title;
			}
		}
		return $titles;
	}
}
