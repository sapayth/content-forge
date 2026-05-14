<?php
// DESCRIPTION: Installer for the Autopilot feature.
// Creates the custom runs table via dbDelta; idempotent on every plugin load.

namespace ContentForge\Autopilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Autopilot DB schema creation and version-gated migrations.
 *
 * @since 1.5.0
 */
class Installer {

	/**
	 * Option key tracking the installed schema version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'cforge_autopilot_db_version';

	/**
	 * Current schema version.
	 *
	 * Bump this when changing the runs table schema, then add a migration branch
	 * in maybe_install().
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Install or upgrade the Autopilot schema if needed.
	 *
	 * Safe to call on every plugin load; gated by the version option.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public static function maybe_install() {
		$installed = get_option( self::VERSION_OPTION, '' );

		if ( $installed === self::VERSION ) {
			return;
		}

		self::create_runs_table();

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Returns the fully-qualified runs table name.
	 *
	 * @since 1.5.0
	 *
	 * @return string
	 */
	public static function runs_table() {
		global $wpdb;

		return $wpdb->prefix . 'cforge_autopilot_runs';
	}

	/**
	 * Create the runs table.
	 *
	 * Schedules live in a CPT (cforge_autopilot); only the high-volume run
	 * history needs a dedicated table for indexed pruning.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	protected static function create_runs_table() {
		global $wpdb;

		$table_name      = self::runs_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			schedule_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			started_at DATETIME NOT NULL,
			finished_at DATETIME NULL,
			topic_used VARCHAR(500) NULL,
			posts_created LONGTEXT NULL,
			posts_requested TINYINT UNSIGNED NOT NULL DEFAULT 1,
			error_message LONGTEXT NULL,
			ai_provider VARCHAR(50) NULL,
			ai_model VARCHAR(100) NULL,
			PRIMARY KEY  (id),
			KEY schedule_started (schedule_id, started_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}
}
