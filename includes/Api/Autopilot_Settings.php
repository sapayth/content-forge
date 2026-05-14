<?php
// DESCRIPTION: REST controller for the global Autopilot feature flag.
// Single switch — when off, the dispatcher and runner don't register.

namespace ContentForge\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use ContentForge\Autopilot\Plugin as Autopilot_Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for /cforge/v1/autopilot/settings.
 *
 * @since 1.5.0
 */
class Autopilot_Settings extends CForge_REST_Controller {

	/** @var string */
	protected $base = 'autopilot/settings';

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
			'/' . $this->base,
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
					'args'                => [
						'enabled' => [
							'required' => false,
							'type'     => 'boolean',
						],
					],
				],
			]
		);
	}

	/**
	 * Read current settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_get( WP_REST_Request $request ) {
		return new WP_REST_Response(
			[
				'enabled' => Autopilot_Plugin::is_enabled(),
			],
			200
		);
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_update( WP_REST_Request $request ) {
		$enabled_param = $request->get_param( 'enabled' );

		if ( $enabled_param !== null ) {
			update_option( Autopilot_Plugin::FEATURE_FLAG_OPTION, (bool) $enabled_param, false );
		}

		return $this->handle_get( $request );
	}
}
