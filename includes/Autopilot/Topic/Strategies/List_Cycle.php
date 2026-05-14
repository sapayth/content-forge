<?php
// DESCRIPTION: List-cycle topic strategy — round-robins through a configured topic list.
// Persists the cursor back via Schedule_Repository so the next run advances by one.

namespace ContentForge\Autopilot\Topic\Strategies;

use ContentForge\Autopilot\Schedule;
use ContentForge\Autopilot\Schedule_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cycle through topic.list, advancing the list_cursor on each call.
 *
 * @since 1.5.0
 */
class List_Cycle {

	/** @var Schedule_Repository */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Schedule_Repository $repository Repository.
	 */
	public function __construct( Schedule_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Pick the next topic and advance the cursor.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @return string             Topic or '' if list is empty.
	 */
	public function resolve( Schedule $schedule ) {
		$list = $schedule->config_get( 'topic.list', [] );
		if ( ! is_array( $list ) || count( $list ) === 0 ) {
			return '';
		}

		$cursor = (int) $schedule->config_get( 'topic.list_cursor', 0 );
		if ( $cursor < 0 || $cursor >= count( $list ) ) {
			$cursor = 0;
		}

		$topic = (string) $list[ $cursor ];

		$config                          = $schedule->config();
		$config['topic']['list_cursor']  = ( $cursor + 1 ) % count( $list );

		$this->repository->update( $schedule->id(), $config, null );

		return $topic;
	}
}
