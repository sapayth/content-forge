<?php
// DESCRIPTION: Random-pick topic strategy — selects a topic from the list with no cursor state.
// Uses wp_rand for cryptographic-safe randomness.

namespace ContentForge\Autopilot\Topic\Strategies;

use ContentForge\Autopilot\Schedule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pick a uniformly random topic from topic.list.
 *
 * @since 1.5.0
 */
class List_Random {

	/**
	 * Resolve a topic.
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

		$index = wp_rand( 0, count( $list ) - 1 );
		return (string) $list[ $index ];
	}
}
