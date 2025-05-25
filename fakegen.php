<?php
/**
 * Plugin Name: FakeGen
 * Description: Generate fake/dummy posts, pages, users, taxonomies, and comments for development/testing.
 * Version: 1.0.0
 * Author: Sapayth Hossain
 * Text Domain: fakegen
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPLv2 or later
 *
 * @package Fakegen
 */

// Exit if accessed directly.
use Fakegen\Loader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

final class FakeGen {
    const VERSION = '1.0.0';

    /*
    * class constructor
    */
    private function __construct() {
        $this->define_constants();

        register_activation_hook( __FILE__, [ 'Fakegen\\Activator', 'activate' ] );

        $loader = new Loader();

        add_action( 'init', [ $loader, 'load' ] );
    }

    /**
     * constants
     */
    public function define_constants() {
        $this->define( 'FAKEGEN_VERSION', self::VERSION );
        $this->define( 'FAKEGEN_PATH', __DIR__ );
        $this->define( 'FAKEGEN_VERSION', '1.0.0' );
        $this->define( 'FAKEGEN_PATH', plugin_dir_path( __FILE__ ) );
        $this->define( 'FAKEGEN_URL', plugin_dir_url( __FILE__ ) );
        $this->define( 'FAKEGEN_BASENAME', plugin_basename( __FILE__ ) );
        $this->define( 'FAKEGEN_ASSETS_URL', FAKEGEN_URL . 'assets/' );
        $this->define( 'FAKEGEN_INCLUDES_PATH', FAKEGEN_PATH . 'includes/' );
        $this->define( 'FAKEGEN_TEXT_DOMAIN', 'fakegen' );
        $this->define( 'FAKEGEN_DBNAME', 'fakegen' );
    }

    /**
     * Define constant if not already set.
     *
     * @param string    $name  Constant name.
     * @param mixed     $value Constant value.
     */
    private function define( $const, $value ) {
        if( ! defined( $const ) ) {
            define( $const, $value );
        }
    }

    /*
    * initializes a singleton instance
    */
    public static function init() {
        static $instance = false;

        if( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }
}

/**
 * initialize the main plugin
 */
if ( ! function_exists( 'fakegen' ) ) {
    function fakegen() {
        return FakeGen::init();
    }
}

// start the plugin
fakegen();