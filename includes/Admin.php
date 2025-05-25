<?php
namespace Fakegen;

use Fakegen\Traits\ContainerTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {
	use ContainerTrait;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

	/**
	 * Register admin menu and enqueue React app.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'FakeGen', 'fakegen' ),
			'FakeGen',
			'manage_options',
			'fakegen',
			[ __CLASS__, 'render_page' ],
			'dashicons-images-alt',
			56
		);
		add_submenu_page(
			'fakegen',
			__( 'Pages/Posts', 'fakegen' ),
			__( 'Pages/Posts', 'fakegen' ),
			'manage_options',
			'fakegen-pages-posts',
			[ __CLASS__, 'render_pages_posts_page' ]
		);
	}

	/**
	 * Render the React app root div.
	 */
	public static function render_page() {
		echo '<div id="fakegen-admin-app"></div>';
	}

	/**
	 * Render the Pages/Posts React app root div.
	 */
	public static function render_pages_posts_page() {
		echo '<div id="fakegen-pages-posts-app"></div>';
	}

	/**
	 * Enqueue React app assets on the plugin pages only.
	 */
	public static function enqueue_assets( $hook ) {
		if ( $hook === 'toplevel_page_fakegen' ) {

		}
		if ( $hook === 'fakegen_page_fakegen-pages-posts' ) {
			wp_enqueue_script(
				'fakegen-admin-app',
				FAKEGEN_ASSETS_URL . 'js/pagesposts.js',
				[ 'wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch' ],
				FAKEGEN_VERSION,
				true
			);
			wp_enqueue_style(
				'fakegen-admin-style',
				FAKEGEN_ASSETS_URL . 'css/pagesposts.css',
				[],
				FAKEGEN_VERSION
			);

            wp_localize_script(
                'fakegen-admin-app',
                'fakegen',
                [
                    'apiUrl'     => esc_url_raw( rest_url( 'fakegen/v1/' ) ),
                    'rest_nonce' => wp_create_nonce( 'wp_rest' ),
                ]
            );
		}
	}
}