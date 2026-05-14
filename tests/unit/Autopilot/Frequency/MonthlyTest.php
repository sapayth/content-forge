<?php
// DESCRIPTION: Unit tests for the Monthly frequency strategy.
// Covers same-month, next-month, and day clamping for short months.

namespace ContentForge\Tests\Autopilot\Frequency;

use ContentForge\Autopilot\Frequency\Monthly;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class MonthlyTest extends TestCase {

	public function test_picks_same_month_when_target_day_is_in_future() {
		$tz       = new DateTimeZone( 'UTC' );
		$monthly  = new Monthly( '09:00', 15, $tz );
		$now      = new DateTimeImmutable( '2026-05-10 12:00:00', $tz );
		$expected = new DateTimeImmutable( '2026-05-15 09:00:00', $tz );

		$this->assertEquals( $expected->format( DATE_ATOM ), $monthly->next_after( $now )->format( DATE_ATOM ) );
	}

	public function test_rolls_to_next_month_when_target_day_already_passed() {
		$tz       = new DateTimeZone( 'UTC' );
		$monthly  = new Monthly( '09:00', 5, $tz );
		$now      = new DateTimeImmutable( '2026-05-10 12:00:00', $tz );
		$expected = new DateTimeImmutable( '2026-06-05 09:00:00', $tz );

		$this->assertEquals( $expected->format( DATE_ATOM ), $monthly->next_after( $now )->format( DATE_ATOM ) );
	}

	public function test_clamps_to_last_day_of_short_month() {
		// Day 31 in February clamps to 28 (or 29 in leap years).
		$tz       = new DateTimeZone( 'UTC' );
		$monthly  = new Monthly( '09:00', 31, $tz );
		$now      = new DateTimeImmutable( '2026-02-01 00:00:00', $tz );

		$next = $monthly->next_after( $now );

		$this->assertSame( '2026-02-28', $next->format( 'Y-m-d' ) );
	}

	public function test_year_rollover_from_december() {
		$tz       = new DateTimeZone( 'UTC' );
		$monthly  = new Monthly( '09:00', 5, $tz );
		$now      = new DateTimeImmutable( '2026-12-10 12:00:00', $tz );
		$expected = new DateTimeImmutable( '2027-01-05 09:00:00', $tz );

		$this->assertEquals( $expected->format( DATE_ATOM ), $monthly->next_after( $now )->format( DATE_ATOM ) );
	}
}
