<?php
/**
 * Google provider adapter.
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Generator\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google provider implementation.
 */
class AI_Provider_Google extends AI_Provider_Base {
	/**
	 * Get API endpoint URL.
	 *
	 * @since 1.2.0
	 *
	 * @return string API endpoint URL.
	 */
	public function get_api_endpoint() {
		// Use v1 API instead of v1beta for better model compatibility
		return 'https://generativelanguage.googleapis.com/v1/models/' . $this->model . ':generateContent?key=' . $this->api_key;
	}

	/**
	 * Get request headers.
	 *
	 * @since 1.2.0
	 *
	 * @return array Request headers.
	 */
	public function get_request_headers() {
		return [
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Get provider slug.
	 *
	 * @since 1.2.0
	 *
	 * @return string Provider slug.
	 */
	public function get_provider_slug() {
		return 'google';
	}

	/**
	 * Build request payload.
	 *
	 * @since 1.2.0
	 *
	 * @param array $params Generation parameters.
	 * @return array Request payload.
	 */
	public function build_request_payload( array $params ) {
		$prompt = $params['prompt'] ?? '';

		return [
			'contents' => [
				[
					'parts' => [
						[
							'text' => $prompt . "\n\nAlways respond with valid JSON containing \"title\" and \"content\" keys. The title should be engaging and SEO-friendly (maximum 60 characters). The content should be comprehensive and well-formatted.",
						],
					],
				],
			],
		];
	}

	/**
	 * Parse response to extract title and content.
	 *
	 * @since 1.2.0
	 *
	 * @param array $response Raw API response.
	 * @return array Array with 'title' and 'content' keys.
	 */
	public function parse_response( array $response ) {
		if ( ! isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return [ 'title' => '', 'content' => '' ];
		}

		$content = $response['candidates'][0]['content']['parts'][0]['text'];
		
		// Clean markdown code blocks if present
		$content = $this->clean_json_content( $content );
		
		$parsed = json_decode( $content, true );

		if ( json_last_error() === JSON_ERROR_NONE && isset( $parsed['title'] ) && isset( $parsed['content'] ) ) {
			// Convert literal newlines to actual newlines in content
			$parsed['content'] = $this->convert_literal_newlines( $parsed['content'] );
			
			return [
				'title'   => $parsed['title'],
				'content' => $parsed['content'],
			];
		}

		// Fallback: Try to parse text response.
		return $this->parse_text_response( $content );
	}

	/**
	 * Make HTTP request to provider API.
	 *
	 * Override to handle Google's API key in URL.
	 *
	 * @since 1.2.0
	 *
	 * @param array $payload Request payload.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function make_request( array $payload ) {
		$endpoint = $this->get_api_endpoint();
		$headers  = $this->get_request_headers();

		
		/**
		 * Filter the request payload before sending to provider API.
		 *
		 * @since 1.2.0
		 *
		 * @param array  $payload  Request payload array.
		 * @param string $provider Provider slug.
		 * @param string $model    Model slug.
		 * @return array Filtered payload.
		 */
		$payload = apply_filters( 'cforge_ai_provider_request_payload', $payload, $this->get_provider_slug(), $this->model );

		$args = [
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 60,
		];

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_data = json_decode( $body, true );
			$error_message = $error_data['error']['message'] ?? __( 'API request failed', 'content-forge' );

			return new WP_Error(
				'api_error',
				$error_message,
				[ 'status' => $status_code ]
			);
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', __( 'Invalid JSON response', 'content-forge' ) );
		}

		/**
		 * Filter the raw provider API response before parsing.
		 *
		 * @since 1.2.0
		 *
		 * @param array  $response Raw API response.
		 * @param string $provider Provider slug.
		 * @return array Filtered response.
		 */
		$data = apply_filters( 'cforge_ai_provider_response', $data, $this->get_provider_slug() );

		return $data;
	}

	/**
	 * Generate both title and content.
	 *
	 * @since 1.2.0
	 *
	 * @param array $params Generation parameters.
	 * @return array|WP_Error Array with 'title' and 'content' keys, or WP_Error on failure.
	 */
	public function generate( array $params ) {
		$payload  = $this->build_request_payload( $params );
		$response = $this->make_request( $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_response( $response );
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.2.0
	 *
	 * @return array Array with 'success' boolean and 'message' string.
	 */
	public function test_connection() {
		// Use GET request to list models - more reliable than content generation
		$url = 'https://generativelanguage.googleapis.com/v1/models?key=' . urlencode( $this->api_key );

		$args = [
			'method'    => 'GET',
			'timeout'   => 30,
			'sslverify' => true,
			'headers'   => [
				'Content-Type' => 'application/json',
			],
		];

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code === 200 ) {
			// Try to parse the response to verify it's valid
			$response_data = json_decode( $response_body, true );

			if ( json_last_error() === JSON_ERROR_NONE && isset( $response_data['models'] ) ) {
				$models_count = count( $response_data['models'] );
				return [
					'success' => true,
					'message' => sprintf(
						__( 'Connection successful! Found %d available models.', 'content-forge' ),
						$models_count
					),
				];
			} else {
				return [
					'success' => false,
					'message' => __( 'Connected but received unexpected response format.', 'content-forge' ),
				];
			}
		} else {
			// Try to extract a meaningful error message
			$error_data = json_decode( $response_body, true );
			$error_message = __( 'Connection failed. Please check your API key and try again.', 'content-forge' );

			if ( isset( $error_data['error']['message'] ) && is_string( $error_data['error']['message'] ) ) {
				$error_message = sanitize_text_field( $error_data['error']['message'] );
			} elseif ( isset( $error_data['error'] ) && is_string( $error_data['error'] ) ) {
				$error_message = sanitize_text_field( $error_data['error'] );
			}

			return [
				'success' => false,
				'message' => $error_message,
			];
		}
	}
}
