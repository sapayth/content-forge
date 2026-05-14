<?php
// DESCRIPTION: Persistence layer for Autopilot run history (custom table).
// Only place that issues SQL against cforge_autopilot_runs.

namespace ContentForge\Autopilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD + pruning for the runs table.
 *
 * @since 1.5.0
 */
class Run_Repository {

	/**
	 * Insert a new run row.
	 *
	 * @since 1.5.0
	 *
	 * @param array $data Associative array matching the table columns.
	 * @return int|false  Inserted row ID or false on failure.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$defaults = [
			'schedule_id'     => 0,
			'status'          => Run::STATUS_QUEUED,
			'started_at'      => gmdate( 'Y-m-d H:i:s' ),
			'finished_at'     => null,
			'topic_used'      => null,
			'posts_created'   => null,
			'posts_requested' => 1,
			'error_message'   => null,
			'ai_provider'     => null,
			'ai_model'        => null,
		];

		$row = array_merge( $defaults, $data );

		if ( is_array( $row['posts_created'] ) ) {
			$row['posts_created'] = wp_json_encode( array_map( 'intval', $row['posts_created'] ) );
		}

		$inserted = $wpdb->insert( Installer::runs_table(), $row );

		if ( ! $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update specific columns on a run row.
	 *
	 * @since 1.5.0
	 *
	 * @param int   $id   Run ID.
	 * @param array $data Columns to update.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		if ( isset( $data['posts_created'] ) && is_array( $data['posts_created'] ) ) {
			$data['posts_created'] = wp_json_encode( array_map( 'intval', $data['posts_created'] ) );
		}

		$result = $wpdb->update(
			Installer::runs_table(),
			$data,
			[ 'id' => (int) $id ]
		);

		return $result !== false;
	}

	/**
	 * Find a run by ID.
	 *
	 * @since 1.5.0
	 *
	 * @param int $id Run ID.
	 * @return Run|null
	 */
	public function find( $id ) {
		global $wpdb;

		$table = Installer::runs_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ),
			ARRAY_A
		);

		return $row ? Run::from_row( $row ) : null;
	}

	/**
	 * List recent runs for a schedule, newest first.
	 *
	 * @since 1.5.0
	 *
	 * @param int $schedule_id Schedule ID.
	 * @param int $limit       Maximum rows to return.
	 * @return Run[]
	 */
	public function list_for_schedule( $schedule_id, $limit = 30 ) {
		global $wpdb;

		$table = Installer::runs_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE schedule_id = %d ORDER BY id DESC LIMIT %d",
				(int) $schedule_id,
				(int) $limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( [ Run::class, 'from_row' ], $rows );
	}

	/**
	 * Count daily run rows that produced posts for a given schedule.
	 *
	 * Used by the per-schedule daily cap check.
	 *
	 * @since 1.5.0
	 *
	 * @param int    $schedule_id Schedule ID.
	 * @param string $since_utc   UTC datetime string lower bound (inclusive).
	 * @return int                Total posts created since $since_utc.
	 */
	public function count_posts_created_since( $schedule_id, $since_utc ) {
		global $wpdb;

		$table = Installer::runs_table();
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT posts_created FROM {$table} WHERE schedule_id = %d AND started_at >= %s",
				(int) $schedule_id,
				$since_utc
			)
		);

		$total = 0;
		foreach ( (array) $rows as $json ) {
			$decoded = is_string( $json ) ? json_decode( $json, true ) : null;
			if ( is_array( $decoded ) ) {
				$total += count( $decoded );
			}
		}
		return $total;
	}

	/**
	 * Prune to the newest N rows per schedule.
	 *
	 * Iterates per schedule. Safe to call from maintenance cron.
	 *
	 * @since 1.5.0
	 *
	 * @param int $keep_per_schedule How many rows to keep per schedule.
	 * @return int                   Total rows deleted.
	 */
	public function prune( $keep_per_schedule = 30 ) {
		global $wpdb;

		$table = Installer::runs_table();
		$total = 0;

		$schedule_ids = $wpdb->get_col( "SELECT DISTINCT schedule_id FROM {$table}" );

		foreach ( (array) $schedule_ids as $schedule_id ) {
			$threshold_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE schedule_id = %d ORDER BY id DESC LIMIT 1 OFFSET %d",
					(int) $schedule_id,
					(int) $keep_per_schedule
				)
			);

			if ( ! $threshold_id ) {
				continue;
			}

			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE schedule_id = %d AND id <= %d",
					(int) $schedule_id,
					(int) $threshold_id
				)
			);

			if ( $deleted ) {
				$total += (int) $deleted;
			}
		}

		return $total;
	}

	/**
	 * Mark runs stuck in 'running' state for too long as failed.
	 *
	 * @since 1.5.0
	 *
	 * @param int $stuck_seconds Seconds since started_at after which a run is stuck.
	 * @return int               Rows updated.
	 */
	public function mark_stuck_failed( $stuck_seconds = 1800 ) {
		global $wpdb;

		$table       = Installer::runs_table();
		$threshold   = gmdate( 'Y-m-d H:i:s', time() - (int) $stuck_seconds );
		$now         = gmdate( 'Y-m-d H:i:s' );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status = %s, finished_at = %s, error_message = %s
				 WHERE status = %s AND started_at < %s",
				Run::STATUS_FAILED,
				$now,
				'Runner timed out',
				Run::STATUS_RUNNING,
				$threshold
			)
		);

		return (int) $updated;
	}
}
