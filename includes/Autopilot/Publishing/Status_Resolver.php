<?php
// DESCRIPTION: Maps publishing.mode config to wp_insert_post arguments.
// Supports: draft, pending, scheduled (publish at now + N hours), publish.

namespace ContentForge\Autopilot\Publishing;

use ContentForge\Autopilot\Schedule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve post_status (and post_date for scheduled) from a schedule's publishing config.
 *
 * @since 1.5.0
 */
class Status_Resolver {

	/**
	 * Returns an array of wp_insert_post arguments to merge into a post payload.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @return array              Subset of wp_insert_post args.
	 */
	public function resolve( Schedule $schedule ) {
		$mode = (string) $schedule->config_get( 'publishing.mode', 'draft' );

		switch ( $mode ) {
			case 'pending':
				return [ 'post_status' => 'pending' ];

			case 'scheduled':
				$delay_hours = max( 0, (int) $schedule->config_get( 'publishing.publish_delay_hours', 1 ) );
				$utc_ts      = time() + ( $delay_hours * HOUR_IN_SECONDS );
				return [
					'post_status'   => 'future',
					'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $utc_ts ), 'Y-m-d H:i:s' ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $utc_ts ),
				];

			case 'publish':
				// Caller must enforce ack_publish_immediately at the REST layer.
				return [ 'post_status' => 'publish' ];

			case 'draft':
			default:
				return [ 'post_status' => 'draft' ];
		}
	}
}
