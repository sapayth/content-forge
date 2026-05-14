<?php
// DESCRIPTION: AI-suggests-topic strategy — uses AI_Content_Generator to propose a fresh topic from a theme.
// Doubles AI calls per run (one for topic, one for the post); the UI surfaces this trade-off.

namespace ContentForge\Autopilot\Topic\Strategies;

use ContentForge\Autopilot\Schedule;
use ContentForge\Generator\AI_Content_Generator;
use ContentForge\Settings\AI_Settings_Manager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ask the configured AI provider for a topic based on theme + recent posts in the target category.
 *
 * Output of the AI is the topic string. We strip quotes/punctuation to keep it clean
 * for use as a generation prompt and dedup comparison.
 *
 * @since 1.5.0
 */
class AI_Theme {

	/**
	 * How many recent post titles to include as "do not repeat" context.
	 *
	 * @var int
	 */
	const RECENT_CONTEXT_COUNT = 10;

	/**
	 * Resolve a topic.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @return string             Topic or '' on failure.
	 */
	public function resolve( Schedule $schedule ) {
		$theme = trim( (string) $schedule->config_get( 'topic.theme', '' ) );
		if ( $theme === '' ) {
			return '';
		}

		if ( ! AI_Settings_Manager::is_configured() ) {
			return '';
		}

		$provider = AI_Settings_Manager::get_active_provider();
		$model    = AI_Settings_Manager::get_active_model();
		$api_key  = AI_Settings_Manager::get_api_key( $provider );
		if ( empty( $api_key ) ) {
			return '';
		}

		$recent_titles = $this->fetch_recent_titles( (int) $schedule->config_get( 'targeting.category_id', 0 ) );
		$prompt        = $this->build_prompt( $theme, $recent_titles );

		$generator = new AI_Content_Generator( $provider, $model, $api_key, 'classic' );
		$result    = $generator->generate( 'general', $prompt );

		if ( is_wp_error( $result ) || empty( $result['title'] ) ) {
			return '';
		}

		// AI returns full title-and-body; we only want the title as the next topic.
		return $this->normalize_topic( (string) $result['title'] );
	}

	/**
	 * Build the topic-ideation prompt.
	 *
	 * @param string $theme         Theme.
	 * @param array  $recent_titles Titles to avoid.
	 * @return string
	 */
	protected function build_prompt( $theme, array $recent_titles ) {
		$avoid = '';
		if ( ! empty( $recent_titles ) ) {
			$avoid = "\n\nAvoid duplicating these recent topics:\n- " . implode( "\n- ", $recent_titles );
		}

		return sprintf(
			"Propose ONE fresh, SEO-friendly blog post topic on the theme: \"%s\".%s\n\n" .
			"Reply with the topic only — a single short title under 60 characters. No quotes, no numbering, no commentary.",
			$theme,
			$avoid
		);
	}

	/**
	 * Recent post titles in the target category.
	 *
	 * @param int $category_id Category term ID.
	 * @return string[]
	 */
	protected function fetch_recent_titles( $category_id ) {
		if ( $category_id <= 0 ) {
			return [];
		}

		$query_args = [
			'category__in'           => [ $category_id ],
			'post_status'            => [ 'publish', 'future', 'draft', 'pending' ],
			'posts_per_page'         => self::RECENT_CONTEXT_COUNT,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		];

		$ids = get_posts( $query_args );
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

	/**
	 * Strip wrapping quotes / leading bullets from an AI-proposed topic.
	 *
	 * @param string $raw Raw AI output.
	 * @return string
	 */
	protected function normalize_topic( $raw ) {
		$topic = trim( wp_strip_all_tags( $raw ) );
		$topic = preg_replace( '/^[\-\*\d\.\)\s]+/u', '', $topic ); // strip leading bullets / numbering
		$topic = trim( $topic, " \t\n\r\0\x0B\"'`" );
		return $topic;
	}
}
