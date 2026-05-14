<?php
// DESCRIPTION: Contract for frequency strategies; given "now" returns the next fire time.
// Implementations live alongside this interface under Autopilot\Frequency.

namespace ContentForge\Autopilot\Frequency;

use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strategy interface for computing the next scheduled fire time.
 *
 * Implementations must be pure: same inputs → same output. State lives in
 * the schedule's config; this only computes.
 *
 * @since 1.5.0
 */
interface Frequency {

	/**
	 * Compute the next fire time strictly after $reference, returned in UTC.
	 *
	 * @since 1.5.0
	 *
	 * @param DateTimeImmutable $reference Reference instant (typically "now") in any timezone.
	 * @return DateTimeImmutable          Next fire time, expressed in UTC.
	 */
	public function next_after( DateTimeImmutable $reference );
}
