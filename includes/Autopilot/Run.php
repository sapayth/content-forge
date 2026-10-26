<?php
// DESCRIPTION: Value object for a single Autopilot run history row.
// Plain DTO; Run_Repository owns hydration from wpdb rows.

namespace ContentForge\Autopilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only view of a row from the cforge_autopilot_runs table.
 *
 * @since 1.5.0
 */
class Run {

	const STATUS_QUEUED  = 'queued';
	const STATUS_RUNNING = 'running';
	const STATUS_SUCCESS = 'success';
	const STATUS_PARTIAL = 'partial';
	const STATUS_FAILED  = 'failed';
	const STATUS_SKIPPED = 'skipped';

	/**
	 * Run ID.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * ID of the schedule this run belongs to.
	 *
	 * @var int
	 */
	public $schedule_id;

	/**
	 * Run status, one of the STATUS_* constants.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * UTC datetime the run started.
	 *
	 * @var string
	 */
	public $started_at;

	/**
	 * UTC datetime the run finished, null while running.
	 *
	 * @var string|null
	 */
	public $finished_at;

	/**
	 * Topic the run generated content for.
	 *
	 * @var string|null
	 */
	public $topic_used;

	/**
	 * IDs of the posts the run created.
	 *
	 * @var int[]
	 */
	public $posts_created;

	/**
	 * Number of posts the run was asked to create.
	 *
	 * @var int
	 */
	public $posts_requested;

	/**
	 * Error message when the run failed.
	 *
	 * @var string|null
	 */
	public $error_message;

	/**
	 * AI provider used for the run.
	 *
	 * @var string|null
	 */
	public $ai_provider;

	/**
	 * AI model used for the run.
	 *
	 * @var string|null
	 */
	public $ai_model;

	/**
	 * Build from an associative wpdb row.
	 *
	 * @since 1.5.0
	 *
	 * @param array $row Row from wpdb::get_row(ARRAY_A).
	 * @return self
	 */
	public static function from_row( array $row ) {
		$instance                  = new self();
		$instance->id              = (int) ( $row['id'] ?? 0 );
		$instance->schedule_id     = (int) ( $row['schedule_id'] ?? 0 );
		$instance->status          = (string) ( $row['status'] ?? self::STATUS_QUEUED );
		$instance->started_at      = (string) ( $row['started_at'] ?? '' );
		$instance->finished_at     = null !== $row['finished_at'] ? (string) $row['finished_at'] : null;
		$instance->topic_used      = null !== $row['topic_used'] ? (string) $row['topic_used'] : null;
		$instance->posts_requested = (int) ( $row['posts_requested'] ?? 1 );
		$instance->error_message   = null !== $row['error_message'] ? (string) $row['error_message'] : null;
		$instance->ai_provider     = null !== $row['ai_provider'] ? (string) $row['ai_provider'] : null;
		$instance->ai_model        = null !== $row['ai_model'] ? (string) $row['ai_model'] : null;

		$decoded                 = is_string( $row['posts_created'] ?? null ) ? json_decode( $row['posts_created'], true ) : [];
		$instance->posts_created = is_array( $decoded ) ? array_map( 'intval', $decoded ) : [];

		return $instance;
	}
}
