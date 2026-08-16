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
	 * Whether this provider can generate images.
	 *
	 * Defaults to false so text-only providers need no implementation. Providers
	 * with an image endpoint override this and generate_image().
	 *
	 * @since 1.8.0
	 *
	 * @return bool True if the provider exposes an image model.
	 */
	public function supports_images() {
		return false;
	}

	/**
	 * Generate an image and return the path to a local temporary file.
	 *
	 * Returning a file path rather than a URL keeps the caller provider-agnostic:
	 * some providers return a URL, others return base64 image data.
	 *
	 * @since 1.8.0
	 *
	 * @param string $prompt Image prompt.
	 * @return string|WP_Error Absolute temp file path, or WP_Error on failure.
	 */
	public function generate_image( string $prompt ) {
		return new WP_Error(
			'cforge_no_image_support',
			__( 'This AI provider does not support image generation.', 'content-forge' )
		);
	}

	/**
	 * Persist raw image bytes to a temporary file.
	 *
	 * @since 1.8.0
	 *
	 * @param string $binary    Raw (already base64-decoded) image bytes.
	 * @param string $extension File extension without the dot.
	 * @return string|WP_Error Temp file path, or WP_Error on failure.
	 */
	protected function save_image_temp_file( string $binary, string $extension = 'png' ) {
		if ( '' === $binary ) {
			return new WP_Error( 'cforge_empty_image', __( 'Provider returned an empty image.', 'content-forge' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp_file = wp_tempnam( 'cforge-ai-image.' . $extension );

		if ( ! $tmp_file ) {
			return new WP_Error( 'cforge_tmpfile_failed', __( 'Could not create a temporary file for the image.', 'content-forge' ) );
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $tmp_file, $binary ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tmp_file );
			return new WP_Error( 'cforge_tmpfile_failed', __( 'Could not write the generated image to disk.', 'content-forge' ) );
		}

		return $tmp_file;
	}

	/**
	 * Make HTTP request to provider API.
	 *
	 * @since 1.2.0
	 * @since 1.8.0 Added the $endpoint override for non-chat endpoints (e.g. images).
	 *
	 * @param array  $payload  Request payload.
	 * @param string $endpoint Optional endpoint override. Defaults to get_api_endpoint().
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	protected function make_request( array $payload, string $endpoint = '' ) {
		$endpoint = '' !== $endpoint ? $endpoint : $this->get_api_endpoint();
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
			$error_data    = json_decode( $body, true );
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
