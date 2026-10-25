<?php
// DESCRIPTION: Interval frequency — fires every N hours from a start instant.
// "Every N hours" is interpreted literally, ignoring DST shifts.

namespace ContentForge\Autopilot\Frequency;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interval-based fire schedule.
 *
 * @since 1.5.0
 */
class Interval implements Frequency {

	const SECONDS_PER_HOUR = 3600;

	/**
	 * Interval length.
	 *
	 * @var int Hours between fires, >= 1
	 */
	protected $hours;

	/**
	 * Instant the interval counts from.
	 *
	 * @var DateTimeImmutable|null Start instant in UTC; null = anchored to "now" on first call
	 */
	protected $start_at_utc;

	/**
	 * Build an every-N-hours frequency.
	 *
	 * @param int    $hours    Interval in hours.
	 * @param string $start_at ISO datetime in UTC (e.g. "2026-05-15T09:00:00Z"). Empty allowed.
	 */
	public function __construct( $hours, $start_at = '' ) {
		$this->hours = max( 1, (int) $hours );

		$this->start_at_utc = null;
		if ( is_string( $start_at ) && '' !== $start_at ) {
			try {
				$this->start_at_utc = ( new DateTimeImmutable( $start_at ) )->setTimezone( new DateTimeZone( 'UTC' ) );
			} catch ( Exception $e ) {
				$this->start_at_utc = null;
			}
		}
	}

	/**
	 * Compute the next fire time strictly after $reference, returned in UTC.
	 *
	 * @param DateTimeImmutable $reference Reference instant (typically "now") in any timezone.
	 *
	 * @return DateTimeImmutable Next fire time, expressed in UTC.
	 */
	public function next_after( DateTimeImmutable $reference ) {
		$ref_utc = $reference->setTimezone( new DateTimeZone( 'UTC' ) );

		if ( null === $this->start_at_utc ) {
			return $ref_utc->modify( '+' . $this->hours . ' hour' );
		}

		// If the configured start is in the future, that's the first fire.
		if ( $this->start_at_utc > $ref_utc ) {
			return $this->start_at_utc;
		}

		// Otherwise, advance from the start in $hours increments past $ref_utc.
		$diff_seconds = $ref_utc->getTimestamp() - $this->start_at_utc->getTimestamp();
		$interval_sec = $this->hours * self::SECONDS_PER_HOUR;
		$periods      = (int) floor( $diff_seconds / $interval_sec ) + 1;
		$next_ts      = $this->start_at_utc->getTimestamp() + ( $periods * $interval_sec );

		return ( new DateTimeImmutable( '@' . $next_ts ) )->setTimezone( new DateTimeZone( 'UTC' ) );
	}
}
