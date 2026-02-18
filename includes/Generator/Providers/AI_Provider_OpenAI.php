<?php
/**
 * OpenAI provider adapter.
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
 * OpenAI provider implementation.
 */
class AI_Provider_OpenAI extends AI_Provider_Base {
	/**
	 * Get API endpoint URL.
	 *
	 * @since 1.2.0
	 *
	 * @return string API endpoint URL.
	 */
	public function get_api_endpoint() {
		return 'https://api.openai.com/v1/chat/completions';
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
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
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
		return 'openai';
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
			'model'           => $this->model,
			'messages'        => [
				[
					'role'    => 'system',
					'content' => 'You are a helpful content writer. Always respond with valid JSON containing "title" and "content" keys. The title should be engaging and SEO-friendly (maximum 60 characters). The content should be comprehensive and well-formatted.',
				],
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			],
			'temperature'     => 0.7,
			'max_tokens'      => 2000,
			'response_format' => [ 'type' => 'json_object' ],
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
		if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
			return [
				'title'   => '',
				'content' => '',
			];
		}

		$content = $response['choices'][0]['message']['content'];

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
	 * @return bool True if connection successful, false otherwise.
	 */
	public function test_connection() {
		$payload = [
			'model'      => $this->model,
			'messages'   => [
				[
					'role'    => 'user',
					'content' => 'Respond with JSON: {"status": "ok"}',
				],
			],
			'max_tokens' => 10,
		];

		$response = $this->make_request( $payload );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		}

		return [
			'success' => true,
			'message' => __( 'Connection successful', 'content-forge' ),
		];
	}
}
