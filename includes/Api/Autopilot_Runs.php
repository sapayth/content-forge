<?php
// DESCRIPTION: REST controller for Autopilot run history.
// Read-only in P2; retry is deferred to P4.

namespace ContentForge\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ContentForge\Autopilot\Run;
use ContentForge\Autopilot\Run_Repository;
use ContentForge\Autopilot\Schedule_Repository;
use ContentForge\Autopilot\Dispatcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for /cforge/v1/autopilot/schedules/{id}/runs.
 *
 * @since 1.5.0
 */
class Autopilot_Runs extends CForge_REST_Controller {

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
	 * @inheritDoc
	 */
	public function permission_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>\d+)/runs',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_list' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'limit' => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'default'           => 30,
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/autopilot/runs/(?P<id>\d+)/retry',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_retry' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);
	}

	/**
	 * List runs for a schedule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_list( WP_REST_Request $request ) {
		$schedule_id = (int) $request['id'];
		$schedule    = $this->schedules->find( $schedule_id );
		if ( ! $schedule ) {
			return new WP_Error( 'cforge_autopilot_not_found', __( 'Schedule not found', 'content-forge' ), [ 'status' => 404 ] );
		}

		$limit = min( 30, max( 1, (int) $request->get_param( 'limit' ) ) );
		$rows  = $this->runs->list_for_schedule( $schedule_id, $limit );

		$serialized = array_map(
			static function ( Run $run ) {
				return [
					'id'              => $run->id,
					'schedule_id'     => $run->schedule_id,
					'status'          => $run->status,
					'started_at'      => $run->started_at,
					'finished_at'     => $run->finished_at,
					'topic_used'      => $run->topic_used,
					'posts_created'   => $run->posts_created,
					'posts_requested' => $run->posts_requested,
					'error_message'   => $run->error_message,
					'ai_provider'     => $run->ai_provider,
					'ai_model'        => $run->ai_model,
				];
			},
			$rows
		);

		return new WP_REST_Response(
			[
				'runs'  => $serialized,
				'total' => count( $serialized ),
			],
			200
		);
	}

	/**
	 * Retry a failed/partial/skipped run by enqueuing a fresh runner action.
	 *
	 * The original run row is unchanged; a new run row is created and a fresh
	 * AS action enqueued, exactly like "Run now" but only allowed against
	 * non-success historical runs (to avoid duplicating successful work).
	 *
	 * @since 1.5.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_retry( WP_REST_Request $request ) {
		$run = $this->runs->find( (int) $request['id'] );
		if ( ! $run ) {
			return new WP_Error( 'cforge_autopilot_run_not_found', __( 'Run not found', 'content-forge' ), [ 'status' => 404 ] );
		}

		$retryable = [ Run::STATUS_FAILED, Run::STATUS_PARTIAL, Run::STATUS_SKIPPED ];
		if ( ! in_array( $run->status, $retryable, true ) ) {
			return new WP_Error(
				'cforge_autopilot_not_retryable',
				__( 'Only failed, partial, or skipped runs can be retried.', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}

		$schedule = $this->schedules->find( $run->schedule_id );
		if ( ! $schedule ) {
			return new WP_Error( 'cforge_autopilot_not_found', __( 'Schedule no longer exists', 'content-forge' ), [ 'status' => 404 ] );
		}

		$new_run_id = $this->runs->insert(
			[
				'schedule_id'     => $schedule->id(),
				'status'          => Run::STATUS_QUEUED,
				'started_at'      => gmdate( 'Y-m-d H:i:s' ),
				'posts_requested' => $run->posts_requested,
			]
		);
		if ( ! $new_run_id ) {
			return new WP_Error( 'cforge_autopilot_retry_failed', __( 'Could not enqueue retry', 'content-forge' ), [ 'status' => 500 ] );
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( Dispatcher::RUN_HOOK, [ $schedule->id(), $new_run_id ], 'cforge_autopilot_runs' );
		} elseif ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time(), Dispatcher::RUN_HOOK, [ $schedule->id(), $new_run_id ], 'cforge_autopilot_runs' );
		}

		return new WP_REST_Response( [ 'run_id' => $new_run_id ], 202 );
	}
}
