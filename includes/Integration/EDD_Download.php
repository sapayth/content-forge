<?php
/**
 * Easy Digital Downloads download meta for Content Forge.
 * Sets price and sales/earnings meta after a download post is created so EDD displays it correctly.
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * EDD download meta adapter.
 */
class EDD_Download {

	/**
	 * Default price range (min, max) for generated downloads.
	 *
	 * @var array{0: float, 1: float}
	 */
	private static $default_price_range = [ 2.99, 49.99 ];

	/**
	 * Apply default EDD meta to a newly created download post.
	 * Call only when the post type is download. Sets edd_price and zero sales/earnings.
	 *
	 * @param int   $post_id         Download post ID.
	 * @param array $download_options Optional. Keys: price_min, price_max. Unused for now, for future use.
	 * @return bool True on success.
	 */
	public static function apply_download_defaults( $post_id, array $download_options = [] ) {
		if ( get_post_type( $post_id ) !== 'download' ) {
			return false;
		}

		$min = isset( $download_options['price_min'] ) ? floatval( $download_options['price_min'] ) : self::$default_price_range[0];
		$max = isset( $download_options['price_max'] ) ? floatval( $download_options['price_max'] ) : self::$default_price_range[1];
		if ( $max < $min ) {
			$max = $min;
		}
		$price = $min + ( ( $max - $min ) * wp_rand( 0, 100 ) / 100 );
		$price = round( $price, 2 );

		if ( function_exists( 'edd_sanitize_amount' ) ) {
			$price_sanitized = edd_sanitize_amount( (string) $price );
		} else {
			$price_sanitized = number_format( $price, 2, '.', '' );
		}

		update_post_meta( $post_id, 'edd_price', $price_sanitized );
		update_post_meta( $post_id, '_edd_download_sales', 0 );
		update_post_meta( $post_id, '_edd_download_earnings', $price_sanitized ? '0.00' : '0' );

		return true;
	}
}
