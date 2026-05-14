<?php
// DESCRIPTION: Daily frequency strategy — fires every day at a configured local time.
// Uses site timezone for the time-of-day; returns UTC for storage.

namespace ContentForge\Autopilot\Frequency;

use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily fire schedule.
 *
 * Configuration:
 *   - time: 'HH:MM' in site timezone.
 *
 * @since 1.5.0
 */
class Daily implements Frequency {

	/**
	 * Local time of day, 'HH:MM'.
	 *
	 * @var string
	 */
	protected $time;

	/**
	 * Timezone for the local time computation.
	 *
	 * @var DateTimeZone
	 */
	protected $site_timezone;

	/**
	 * Constructor.
	 *
	 * @since 1.5.0
	 *
	 * @param string            $time          Local time 'HH:MM'.
	 * @param DateTimeZone|null $site_timezone Site timezone; defaults to wp_timezone().
	 */
	public function __construct( $time, ?DateTimeZone $site_timezone = null ) {
		$this->time          = $this->normalize_time( $time );
		$this->site_timezone = $site_timezone ?: $this->resolve_site_timezone();
	}

	/**
	 * @inheritDoc
	 */
	public function next_after( DateTimeImmutable $reference ) {
		$local_reference = $reference->setTimezone( $this->site_timezone );

		list( $hour, $minute ) = array_map( 'intval', explode( ':', $this->time ) );

		$candidate = $local_reference->setTime( $hour, $minute, 0 );

		if ( $candidate <= $local_reference ) {
			$candidate = $candidate->modify( '+1 day' )->setTime( $hour, $minute, 0 );
		}

		return $candidate->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Normalize an HH:MM string. Falls back to 09:00 on invalid input.
	 *
	 * @param string $time Raw value.
	 * @return string Sanitized 'HH:MM'.
	 */
	protected function normalize_time( $time ) {
		if ( ! is_string( $time ) || ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m ) ) {
			return '09:00';
		}
		return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
	}

	/**
	 * Resolve site timezone using wp_timezone() when available.
	 *
	 * @return DateTimeZone
	 */
	protected function resolve_site_timezone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}
		return new DateTimeZone( 'UTC' );
	}
}
