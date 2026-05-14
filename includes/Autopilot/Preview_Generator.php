<?php
// DESCRIPTION: One-off preview generation — same AI pipeline as a real run, zero persistence.
// Used by the "Preview" button in the schedule form to show sample output before saving.

namespace ContentForge\Autopilot;

use WP_Error;
use ContentForge\Autopilot\Topic\Strategies\AI_Theme;
use ContentForge\Generator\AI_Content_Generator;
use ContentForge\Settings\AI_Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a preview from an unsaved schedule config.
 *
 * Bypasses Topic_Resolver (which mutates state) and picks the most-likely-next
 * topic from the config without advancing any cursors or queues.
 *
 * @since 1.5.0
 */
class Preview_Generator {

	/**
	 * Generate a preview for an unsaved/saved schedule config.
	 *
	 * @since 1.5.0
	 *
	 * @param array $config Validated config array.
	 * @return array|WP_Error  ['topic', 'title', 'excerpt', 'content'] on success.
	 */
	public function generate( array $config ) {
		if ( ! AI_Settings_Manager::is_configured() ) {
			return new WP_Error(
				'cforge_autopilot_ai_not_configured',
				__( 'Configure AI before generating a preview.', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}

		$topic = $this->pick_topic_for_preview( $config );
		if ( $topic === '' ) {
			return new WP_Error(
				'cforge_autopilot_no_topic',
				__( 'No topic available for preview. Provide at least one topic or a theme.', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}

		$provider = AI_Settings_Manager::get_active_provider();
		$model    = AI_Settings_Manager::get_active_model();
		$api_key  = AI_Settings_Manager::get_api_key( $provider );
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'cforge_autopilot_no_api_key',
				__( 'AI provider API key missing.', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}

		$post_type   = isset( $config['targeting']['post_type'] ) ? (string) $config['targeting']['post_type'] : 'post';
		$editor_type = function_exists( 'cforge_detect_editor_type' ) ? cforge_detect_editor_type( $post_type ) : 'block';

		$custom_prompt = isset( $config['ai']['custom_prompt'] ) ? trim( (string) $config['ai']['custom_prompt'] ) : '';
		$prompt        = trim( $topic . ( $custom_prompt !== '' ? "\n\n" . $custom_prompt : '' ) );

		$generator = new AI_Content_Generator( $provider, $model, $api_key, $editor_type );
		$result    = $generator->generate( 'general', $prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$title   = isset( $result['title'] ) ? (string) $result['title'] : '';
		$content = isset( $result['content'] ) ? (string) $result['content'] : '';

		return [
			'topic'   => $topic,
			'title'   => $title,
			'excerpt' => $this->build_excerpt( $content ),
			'content' => $content,
		];
	}

	/**
	 * Pick the most-likely-next topic for a preview without mutating state.
	 *
	 * @param array $config Schedule config.
	 * @return string
	 */
	protected function pick_topic_for_preview( array $config ) {
		$topic_cfg = isset( $config['topic'] ) && is_array( $config['topic'] ) ? $config['topic'] : [];
		$mode      = isset( $topic_cfg['mode'] ) ? (string) $topic_cfg['mode'] : 'list_cycle';

		switch ( $mode ) {
			case 'list_random':
				$list = isset( $topic_cfg['list'] ) && is_array( $topic_cfg['list'] ) ? $topic_cfg['list'] : [];
				if ( empty( $list ) ) {
					return '';
				}
				return (string) $list[ wp_rand( 0, count( $list ) - 1 ) ];

			case 'queue':
				$queue = isset( $topic_cfg['queue'] ) && is_array( $topic_cfg['queue'] ) ? $topic_cfg['queue'] : [];
				return ! empty( $queue ) ? (string) $queue[0] : '';

			case 'ai_theme':
				return $this->resolve_ai_theme_topic( $config );

			case 'list_cycle':
			default:
				$list = isset( $topic_cfg['list'] ) && is_array( $topic_cfg['list'] ) ? $topic_cfg['list'] : [];
				if ( empty( $list ) ) {
					return '';
				}
				$cursor = isset( $topic_cfg['list_cursor'] ) ? max( 0, (int) $topic_cfg['list_cursor'] ) : 0;
				if ( $cursor >= count( $list ) ) {
					$cursor = 0;
				}
				return (string) $list[ $cursor ];
		}
	}

	/**
	 * AI_Theme strategy needs a Schedule object; build a transient stand-in.
	 *
	 * @param array $config Config.
	 * @return string
	 */
	protected function resolve_ai_theme_topic( array $config ) {
		$theme = isset( $config['topic']['theme'] ) ? trim( (string) $config['topic']['theme'] ) : '';
		if ( $theme === '' ) {
			return '';
		}

		// Build a minimal in-memory Schedule wrapper. We can't construct a real
		// Schedule without a WP_Post, so we replicate AI_Theme inline here.
		$category_id = isset( $config['targeting']['category_id'] ) ? (int) $config['targeting']['category_id'] : 0;

		$provider = AI_Settings_Manager::get_active_provider();
		$model    = AI_Settings_Manager::get_active_model();
		$api_key  = AI_Settings_Manager::get_api_key( $provider );
		if ( empty( $api_key ) ) {
			return '';
		}

		$recent_titles = [];
		if ( $category_id > 0 ) {
			$ids = get_posts(
				[
					'category__in'           => [ $category_id ],
					'post_status'            => [ 'publish', 'future', 'draft', 'pending' ],
					'posts_per_page'         => AI_Theme::RECENT_CONTEXT_COUNT,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'update_post_meta_cache' => false,
				]
			);
			foreach ( $ids as $id ) {
				$title = get_the_title( $id );
				if ( $title ) {
					$recent_titles[] = (string) $title;
				}
			}
		}

		$avoid = '';
		if ( ! empty( $recent_titles ) ) {
			$avoid = "\n\nAvoid duplicating these recent topics:\n- " . implode( "\n- ", $recent_titles );
		}

		$prompt = sprintf(
			"Propose ONE fresh, SEO-friendly blog post topic on the theme: \"%s\".%s\n\n" .
			"Reply with the topic only — a single short title under 60 characters. No quotes, no numbering, no commentary.",
			$theme,
			$avoid
		);

		$generator = new AI_Content_Generator( $provider, $model, $api_key, 'classic' );
		$result    = $generator->generate( 'general', $prompt );

		if ( is_wp_error( $result ) || empty( $result['title'] ) ) {
			return '';
		}

		$topic = trim( wp_strip_all_tags( (string) $result['title'] ) );
		$topic = preg_replace( '/^[\-\*\d\.\)\s]+/u', '', $topic );
		return trim( $topic, " \t\n\r\0\x0B\"'`" );
	}

	/**
	 * First ~200 chars of content as an excerpt (HTML stripped).
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	protected function build_excerpt( $content ) {
		$plain = wp_strip_all_tags( (string) $content );
		$plain = preg_replace( '/\s+/u', ' ', $plain );
		$plain = trim( (string) $plain );
		if ( mb_strlen( $plain ) <= 240 ) {
			return $plain;
		}
		return rtrim( mb_substr( $plain, 0, 240 ) ) . '…';
	}
}
