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
	const OPTION_MODEL        = 'cforge_ai_model';
	const OPTION_KEY_PREFIX   = 'cforge_ai_';
	const OPTION_KEY_SUFFIX   = '_key';

	// Default values.
	const DEFAULT_PROVIDER          = 'openai';
	const DEFAULT_MODEL_OPENAI      = 'gpt-4';
	const DEFAULT_MODEL_ANTHROPIC   = 'claude-3-opus-20240229';
	const DEFAULT_MODEL_GOOGLE      = 'gemini-pro';

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
					// Latest models.
					'gemini-3-pro-preview'          => 'Gemini 3 Pro',
					'gemini-2.5-pro'                => 'Gemini 2.5 Pro',
					'gemini-2.5-flash'             => 'Gemini 2.5 Flash',
					'gemini-2.5-flash-lite'         => 'Gemini 2.5 Flash-Lite',
					// Legacy models.
					'gemini-2.0-flash'             => 'Gemini 2.0 Flash',
					'gemini-2.0-flash-lite'        => 'Gemini 2.0 Flash-Lite',
					'gemini-pro'                   => 'Gemini Pro',
					'gemini-pro-vision'            => 'Gemini Pro Vision',
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
	 * Get active model.
	 *
	 * @since 1.2.0
	 *
	 * @return string Model slug.
	 */
	public static function get_active_model() {
		$provider = self::get_active_provider();
		$model    = get_option( self::OPTION_MODEL );

		// If no model set, use default for provider.
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
			// Return first model as fallback.
			$model_keys = array_keys( $models );
			return $model_keys[0] ?? '';
		}

		return $model;
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
		if ( ! isset( $models[ $model ] ) ) {
			return false;
		}

		// Save provider and model.
		update_option( self::OPTION_PROVIDER, $provider );
		update_option( self::OPTION_MODEL, $model );

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
		return [
			'provider'     => self::get_active_provider(),
			'model'        => self::get_active_model(),
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
	 * Encrypt API key.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key API key to encrypt.
	 * @return string Encrypted key.
	 */
	public static function encrypt_key( string $key ) {
		// Use WordPress salts for basic encryption.
		$salt = wp_salt();
		return base64_encode( $key . $salt );
	}

	/**
	 * Decrypt API key.
	 *
	 * @since 1.2.0
	 *
	 * @param string $encrypted_key Encrypted key.
	 * @return string|false Decrypted key or false on failure.
	 */
	public static function decrypt_key( string $encrypted_key ) {
		$salt = wp_salt();
		$decoded = base64_decode( $encrypted_key, true );

		if ( false === $decoded ) {
			return false;
		}

		$key = str_replace( $salt, '', $decoded );
		return $key;
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

		// Show first 7 characters and mask the rest.
		$length = strlen( $key );
		if ( $length <= 7 ) {
			return str_repeat( '*', $length );
		}

		return substr( $key, 0, 7 ) . str_repeat( '*', max( 4, $length - 7 ) );
	}
}
