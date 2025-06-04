<?php
namespace ContentForge;

use ContentForge\Traits\ContainerTrait;

if (!defined('ABSPATH')) {
	exit;
}

class Admin
{
	use ContainerTrait;

	public function __construct()
	{
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
	}

	/**
	 * Register admin menu and enqueue React app.
	 */
	public static function register_menu()
	{
		$parent_slug = 'cforge';
		$capability = 'manage_options';

		add_menu_page(
			__('Content Forge', 'content-forge'),
			__('Content Forge', 'content-forge'),
			$capability,
			$parent_slug,
			[__CLASS__, 'render_pages_posts_page'],
			'dashicons-images-alt',
			56
		);
		add_submenu_page(
			$parent_slug,
			__('Pages/Posts', 'content-forge'),
			__('Pages/Posts', 'content-forge'),
			$capability,
			$parent_slug,
			[__CLASS__, 'render_pages_posts_page']
		);
		add_submenu_page(
			$parent_slug,
			__('Comments', 'content-forge'),
			__('Comments', 'content-forge'),
			$capability,
			'cforge-comments',
			[__CLASS__, 'render_comments_page']
		);
		add_submenu_page(
			$parent_slug,
			__('Users', 'content-forge'),
			__('Users', 'content-forge'),
			$capability,
			'cforge-users',
			[__CLASS__, 'render_users_page']
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
	 * Enqueue React app assets on the plugin pages only.
	 */
	public static function enqueue_assets($hook)
	{
		if ('toplevel_page_cforge' === $hook) {
			wp_enqueue_script(
				'cforge-admin-app',
				CFORGE_ASSETS_URL . 'js/pagesPosts.js',
				['wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch'],
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
					'apiUrl' => esc_url_raw(rest_url('cforge/v1/')),
					'rest_nonce' => wp_create_nonce('wp_rest'),
				]
			);
		} elseif ('content-forge_page_cforge-comments' === $hook) {
			wp_enqueue_script(
				'cforge-comments-app',
				CFORGE_ASSETS_URL . 'js/comments.js',
				['wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch'],
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
					'apiUrl' => esc_url_raw(rest_url('cforge/v1/')),
					'rest_nonce' => wp_create_nonce('wp_rest'),
					'post_types' => get_post_types(['public' => true], ),
				]
			);
		} elseif ('content-forge_page_cforge-users' === $hook) {
			wp_enqueue_script(
				'cforge-users-app',
				CFORGE_ASSETS_URL . 'js/users.js',
				['wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch'],
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
					'apiUrl' => esc_url_raw(rest_url('cforge/v1/')),
					'rest_nonce' => wp_create_nonce('wp_rest'),
					'roles' => wp_roles()->get_names(),
				]
			);
		}
	}
}