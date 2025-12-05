<?php
/**
 * Plugin Name: Content Forge
 * Description: Generate fake/dummy posts, pages, users, comments for development/testing.
 * Version: 1.1.0
 * Author: Sapayth H.
 * Author URI: https://sapayth.com
 * Text Domain: content-forge
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPLv2 or later
 *
 * @package ContentForge
 */

// Exit if accessed directly.
use ContentForge\Loader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

final class ContentForge {
    const VERSION = '1.1.0';

    /**
     * Class constructor.
     */
    private function __construct() {
        $this->define_constants();
        register_activation_hook( __FILE__, [ 'ContentForge\\Activator', 'activate' ] );
        $loader = new Loader();
        add_action( 'init', [ $loader, 'load' ] );
        // load the general functions
        require_once CFORGE_INCLUDES_PATH . 'functions/general.php';
    }

    /**
     * Define plugin constants.
     */
    public function define_constants() {
        $this->define( 'CFORGE_VERSION', self::VERSION );
        $this->define( 'CFORGE_PATH', plugin_dir_path( __FILE__ ) );
        $this->define( 'CFORGE_URL', plugin_dir_url( __FILE__ ) );
        $this->define( 'CFORGE_BASENAME', plugin_basename( __FILE__ ) );
        $this->define( 'CFORGE_ASSETS_URL', CFORGE_URL . 'assets/' );
        $this->define( 'CFORGE_INCLUDES_PATH', CFORGE_PATH . 'includes/' );
        $this->define( 'CFORGE_TEXT_DOMAIN', 'content-forge' );
        $this->define( 'CFORGE_DBNAME', 'cforge' );
    }

    /**
     * Define constant if not already set.
     *
     * @param string $constant_name Constant name.
     * @param mixed  $value         Constant value.
     */
    private function define( $constant_name, $value ) {
        if ( ! defined( $constant_name ) ) {
            define( $constant_name, $value );
        }
    }

    /**
     * Initialize a singleton instance.
     *
     * @return ContentForge
     */
    public static function init() {
        static $instance = false;
        if ( ! $instance ) {
            $instance = new self();
        }
        return $instance;
    }
}

// start the plugin
ContentForge::init();
