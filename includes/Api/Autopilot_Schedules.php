<?php
// DESCRIPTION: REST controller for Autopilot schedules — CRUD + lifecycle endpoints.
// All routes require manage_options; writes are nonced via the wp_rest nonce.

namespace ContentForge\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use DateTimeImmutable;
use DateTimeZone;
use ContentForge\Autopilot\Plugin as Autopilot_Plugin;
use ContentForge\Autopilot\Schedule;
use ContentForge\Autopilot\Schedule_Repository;
use ContentForge\Autopilot\Run_Repository;
use ContentForge\Autopilot\Dispatcher;
use ContentForge\Autopilot\Preview_Generator;
use ContentForge\Autopilot\Frequency\Factory as FrequencyFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for /cforge/v1/autopilot/schedules.
 *
 * @since 1.5.0
 */
class Autopilot_Schedules extends CForge_REST_Controller {

	/** @var string */
	protected $base = 'autopilot/schedules';

	/** @var Schedule_Repository */
	protected $schedules;

	/** @var Run_Repository */
	protected $runs;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->schedules = new Schedule_Repository();
		$this->runs      = new Run_Repository();
	}

	/**
	 * Higher permission for autopilot — manage_options not just edit_posts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function permission_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register routes.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handle_list' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_create' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
			]
		);

		// Preview route — POST without an ID.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/preview',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_preview' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		// Bulk action route.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/bulk',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_bulk' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'action' => [
						'required' => true,
						'enum'     => [ 'pause', 'resume', 'archive' ],
					],
					'ids' => [
						'required' => true,
						'type'     => 'array',
						'items'    => [ 'type' => 'integer' ],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handle_get' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'handle_update' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'handle_delete' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
			]
		);

		foreach ( [ 'pause', 'resume', 'clone', 'run-now' ] as $action ) {
			register_rest_route(
				$this->namespace,
				'/' . $this->base . '/(?P<id>\d+)/' . $action,
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_action_' . str_replace( '-', '_', $action ) ],
					'permission_callback' => [ $this, 'permission_check' ],
				]
			);
		}
	}

	// ----- Handlers -----

	/**
	 * List schedules.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_list( WP_REST_Request $request ) {
		$schedules = $this->schedules->list_all();
		$payload   = array_map( [ $this, 'serialize' ], $schedules );

		return new WP_REST_Response(
			[
				'schedules'       => $payload,
				'feature_enabled' => Autopilot_Plugin::is_enabled(),
			],
			200
		);
	}

	/**
	 * Get one schedule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get( WP_REST_Request $request ) {
		$schedule = $this->schedules->find( (int) $request['id'] );
		if ( ! $schedule ) {
			return $this->not_found();
		}
		return new WP_REST_Response( $this->serialize( $schedule ), 200 );
	}

	/**
	 * Create a schedule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create( WP_REST_Request $request ) {
		$name   = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$config = $request->get_param( 'config' );

		if ( $name === '' ) {
			return new WP_Error( 'cforge_autopilot_name_required', __( 'Name is required', 'content-forge' ), [ 'status' => 400 ] );
		}
		$config = $this->sanitize_config( is_array( $config ) ? $config : [] );

		$ack_check = $this->require_publish_ack_when_immediate( $config );
		if ( is_wp_error( $ack_check ) ) {
			return $ack_check;
		}

		$schedule = $this->schedules->create( $name, $config );
		if ( is_wp_error( $schedule ) ) {
			return $schedule;
		}

		return new WP_REST_Response( $this->serialize( $schedule ), 201 );
	}

	/**
	 * Apply a bulk action (pause/resume/archive) to a list of schedule IDs.
	 *
	 * Per-row errors are collected and reported rather than aborting the batch.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_bulk( WP_REST_Request $request ) {
		$action = (string) $request->get_param( 'action' );
		$ids    = array_map( 'intval', (array) $request->get_param( 'ids' ) );
		$ids    = array_values( array_unique( array_filter( $ids ) ) );

		$succeeded = [];
		$failed    = [];

		foreach ( $ids as $id ) {
			$schedule = $this->schedules->find( $id );
			if ( ! $schedule ) {
				$failed[] = [ 'id' => $id, 'error' => 'not_found' ];
				continue;
			}

			try {
				switch ( $action ) {
					case 'pause':
						$this->schedules->pause( $id );
						break;

					case 'resume':
						$next = FrequencyFactory::from_config( (array) $schedule->config_get( 'frequency', [] ) )
							->next_after( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
						$this->schedules->update_next_run( $id, $next->format( 'Y-m-d H:i:s' ) );
						$this->schedules->activate( $id );
						break;

					case 'archive':
						$this->schedules->archive( $id );
						break;
				}
				$succeeded[] = $id;
			} catch ( \Throwable $e ) {
				$failed[] = [ 'id' => $id, 'error' => $e->getMessage() ];
			}
		}

		return new WP_REST_Response(
			[
				'action'    => $action,
				'succeeded' => $succeeded,
				'failed'    => $failed,
			],
			200
		);
	}

	/**
	 * Preview a schedule — generates one sample post without saving anything.
	 *
	 * Accepts the same payload shape as create. Useful for sanity-checking a
	 * prompt before activating the autopilot.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_preview( WP_REST_Request $request ) {
		$config_in = $request->get_param( 'config' );
		$config    = $this->sanitize_config( is_array( $config_in ) ? $config_in : [] );

		$result = ( new Preview_Generator() )->generate( $config );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Update a schedule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update( WP_REST_Request $request ) {
		$schedule = $this->schedules->find( (int) $request['id'] );
		if ( ! $schedule ) {
			return $this->not_found();
		}

		$name      = $request->get_param( 'name' );
		$new_name  = is_string( $name ) ? sanitize_text_field( $name ) : null;
		$config_in = $request->get_param( 'config' );
		$config    = is_array( $config_in ) ? $this->sanitize_config( $config_in ) : $schedule->config();

		$ack_check = $this->require_publish_ack_when_immediate( $config );
		if ( is_wp_error( $ack_check ) ) {
			return $ack_check;
		}

		$updated = $this->schedules->update( $schedule->id(), $config, $new_name );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		// If the schedule is currently active, recompute next_run_at from the new frequency.
		if ( $updated->status() === 'active' ) {
			$next = FrequencyFactory::from_config( (array) $updated->config_get( 'frequency', [] ) )
				->next_after( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
			$this->schedules->update_next_run( $updated->id(), $next->format( 'Y-m-d H:i:s' ) );
			$updated = $this->schedules->find( $updated->id() );
		}

		return new WP_REST_Response( $this->serialize( $updated ), 200 );
	}

	/**
	 * Delete (archive or hard-delete) a schedule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete( WP_REST_Request $request ) {
		$id    = (int) $request['id'];
		$force = (bool) $request->get_param( 'force' );

		$schedule = $this->schedules->find( $id );
		if ( ! $schedule ) {
			return $this->not_found();
		}

		if ( $force ) {
			wp_delete_post( $id, true );
		} else {
			$this->schedules->archive( $id );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Pause.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_action_pause( WP_REST_Request $request ) {
		$schedule = $this->schedules->find( (int) $request['id'] );
		if ( ! $schedule ) {
			return $this->not_found();
		}
		$this->schedules->pause( $schedule->id() );
		return new WP_REST_Response( $this->serialize( $this->schedules->find( $schedule->id() ) ), 200 );
	}

	/**
	 * Resume — sets next_run_at from current config and activates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_action_resume( WP_REST_Request $request ) {
		$schedule = $this->schedules->find( (int) $request['id'] );
		if ( ! $schedule ) {
			return $this->not_found();
		}

		$next = FrequencyFactory::from_config( (array) $schedule->config_get( 'frequency', [] ) )
			->next_after( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );

		$this->schedules->update_next_run( $schedule->id(), $next->format( 'Y-m-d H:i:s' ) );
		$this->schedules->activate( $schedule->id() );

		return new WP_REST_Response( $this->serialize( $this->schedules->find( $schedule->id() ) ), 200 );
	}

	/**
	 * Clone.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_action_clone( WP_REST_Request $request ) {
		$cloned = $this->schedules->clone_schedule( (int) $request['id'] );
		if ( is_wp_error( $cloned ) ) {
			return $cloned;
		}
		return new WP_REST_Response( $this->serialize( $cloned ), 201 );
	}

	/**
	 * Run now — enqueues an immediate runner action regardless of next_run_at.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_action_run_now( WP_REST_Request $request ) {
		$schedule = $this->schedules->find( (int) $request['id'] );
		if ( ! $schedule ) {
			return $this->not_found();
		}

		$posts_per_run = max( 1, (int) $schedule->config_get( 'posts_per_run', 1 ) );

		$run_id = $this->runs->insert(
			[
				'schedule_id'     => $schedule->id(),
				'status'          => 'queued',
				'started_at'      => gmdate( 'Y-m-d H:i:s' ),
				'posts_requested' => $posts_per_run,
			]
		);

		if ( ! $run_id ) {
			return new WP_Error( 'cforge_autopilot_run_failed', __( 'Could not enqueue run', 'content-forge' ), [ 'status' => 500 ] );
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( Dispatcher::RUN_HOOK, [ $schedule->id(), $run_id ], 'cforge_autopilot_runs' );
		} elseif ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time(), Dispatcher::RUN_HOOK, [ $schedule->id(), $run_id ], 'cforge_autopilot_runs' );
		}

		return new WP_REST_Response( [ 'run_id' => $run_id ], 202 );
	}

	// ----- Helpers -----

	/**
	 * Serialize a Schedule for REST output.
	 *
	 * @param Schedule $schedule Schedule.
	 * @return array
	 */
	protected function serialize( Schedule $schedule ) {
		return [
			'id'              => $schedule->id(),
			'name'            => $schedule->name(),
			'status'          => $schedule->status(),
			'post_status'     => $schedule->post_status(),
			'config'          => $schedule->config(),
			'next_run_at'     => $schedule->next_run_at(),
			'last_run_at'     => $schedule->last_run_at(),
			'runs_completed'  => $schedule->runs_completed(),
			'posts_created'   => $schedule->posts_created(),
			'failure_streak'  => $schedule->failure_streak(),
			'runs_cap'        => $schedule->runs_cap(),
			'stop_at'         => $schedule->stop_at(),
			'created_by'      => $schedule->created_by(),
		];
	}

	/**
	 * Sanitize incoming config, layering over defaults.
	 *
	 * @param array $incoming Incoming config (possibly partial).
	 * @return array
	 */
	protected function sanitize_config( array $incoming ) {
		$merged = array_replace_recursive( Schedule::default_config(), $incoming );

		// Type-coerce a handful of fields the UI most commonly fumbles.
		$merged['posts_per_run']                        = max( 1, min( 5, (int) ( $merged['posts_per_run'] ?? 1 ) ) );
		$merged['avoid_recent_duplicates']              = (bool) $merged['avoid_recent_duplicates'];
		$merged['targeting']['post_type']               = sanitize_key( $merged['targeting']['post_type'] ?? 'post' );
		$merged['targeting']['category_id']             = (int) ( $merged['targeting']['category_id'] ?? 0 );
		$merged['targeting']['tag_ids']                 = array_values( array_map( 'intval', (array) ( $merged['targeting']['tag_ids'] ?? [] ) ) );
		$merged['targeting']['author_id']               = (int) ( $merged['targeting']['author_id'] ?? 0 );
		$merged['targeting']['ai_suggests_tags']        = (bool) ( $merged['targeting']['ai_suggests_tags'] ?? false );
		$merged['targeting']['featured_image']['source'] = sanitize_key( $merged['targeting']['featured_image']['source'] ?? 'none' );
		$merged['targeting']['featured_image']['url']    = esc_url_raw( (string) ( $merged['targeting']['featured_image']['url'] ?? '' ) );

		$merged['ai']['custom_prompt'] = sanitize_textarea_field( (string) ( $merged['ai']['custom_prompt'] ?? '' ) );
		$merged['ai']['tone']          = sanitize_key( (string) ( $merged['ai']['tone'] ?? 'professional' ) );
		$merged['ai']['length']        = sanitize_key( (string) ( $merged['ai']['length'] ?? 'medium' ) );

		$merged['frequency']['mode']           = sanitize_key( (string) ( $merged['frequency']['mode'] ?? 'daily' ) );
		$merged['frequency']['time']           = (string) ( $merged['frequency']['time'] ?? '09:00' );
		$merged['frequency']['weekdays']       = array_values( array_map( 'intval', (array) ( $merged['frequency']['weekdays'] ?? [ 1 ] ) ) );
		$merged['frequency']['day_of_month']   = (int) ( $merged['frequency']['day_of_month'] ?? 1 );
		$merged['frequency']['interval_hours'] = max( 1, (int) ( $merged['frequency']['interval_hours'] ?? 24 ) );
		$merged['frequency']['start_at']       = sanitize_text_field( (string) ( $merged['frequency']['start_at'] ?? '' ) );
		$merged['frequency']['stagger_hours']  = max( 0, (int) ( $merged['frequency']['stagger_hours'] ?? 0 ) );

		$merged['publishing']['mode']                   = sanitize_key( (string) ( $merged['publishing']['mode'] ?? 'draft' ) );
		$merged['publishing']['publish_delay_hours']    = max( 0, (int) ( $merged['publishing']['publish_delay_hours'] ?? 1 ) );
		$merged['publishing']['ack_publish_immediately'] = (bool) ( $merged['publishing']['ack_publish_immediately'] ?? false );

		$merged['safety']['daily_post_cap']             = max( 1, (int) ( $merged['safety']['daily_post_cap'] ?? 5 ) );
		$merged['safety']['auto_pause_after_failures']  = max( 1, min( 10, (int) ( $merged['safety']['auto_pause_after_failures'] ?? 3 ) ) );

		$merged['notifications']['email_mode'] = sanitize_key( (string) ( $merged['notifications']['email_mode'] ?? 'none' ) );
		$merged['notifications']['email_to']   = sanitize_email( (string) ( $merged['notifications']['email_to'] ?? '' ) );

		$merged['topic']['mode']         = sanitize_key( (string) ( $merged['topic']['mode'] ?? 'list_cycle' ) );
		$merged['topic']['list']         = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $merged['topic']['list'] ?? [] ) ) ) );
		$merged['topic']['queue']        = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $merged['topic']['queue'] ?? [] ) ) ) );
		$merged['topic']['queue_cursor'] = max( 0, (int) ( $merged['topic']['queue_cursor'] ?? 0 ) );
		$merged['topic']['list_cursor']  = max( 0, (int) ( $merged['topic']['list_cursor'] ?? 0 ) );
		$merged['topic']['theme']        = sanitize_text_field( (string) ( $merged['topic']['theme'] ?? '' ) );

		return $merged;
	}

	/**
	 * Reject publish-immediately mode when the ack flag isn't set.
	 *
	 * @param array $config Config.
	 * @return WP_Error|null
	 */
	protected function require_publish_ack_when_immediate( array $config ) {
		$mode = $config['publishing']['mode'] ?? 'draft';
		$ack  = ! empty( $config['publishing']['ack_publish_immediately'] );

		if ( $mode === 'publish' && ! $ack ) {
			return new WP_Error(
				'cforge_autopilot_publish_ack_required',
				__( 'Publish-immediately requires explicit acknowledgement.', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}
		return null;
	}

	/**
	 * 404 helper.
	 *
	 * @return WP_Error
	 */
	protected function not_found() {
		return new WP_Error( 'cforge_autopilot_not_found', __( 'Schedule not found', 'content-forge' ), [ 'status' => 404 ] );
	}
}
