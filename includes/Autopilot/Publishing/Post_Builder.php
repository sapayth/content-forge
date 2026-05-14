<?php
// DESCRIPTION: Composes the wp_insert_post args for a single Autopilot-generated post.
// Reuses AI_Content_Generator output (title + content) and applies targeting metadata.

namespace ContentForge\Autopilot\Publishing;

use ContentForge\Autopilot\Schedule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build a fully-formed wp_insert_post payload from AI output + schedule targeting.
 *
 * Does not insert; pure data shaping. Caller is responsible for wp_insert_post
 * and post-insert taxonomy/featured-image attachment.
 *
 * @since 1.5.0
 */
class Post_Builder {

	/**
	 * @var Status_Resolver
	 */
	protected $status_resolver;

	/**
	 * Constructor.
	 *
	 * @param Status_Resolver $status_resolver Resolver.
	 */
	public function __construct( Status_Resolver $status_resolver ) {
		$this->status_resolver = $status_resolver;
	}

	/**
	 * Build wp_insert_post args from AI result + schedule.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule  Schedule.
	 * @param string   $topic     Topic used.
	 * @param array    $ai_result AI_Content_Generator->generate() result with 'title' and 'content'.
	 * @return array              Sanitized wp_insert_post args.
	 */
	public function build( Schedule $schedule, $topic, array $ai_result ) {
		$post_type = (string) $schedule->config_get( 'targeting.post_type', 'post' );
		$author_id = (int) $schedule->config_get( 'targeting.author_id', 0 );
		if ( $author_id <= 0 ) {
			$author_id = $schedule->created_by();
		}

		$category_id = (int) $schedule->config_get( 'targeting.category_id', 0 );
		$tag_ids     = $schedule->config_get( 'targeting.tag_ids', [] );
		$tag_ids     = is_array( $tag_ids ) ? array_map( 'intval', $tag_ids ) : [];

		$args = array_merge(
			[
				'post_type'    => $post_type,
				'post_title'   => isset( $ai_result['title'] ) ? wp_strip_all_tags( (string) $ai_result['title'] ) : (string) $topic,
				'post_content' => isset( $ai_result['content'] ) ? (string) $ai_result['content'] : '',
				'post_author'  => $author_id,
			],
			$this->status_resolver->resolve( $schedule )
		);

		if ( $category_id > 0 && $post_type === 'post' ) {
			$args['post_category'] = [ $category_id ];
		}

		if ( ! empty( $tag_ids ) && $post_type === 'post' ) {
			$args['tags_input'] = $tag_ids;
		}

		/**
		 * Filter the wp_insert_post args before insertion.
		 *
		 * @since 1.5.0
		 *
		 * @param array    $args      The wp_insert_post args.
		 * @param Schedule $schedule  Schedule.
		 * @param array    $ai_result Raw AI result.
		 */
		return (array) apply_filters( 'cforge_autopilot_generated_post_args', $args, $schedule, $ai_result );
	}
}
