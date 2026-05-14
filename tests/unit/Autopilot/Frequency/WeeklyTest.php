<?php
// DESCRIPTION: Unit tests for the Weekly frequency strategy.
// Covers same-week/next-week selection, multi-day picks, and weekday rollover.

namespace ContentForge\Tests\Autopilot\Frequency;

use ContentForge\Autopilot\Frequency\Weekly;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class WeeklyTest extends TestCase {

	public function test_picks_next_weekday_in_same_week_when_available() {
		// Tuesday 2026-05-12; fire on Friday (5) at 09:00.
		$tz       = new DateTimeZone( 'UTC' );
		$weekly   = new Weekly( '09:00', [ 5 ], $tz );
		$now      = new DateTimeImmutable( '2026-05-12 06:00:00', $tz );
		$expected = new DateTimeImmutable( '2026-05-15 09:00:00', $tz );

		$this->assertEquals( $expected->format( DATE_ATOM ), $weekly->next_after( $now )->format( DATE_ATOM ) );
	}

	public function test_rolls_to_next_week_when_all_selected_weekdays_passed_today() {
		// Friday 2026-05-15 10:00; only Mondays selected.
		$tz       = new DateTimeZone( 'UTC' );
		$weekly   = new Weekly( '09:00', [ 1 ], $tz );
		$now      = new DateTimeImmutable( '2026-05-15 10:00:00', $tz );
		$expected = new DateTimeImmutable( '2026-05-18 09:00:00', $tz ); // Monday next week.

		$this->assertEquals( $expected->format( DATE_ATOM ), $weekly->next_after( $now )->format( DATE_ATOM ) );
	}

	public function test_picks_today_when_time_is_still_in_future() {
		// Monday 2026-05-11 06:00; Mondays at 09:00 -> same day 09:00.
		$tz       = new DateTimeZone( 'UTC' );
		$weekly   = new Weekly( '09:00', [ 1 ], $tz );
		$now      = new DateTimeImmutable( '2026-05-11 06:00:00', $tz );
		$expected = new DateTimeImmutable( '2026-05-11 09:00:00', $tz );

		$this->assertEquals( $expected->format( DATE_ATOM ), $weekly->next_after( $now )->format( DATE_ATOM ) );
	}

	public function test_multiple_weekdays_picks_earliest_future() {
		// Sunday 2026-05-10 23:00; pick Mon (1) or Wed (3).
		$tz       = new DateTimeZone( 'UTC' );
		$weekly   = new Weekly( '09:00', [ 1, 3 ], $tz );
		$now      = new DateTimeImmutable( '2026-05-10 23:00:00', $tz );
		$expected = new DateTimeImmutable( '2026-05-11 09:00:00', $tz ); // Monday

		$this->assertEquals( $expected->format( DATE_ATOM ), $weekly->next_after( $now )->format( DATE_ATOM ) );
	}

	public function test_empty_weekdays_falls_back_to_monday() {
		$tz     = new DateTimeZone( 'UTC' );
		$weekly = new Weekly( '09:00', [], $tz );
		$now    = new DateTimeImmutable( '2026-05-10 23:00:00', $tz ); // Sunday
		$next   = $weekly->next_after( $now );

		$this->assertSame( '1', $next->format( 'N' ) ); // Monday.
	}
}
