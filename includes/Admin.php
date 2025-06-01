<?php
namespace ContentForge;

use ContentForge\Traits\ContainerTrait;

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
        $parent_slug = 'cforge';
        $capability  = 'manage_options';

		add_menu_page(
			__( 'Content Forge', 'cforge' ),
            __( 'Content Forge', 'cforge' ),
			$capability,
			$parent_slug,
			[ __CLASS__, 'render_pages_posts_page' ],
			'dashicons-images-alt',
			56
		);
		add_submenu_page(
			$parent_slug,
			__( 'Pages/Posts', 'cforge' ),
			__( 'Pages/Posts', 'cforge' ),
			$capability,
			$parent_slug,
			[ __CLASS__, 'render_pages_posts_page' ]
		);
		add_submenu_page(
			$parent_slug,
			__( 'Comments', 'cforge' ),
			__( 'Comments', 'cforge' ),
			$capability,
			'cforge-comments',
			[ __CLASS__, 'render_comments_page' ]
		);
	}

	/**
	 * Render the Pages/Posts React app root div.
	 */
	public static function render_pages_posts_page() {
		echo '<div id="cforge-pages-posts-app"></div>';
	}

	/**
	 * Render the Comments React app root div.
	 */
	public static function render_comments_page() {
		echo '<div id="cforge-comments-app"></div>';
	}

	/**
	 * Enqueue React app assets on the plugin pages only.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_cforge' === $hook ) {
			wp_enqueue_script(
				'cforge-admin-app',
				CFORGE_ASSETS_URL . 'js/pagesPosts.js',
				[ 'wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch' ],
				CFORGE_VERSION,
				true
			);
			wp_enqueue_style(
				'cforge-admin-style',
				CFORGE_ASSETS_URL . 'css/pagesPosts.css',
				[],
				CFORGE_VERSION
			);

            wp_localize_script(
                'cforge-admin-app',
                'cforge',
                [
                    'apiUrl'     => esc_url_raw( rest_url( 'cforge/v1/' ) ),
                    'rest_nonce' => wp_create_nonce( 'wp_rest' ),
                ]
            );
		} elseif ( 'cforge_page_cforge-comments' === $hook ) {
			wp_enqueue_script(
				'cforge-comments-app',
				CFORGE_ASSETS_URL . 'js/comments.js',
				[ 'wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch' ],
				CFORGE_VERSION,
				true
			);
			wp_enqueue_style(
				'cforge-comments-style',
				CFORGE_ASSETS_URL . 'css/comments.css',
				[],
				CFORGE_VERSION
			);

            wp_localize_script(
                'cforge-comments-app',
                'cforge',
                [
                    'apiUrl'     => esc_url_raw( rest_url( 'cforge/v1/' ) ),
                    'rest_nonce' => wp_create_nonce( 'wp_rest' ),
                ]
            );
		}
	}
}