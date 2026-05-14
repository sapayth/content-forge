<?php
// DESCRIPTION: Value object representing a single Autopilot schedule.
// Wraps a WP_Post + meta, exposing typed accessors and a default config shape.

namespace ContentForge\Autopilot;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable view of an Autopilot schedule.
 *
 * Construct via Schedule_Repository methods; do not new this up directly from
 * untrusted data.
 *
 * @since 1.5.0
 */
class Schedule {

	const POST_TYPE = 'cforge_autopilot';

	const META_CONFIG          = '_cforge_config';
	const META_NEXT_RUN_AT     = '_cforge_next_run_at';
	const META_LAST_RUN_AT     = '_cforge_last_run_at';
	const META_RUNS_COMPLETED  = '_cforge_runs_completed';
	const META_POSTS_CREATED   = '_cforge_posts_created';
	const META_FAILURE_STREAK  = '_cforge_failure_streak';
	const META_RUNS_CAP        = '_cforge_runs_cap';
	const META_STOP_AT         = '_cforge_stop_at';
	const META_AUTO_PAUSED     = '_cforge_auto_paused';
	const META_AUTO_PAUSE_NOTE = '_cforge_auto_pause_note';
	const META_COMPLETED_FLAG  = '_cforge_completed';

	/**
	 * Status mapping uses built-in post_status:
	 *  draft   = newly created, not yet activated
	 *  publish = active
	 *  private = paused / auto-paused / completed (distinguished by meta flags)
	 *  trash   = archived (soft delete)
	 */

	/**
	 * Underlying post object.
	 *
	 * @var WP_Post
	 */
	protected $post;

	/**
	 * Decoded config array.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Constructor.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_Post $post   The CPT row.
	 * @param array   $config Decoded config from post meta.
	 */
	public function __construct( WP_Post $post, array $config ) {
		$this->post   = $post;
		$this->config = $config;
	}

	/**
	 * Default config shape used when none is stored.
	 *
	 * Kept in one place so schema_version migrations have a baseline.
	 *
	 * @since 1.5.0
	 *
	 * @return array
	 */
	public static function default_config() {
		return [
			'schema_version' => 1,
			'topic'          => [
				'mode'         => 'list_cycle',
				'list'         => [],
				'queue'        => [],
				'queue_cursor' => 0,
				'list_cursor'  => 0,
				'theme'        => '',
			],
			'avoid_recent_duplicates' => true,
			'targeting'               => [
				'post_type'         => 'post',
				'category_id'       => 0,
				'tag_ids'           => [],
				'ai_suggests_tags'  => false,
				'author_id'         => 0,
				'featured_image'    => [
					'source' => 'none',
					'url'    => '',
				],
			],
			'ai' => [
				'provider_override' => null,
				'model_override'    => null,
				'custom_prompt'     => '',
				'tone'              => 'professional',
				'length'            => 'medium',
			],
			'frequency' => [
				'mode'           => 'daily',
				'time'           => '09:00',
				'weekdays'       => [ 1 ],
				'day_of_month'   => 1,
				'interval_hours' => 24,
				'start_at'       => '',
				'stagger_hours'  => 0,
			],
			'publishing' => [
				'mode'                   => 'draft',
				'publish_delay_hours'    => 1,
				'ack_publish_immediately' => false,
			],
			'safety' => [
				'daily_post_cap'             => 5,
				'auto_pause_after_failures'  => 3,
			],
			'notifications' => [
				'email_mode' => 'none',
				'email_to'   => '',
			],
			'posts_per_run' => 1,
		];
	}

	/**
	 * Get schedule ID.
	 *
	 * @return int
	 */
	public function id() {
		return (int) $this->post->ID;
	}

	/**
	 * Get schedule name.
	 *
	 * @return string
	 */
	public function name() {
		return (string) $this->post->post_title;
	}

	/**
	 * Get raw post_status.
	 *
	 * @return string
	 */
	public function post_status() {
		return (string) $this->post->post_status;
	}

	/**
	 * Resolved domain status: active|paused|auto_paused|completed|draft|archived.
	 *
	 * @return string
	 */
	public function status() {
		switch ( $this->post->post_status ) {
			case 'publish':
				return 'active';
			case 'private':
				if ( $this->meta_flag( self::META_COMPLETED_FLAG ) ) {
					return 'completed';
				}
				if ( $this->meta_flag( self::META_AUTO_PAUSED ) ) {
					return 'auto_paused';
				}
				return 'paused';
			case 'trash':
				return 'archived';
			case 'draft':
			default:
				return 'draft';
		}
	}

	/**
	 * Get config array.
	 *
	 * @return array
	 */
	public function config() {
		return $this->config;
	}

	/**
	 * Get a deep config value via dot notation. Returns $default if missing.
	 *
	 * @param string $path    Dot-notated path, e.g. "topic.mode".
	 * @param mixed  $default Default if not found.
	 * @return mixed
	 */
	public function config_get( $path, $default = null ) {
		$node = $this->config;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
				return $default;
			}
			$node = $node[ $segment ];
		}
		return $node;
	}

	/**
	 * Get author user ID who created the schedule.
	 *
	 * @return int
	 */
	public function created_by() {
		return (int) $this->post->post_author;
	}

	/**
	 * Get next_run_at as UTC datetime string, or empty if not scheduled.
	 *
	 * @return string
	 */
	public function next_run_at() {
		return (string) get_post_meta( $this->id(), self::META_NEXT_RUN_AT, true );
	}

	/**
	 * Get last_run_at as UTC datetime string, or empty.
	 *
	 * @return string
	 */
	public function last_run_at() {
		return (string) get_post_meta( $this->id(), self::META_LAST_RUN_AT, true );
	}

	/**
	 * Aggregate counter helpers.
	 */
	public function runs_completed() {
		return (int) get_post_meta( $this->id(), self::META_RUNS_COMPLETED, true );
	}

	public function posts_created() {
		return (int) get_post_meta( $this->id(), self::META_POSTS_CREATED, true );
	}

	public function failure_streak() {
		return (int) get_post_meta( $this->id(), self::META_FAILURE_STREAK, true );
	}

	public function runs_cap() {
		$value = get_post_meta( $this->id(), self::META_RUNS_CAP, true );
		return $value === '' ? null : (int) $value;
	}

	public function stop_at() {
		$value = get_post_meta( $this->id(), self::META_STOP_AT, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Underlying WP_Post — used by repository when persisting.
	 *
	 * @return WP_Post
	 */
	public function post() {
		return $this->post;
	}

	/**
	 * Read a boolean flag from meta.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	protected function meta_flag( $key ) {
		return (bool) get_post_meta( $this->id(), $key, true );
	}
}
