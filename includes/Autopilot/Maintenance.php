<?php
// DESCRIPTION: Daily maintenance — prunes run history and marks stuck runs as failed.
// Lightweight; runs at 03:00 site time via Action Scheduler recurring action.

namespace ContentForge\Autopilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily housekeeping for Autopilot.
 *
 * @since 1.5.0
 */
class Maintenance {

	const HOOK = 'cforge_autopilot_maintenance';

	/** @var Run_Repository */
	protected $runs;

	/**
	 * Constructor.
	 *
	 * @param Run_Repository $runs Run repo.
	 */
	public function __construct( Run_Repository $runs ) {
		$this->runs = $runs;
	}

	/**
	 * Register AS hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK, [ $this, 'handle' ] );
	}

	/**
	 * Maintenance handler.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function handle() {
		$this->runs->prune( 30 );
		$this->runs->mark_stuck_failed( 1800 );
	}
}
