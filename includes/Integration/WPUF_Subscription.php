<?php
/**
 * WP User Frontend subscription pack meta for Content Forge.
 * Sets billing, expiration, and post limits meta after a wpuf_subscription post is created so WPUF displays it correctly.
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPUF subscription pack meta adapter.
 */
class WPUF_Subscription {

	/**
	 * Expiration period options used by WPUF.
	 *
	 * @var string[]
	 */
	private static $expiration_periods = [ 'day', 'week', 'month', 'year' ];

	/**
	 * Apply default WPUF subscription meta to a newly created wpuf_subscription post.
	 *
	 * @param int   $post_id           Subscription post ID.
	 * @param array $subscription_options Optional. Keys for future use.
	 * @return bool True on success.
	 */
	public static function apply_subscription_defaults( $post_id, array $subscription_options = [] ) {
		if ( get_post_type( $post_id ) !== 'wpuf_subscription' ) {
			return false;
		}

		$is_free   = (bool) ( wp_rand( 0, 2 ) === 0 );
		$is_recurring = (bool) ( wp_rand( 0, 1 ) && ! $is_free );

		$billing_amount = $is_free ? 0 : ( wp_rand( 5, 99 ) );
		$expiration_number = $is_free ? 0 : ( $is_recurring ? wp_rand( 1, 12 ) : wp_rand( 1, 24 ) );
		$expiration_period = self::$expiration_periods[ array_rand( self::$expiration_periods ) ];
		if ( $expiration_number > 0 && in_array( $expiration_period, [ 'week', 'month', 'year' ], true ) ) {
			if ( $expiration_period === 'year' && $expiration_number > 5 ) {
				$expiration_number = 5;
			}
			if ( $expiration_period === 'month' && $expiration_number > 24 ) {
				$expiration_number = 24;
			}
		}

		$recurring_pay = $is_recurring ? 'yes' : 'no';
		$billing_cycle_number = $is_recurring ? wp_rand( 1, 12 ) : 1;
		$cycle_period = $is_recurring ? ( wp_rand( 0, 1 ) ? 'month' : 'year' ) : $expiration_period;

		$post_limits = [ 'post' => (string) ( $is_free ? wp_rand( 3, 10 ) : wp_rand( 5, 50 ) ) ];
		if ( wp_rand( 0, 2 ) === 0 ) {
			$post_limits['post'] = '-1';
		}

		update_post_meta( $post_id, '_billing_amount', $billing_amount );
		update_post_meta( $post_id, '_expiration_number', $expiration_number );
		update_post_meta( $post_id, '_expiration_period', $expiration_period );
		update_post_meta( $post_id, '_recurring_pay', $recurring_pay );
		update_post_meta( $post_id, '_billing_cycle_number', $billing_cycle_number );
		update_post_meta( $post_id, '_cycle_period', $cycle_period );
		update_post_meta( $post_id, '_enable_billing_limit', 'no' );
		update_post_meta( $post_id, '_billing_limit', '' );
		update_post_meta( $post_id, '_trial_status', 'no' );
		update_post_meta( $post_id, '_trial_duration', '' );
		update_post_meta( $post_id, '_trial_duration_type', '' );
		update_post_meta( $post_id, '_post_type_name', $post_limits );
		update_post_meta( $post_id, 'additional_cpt_options', [] );
		update_post_meta( $post_id, '_enable_post_expiration', '' );
		update_post_meta( $post_id, '_post_expiration_time', '' );
		update_post_meta( $post_id, '_post_expiration_number', '' );
		update_post_meta( $post_id, '_post_expiration_period', '' );
		update_post_meta( $post_id, '_expired_post_status', '' );
		update_post_meta( $post_id, '_enable_mail_after_expired', '' );
		update_post_meta( $post_id, '_post_expiration_message', '' );
		update_post_meta( $post_id, '_total_feature_item', '' );
		update_post_meta( $post_id, '_remove_feature_item', '' );
		update_post_meta( $post_id, '_sort_order', 0 );

		return true;
	}
}
