<?php
/**
 * Base class for AI provider adapters.
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
 * Abstract base class for AI provider implementations.
 */
abstract class AI_Provider_Base {
	/**
	 * API key for the provider.
	 *
	 * @var string
	 */
	protected string $api_key;

	/**
	 * Model to use.
	 *
	 * @var string
	 */
	protected string $model;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param string $api_key API key.
	 * @param string $model   Model slug.
	 */
	public function __construct( string $api_key, string $model ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Generate both title and content in a single API call.
	 *
	 * @since 1.2.0
	 *
	 * @param array $params Generation parameters.
	 * @return array|WP_Error Array with 'title' and 'content' keys, or WP_Error on failure.
	 */
	abstract public function generate( array $params );

	/**
	 * Test API connection.
	 *
	 * @since 1.2.0
	 *
	 * @return array Array with 'success' boolean and 'message' string.
	 */
	abstract public function test_connection();

	/**
	 * Get API endpoint URL.
	 *
	 * @since 1.2.0
	 *
	 * @return string API endpoint URL.
	 */
	abstract protected function get_api_endpoint();

	/**
	 * Build request payload.
	 *
	 * @since 1.2.0
	 *
	 * @param array $params Generation parameters.
	 * @return array Request payload.
	 */
	abstract protected function build_request_payload( array $params );

	/**
	 * Parse response to extract title and content.
	 *
	 * @since 1.2.0
	 *
	 * @param array $response Raw API response.
	 * @return array Array with 'title' and 'content' keys.
	 */
	abstract protected function parse_response( array $response );

	/**
	 * Make HTTP request to provider API.
	 *
	 * @since 1.2.0
	 *
	 * @param array $payload Request payload.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	protected function make_request( array $payload ) {
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
	 * Get request headers.
	 *
	 * @since 1.2.0
	 *
	 * @return array Request headers.
	 */
	abstract protected function get_request_headers();

	/**
	 * Get provider slug.
	 *
	 * @since 1.2.0
	 *
	 * @return string Provider slug.
	 */
	abstract public function get_provider_slug();

	/**
	 * Clean JSON content by removing markdown code blocks.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content Raw content that may contain markdown code blocks.
	 * @return string Cleaned content.
	 */
	protected function clean_json_content( string $content ) {
		// Remove markdown code block wrappers (```json ... ``` or ``` ... ```)
		$content = preg_replace( '/^```(?:json)?\s*\n?/m', '', $content );
		$content = preg_replace( '/\n?```\s*$/m', '', $content );
		
		return trim( $content );
	}

	/**
	 * Convert literal newline characters to actual newlines.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content Content with literal \n characters.
	 * @return string Content with actual newlines.
	 */
	protected function convert_literal_newlines( string $content ) {
		// Convert literal \n to actual newlines
		$content = str_replace( '\\n', "\n", $content );
		
		return $content;
	}

	/**
	 * Parse text response when JSON parsing fails.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content Raw text content.
	 * @return array Array with 'title' and 'content' keys.
	 */
	protected function parse_text_response( string $content ) {
		// Try to extract title from first line or heading.
		$lines = explode( "\n", trim( $content ) );
		$title = '';

		// Look for title in first few lines.
		foreach ( array_slice( $lines, 0, 3 ) as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) && strlen( $line ) < 100 ) {
				// Remove markdown headers.
				$title = preg_replace( '/^#+\s*/', '', $line );
				break;
			}
		}

		// If no title found, use first 60 characters.
		if ( empty( $title ) ) {
			$title = substr( trim( $content ), 0, 60 );
		}

		// Content is everything else.
		$content_text = $content;

		return [
			'title'   => sanitize_text_field( $title ),
			'content' => $content_text,
		];
	}
}
