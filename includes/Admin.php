<?php
/**
 * Admin class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge;

use ContentForge\Traits\ContainerTrait;

if (!defined('ABSPATH')) {
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
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('wp_ajax_cforge_telemetry_opt_in', [$this, 'handle_telemetry_opt_in']);
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
	 * Handle telemetry opt-in AJAX request.
	 */
	/**
	 * Handle telemetry opt-in AJAX request.
	 */
	public function handle_telemetry_opt_in()
	{
		// Ensure autoloader is loaded (important for AJAX context)
		if (file_exists(CFORGE_PATH . '/vendor/autoload.php')) {
			require_once CFORGE_PATH . '/vendor/autoload.php';
		}

		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cforge_telemetry')) {
			wp_send_json_error(['message' => __('Invalid security token.', 'content-forge')]);
		}

		// Check user capability
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions.', 'content-forge')]);
		}

		// Opt in to telemetry
		try {
			Telemetry_Manager::opt_in();
		} catch (Exception $e) {
			wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
		}

		wp_send_json_success([
			'message' => __('Telemetry tracking enabled successfully!', 'content-forge'),
			'enabled' => true,
		]);
	}

	/**
	 * Enqueue React app assets on the plugin pages only.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_assets($hook)
	{
		$page_configs = [
			'toplevel_page_cforge' => [
				'script_handle' => 'cforge-admin-app',
				'script_file' => 'pagesPosts.js',
				'style_handle' => 'cforge-admin-style',
				'style_file' => 'pagesPosts.css',
				'localize_data' => [
					'apiUrl' => esc_url_raw(rest_url('cforge/v1/')),
					'rest_nonce' => wp_create_nonce('wp_rest'),
					'ajax_url' => admin_url('admin-ajax.php'),
					'ajax_nonce' => wp_create_nonce('cforge_telemetry'),
					'telemetry_enabled' => Telemetry_Manager::is_tracking_allowed(),
					'pluginVersion' => CFORGE_VERSION,
					'restUrl' => rest_url(),
				],
			],
			'content-forge_page_cforge-comments' => [
				'script_handle' => 'cforge-comments-app',
				'script_file' => 'comments.js',
				'style_handle' => 'cforge-comments-style',
				'style_file' => 'comments.css',
				'localize_data' => [
					'apiUrl' => esc_url_raw(rest_url('cforge/v1/')),
					'rest_nonce' => wp_create_nonce('wp_rest'),
					'post_types' => get_post_types(['public' => true]),
					'ajax_url' => admin_url('admin-ajax.php'),
					'ajax_nonce' => wp_create_nonce('cforge_telemetry'),
					'telemetry_enabled' => Telemetry_Manager::is_tracking_allowed(),
				],
			],
			'content-forge_page_cforge-users' => [
				'script_handle' => 'cforge-users-app',
				'script_file' => 'users.js',
				'style_handle' => 'cforge-users-style',
				'style_file' => 'users.css',
				'localize_data' => [
					'apiUrl' => esc_url_raw(rest_url('cforge/v1/')),
					'rest_nonce' => wp_create_nonce('wp_rest'),
					'roles' => wp_roles()->get_names(),
					'ajax_url' => admin_url('admin-ajax.php'),
					'ajax_nonce' => wp_create_nonce('cforge_telemetry'),
					'telemetry_enabled' => Telemetry_Manager::is_tracking_allowed(),
				],
			],
			'content-forge_page_cforge-taxonomies' => [
				'script_handle' => 'cforge-taxonomies-app',
				'script_file' => 'taxonomy.js',
				'style_handle' => 'cforge-taxonomies-style',
				'style_file' => 'taxonomy.css',
				'localize_data' => [
					'apiUrl' => esc_url_raw(rest_url('cforge/v1/')),
					'rest_nonce' => wp_create_nonce('wp_rest'),
					'ajax_url' => admin_url('admin-ajax.php'),
					'ajax_nonce' => wp_create_nonce('cforge_telemetry'),
					'telemetry_enabled' => Telemetry_Manager::is_tracking_allowed(),
					'taxonomies' => array_filter(
						get_taxonomies(['public' => true], 'objects'),
						function ($taxonomy) {
							// Exclude internal WordPress taxonomies that shouldn't have custom terms
							$excluded = ['post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area'];
							return !in_array($taxonomy->name, $excluded, true);
						}
					),
				],
			],
		];

		if (isset($page_configs[$hook])) {
			self::enqueue_page_assets($page_configs[$hook]);
		}
	}

	/**
	 * Helper method to enqueue scripts and styles for a specific page.
	 *
	 * @param array $config Configuration array with script/style handles and files.
	 */
	private static function enqueue_page_assets($config)
	{
		// Enqueue script
		wp_enqueue_script(
			$config['script_handle'],
			CFORGE_ASSETS_URL . 'js/' . $config['script_file'],
			['wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch'],
			CFORGE_VERSION,
			true
		);

		// Enqueue style
		wp_enqueue_style(
			$config['style_handle'],
			CFORGE_ASSETS_URL . 'css/' . $config['style_file'],
			[],
			CFORGE_VERSION
		);

		// Localize script with data
		wp_localize_script(
			$config['script_handle'],
			'cforge',
			$config['localize_data']
		);
	}
}
