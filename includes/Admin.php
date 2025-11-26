<?php
/**
 * Admin class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge;

use ContentForge\Traits\ContainerTrait;

if ( !defined( 'ABSPATH' ) )
{
	exit;
}

class Admin
{

	use ContainerTrait;

	/**
	 * Constructor for Admin class.
	 */
	public function __construct()
	{
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register admin menu and enqueue React app.
	 */
	public static function register_menu()
	{
		$parent_slug = 'cforge';
		$capability  = 'manage_options';

		add_menu_page(
			__( 'Content Forge', 'content-forge' ),
			__( 'Content Forge', 'content-forge' ),
			$capability,
			$parent_slug,
			[ __CLASS__, 'render_pages_posts_page' ],
			'dashicons-images-alt',
			56
		);
		add_submenu_page(
			$parent_slug,
			__( 'Pages/Posts', 'content-forge' ),
			__( 'Pages/Posts', 'content-forge' ),
			$capability,
			$parent_slug,
			[ __CLASS__, 'render_pages_posts_page' ]
		);
		add_submenu_page(
			$parent_slug,
			__( 'Comments', 'content-forge' ),
			__( 'Comments', 'content-forge' ),
			$capability,
			'cforge-comments',
			[ __CLASS__, 'render_comments_page' ]
		);
		add_submenu_page(
			$parent_slug,
			__( 'Users', 'content-forge' ),
			__( 'Users', 'content-forge' ),
			$capability,
			'cforge-users',
			[ __CLASS__, 'render_users_page' ]
		);
		add_submenu_page(
			$parent_slug,
			__( 'Taxonomies', 'content-forge' ),
			__( 'Taxonomies', 'content-forge' ),
			$capability,
			'cforge-taxonomies',
			[ __CLASS__, 'render_taxonomies_page' ]
		);
	}

	/**
	 * Render the Pages/Posts React app root div.
	 */
	public static function render_pages_posts_page()
	{
		echo '<div id="cforge-pages-posts-app" style="margin-left: -20px"></div>';
	}

	/**
	 * Render the Comments React app root div.
	 */
	public static function render_comments_page()
	{
		echo '<div id="cforge-comments-app" style="margin-left: -20px"></div>';
	}

	/**
	 * Render the Users React app root div.
	 */
	public static function render_users_page()
	{
		echo '<div id="cforge-users-app" style="margin-left: -20px"></div>';
	}

	/**
	 * Render the Taxonomies React app root div.
	 */
	public static function render_taxonomies_page()
	{
		echo '<div id="cforge-taxonomies-app" style="margin-left: -20px"></div>';
	}

	/**
	 * Enqueue React app assets on the plugin pages only.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_assets( $hook )
	{
		if ( 'toplevel_page_cforge' === $hook )
		{
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
		} elseif ( 'content-forge_page_cforge-comments' === $hook )
		{
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
					'post_types' => get_post_types( [ 'public' => true ], ),
				]
			);
		} elseif ( 'content-forge_page_cforge-users' === $hook )
		{
			wp_enqueue_script(
				'cforge-users-app',
				CFORGE_ASSETS_URL . 'js/users.js',
				[ 'wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch' ],
				CFORGE_VERSION,
				true
			);
			wp_enqueue_style(
				'cforge-users-style',
				CFORGE_ASSETS_URL . 'css/users.css',
				[],
				CFORGE_VERSION
			);

			wp_localize_script(
				'cforge-users-app',
				'cforge',
				[
					'apiUrl'     => esc_url_raw( rest_url( 'cforge/v1/' ) ),
					'rest_nonce' => wp_create_nonce( 'wp_rest' ),
					'roles'      => wp_roles()->get_names(),
				]
			);
		} elseif ( 'content-forge_page_cforge-taxonomies' === $hook )
		{
			wp_enqueue_script(
				'cforge-taxonomies-app',
				CFORGE_ASSETS_URL . 'js/taxonomy.js',
				[ 'wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch' ],
				CFORGE_VERSION,
				true
			);
			wp_enqueue_style(
				'cforge-taxonomies-style',
				CFORGE_ASSETS_URL . 'css/taxonomy.css',
				[],
				CFORGE_VERSION
			);

			wp_localize_script(
				'cforge-taxonomies-app',
				'cforge',
				[
					'apiUrl'     => esc_url_raw( rest_url( 'cforge/v1/' ) ),
					'rest_nonce' => wp_create_nonce( 'wp_rest' ),
					'taxonomies' => array_filter(
						get_taxonomies( [ 'public' => true ], 'objects' ),
						function ( $taxonomy )
						{
							// Exclude internal WordPress taxonomies that shouldn't have custom terms
							$excluded = [ 'post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area' ];
							return !in_array( $taxonomy->name, $excluded, true );
						}
					),
				]
			);
		}
	}
}
