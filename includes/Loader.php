<?php
/**
 * Loader class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge;

use ContentForge\Traits\ContainerTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loader {


	use ContainerTrait;

	/**
	 * Registered generator instances.
	 *
	 * @var array
	 */
	protected $generators = [];

	/**
	 * Load all generator classes.
	 */
	protected function load_generators()
	{
		$generator_dir = CFORGE_INCLUDES_PATH . 'Generator/';
		if ( is_dir( $generator_dir ) ) {
			foreach ( glob( $generator_dir . '*.php' ) as $file ) {
				require_once $file;
			}
		}
	}

	/**
	 * Load components for Content Forge plugin.
	 *
	 * @return void
	 */
	public function load()
	{
		$this->load_generators();

		// Load AI Settings Manager
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Settings/AI_Settings_Manager.php' ) ) {
			require_once CFORGE_INCLUDES_PATH . 'Settings/AI_Settings_Manager.php';
		}

		// Load Content Type Data
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Content/Content_Type_Data.php' ) ) {
			require_once CFORGE_INCLUDES_PATH . 'Content/Content_Type_Data.php';
		}

		// Load AI Provider Base
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_Base.php' ) ) {
			require_once CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_Base.php';
		}

		// Load AI Provider implementations
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_OpenAI.php' ) ) {
			require_once CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_OpenAI.php';
		}
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_Anthropic.php' ) ) {
			require_once CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_Anthropic.php';
		}
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_Google.php' ) ) {
			require_once CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_Google.php';
		}

		// Load AI Content Generator
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Generator/AI_Content_Generator.php' ) ) {
			require_once CFORGE_INCLUDES_PATH . 'Generator/AI_Content_Generator.php';
		}

		// Load AI Scheduled Generator
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Generator/AI_Scheduled_Generator.php' ) ) {
			require_once CFORGE_INCLUDES_PATH . 'Generator/AI_Scheduled_Generator.php';
		}

		$this->container['api'] = new Api();

		// Load and initialize telemetry tracking.
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Telemetry_Manager.php' ) ) {
			require_once CFORGE_INCLUDES_PATH . 'Telemetry_Manager.php';
		}
		Telemetry_Manager::init();

		if ( is_admin() ) {
			$this->container['admin'] = new Admin();
		}

		if ( class_exists( 'WooCommerce', false ) ) {
			\ContentForge\Integration\WooCommerce_Product_Content::register();
		}

		if ( post_type_exists( 'download' ) ) {
			\ContentForge\Integration\EDD_Download_Content::register();
		}

		if ( post_type_exists( 'tribe_events' ) ) {
			\ContentForge\Integration\TEC_Event_Content::register();
		}

		add_action( 'init', [ __CLASS__, 'register_wpuf_subscription_integration' ], 20 );
	}

	/**
	 * Register WPUF subscription content filters on a late init so wpuf_subscription post type exists.
	 */
	public static function register_wpuf_subscription_integration() {
		if ( ! post_type_exists( 'wpuf_subscription' ) ) {
			return;
		}
		\ContentForge\Integration\WPUF_Subscription_Content::register();
	}
}
