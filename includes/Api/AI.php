<?php
/**
 * AI REST API controller for Content Forge plugin.
 *
 * @since   1.2.0
 * @package ContentForge
 */

namespace ContentForge\Api;

use WP_REST_Server;
use WP_Error;
use ContentForge\Settings\AI_Settings_Manager;
use ContentForge\Generator\AI_Content_Generator;

class AI extends CForge_REST_Controller {
	/**
	 * Route base
	 *
	 * @var string
	 */
	protected $base = 'ai';

	/**
	 * Constructor for AI REST API controller.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes for AI operations.
	 *
	 * @since 1.2.0
	 */
	public function register_routes() {
		// Get settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
			]
		);

		// Save settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/settings',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_settings' ],
					'permission_callback' => [ $this, 'permission_check_settings' ],
					'args'                => [
						'provider' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'model'    => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'api_key'  => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// Get models for provider.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/models/(?P<provider>[a-z-]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_models' ],
					'permission_callback' => [ $this, 'permission_check' ],
					'args'                => [
						'provider' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
			]
		);

		// Test connection.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/test-connection',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'test_connection' ],
					'permission_callback' => [ $this, 'permission_check_settings' ],
					'args'                => [
						'provider' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'model'    => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'api_key'  => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// Get API key status (masked).
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/api-key/(?P<provider>[a-z-]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_api_key_status' ],
					'permission_callback' => [ $this, 'permission_check_settings' ],
					'args'                => [
						'provider' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
			]
		);

		// Generate content.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/generate',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'generate' ],
					'permission_callback' => [ $this, 'permission_check' ],
					'args'                => [
						'content_type'  => [
							'required'          => false,
							'default'           => 'general',
							'sanitize_callback' => 'sanitize_key',
						],
						'custom_prompt' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'editor_type'   => [
							'required'          => false,
							'default'           => 'block',
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
			]
		);
	}

	/**
	 * Permission check for settings operations.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return bool
	 */
	public function permission_check_settings( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get AI settings.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response
	 */
	public function get_settings( $request ) {
		$provider = AI_Settings_Manager::get_active_provider();

		return new \WP_REST_Response(
			[
				'provider'      => $provider,
				'model'         => AI_Settings_Manager::get_active_model(),
				'is_configured' => AI_Settings_Manager::is_configured(),
				'providers'     => AI_Settings_Manager::get_providers(),
				'models'        => AI_Settings_Manager::get_models( $provider ),
			],
			200
		);
	}

	/**
	 * Save AI settings.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_settings( $request ) {
		$params = $request->get_json_params();

		$settings = [
			'provider' => isset( $params['provider'] ) ? sanitize_key( $params['provider'] ) : '',
			'model'    => isset( $params['model'] ) ? sanitize_key( $params['model'] ) : '',
			'api_key'  => isset( $params['api_key'] ) ? sanitize_text_field( $params['api_key'] ) : '',
		];

		$result = AI_Settings_Manager::save_settings( $settings );

		if ( ! $result ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Failed to save settings. Please check your input.', 'content-forge' ),
				],
				400
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Settings saved successfully', 'content-forge' ),
			],
			200
		);
	}

	/**
	 * Get models for a provider.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response
	 */
	public function get_models( $request ) {
		$provider = $request->get_param( 'provider' );
		$models   = AI_Settings_Manager::get_models( $provider );

		return new \WP_REST_Response(
			[
				'models' => $models,
			],
			200
		);
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response
	 */
	public function test_connection( $request ) {
		$params = $request->get_json_params();

		$provider = isset( $params['provider'] ) ? sanitize_key( $params['provider'] ) : '';
		$model    = isset( $params['model'] ) ? sanitize_key( $params['model'] ) : '';
		$api_key  = isset( $params['api_key'] ) ? sanitize_text_field( $params['api_key'] ) : '';

		if ( empty( $provider ) || empty( $model ) || empty( $api_key ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Missing required parameters', 'content-forge' ),
					'code'    => 'missing_params',
				],
				400
			);
		}

		// Validate provider.
		$providers = AI_Settings_Manager::get_providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid provider', 'content-forge' ),
					'code'    => 'invalid_provider',
				],
				400
			);
		}

		// Validate model.
		$models = AI_Settings_Manager::get_models( $provider );
		if ( ! isset( $models[ $model ] ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid model for provider', 'content-forge' ),
					'code'    => 'invalid_model',
				],
				400
			);
		}

		// Test connection.
		$generator = new AI_Content_Generator( $provider, $model, $api_key, 'block' );
		$result    = $generator->test_connection();

		if ( $result['success'] ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Connection successful', 'content-forge' ),
				],
				200
			);
		}

		// Determine error code.
		$error_code = 'connection_failed';
		$error_message = $result['message'];

		// Try to parse error for better messages.
		if ( strpos( $error_message, 'Invalid' ) !== false || strpos( $error_message, 'authentication' ) !== false ) {
			$error_code = 'invalid_api_key';
			$error_message = __( 'Invalid API key. Please verify your API key and try again.', 'content-forge' );
		} elseif ( strpos( $error_message, 'rate limit' ) !== false || strpos( $error_message, 'quota' ) !== false ) {
			$error_code = 'rate_limit';
			$error_message = __( 'Rate limit exceeded. Please try again later.', 'content-forge' );
		} elseif ( strpos( $error_message, 'network' ) !== false || strpos( $error_message, 'connection' ) !== false ) {
			$error_code = 'network_error';
			$error_message = __( 'Connection failed. Please check your internet connection and try again.', 'content-forge' );
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $error_message,
				'code'    => $error_code,
			],
			400
		);
	}

	/**
	 * Get API key status (masked).
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response
	 */
	public function get_api_key_status( $request ) {
		$provider = $request->get_param( 'provider' );
		$masked_key = AI_Settings_Manager::get_masked_api_key( $provider );

		return new \WP_REST_Response(
			[
				'has_key'   => false !== $masked_key,
				'masked_key' => $masked_key ?: '',
			],
			200
		);
	}

	/**
	 * Generate content using AI.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate( $request ) {
		$params = $request->get_json_params();

		$content_type  = isset( $params['content_type'] ) ? sanitize_key( $params['content_type'] ) : 'general';
		$custom_prompt = isset( $params['custom_prompt'] ) ? sanitize_textarea_field( $params['custom_prompt'] ) : '';
		$editor_type   = isset( $params['editor_type'] ) ? sanitize_key( $params['editor_type'] ) : 'block';

		// Check if AI is configured.
		if ( ! AI_Settings_Manager::is_configured() ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'AI is not configured. Please configure AI settings first.', 'content-forge' ),
					'code'    => 'not_configured',
				],
				400
			);
		}

		$provider = AI_Settings_Manager::get_active_provider();
		$model    = AI_Settings_Manager::get_active_model();
		$api_key  = AI_Settings_Manager::get_api_key( $provider );

		if ( ! $api_key ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'API key not found for active provider.', 'content-forge' ),
					'code'    => 'missing_api_key',
				],
				400
			);
		}

		$generator = new AI_Content_Generator( $provider, $model, $api_key, $editor_type );
		$result    = $generator->generate( $content_type, $custom_prompt );

		if ( is_wp_error( $result ) ) {
			$error_code = $result->get_error_code();
			$error_message = $result->get_error_message();

			// Map error codes to user-friendly messages.
			if ( 'api_error' === $error_code ) {
				if ( strpos( $error_message, 'rate limit' ) !== false || strpos( $error_message, 'quota' ) !== false ) {
					$error_code = 'rate_limit';
					$error_message = __( 'Rate limit exceeded. Please try again later.', 'content-forge' );
				} elseif ( strpos( $error_message, 'Invalid' ) !== false || strpos( $error_message, 'authentication' ) !== false ) {
					$error_code = 'invalid_api_key';
					$error_message = __( 'Invalid API key. Please verify your API key and try again.', 'content-forge' );
				}
			}

			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => $error_message,
					'code'    => $error_code,
				],
				400
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'title'   => $result['title'] ?? '',
				'content' => $result['content'] ?? '',
				'provider' => $provider,
				'model'    => $model,
			],
			200
		);
	}
}
