<?php
// DESCRIPTION: Builds a Frequency strategy from a schedule's config['frequency'] block.
// Supports daily, weekly, monthly, interval.

namespace ContentForge\Autopilot\Frequency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map config['frequency'] to a Frequency strategy instance.
 *
 * @since 1.5.0
 */
class Factory {

	/**
	 * Build a strategy from config.
	 *
	 * @since 1.5.0
	 *
	 * @param array $frequency_config The 'frequency' sub-array of a schedule config.
	 * @return Frequency
	 */
	public static function from_config( array $frequency_config ) {
		$mode           = isset( $frequency_config['mode'] ) ? (string) $frequency_config['mode'] : 'daily';
		$time           = isset( $frequency_config['time'] ) ? (string) $frequency_config['time'] : '09:00';
		$weekdays       = isset( $frequency_config['weekdays'] ) && is_array( $frequency_config['weekdays'] ) ? $frequency_config['weekdays'] : [ 1 ];
		$day_of_month   = isset( $frequency_config['day_of_month'] ) ? (int) $frequency_config['day_of_month'] : 1;
		$interval_hours = isset( $frequency_config['interval_hours'] ) ? (int) $frequency_config['interval_hours'] : 24;
		$start_at       = isset( $frequency_config['start_at'] ) ? (string) $frequency_config['start_at'] : '';

		switch ( $mode ) {
			case 'weekly':
				return new Weekly( $time, $weekdays );
			case 'monthly':
				return new Monthly( $time, $day_of_month );
			case 'interval':
				return new Interval( $interval_hours, $start_at );
			case 'daily':
			default:
				return new Daily( $time );
		}
	}
}
