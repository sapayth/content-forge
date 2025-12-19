<?php
/**
 * AI Settings Manager class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages AI provider settings, API key storage, and configuration.
 */
class AI_Settings_Manager {
	// Provider constants.
	const PROVIDER_OPENAI    = 'openai';
	const PROVIDER_ANTHROPIC = 'anthropic';
	const PROVIDER_GOOGLE    = 'google';

	// Option names.
	const OPTION_PROVIDER     = 'cforge_ai_provider';
	const OPTION_KEY_PREFIX   = 'cforge_ai_';
	const OPTION_KEY_SUFFIX   = '_key';
	const OPTION_MODEL_SUFFIX = '_model';

	// Default values.
	const DEFAULT_PROVIDER          = 'openai';
	const DEFAULT_MODEL_OPENAI      = 'gpt-4';
	const DEFAULT_MODEL_ANTHROPIC   = 'claude-3-opus-20240229';
	const DEFAULT_MODEL_GOOGLE      = 'gemini-2.5-flash';

	/**
	 * Get available AI providers.
	 *
	 * @since 1.2.0
	 *
	 * @return array Array of provider slugs => labels.
	 */
	public static function get_providers() {
		$providers = [
			self::PROVIDER_OPENAI    => 'OpenAI',
			self::PROVIDER_ANTHROPIC => 'Anthropic',
			self::PROVIDER_GOOGLE    => 'Google',
		];

		/**
		 * Filter AI providers.
		 *
		 * @since 1.2.0
		 *
		 * @param array $providers Array of provider slugs => labels.
		 * @return array Filtered providers.
		 */
		return apply_filters( 'cforge_ai_providers', $providers );
	}

	/**
	 * Get available models for a provider.
	 *
	 * @since 1.2.0
	 *
	 * @param string $provider Provider slug.
	 * @return array Array of model slugs => labels.
	 */
	public static function get_models( string $provider ) {
		$models = [];

		switch ( $provider ) {
			case self::PROVIDER_OPENAI:
				$models = [
					// Latest models.
					'gpt-4o'         => 'GPT-4o',
					'gpt-4o-mini'    => 'GPT-4o Mini',
					'gpt-4-turbo'    => 'GPT-4 Turbo',
					'gpt-4'          => 'GPT-4',
					'gpt-3.5-turbo'  => 'GPT-3.5 Turbo',
				];
				break;

			case self::PROVIDER_ANTHROPIC:
				$models = [
					// Latest models.
					'claude-opus-4-5-20251101'      => 'Claude Opus 4.5',
					'claude-sonnet-4-5-20250929'    => 'Claude Sonnet 4.5',
					'claude-haiku-4-5-20251001'     => 'Claude Haiku 4.5',
					// Legacy models.
					'claude-opus-4-1-20250805'      => 'Claude Opus 4.1',
					'claude-sonnet-4-20250514'      => 'Claude Sonnet 4',
					'claude-opus-4-20250514'        => 'Claude Opus 4',
					'claude-3-7-sonnet-20250219'    => 'Claude Sonnet 3.7',
					'claude-3-5-haiku-20241022'     => 'Claude Haiku 3.5',
					'claude-3-opus-20240229'       => 'Claude 3 Opus',
					'claude-3-sonnet-20240229'     => 'Claude 3 Sonnet',
					'claude-3-haiku-20240307'      => 'Claude 3 Haiku',
				];
				break;

			case self::PROVIDER_GOOGLE:
				$models = [
					// Gemini 3 models (latest)
					'gemini-3-pro-preview'         => 'Gemini 3 Pro Preview',
					'gemini-3-flash-preview'       => 'Gemini 3 Flash Preview',

					// Gemini 2.5 models
					'gemini-2.5-pro'               => 'Gemini 2.5 Pro',
					'gemini-2.5-flash'             => 'Gemini 2.5 Flash',
					'gemini-2.5-flash-lite'        => 'Gemini 2.5 Flash-Lite',
					'gemini-2.5-flash-preview-09-2025' => 'Gemini 2.5 Flash Preview Sep 2025',
					'gemini-2.5-flash-lite-preview-09-2025' => 'Gemini 2.5 Flash-Lite Preview Sep 2025',

					// Gemini 2.0 models
					'gemini-2.0-flash-exp'         => 'Gemini 2.0 Flash Experimental',
					'gemini-2.0-flash'             => 'Gemini 2.0 Flash',
					'gemini-2.0-flash-001'         => 'Gemini 2.0 Flash 001',
					'gemini-2.0-flash-lite-001'    => 'Gemini 2.0 Flash-Lite 001',
					'gemini-2.0-flash-lite'        => 'Gemini 2.0 Flash-Lite',
					'gemini-2.0-flash-lite-preview-02-05' => 'Gemini 2.0 Flash-Lite Preview 02-05',
					'gemini-2.0-flash-lite-preview' => 'Gemini 2.0 Flash-Lite Preview',

					// Gemini Experimental models
					'gemini-exp-1206'              => 'Gemini Experimental 1206',

					// Latest stable models
					'gemini-flash-latest'          => 'Gemini Flash Latest',
					'gemini-flash-lite-latest'     => 'Gemini Flash-Lite Latest',
					'gemini-pro-latest'            => 'Gemini Pro Latest',

					// Legacy models (for compatibility)
					'gemini-1.5-pro'               => 'Gemini 1.5 Pro',
					'gemini-1.5-flash'             => 'Gemini 1.5 Flash',
					'gemini-1.5-flash-8b'          => 'Gemini 1.5 Flash (8B)',
					'gemini-1.0-pro'               => 'Gemini 1.0 Pro',
					'gemini-pro'                   => 'Gemini Pro (Legacy)',
				];
				break;
		}

		/**
		 * Filter AI models for a specific provider.
		 *
		 * @since 1.2.0
		 *
		 * @param array  $models   Array of model slugs => labels.
		 * @param string $provider Provider slug.
		 * @return array Filtered models.
		 */
		return apply_filters( 'cforge_ai_models', $models, $provider );
	}

	/**
	 * Get active provider.
	 *
	 * @since 1.2.0
	 *
	 * @return string Provider slug.
	 */
	public static function get_active_provider() {
		$provider = get_option( self::OPTION_PROVIDER, self::DEFAULT_PROVIDER );
		$providers = self::get_providers();

		// Validate provider exists.
		if ( ! isset( $providers[ $provider ] ) ) {
			return self::DEFAULT_PROVIDER;
		}

		return $provider;
	}

	/**
	 * Get stored model for a specific provider.
	 *
	 * @since 1.2.0
	 *
	 * @param string $provider Provider slug.
	 * @return string Model slug.
	 */
	public static function get_stored_model( string $provider ) {
		$option_name = self::OPTION_KEY_PREFIX . $provider . self::OPTION_MODEL_SUFFIX;
		$model = get_option( $option_name );

		// If no model stored, use default for provider.
		if ( ! $model ) {
			switch ( $provider ) {
				case self::PROVIDER_OPENAI:
					return self::DEFAULT_MODEL_OPENAI;
				case self::PROVIDER_ANTHROPIC:
					return self::DEFAULT_MODEL_ANTHROPIC;
				case self::PROVIDER_GOOGLE:
					return self::DEFAULT_MODEL_GOOGLE;
			}
		}

		// Validate model exists for provider.
		$models = self::get_models( $provider );

		if ( ! isset( $models[ $model ] ) ) {
			// Special handling for Google provider - try to fix common typos
			if ( $provider === self::PROVIDER_GOOGLE ) {
				$fixed_model = $model;

				// Fix missing '2.0' prefix for Gemini 2.0 models
				if ( strpos( $model, 'gemini-20-' ) === 0 ) {
					$fixed_model = str_replace( 'gemini-20-', 'gemini-2.0-', $model );
				}
				// Fix other common typos if needed
				elseif ( strpos( $model, 'gemini-25-' ) === 0 ) {
					$fixed_model = str_replace( 'gemini-25-', 'gemini-2.5-', $model );
				}

				// Check if the fixed model exists
				if ( isset( $models[ $fixed_model ] ) ) {
					// Update the stored value with the corrected model
					update_option( self::OPTION_KEY_PREFIX . $provider . self::OPTION_MODEL_SUFFIX, $fixed_model );
					return $fixed_model;
				}
			}

			// Return first model as fallback.
			$model_keys = array_keys( $models );
			return $model_keys[0] ?? '';
		}

		return $model;
	}

	/**
	 * Save model for a specific provider.
	 *
	 * @since 1.2.0
	 *
	 * @param string $provider Provider slug.
	 * @param string $model    Model slug.
	 * @return bool True on success, false on failure.
	 */
	public static function save_provider_model( string $provider, string $model ) {
		$option_name = self::OPTION_KEY_PREFIX . $provider . self::OPTION_MODEL_SUFFIX;

		// Validate provider.
		$providers = self::get_providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			return false;
		}

		// Validate model.
		$models = self::get_models( $provider );
		if ( $provider === self::PROVIDER_GOOGLE ) {
			// Allow any model that starts with 'gemini-' for Google provider
			if ( strpos( $model, 'gemini-' ) !== 0 && ! isset( $models[ $model ] ) ) {
				return false;
			}
		} elseif ( ! isset( $models[ $model ] ) ) {
			return false;
		}

		return update_option( $option_name, $model );
	}

	/**
	 * Get active model.
	 *
	 * @since 1.2.0
	 *
	 * @return string Model slug.
	 */
	public static function get_active_model() {
		$provider = self::get_active_provider();

		// Get stored model for the active provider
		return self::get_stored_model( $provider );
	}

	/**
	 * Get API key for a provider.
	 *
	 * @since 1.2.0
	 *
	 * @param string $provider Provider slug.
	 * @return string|false Decrypted API key or false if not found.
	 */
	public static function get_api_key( string $provider ) {
		$option_name = self::OPTION_KEY_PREFIX . $provider . self::OPTION_KEY_SUFFIX;
		$encrypted_key = get_option( $option_name );

		if ( ! $encrypted_key ) {
			return false;
		}

		return self::decrypt_key( $encrypted_key );
	}

	/**
	 * Save API key for a provider.
	 *
	 * @since 1.2.0
	 *
	 * @param string $provider Provider slug.
	 * @param string $key      API key to save.
	 * @return bool True on success, false on failure.
	 */
	public static function save_api_key( string $provider, string $key ) {
		$option_name = self::OPTION_KEY_PREFIX . $provider . self::OPTION_KEY_SUFFIX;
		$encrypted_key = self::encrypt_key( $key );

		return update_option( $option_name, $encrypted_key );
	}

	/**
	 * Save AI settings.
	 *
	 * @since 1.2.0
	 *
	 * @param array $settings Settings array with provider, model, and api_key.
	 * @return bool True on success, false on failure.
	 */
	public static function save_settings( array $settings ) {
		$provider = isset( $settings['provider'] ) ? sanitize_key( $settings['provider'] ) : '';
		$model    = isset( $settings['model'] ) ? sanitize_key( $settings['model'] ) : '';
		$api_key  = isset( $settings['api_key'] ) ? sanitize_text_field( $settings['api_key'] ) : '';

		// Validate provider.
		$providers = self::get_providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			return false;
		}

		// Validate model.
		$models = self::get_models( $provider );

		// More permissive validation for Google models
		if ( $provider === self::PROVIDER_GOOGLE ) {
			// Allow any model that starts with 'gemini-' for Google provider
			if ( strpos( $model, 'gemini-' ) !== 0 && ! isset( $models[ $model ] ) ) {
				return false;
			}
		}

		// Save provider and model.
		update_option( self::OPTION_PROVIDER, $provider );

		// Save model for the specific provider
		self::save_provider_model( $provider, $model );

		// Save API key if provided.
		if ( ! empty( $api_key ) ) {
			self::save_api_key( $provider, $api_key );
		}

		return true;
	}

	/**
	 * Get all settings.
	 *
	 * @since 1.2.0
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		$provider = self::get_active_provider();
		return [
			'provider'     => $provider,
			'model'        => self::get_active_model(),
			'api_key'      => self::get_api_key( $provider ),
			'is_configured' => self::is_configured(),
		];
	}

	/**
	 * Check if AI is configured.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public static function is_configured() {
		$provider = self::get_active_provider();
		$api_key  = self::get_api_key( $provider );

		return ! empty( $api_key );
	}

	/**
	 * Encrypt API key using AES-256-CBC.
	 *
	 * @since 1.2.0
	 *
	 * @param string $api_key API key to encrypt.
	 * @return string Encrypted key.
	 */
	public static function encrypt_key( string $api_key ) {
		if ( empty( $api_key ) ) {
			return '';
		}

		$key = wp_salt( 'auth' );
		$method = 'AES-256-CBC';
		$iv_length = openssl_cipher_iv_length( $method );

		if ( $iv_length === false ) {
			return '';
		}

		$iv = openssl_random_pseudo_bytes( $iv_length );
		if ( $iv === false ) {
			return '';
		}

		$encrypted = openssl_encrypt( $api_key, $method, $key, 0, $iv );

		if ( $encrypted === false ) {
			return '';
		}

		return base64_encode( $encrypted . '::' . $iv );
	}

	/**
	 * Decrypt API key using AES-256-CBC.
	 *
	 * @since 1.2.0
	 *
	 * @param string $encrypted_key Encrypted key.
	 * @return string|false Decrypted key or false on failure.
	 */
	public static function decrypt_key( string $encrypted_key ) {
		if ( empty( $encrypted_key ) ) {
			return false;
		}

		$key = wp_salt( 'auth' );
		$method = 'AES-256-CBC';

		$data = base64_decode( $encrypted_key );
		if ( $data === false ) {
			return false;
		}

		$parts = explode( '::', $data, 2 );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		list( $encrypted, $iv ) = $parts;

		$decrypted = openssl_decrypt( $encrypted, $method, $key, 0, $iv );

		if ( $decrypted === false ) {
			return false;
		}

		return $decrypted;
	}

	/**
	 * Get masked API key for display.
	 *
	 * @since 1.2.0
	 *
	 * @param string $provider Provider slug.
	 * @return string|false Masked key or false if not found.
	 */
	public static function get_masked_api_key( string $provider ) {
		$key = self::get_api_key( $provider );

		if ( ! $key ) {
			return false;
		}

		$length = strlen( $key );

		// For keys 4 characters or shorter, mask all
		if ( $length <= 4 ) {
			return str_repeat( '*', $length );
		}

		// Show first 2 and last 2 characters, mask the middle
		// Limit total display to 20 characters max
		$total_display_length = min( 20, $length );

		if ( $total_display_length <= 4 ) {
			// If even after limiting to 20, we have 4 or less chars
			return str_repeat( '*', $total_display_length );
		}

		$first_chars = substr( $key, 0, 2 );
		$last_chars = substr( $key, -2 );
		$middle_asterisks = str_repeat( '*', $total_display_length - 4 );

		return $first_chars . $middle_asterisks . $last_chars;
	}
}
