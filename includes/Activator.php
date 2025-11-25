<?php
/**
 * Activator class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	/**
	 * Run activation tasks (e.g., create custom DB table).
	 */
	public static function activate() {
		self::create_tracking_table();
	}

	/**
	 * Create the custom DB table for tracking generated content.
	 */
	public static function create_tracking_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . CFORGE_DBNAME;
		$charset_collate = $wpdb->get_charset_collate();

		// Table schema: id, object_id, data_type, created_at, created_by, meta (JSON, nullable)
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id BIGINT UNSIGNED NOT NULL,
			data_type VARCHAR(32) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_by BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			KEY object_id (object_id),
			KEY data_type (data_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}
}
