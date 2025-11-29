<?php
/**
 * Telemetry class for Content Forge plugin.
 *
 * Handles telemetry initialization and configuration.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge;

use BitApps\WPTelemetry\Telemetry\Telemetry;
use BitApps\WPTelemetry\Telemetry\TelemetryConfig;

if ( !defined( 'ABSPATH' ) )
{
	exit;
}

/**
 * Telemetry class for initializing and managing telemetry tracking.
 *
 * This class handles the initialization and configuration of the wp-telemetry library
 * for the Content Forge plugin. It sends weekly reports about plugin usage to a
 * configured server and provides deactivation feedback surveys.
 *
 * Available filter hooks for customization:
 * - `cforge_telemetry_additional_data` - Add additional data to tracking reports
 * - `cforge_telemetry_data` - Modify telemetry data before sending
 * - `cforge_deactivate_reasons` - Add custom deactivation reasons to feedback survey
 * - `cforge_telemetry_server_url` - Override the telemetry server URL
 * - `cforge_telemetry_terms_url` - Set terms of service URL
 * - `cforge_telemetry_policy_url` - Set privacy policy URL
 */
class Telemetry_Manager
{
	/**
	 * Initialize telemetry client.
	 *
	 * Configures and initializes the wp-telemetry library with plugin-specific settings.
	 * The telemetry will send weekly reports to the configured server URL.
	 *
	 * @return void
	 */
	public static function init()
	{
		// Only initialize if the telemetry library is available.
		if ( !class_exists( 'BitApps\\WPTelemetry\\Telemetry\\Telemetry' ) )
		{
			return;
		}

		// Configure telemetry settings.
		TelemetryConfig::setTitle( __( 'Content Forge', 'content-forge' ) );
		TelemetryConfig::setSlug( 'content-forge' );
		TelemetryConfig::setPrefix( 'cforge_' );
		TelemetryConfig::setVersion( CFORGE_VERSION );

		// Set server base URL for telemetry.
		$server_url = apply_filters( 'cforge_telemetry_server_url', 'http://feedio.sapayth.com/api/' );
		if ( !empty( $server_url ) )
		{
			TelemetryConfig::setServerBaseUrl( $server_url );
		}

		// Set optional terms and policy URLs.
		$terms_url = apply_filters( 'cforge_telemetry_terms_url', '' );
		if ( !empty( $terms_url ) )
		{
			TelemetryConfig::setTermsUrl( $terms_url );
		}

		$policy_url = apply_filters( 'cforge_telemetry_policy_url', '' );
		if ( !empty( $policy_url ) )
		{
			TelemetryConfig::setPolicyUrl( $policy_url );
		}

		// Add plugin_name to telemetry data to match feedio API requirements.
		add_filter( 'cforge_telemetry_data', [ __CLASS__, 'add_plugin_name_to_data' ], 10, 1 );

		// Add inactive plugins list to telemetry data.
		add_filter( 'cforge_telemetry_data', [ __CLASS__, 'add_inactive_plugins_to_data' ], 10, 1 );

		// Add logging hook to track when telemetry data is being sent
		add_action( 'cforge_tracking_opt_in', [ __CLASS__, 'log_telemetry_send' ], 10, 0 );

		// Initialize telemetry tracking.
		Telemetry::report()->addPluginData()->init();

		// Initialize deactivation feedback survey.
		Telemetry::feedback()->init();
	}

	/**
	 * Add plugin_name to telemetry data.
	 *
	 * This ensures the telemetry data includes plugin_name which is preferred
	 * by the feedio API. The plugin_name is set to the plugin slug for consistency.
	 * The API will also accept plugin_slug as a fallback.
	 *
	 * @param array $data The telemetry data array.
	 * @return array Modified data array with plugin_name added.
	 */
	public static function add_plugin_name_to_data( $data )
	{
		// Use the plugin slug as plugin_name (e.g., 'content-forge').
		// This makes the data more readable than using the prefix (e.g., 'cforge_').
		$data[ 'plugin_name' ] = TelemetryConfig::getSlug();
		return $data;
	}

	/**
	 * Add inactive plugins list to telemetry data.
	 *
	 * The wp-telemetry library only sends active plugins by default.
	 * This filter adds inactive plugins to the data for complete plugin tracking.
	 *
	 * @param array $data The telemetry data array.
	 * @return array Modified data array with inactive_plugins_list added.
	 */
	public static function add_inactive_plugins_to_data( $data )
	{
		// Only add if we have access to WordPress functions
		if ( !function_exists( 'get_plugins' ) )
		{
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$allPlugins        = get_plugins();
		$activePluginsKeys = get_option( 'active_plugins', [] );
		$inactivePlugins   = [];

		foreach ( $allPlugins as $pluginKey => $pluginData )
		{
			// Skip if this plugin is active
			if ( in_array( $pluginKey, $activePluginsKeys, true ) )
			{
				continue;
			}

			// Format inactive plugin data
			$inactivePlugins[ $pluginKey ] = [
				'name'    => wp_strip_all_tags( $pluginData[ 'Name' ] ?? '' ),
				'version' => wp_strip_all_tags( $pluginData[ 'Version' ] ?? '' ),
				'author'  => wp_strip_all_tags( $pluginData[ 'Author' ] ?? '' ),
			];
		}

		// Add inactive plugins list to data
		if ( !empty( $inactivePlugins ) )
		{
			$data[ 'inactive_plugins_list' ] = $inactivePlugins;
		}

		return $data;
	}

	/**
	 * Log telemetry send attempt.
	 * Hooked into cforge_tracking_opt_in action.
	 *
	 * @return void
	 */
	public static function log_telemetry_send()
	{
		// Hook fired - telemetry opt-in completed
	}

	/**
	 * Opt in to telemetry tracking.
	 *
	 * Allows users to opt in to telemetry tracking programmatically.
	 *
	 * @return void
	 */
	public static function opt_in()
	{
		// Ensure autoloader is loaded
		if ( file_exists( CFORGE_PATH . '/vendor/autoload.php' ) )
		{
			require_once CFORGE_PATH . '/vendor/autoload.php';
		}

		// Check if class exists with full namespace
		$telemetryClass = 'BitApps\\WPTelemetry\\Telemetry\\Telemetry';
		
		// Try autoloading by attempting to use the class
		if ( !class_exists( $telemetryClass ) )
		{
			// Try to trigger autoloader
			try
			{
				// Try to use the class to trigger autoload
				class_exists( $telemetryClass, true );
			} catch ( \Exception $e )
			{
				// Class not found, return early
			}
		}

		// Final check
		if ( !class_exists( $telemetryClass ) )
		{
			return;
		}

		// Clear last sent timestamp to force immediate send (bypass weekly check)
		delete_option( 'cforge_tracking_last_sended_at' );

		// Call trackingOptIn
		Telemetry::report()->trackingOptIn();

		// Use reflection to call sendTrackingReport directly to bypass weekly check
		try
		{
			$reflection = new \ReflectionClass( Telemetry::report() );
			$method     = $reflection->getMethod( 'sendTrackingReport' );
			$method->setAccessible( true );
			$method->invoke( Telemetry::report() );
		} catch ( \Exception $e )
		{
			// Error calling sendTrackingReport
		}
	}

	/**
	 * Opt out of telemetry tracking.
	 *
	 * Allows users to opt out of telemetry tracking programmatically.
	 *
	 * @return void
	 */
	public static function opt_out()
	{
		if ( class_exists( 'BitApps\\WPTelemetry\\Telemetry' ) )
		{
			Telemetry::report()->trackingOptOut();
		}
	}

	/**
	 * Check if telemetry tracking is allowed.
	 *
	 * @return bool True if tracking is enabled, false otherwise.
	 */
	public static function is_tracking_allowed()
	{
		if ( class_exists( 'BitApps\\WPTelemetry\\Telemetry' ) )
		{
			return Telemetry::report()->isTrackingAllowed();
		}
		return false;
	}
}

