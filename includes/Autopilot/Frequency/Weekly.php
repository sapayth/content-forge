<?php
// DESCRIPTION: Weekly frequency — fires at HH:MM on the configured weekdays.
// Weekdays use ISO numbering: 1=Mon, 7=Sun.

namespace ContentForge\Autopilot\Frequency;

use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly fire schedule on a set of weekdays.
 *
 * @since 1.5.0
 */
class Weekly implements Frequency {

	/** @var string HH:MM */
	protected $time;

	/** @var int[] ISO weekdays 1..7 */
	protected $weekdays;

	/** @var DateTimeZone */
	protected $site_timezone;

	/**
	 * @param string            $time          HH:MM in site timezone.
	 * @param array             $weekdays      ISO weekdays (1=Mon..7=Sun). Empty falls back to Monday.
	 * @param DateTimeZone|null $site_timezone Site tz; defaults to wp_timezone().
	 */
	public function __construct( $time, array $weekdays, ?DateTimeZone $site_timezone = null ) {
		$this->time          = $this->normalize_time( $time );
		$this->weekdays      = $this->normalize_weekdays( $weekdays );
		$this->site_timezone = $site_timezone ?: $this->resolve_site_timezone();
	}

	/**
	 * @inheritDoc
	 */
	public function next_after( DateTimeImmutable $reference ) {
		$local = $reference->setTimezone( $this->site_timezone );
		list( $hour, $minute ) = array_map( 'intval', explode( ':', $this->time ) );

		// Try up to 8 future days; one of them must match.
		for ( $offset = 0; $offset <= 8; $offset++ ) {
			$candidate = $local->modify( '+' . $offset . ' day' )->setTime( $hour, $minute, 0 );
			$iso_dow   = (int) $candidate->format( 'N' );
			if ( in_array( $iso_dow, $this->weekdays, true ) && $candidate > $local ) {
				return $candidate->setTimezone( new DateTimeZone( 'UTC' ) );
			}
		}

		// Defensive fallback: same time next week, first selected weekday.
		$first = $this->weekdays[0];
		$days  = ( 7 + $first - (int) $local->format( 'N' ) ) % 7;
		$days  = $days === 0 ? 7 : $days;
		$next  = $local->modify( '+' . $days . ' day' )->setTime( $hour, $minute, 0 );
		return $next->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Normalize HH:MM input.
	 *
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
	 * Normalize weekdays list to unique sorted ISO numbers in [1,7].
	 *
	 * @param array $weekdays Raw.
	 * @return int[]
	 */
	protected function normalize_weekdays( array $weekdays ) {
		$cleaned = [];
		foreach ( $weekdays as $day ) {
			$day = (int) $day;
			if ( $day >= 1 && $day <= 7 ) {
				$cleaned[] = $day;
			}
		}
		$cleaned = array_values( array_unique( $cleaned ) );
		if ( empty( $cleaned ) ) {
			$cleaned = [ 1 ];
		}
		sort( $cleaned );
		return $cleaned;
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
