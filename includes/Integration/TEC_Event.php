<?php
/**
 * The Events Calendar event meta for Content Forge.
 * Sets start/end date and timezone meta after an event post is created so TEC displays it correctly.
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TEC event meta adapter.
 */
class TEC_Event {

	const DATETIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Apply default TEC event meta to a newly created tribe_events post.
	 * Sets _EventStartDate, _EventEndDate, UTC variants, timezone, and duration.
	 *
	 * @param int   $post_id        Event post ID.
	 * @param array $event_options Optional. Keys for future use (e.g. start_offset_days, duration_hours).
	 * @return bool True on success.
	 */
	public static function apply_event_defaults( $post_id, array $event_options = [] ) {
		if ( get_post_type( $post_id ) !== 'tribe_events' ) {
			return false;
		}

		$tz_string = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
		$tz        = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( $tz_string );
		$utc       = new \DateTimeZone( 'UTC' );

		$days_ahead = isset( $event_options['start_offset_days'] ) ? (int) $event_options['start_offset_days'] : wp_rand( 0, 180 );
		$duration_hours = isset( $event_options['duration_hours'] ) ? (float) $event_options['duration_hours'] : ( 1 + wp_rand( 0, 200 ) / 100 );

		$start = new \DateTime( 'today', $tz );
		$start->modify( '+' . $days_ahead . ' days' );
		$start->setTime( wp_rand( 9, 20 ), wp_rand( 0, 1 ) * 30, 0 );

		$end = clone $start;
		$end->modify( '+' . ( (int) round( $duration_hours * 3600 ) ) . ' seconds' );

		$start_local = $start->format( self::DATETIME_FORMAT );
		$end_local   = $end->format( self::DATETIME_FORMAT );

		$start_utc = clone $start;
		$start_utc->setTimezone( $utc );
		$end_utc = clone $end;
		$end_utc->setTimezone( $utc );

		$start_utc_str = $start_utc->format( self::DATETIME_FORMAT );
		$end_utc_str   = $end_utc->format( self::DATETIME_FORMAT );

		$duration_seconds = $end->getTimestamp() - $start->getTimestamp();

		update_post_meta( $post_id, '_EventStartDate', $start_local );
		update_post_meta( $post_id, '_EventEndDate', $end_local );
		update_post_meta( $post_id, '_EventStartDateUTC', $start_utc_str );
		update_post_meta( $post_id, '_EventEndDateUTC', $end_utc_str );
		update_post_meta( $post_id, '_EventTimezone', $tz_string );
		update_post_meta( $post_id, '_EventDuration', $duration_seconds );
		update_post_meta( $post_id, '_EventAllDay', 'no' );

		return true;
	}
}
