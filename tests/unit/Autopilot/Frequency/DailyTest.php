<?php
// DESCRIPTION: Unit tests for the Daily frequency strategy.
// Pure-PHP — no WordPress bootstrap required (timezone injected via constructor).

namespace ContentForge\Tests\Autopilot\Frequency;

use ContentForge\Autopilot\Frequency\Daily;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class DailyTest extends TestCase {

	public function test_next_after_picks_same_day_when_time_is_in_future() {
		$tz       = new DateTimeZone( 'America/New_York' );
		$daily    = new Daily( '09:00', $tz );
		$now      = new DateTimeImmutable( '2026-05-14 06:00:00', $tz );
		$expected = ( new DateTimeImmutable( '2026-05-14 09:00:00', $tz ) )
			->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertEquals(
			$expected->format( DATE_ATOM ),
			$daily->next_after( $now )->format( DATE_ATOM )
		);
	}

	public function test_next_after_rolls_to_next_day_when_time_already_passed() {
		$tz       = new DateTimeZone( 'America/New_York' );
		$daily    = new Daily( '09:00', $tz );
		$now      = new DateTimeImmutable( '2026-05-14 10:00:00', $tz );
		$expected = ( new DateTimeImmutable( '2026-05-15 09:00:00', $tz ) )
			->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertEquals(
			$expected->format( DATE_ATOM ),
			$daily->next_after( $now )->format( DATE_ATOM )
		);
	}

	public function test_next_after_rolls_to_next_day_when_exactly_at_time() {
		$tz       = new DateTimeZone( 'UTC' );
		$daily    = new Daily( '09:00', $tz );
		$now      = new DateTimeImmutable( '2026-05-14 09:00:00', $tz );
		$expected = new DateTimeImmutable( '2026-05-15 09:00:00', $tz );

		$this->assertEquals(
			$expected->format( DATE_ATOM ),
			$daily->next_after( $now )->format( DATE_ATOM )
		);
	}

	public function test_handles_dst_spring_forward_in_us_eastern() {
		// 2026-03-08 02:00 -> 03:00 in America/New_York (spring forward).
		$tz       = new DateTimeZone( 'America/New_York' );
		$daily    = new Daily( '09:00', $tz );
		$now      = new DateTimeImmutable( '2026-03-07 10:00:00', $tz );
		$next     = $daily->next_after( $now );
		$expected = ( new DateTimeImmutable( '2026-03-08 09:00:00', $tz ) )
			->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertEquals(
			$expected->format( DATE_ATOM ),
			$next->format( DATE_ATOM )
		);
	}

	public function test_invalid_time_falls_back_to_nine_am() {
		$tz    = new DateTimeZone( 'UTC' );
		$daily = new Daily( 'not-a-time', $tz );
		$now   = new DateTimeImmutable( '2026-05-14 08:00:00', $tz );

		$next = $daily->next_after( $now );

		$this->assertSame( '09:00:00', $next->format( 'H:i:s' ) );
		$this->assertSame( '2026-05-14', $next->format( 'Y-m-d' ) );
	}

	public function test_returns_value_in_utc_regardless_of_input_timezone() {
		$tz       = new DateTimeZone( 'Asia/Tokyo' );
		$daily    = new Daily( '09:00', $tz );
		$now      = new DateTimeImmutable( '2026-05-14 06:00:00', $tz );

		$next = $daily->next_after( $now );

		$this->assertSame( 'UTC', $next->getTimezone()->getName() );
	}
}
