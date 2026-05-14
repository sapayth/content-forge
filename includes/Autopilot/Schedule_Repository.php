<?php
// DESCRIPTION: Persistence layer for Autopilot schedules (CPT + post meta).
// Single place that touches wp_insert_post/get_post_meta/WP_Query for schedules.

namespace ContentForge\Autopilot;

use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD + lifecycle operations for the cforge_autopilot CPT.
 *
 * @since 1.5.0
 */
class Schedule_Repository {

	/**
	 * Find a schedule by ID.
	 *
	 * @since 1.5.0
	 *
	 * @param int $id Post ID.
	 * @return Schedule|null
	 */
	public function find( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || $post->post_type !== Schedule::POST_TYPE ) {
			return null;
		}
		return $this->hydrate( $post );
	}

	/**
	 * Find schedules whose next_run_at is at or before $now and are active.
	 *
	 * Ordered ascending by next_run_at so older slots fire first.
	 *
	 * @since 1.5.0
	 *
	 * @param string $now_utc UTC datetime string (Y-m-d H:i:s).
	 * @param int    $limit   Maximum schedules to fetch.
	 * @return Schedule[]
	 */
	public function find_due( $now_utc, $limit = 25 ) {
		$query = new WP_Query(
			[
				'post_type'      => Schedule::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_key'       => Schedule::META_NEXT_RUN_AT,
				'meta_query'     => [
					[
						'key'     => Schedule::META_NEXT_RUN_AT,
						'value'   => $now_utc,
						'compare' => '<=',
						'type'    => 'DATETIME',
					],
				],
				'no_found_rows'  => true,
				'fields'         => 'all',
			]
		);

		$schedules = [];
		foreach ( $query->posts as $post ) {
			$schedules[] = $this->hydrate( $post );
		}
		return $schedules;
	}

	/**
	 * Create a new schedule (always starts as draft).
	 *
	 * @since 1.5.0
	 *
	 * @param string $name   Schedule name.
	 * @param array  $config Validated config array.
	 * @param int    $author User ID to attribute as creator.
	 * @return Schedule|\WP_Error
	 */
	public function create( $name, array $config, $author = 0 ) {
		$author = $author > 0 ? (int) $author : get_current_user_id();

		$post_id = wp_insert_post(
			[
				'post_type'   => Schedule::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => sanitize_text_field( $name ),
				'post_author' => $author,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, Schedule::META_CONFIG, wp_json_encode( $config ) );
		update_post_meta( $post_id, Schedule::META_RUNS_COMPLETED, 0 );
		update_post_meta( $post_id, Schedule::META_POSTS_CREATED, 0 );
		update_post_meta( $post_id, Schedule::META_FAILURE_STREAK, 0 );

		return $this->find( $post_id );
	}

	/**
	 * Update an existing schedule's name and/or config.
	 *
	 * @since 1.5.0
	 *
	 * @param int        $id     Schedule ID.
	 * @param array      $config New config (full replacement).
	 * @param string|null $name  New name or null to leave.
	 * @return Schedule|\WP_Error
	 */
	public function update( $id, array $config, $name = null ) {
		$schedule = $this->find( $id );
		if ( ! $schedule ) {
			return new \WP_Error( 'cforge_autopilot_not_found', __( 'Schedule not found', 'content-forge' ), [ 'status' => 404 ] );
		}

		if ( $name !== null ) {
			wp_update_post(
				[
					'ID'         => $id,
					'post_title' => sanitize_text_field( $name ),
				]
			);
		}

		update_post_meta( $id, Schedule::META_CONFIG, wp_json_encode( $config ) );

		return $this->find( $id );
	}

	/**
	 * Update the stored next_run_at (UTC) for a schedule.
	 *
	 * @since 1.5.0
	 *
	 * @param int    $id        Schedule ID.
	 * @param string $next_utc  UTC datetime string, or empty to clear.
	 * @return void
	 */
	public function update_next_run( $id, $next_utc ) {
		if ( $next_utc === '' ) {
			delete_post_meta( $id, Schedule::META_NEXT_RUN_AT );
			return;
		}
		update_post_meta( $id, Schedule::META_NEXT_RUN_AT, $next_utc );
	}

	/**
	 * Activate a schedule. Caller is responsible for setting next_run_at first.
	 *
	 * @since 1.5.0
	 *
	 * @param int $id Schedule ID.
	 * @return void
	 */
	public function activate( $id ) {
		wp_update_post(
			[
				'ID'          => (int) $id,
				'post_status' => 'publish',
			]
		);
		delete_post_meta( $id, Schedule::META_AUTO_PAUSED );
		delete_post_meta( $id, Schedule::META_AUTO_PAUSE_NOTE );
		delete_post_meta( $id, Schedule::META_COMPLETED_FLAG );
	}

	/**
	 * Pause a schedule (user-initiated).
	 *
	 * @since 1.5.0
	 *
	 * @param int $id Schedule ID.
	 * @return void
	 */
	public function pause( $id ) {
		wp_update_post(
			[
				'ID'          => (int) $id,
				'post_status' => 'private',
			]
		);
		delete_post_meta( $id, Schedule::META_AUTO_PAUSED );
	}

	/**
	 * Auto-pause a schedule (system-initiated, with reason).
	 *
	 * @since 1.5.0
	 *
	 * @param int    $id     Schedule ID.
	 * @param string $reason Short reason key (e.g. consecutive_failures).
	 * @return void
	 */
	public function auto_pause( $id, $reason ) {
		wp_update_post(
			[
				'ID'          => (int) $id,
				'post_status' => 'private',
			]
		);
		update_post_meta( $id, Schedule::META_AUTO_PAUSED, 1 );
		update_post_meta( $id, Schedule::META_AUTO_PAUSE_NOTE, sanitize_text_field( $reason ) );
	}

	/**
	 * Mark a schedule completed (stop condition reached).
	 *
	 * @since 1.5.0
	 *
	 * @param int $id Schedule ID.
	 * @return void
	 */
	public function mark_completed( $id ) {
		wp_update_post(
			[
				'ID'          => (int) $id,
				'post_status' => 'private',
			]
		);
		update_post_meta( $id, Schedule::META_COMPLETED_FLAG, 1 );
		delete_post_meta( $id, Schedule::META_NEXT_RUN_AT );
	}

	/**
	 * Soft-delete (trash) a schedule.
	 *
	 * @since 1.5.0
	 *
	 * @param int $id Schedule ID.
	 * @return void
	 */
	public function archive( $id ) {
		wp_trash_post( (int) $id );
	}

	/**
	 * Record a successful or failed run on the schedule counters.
	 *
	 * @since 1.5.0
	 *
	 * @param int    $id              Schedule ID.
	 * @param int    $posts_created   Number of posts created in this run.
	 * @param bool   $is_failure      True if the run is considered a failure.
	 * @param string $last_run_at_utc UTC datetime string for the run completion.
	 * @return void
	 */
	public function record_run( $id, $posts_created, $is_failure, $last_run_at_utc ) {
		$current_runs  = (int) get_post_meta( $id, Schedule::META_RUNS_COMPLETED, true );
		$current_posts = (int) get_post_meta( $id, Schedule::META_POSTS_CREATED, true );
		$current_fails = (int) get_post_meta( $id, Schedule::META_FAILURE_STREAK, true );

		update_post_meta( $id, Schedule::META_RUNS_COMPLETED, $current_runs + 1 );
		update_post_meta( $id, Schedule::META_POSTS_CREATED, $current_posts + max( 0, (int) $posts_created ) );
		update_post_meta( $id, Schedule::META_FAILURE_STREAK, $is_failure ? $current_fails + 1 : 0 );
		update_post_meta( $id, Schedule::META_LAST_RUN_AT, $last_run_at_utc );
	}

	/**
	 * List schedules (any status except trashed by default).
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Optional: 'statuses' (array of post_status), 'limit' (int), 'offset' (int).
	 * @return Schedule[]
	 */
	public function list_all( array $args = [] ) {
		$statuses = isset( $args['statuses'] ) ? (array) $args['statuses'] : [ 'draft', 'publish', 'private' ];
		$limit    = isset( $args['limit'] ) ? (int) $args['limit'] : 100;
		$offset   = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

		$query = new WP_Query(
			[
				'post_type'      => Schedule::POST_TYPE,
				'post_status'    => $statuses,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			]
		);

		$schedules = [];
		foreach ( $query->posts as $post ) {
			$schedules[] = $this->hydrate( $post );
		}
		return $schedules;
	}

	/**
	 * Clone a schedule. New row starts in 'draft' status with " (copy)" appended.
	 *
	 * @since 1.5.0
	 *
	 * @param int $id Source schedule ID.
	 * @return Schedule|\WP_Error
	 */
	public function clone_schedule( $id ) {
		$source = $this->find( $id );
		if ( ! $source ) {
			return new \WP_Error( 'cforge_autopilot_not_found', __( 'Schedule not found', 'content-forge' ), [ 'status' => 404 ] );
		}

		$config         = $source->config();
		$config['topic']['list_cursor']  = 0;
		$config['topic']['queue_cursor'] = 0;

		return $this->create(
			$source->name() . ' ' . __( '(copy)', 'content-forge' ),
			$config,
			$source->created_by()
		);
	}

	/**
	 * Set runs_cap meta. Pass null to clear.
	 *
	 * @since 1.5.0
	 *
	 * @param int      $id  Schedule ID.
	 * @param int|null $cap Cap or null.
	 * @return void
	 */
	public function set_runs_cap( $id, $cap ) {
		if ( $cap === null ) {
			delete_post_meta( $id, Schedule::META_RUNS_CAP );
			return;
		}
		update_post_meta( $id, Schedule::META_RUNS_CAP, (int) $cap );
	}

	/**
	 * Set stop_at meta (UTC datetime string). Pass empty to clear.
	 *
	 * @since 1.5.0
	 *
	 * @param int    $id      Schedule ID.
	 * @param string $stop_at UTC datetime string.
	 * @return void
	 */
	public function set_stop_at( $id, $stop_at ) {
		if ( $stop_at === '' ) {
			delete_post_meta( $id, Schedule::META_STOP_AT );
			return;
		}
		update_post_meta( $id, Schedule::META_STOP_AT, (string) $stop_at );
	}

	/**
	 * Hydrate a Schedule from a WP_Post by decoding config meta.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_Post $post Post object.
	 * @return Schedule
	 */
	protected function hydrate( WP_Post $post ) {
		$raw_config = get_post_meta( $post->ID, Schedule::META_CONFIG, true );
		$config     = is_string( $raw_config ) && $raw_config !== '' ? json_decode( $raw_config, true ) : [];
		if ( ! is_array( $config ) ) {
			$config = [];
		}
		$config = array_replace_recursive( Schedule::default_config(), $config );

		return new Schedule( $post, $config );
	}
}
