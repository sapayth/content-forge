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

if ( ! defined( 'ABSPATH' ) ) {
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
class Telemetry_Manager {
    /**
     * Initialize telemetry client.
     *
     * Configures and initializes the wp-telemetry library with plugin-specific settings.
     * The telemetry will send weekly reports to the configured server URL.
     *
     * @return void
     */
    public static function init() {
        // Only initialize if the telemetry library is available.
        if ( ! class_exists( 'BitApps\\WPTelemetry\\Telemetry\\Telemetry' ) ) {
            return;
        }
        // Configure telemetry settings.
        TelemetryConfig::setTitle( __( 'Content Forge', 'content-forge' ) );
        TelemetryConfig::setSlug( 'content-forge' );
        TelemetryConfig::setPrefix( 'cforge_' );
        TelemetryConfig::setVersion( CFORGE_VERSION );
        // Set server base URL for telemetry.
        $server_url = apply_filters( 'cforge_telemetry_server_url', 'https://feedio.sapayth.com/api/' );
        if ( ! empty( $server_url ) ) {
            TelemetryConfig::setServerBaseUrl( $server_url );
        }
        // Set optional terms and policy URLs.
        $terms_url = apply_filters( 'cforge_telemetry_terms_url', '' );
        if ( ! empty( $terms_url ) ) {
            TelemetryConfig::setTermsUrl( $terms_url );
        }
        $policy_url = apply_filters( 'cforge_telemetry_policy_url', 'https://feedio.sapayth.com/policy' );
        if ( ! empty( $policy_url ) ) {
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
        // Override the admin notice view to customize the "Data We Collect" link
        // Remove the default admin notice and add our custom one
        // Use admin_init hook with later priority to ensure telemetry is initialized first
        add_action( 'admin_init', [ __CLASS__, 'remove_default_telemetry_notice' ], 20 );
        add_action( 'admin_notices', [ __CLASS__, 'custom_telemetry_notice' ], 10 );
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
    public static function add_plugin_name_to_data( $data ) {
        // Use the plugin slug as plugin_name (e.g., 'content-forge').
        // This makes the data more readable than using the prefix (e.g., 'cforge_').
        $data['plugin_name'] = TelemetryConfig::getSlug();
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
    public static function add_inactive_plugins_to_data( $data ) {
        // Only add if we have access to WordPress functions
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }
        $all_plugins         = get_plugins();
        $active_plugins_keys = get_option( 'active_plugins', [] );
        $inactive_plugins    = [];
        foreach ( $all_plugins as $plugin_key => $plugin_data ) {
            // Skip if this plugin is active
            if ( in_array( $plugin_key, $active_plugins_keys, true ) ) {
                continue;
            }
            // Format inactive plugin data
            $inactive_plugins[ $plugin_key ] = [
                'name'    => wp_strip_all_tags( $plugin_data['Name'] ?? '' ),
                'version' => wp_strip_all_tags( $plugin_data['Version'] ?? '' ),
                'author'  => wp_strip_all_tags( $plugin_data['Author'] ?? '' ),
            ];
        }
        // Add inactive plugins list to data
        if ( ! empty( $inactive_plugins ) ) {
            $data['inactive_plugins_list'] = $inactive_plugins;
        }
        return $data;
    }

    /**
     * Log telemetry send attempt.
     * Hooked into cforge_tracking_opt_in action.
     *
     * @return void
     */
    public static function log_telemetry_send() {
        // Hook fired - telemetry opt-in completed
    }

    /**
     * Opt in to telemetry tracking.
     *
     * Allows users to opt in to telemetry tracking programmatically.
     *
     * @return void
     */
    public static function opt_in() {
        // Ensure autoloader is loaded
        if ( file_exists( CFORGE_PATH . '/vendor/autoload.php' ) ) {
            require_once CFORGE_PATH . '/vendor/autoload.php';
        }
        // Check if class exists with full namespace
        $telemetry_class = 'BitApps\\WPTelemetry\\Telemetry\\Telemetry';
        // Try autoloading by attempting to use the class
        if ( ! class_exists( $telemetry_class ) ) {
            // Try to trigger autoloader
            try {
                // Try to use the class to trigger autoload
                class_exists( $telemetry_class, true );
            } catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                // Class not found, return early.
            }
        }
        // Final check
        if ( ! class_exists( $telemetry_class ) ) {
            return;
        }
        // Clear last sent timestamp to force immediate send (bypass weekly check)
        delete_option( 'cforge_tracking_last_sended_at' );
        // Call trackingOptIn
        Telemetry::report()->trackingOptIn();
        // Use reflection to call sendTrackingReport directly to bypass weekly check
        try {
            $reflection = new \ReflectionClass( Telemetry::report() );
            $method     = $reflection->getMethod( 'sendTrackingReport' );
            $method->setAccessible( true );
            $method->invoke( Telemetry::report() );
        } catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            // Error calling sendTrackingReport.
        }
    }

    /**
     * Opt out of telemetry tracking.
     *
     * Allows users to opt out of telemetry tracking programmatically.
     *
     * @return void
     */
    public static function opt_out() {
        if ( class_exists( 'BitApps\\WPTelemetry\\Telemetry' ) ) {
            Telemetry::report()->trackingOptOut();
        }
    }

    /**
     * Check if telemetry tracking is allowed.
     *
     * @return bool True if tracking is enabled, false otherwise.
     */
    public static function is_tracking_allowed() {
        if ( class_exists( 'BitApps\\WPTelemetry\\Telemetry' ) ) {
            return Telemetry::report()->isTrackingAllowed();
        }
        return false;
    }

    /**
     * Remove the default wp-telemetry admin notice.
     *
     * This is called on admin_init with priority 20 to ensure the telemetry
     * library has already added its admin_notices action.
     *
     * @return void
     */
    public static function remove_default_telemetry_notice() {
        if ( class_exists( 'BitApps\\WPTelemetry\\Telemetry\\Telemetry' ) ) {
            remove_action( 'admin_notices', [ Telemetry::report(), 'adminNotice' ] );
        }
    }

    /**
     * Custom telemetry admin notice with expandable "Data We Collect" section.
     *
     * This overrides the default wp-telemetry notice to show an expandable section
     * that displays what data is collected when "what we collect" is clicked.
     *
     * @return void
     */
    public static function custom_telemetry_notice() {
        // Only show if tracking notice hasn't been dismissed and tracking is not allowed
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Check if notice should be shown (same logic as wp-telemetry)
        $notice_dismissed = get_option( TelemetryConfig::getPrefix() . 'tracking_notice_dismissed' );
        $tracking_allowed = get_option( TelemetryConfig::getPrefix() . 'allow_tracking' );
        if ( $notice_dismissed || $tracking_allowed ) {
            return;
        }
        $opt_in_url  = wp_nonce_url( add_query_arg( TelemetryConfig::getPrefix() . 'tracking_opt_in', 'true' ), '_wpnonce' );
        $opt_out_url = wp_nonce_url( add_query_arg( TelemetryConfig::getPrefix() . 'tracking_opt_out', 'true' ), '_wpnonce' );
        $plugin_name = TelemetryConfig::getTitle();
        
        // Define what data we collect (comma-separated list)
        $collected_data = [
            __( 'WordPress version', 'content-forge' ),
            __( 'PHP version', 'content-forge' ),
            __( 'Plugin version', 'content-forge' ),
            __( 'Active plugins list', 'content-forge' ),
            __( 'Inactive plugins list', 'content-forge' ),
            __( 'Active theme name', 'content-forge' ),
            __( 'Site URL', 'content-forge' ),
            __( 'Plugin name', 'content-forge' ),
        ];
        $collected_data_string = implode( ', ', $collected_data );
        
        $notice_text = sprintf(
            // Translators: %1$s is the plugin name, %2$s is the "what we collect" clickable text.
            __( 'Want to help make %1$s even more awesome? Allow %1$s to collect diagnostic data and usage information. (%2$s)', 'content-forge' ),
            '<strong>' . esc_html( $plugin_name ) . '</strong>',
            '<a href="#" class="cforge-what-we-collect-toggle" style="cursor: pointer; text-decoration: underline;">' . esc_html__( 'what we collect', 'content-forge' ) . '</a>'
        );
        ?>
        <div class="updated cforge-telemetry-notice">
            <p><?php echo wp_kses_post( $notice_text ); ?></p>
            <div class="cforge-collected-data" style="display: none;">
                <p style="margin: 5px 0 0 0;"><?php echo esc_html( $collected_data_string ); ?></p>
            </div>
            <p class="submit">
                &nbsp;<a href="<?php echo esc_url( $opt_in_url ); ?>"
                    class="button-primary button-large"><?php esc_html_e( 'Allow', 'content-forge' ); ?></a>&nbsp;
                <a href="<?php echo esc_url( $opt_out_url ); ?>"
                    class="button-secondary button-large"><?php esc_html_e( 'No thanks', 'content-forge' ); ?></a>
            </p>
        </div>
        <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                var toggle = document.querySelector('.cforge-what-we-collect-toggle');
                var dataSection = document.querySelector('.cforge-collected-data');
                
                if (toggle && dataSection) {
                    toggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (dataSection.style.display === 'none') {
                            dataSection.style.display = 'block';
                        } else {
                            dataSection.style.display = 'none';
                        }
                    });
                }
            });
        })();
        </script>
        <?php
    }
}
