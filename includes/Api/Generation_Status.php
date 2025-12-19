<?php
/**
 * Generation Status REST API controller for Content Forge plugin.
 *
 * @since   1.2.0
 * @package ContentForge
 */

namespace ContentForge\Api;

use WP_REST_Server;
use ContentForge\Generator\AI_Scheduled_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Generation_Status extends CForge_REST_Controller {
	/**
	 * Route base
	 *
	 * @var string
	 */
	protected $base = 'generation';

	/**
	 * Scheduled generator instance.
	 *
	 * @var AI_Scheduled_Generator
	 */
	protected $scheduled_generator;

	/**
	 * Constructor for Generation Status REST API controller.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		$this->scheduled_generator = new AI_Scheduled_Generator();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes for generation status.
	 *
	 * @since 1.2.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/status',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handle_status_check' ],
					'permission_callback' => [ $this, 'permission_check' ],
					'args'                => [
						'batch_id' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Batch ID for tracking generation progress', 'content-forge' ),
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/list',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handle_list_batches' ],
					'permission_callback' => [ $this, 'permission_check' ],
					'args'                => [
						'status' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
							'default'           => 'all',
							'enum'              => [ 'all', 'processing', 'completed', 'failed' ],
							'description'       => __( 'Filter batches by status', 'content-forge' ),
						],
						'limit' => [
							'required'          => false,
							'sanitize_callback' => 'absint',
							'default'           => 10,
							'description'       => __( 'Maximum number of batches to return', 'content-forge' ),
						],
					],
				],
			]
		);
	}

	/**
	 * Handle generation status check.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
	 */
	public function handle_status_check( $request ) {
		$batch_id = $request->get_param( 'batch_id' );

		if ( empty( $batch_id ) ) {
			return new \WP_Error(
				'batch_id_required',
				__( 'Batch ID is required', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}

		$status = $this->scheduled_generator->get_batch_status( $batch_id );

		if ( is_wp_error( $status ) ) {
			return $status;
		}

		return new \WP_REST_Response( $status, 200 );
	}

	/**
	 * Handle listing of generation batches.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
	 */
	public function handle_list_batches( $request ) {
		global $wpdb;

		$status_filter = $request->get_param( 'status' );
		$limit = min( $request->get_param( 'limit' ), 50 ); // Max 50

		// Get all batch options
		$pattern = AI_Scheduled_Generator::BATCH_OPTION_PREFIX . '%';
		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT %d",
				$pattern,
				$limit
			)
		);

		$batches = [];
		$user_id = get_current_user_id();

		foreach ( $options as $option ) {
			$batch_data = maybe_unserialize( $option->option_value );

			// Skip if not user's batch
			if ( ! isset( $batch_data['user_id'] ) || $batch_data['user_id'] !== $user_id ) {
				continue;
			}

			// Extract batch ID from option name
			$batch_id = str_replace( AI_Scheduled_Generator::BATCH_OPTION_PREFIX, '', $option->option_name );

			// Filter by status if specified
			if ( $status_filter !== 'all' && $batch_data['status'] !== $status_filter ) {
				continue;
			}

			// Calculate progress
			$progress = 0;
			if ( isset( $batch_data['total'] ) && $batch_data['total'] > 0 ) {
				$progress = round( ( $batch_data['completed'] / $batch_data['total'] ) * 100 );
			}

			$batches[] = [
				'batch_id'          => $batch_id,
				'total'             => $batch_data['total'] ?? 0,
				'completed'         => $batch_data['completed'] ?? 0,
				'pending'           => $batch_data['pending'] ?? 0,
				'progress_percentage'=> $progress,
				'status'            => $batch_data['status'] ?? 'unknown',
				'created_at'        => $batch_data['created_at'] ?? 0,
				'completed_at'      => $batch_data['completed_at'] ?? null,
				'error_count'       => isset( $batch_data['errors'] ) ? count( $batch_data['errors'] ) : 0,
			];
		}

		return new \WP_REST_Response(
			[
				'batches' => $batches,
				'total'   => count( $batches ),
			],
			200
		);
	}
}