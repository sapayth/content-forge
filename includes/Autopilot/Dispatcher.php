<?php
// DESCRIPTION: Per-minute Action Scheduler tick — finds due schedules and enqueues runners.
// Advances next_run_at BEFORE enqueueing so crashes lose a run instead of duplicating.

namespace ContentForge\Autopilot;

use DateTimeImmutable;
use DateTimeZone;
use ContentForge\Autopilot\Frequency\Factory as FrequencyFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatcher for Autopilot.
 *
 * @since 1.5.0
 */
class Dispatcher {

	const HOOK            = 'cforge_autopilot_dispatch';
	const RUN_HOOK        = 'cforge_autopilot_run_schedule';
	const LOCK_TRANSIENT  = 'cforge_autopilot_dispatch_lock';
	const HEARTBEAT_OPTION = 'cforge_autopilot_last_dispatch';

	/** @var Schedule_Repository */
	protected $schedules;

	/** @var Run_Repository */
	protected $runs;

	/**
	 * Constructor.
	 *
	 * @param Schedule_Repository $schedules Schedule repo.
	 * @param Run_Repository      $runs      Run repo.
	 */
	public function __construct( Schedule_Repository $schedules, Run_Repository $runs ) {
		$this->schedules = $schedules;
		$this->runs      = $runs;
	}

	/**
	 * Register Action Scheduler hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK, [ $this, 'handle' ] );
	}

	/**
	 * Dispatcher tick handler.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! Plugin::is_enabled() ) {
			return;
		}

		// Concurrency guard. 50s TTL — shorter than the 60s tick interval.
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}
		set_transient( self::LOCK_TRANSIENT, 1, 50 );

		try {
			$now_utc = gmdate( 'Y-m-d H:i:s' );
			$due     = $this->schedules->find_due( $now_utc, 25 );

			foreach ( $due as $schedule ) {
				$this->dispatch_one( $schedule );
			}

			update_option( self::HEARTBEAT_OPTION, time(), false );
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Process a single due schedule: advance next_run_at and enqueue runner.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @return void
	 */
	protected function dispatch_one( Schedule $schedule ) {
		$reference = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$next      = FrequencyFactory::from_config( (array) $schedule->config_get( 'frequency', [] ) )->next_after( $reference );

		// Stop-condition check: cap reached, or stop_at reached.
		if ( $this->stop_condition_reached( $schedule, $reference ) ) {
			$this->schedules->mark_completed( $schedule->id() );
			return;
		}

		$this->schedules->update_next_run( $schedule->id(), $next->format( 'Y-m-d H:i:s' ) );

		$posts_per_run = max( 1, (int) $schedule->config_get( 'posts_per_run', 1 ) );

		$run_id = $this->runs->insert(
			[
				'schedule_id'     => $schedule->id(),
				'status'          => Run::STATUS_QUEUED,
				'started_at'      => gmdate( 'Y-m-d H:i:s' ),
				'posts_requested' => $posts_per_run,
			]
		);

		if ( ! $run_id ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				self::RUN_HOOK,
				[ $schedule->id(), $run_id ],
				'cforge_autopilot_runs'
			);
		} elseif ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time(),
				self::RUN_HOOK,
				[ $schedule->id(), $run_id ],
				'cforge_autopilot_runs'
			);
		}
	}

	/**
	 * Check stop_at and runs_cap conditions.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule          $schedule Schedule.
	 * @param DateTimeImmutable $now      UTC "now".
	 * @return bool
	 */
	protected function stop_condition_reached( Schedule $schedule, DateTimeImmutable $now ) {
		$cap = $schedule->runs_cap();
		if ( $cap !== null && $schedule->runs_completed() >= $cap ) {
			return true;
		}

		$stop_at = $schedule->stop_at();
		if ( $stop_at !== '' ) {
			try {
				$stop = new DateTimeImmutable( $stop_at, new DateTimeZone( 'UTC' ) );
				if ( $now >= $stop ) {
					return true;
				}
			} catch ( \Exception $e ) {
				// Invalid stop_at — ignore.
				return false;
			}
		}

		return false;
	}
}
