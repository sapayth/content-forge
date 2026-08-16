<?php
/**
 * DESCRIPTION: REST endpoints backing the Content Forge admin Dashboard page.
 * DESCRIPTION: Serves generated-content counts, Autopilot health and promoted-plugin state.
 *
 * @package ContentForge
 * @since   1.7.0
 */

namespace ContentForge\Api;

use ContentForge\Autopilot\Dashboard_Widget;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard controller.
 *
 * @since 1.7.0
 */
class Dashboard extends CForge_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $base = 'dashboard';

	/**
	 * Plugins offered for one-click install on the dashboard.
	 *
	 * Slugs only — the download URL always comes from plugins_api(), so installs
	 * are served by WordPress.org and never by a third-party host.
	 *
	 * @var array<int, string>
	 */
	const PROMOTED_SLUGS = [ 'nemtly-booking', 'nemtly-jobs' ];

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handle_stats' ],
					'permission_callback' => [ $this, 'permission_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/install',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_install' ],
					'permission_callback' => [ $this, 'install_permission_check' ],
					'args'                => [
						'slug' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
			]
		);
	}

	/**
	 * Installing and activating plugins needs more than the read capability.
	 *
	 * @return bool
	 */
	public function install_permission_check() {
		return current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' );
	}

	/**
	 * Everything the dashboard renders, in one request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_stats() {
		$counts = $this->get_counts();

		return new \WP_REST_Response(
			[
				'counts'    => $counts,
				'total'     => array_sum( $counts ),
				'autopilot' => ( new Dashboard_Widget() )->get_status(),
				'plugins'   => $this->get_promoted_plugins(),
			],
			200
		);
	}

	/**
	 * Install (if needed) and activate one of the promoted plugins.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_install( $request ) {
		$slug = $request->get_param( 'slug' );

		if ( ! in_array( $slug, self::PROMOTED_SLUGS, true ) ) {
			return new \WP_Error(
				'cforge_plugin_not_allowed',
				__( 'That plugin cannot be installed from here.', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		// Loaded by wp-admin/admin.php, not by class-wp-upgrader.php — and this is
		// a REST request, so nothing else has pulled the skin in.
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

		$plugin_file = $this->get_plugin_file( $slug );

		if ( '' === $plugin_file ) {
			$api = plugins_api(
				'plugin_information',
				[
					'slug'   => $slug,
					'fields' => [ 'sections' => false ],
				]
			);

			if ( is_wp_error( $api ) ) {
				return new \WP_Error(
					'cforge_plugins_api_failed',
					$api->get_error_message(),
					[ 'status' => 502 ]
				);
			}

			$upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
			$result   = $upgrader->install( $api->download_link );

			if ( is_wp_error( $result ) ) {
				return new \WP_Error( 'cforge_install_failed', $result->get_error_message(), [ 'status' => 500 ] );
			}

			if ( true !== $result ) {
				return new \WP_Error(
					'cforge_install_failed',
					__( 'The plugin could not be installed. Please install it from the Plugins screen.', 'content-forge' ),
					[ 'status' => 500 ]
				);
			}

			$plugin_file = $this->get_plugin_file( $slug );
		}

		if ( '' === $plugin_file ) {
			return new \WP_Error(
				'cforge_plugin_missing',
				__( 'The plugin was installed but could not be located.', 'content-forge' ),
				[ 'status' => 500 ]
			);
		}

		if ( ! is_plugin_active( $plugin_file ) ) {
			$activated = activate_plugin( $plugin_file );

			if ( is_wp_error( $activated ) ) {
				return new \WP_Error( 'cforge_activate_failed', $activated->get_error_message(), [ 'status' => 500 ] );
			}
		}

		return new \WP_REST_Response(
			[
				'slug'   => $slug,
				'status' => 'active',
			],
			200
		);
	}

	/**
	 * Generated-object counts, bucketed to match the generator pages.
	 *
	 * @return array<string, int>
	 */
	protected function get_counts() {
		global $wpdb;

		$counts = [
			'posts'    => 0,
			'cpt'      => 0,
			'users'    => 0,
			'comments' => 0,
			'terms'    => 0,
		];

		$table = $wpdb->prefix . CFORGE_DBNAME;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return $counts;
		}

		$rows = $wpdb->get_results( "SELECT data_type, COUNT(*) AS total FROM {$table} GROUP BY data_type", ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $rows ) ) {
			return $counts;
		}

		return self::bucket_rows( $rows, get_taxonomies( [], 'names' ) );
	}

	/**
	 * Bucket raw tracking rows into the five dashboard tiles.
	 *
	 * Split out from get_counts() so the mapping can be tested without a database.
	 *
	 * @param array<int, array{data_type:string,total:int|string}> $rows       Grouped tracking rows.
	 * @param array<string, string>                                $taxonomies Registered taxonomy names, keyed by name.
	 *
	 * @return array<string, int>
	 */
	public static function bucket_rows( array $rows, array $taxonomies ) {
		$counts = [
			'posts'    => 0,
			'cpt'      => 0,
			'users'    => 0,
			'comments' => 0,
			'terms'    => 0,
		];

		foreach ( $rows as $row ) {
			$type  = (string) $row['data_type'];
			$total = (int) $row['total'];

			if ( 'user' === $type ) {
				$counts['users'] += $total;
			} elseif ( 'comment' === $type ) {
				$counts['comments'] += $total;
			} elseif ( isset( $taxonomies[ $type ] ) ) {
				$counts['terms'] += $total;
			} elseif ( in_array( $type, [ 'post', 'page' ], true ) ) {
				$counts['posts'] += $total;
			} else {
				// Anything else tracked is a custom post type row.
				$counts['cpt'] += $total;
			}
		}

		return $counts;
	}

	/**
	 * Install/active state for each promoted plugin.
	 *
	 * @return array<int, array{slug:string,installed:bool,active:bool}>
	 */
	protected function get_promoted_plugins() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins = [];

		foreach ( self::PROMOTED_SLUGS as $slug ) {
			$file = $this->get_plugin_file( $slug );

			$plugins[] = [
				'slug'      => $slug,
				'installed' => '' !== $file,
				'active'    => '' !== $file && is_plugin_active( $file ),
			];
		}

		return $plugins;
	}

	/**
	 * Resolve a slug to its installed plugin file, if present.
	 *
	 * Matched on directory name rather than a hardcoded bootstrap filename, so a
	 * plugin that renames its main file still resolves.
	 *
	 * @param string $slug Plugin directory slug.
	 *
	 * @return string Plugin file relative to the plugins dir, or '' when not installed.
	 */
	protected function get_plugin_file( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( 0 === strpos( $file, $slug . '/' ) ) {
				return $file;
			}
		}

		return '';
	}
}
