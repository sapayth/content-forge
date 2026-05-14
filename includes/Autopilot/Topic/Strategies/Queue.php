<?php
// DESCRIPTION: Queue topic strategy — pops the head of topic.queue and persists the shorter queue.
// Auto-pauses the schedule with reason 'queue_empty' when the queue is drained.

namespace ContentForge\Autopilot\Topic\Strategies;

use ContentForge\Autopilot\Schedule;
use ContentForge\Autopilot\Schedule_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consume topics from topic.queue, head-first.
 *
 * @since 1.5.0
 */
class Queue {

	/** @var Schedule_Repository */
	protected $repository;

	/**
	 * @param Schedule_Repository $repository Repository.
	 */
	public function __construct( Schedule_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Resolve and consume a topic. Auto-pauses if the queue is empty after consumption.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @return string             Topic or '' if queue is empty.
	 */
	public function resolve( Schedule $schedule ) {
		$queue = $schedule->config_get( 'topic.queue', [] );
		if ( ! is_array( $queue ) || count( $queue ) === 0 ) {
			$this->repository->auto_pause( $schedule->id(), 'queue_empty' );

			/**
			 * Fires when a schedule's topic queue drains and it auto-pauses.
			 *
			 * @since 1.5.0
			 *
			 * @param Schedule $schedule Schedule.
			 * @param string   $reason   Reason key.
			 */
			do_action( 'cforge_autopilot_schedule_auto_paused', $schedule, 'queue_empty' );
			return '';
		}

		$topic = (string) array_shift( $queue );

		$config                   = $schedule->config();
		$config['topic']['queue'] = array_values( $queue );

		$this->repository->update( $schedule->id(), $config, null );

		// If queue is now empty after consuming this topic, auto-pause AFTER this run completes.
		// We don't pause inline so this run still completes.
		if ( count( $queue ) === 0 ) {
			update_post_meta( $schedule->id(), '_cforge_queue_drained', 1 );
		}

		return $topic;
	}
}
