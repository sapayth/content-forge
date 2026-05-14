<?php
// DESCRIPTION: Monthly frequency — fires at HH:MM on a given day-of-month.
// Clamps to the last day of the month when the configured day exceeds month length.

namespace ContentForge\Autopilot\Frequency;

use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Monthly fire schedule.
 *
 * @since 1.5.0
 */
class Monthly implements Frequency {

	/** @var string HH:MM */
	protected $time;

	/** @var int 1..31 */
	protected $day_of_month;

	/** @var DateTimeZone */
	protected $site_timezone;

	/**
	 * @param string            $time          HH:MM site timezone.
	 * @param int               $day_of_month  Day 1..31. Out-of-range clamps to month end.
	 * @param DateTimeZone|null $site_timezone Site tz; defaults to wp_timezone().
	 */
	public function __construct( $time, $day_of_month, ?DateTimeZone $site_timezone = null ) {
		$this->time          = $this->normalize_time( $time );
		$this->day_of_month  = max( 1, min( 31, (int) $day_of_month ) );
		$this->site_timezone = $site_timezone ?: $this->resolve_site_timezone();
	}

	/**
	 * @inheritDoc
	 */
	public function next_after( DateTimeImmutable $reference ) {
		$local = $reference->setTimezone( $this->site_timezone );
		list( $hour, $minute ) = array_map( 'intval', explode( ':', $this->time ) );

		$candidate = $this->candidate_for_month( $local, (int) $local->format( 'Y' ), (int) $local->format( 'm' ), $hour, $minute );

		if ( $candidate <= $local ) {
			$year  = (int) $local->format( 'Y' );
			$month = (int) $local->format( 'm' ) + 1;
			if ( $month > 12 ) {
				$month = 1;
				$year++;
			}
			$candidate = $this->candidate_for_month( $local, $year, $month, $hour, $minute );
		}

		return $candidate->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Compute the candidate fire datetime in a given year/month, clamping day.
	 *
	 * @param DateTimeImmutable $local  Reference in site tz (used for tz).
	 * @param int               $year   Year.
	 * @param int               $month  Month 1..12.
	 * @param int               $hour   Hour.
	 * @param int               $minute Minute.
	 * @return DateTimeImmutable        Candidate in site tz.
	 */
	protected function candidate_for_month( DateTimeImmutable $local, $year, $month, $hour, $minute ) {
		$last_day = (int) ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), $this->site_timezone ) )
			->modify( 'last day of this month' )
			->format( 'j' );

		$day = min( $this->day_of_month, $last_day );

		return ( new DateTimeImmutable(
			sprintf( '%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute ),
			$this->site_timezone
		) );
	}

	/**
	 * @param string $time Raw.
	 * @return string
	 */
	protected function normalize_time( $time ) {
		if ( ! is_string( $time ) || ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m ) ) {
			return '09:00';
		}
		return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
	}

	/**
	 * @return DateTimeZone
	 */
	protected function resolve_site_timezone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}
		return new DateTimeZone( 'UTC' );
	}
}
