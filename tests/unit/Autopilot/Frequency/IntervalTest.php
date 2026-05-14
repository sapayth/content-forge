<?php
// DESCRIPTION: Unit tests for the Interval frequency strategy.
// Covers anchored vs unanchored start, and skipping past missed periods.

namespace ContentForge\Tests\Autopilot\Frequency;

use ContentForge\Autopilot\Frequency\Interval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class IntervalTest extends TestCase {

	public function test_unanchored_advances_by_interval_from_now() {
		$utc      = new DateTimeZone( 'UTC' );
		$interval = new Interval( 6, '' );
		$now      = new DateTimeImmutable( '2026-05-14 06:00:00', $utc );

		$next = $interval->next_after( $now );

		$this->assertSame( '2026-05-14 12:00:00', $next->format( 'Y-m-d H:i:s' ) );
	}

	public function test_anchored_future_start_returns_start_exactly() {
		$utc      = new DateTimeZone( 'UTC' );
		$interval = new Interval( 6, '2026-05-15T09:00:00Z' );
		$now      = new DateTimeImmutable( '2026-05-14 06:00:00', $utc );

		$next = $interval->next_after( $now );

		$this->assertSame( '2026-05-15 09:00:00', $next->format( 'Y-m-d H:i:s' ) );
	}

	public function test_anchored_past_start_advances_in_period_steps() {
		// Start was 2026-05-01 09:00, interval 6h. "Now" is 2026-05-14 06:00.
		// 14 May 06:00 minus 1 May 09:00 = 12 days 21 hours = 309h.
		// 309 / 6 = 51.5 → period index 52, next fire = start + 52*6h.
		// 1 May 09:00 + 312h = 14 May 09:00.
		$utc      = new DateTimeZone( 'UTC' );
		$interval = new Interval( 6, '2026-05-01T09:00:00Z' );
		$now      = new DateTimeImmutable( '2026-05-14 06:00:00', $utc );

		$next = $interval->next_after( $now );

		$this->assertSame( '2026-05-14 09:00:00', $next->format( 'Y-m-d H:i:s' ) );
	}

	public function test_minimum_interval_is_one_hour() {
		$utc      = new DateTimeZone( 'UTC' );
		$interval = new Interval( 0, '' );
		$now      = new DateTimeImmutable( '2026-05-14 06:00:00', $utc );

		$next = $interval->next_after( $now );

		$this->assertSame( '2026-05-14 07:00:00', $next->format( 'Y-m-d H:i:s' ) );
	}
}
