<?php
/**
 * WooCommerce product integration for Content Forge.
 * Sets product meta (price, SKU, stock) after a product post is created.
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce product data adapter.
 */
class WooCommerce_Product {

	/**
	 * Apply product options to a newly created product post.
	 * Call only when WooCommerce is active and $post_id is a product.
	 *
	 * @param int   $post_id         Product post ID.
	 * @param array $product_options Keys: price_min, price_max, sale_price_min, sale_price_max,
	 *                               generate_sku, sku_prefix, stock_status.
	 * @return bool True on success, false if product could not be loaded or saved.
	 */
	public static function apply_product_options( $post_id, array $product_options ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}

		$price_min = isset( $product_options['price_min'] ) ? floatval( $product_options['price_min'] ) : 0;
		$price_max = isset( $product_options['price_max'] ) ? floatval( $product_options['price_max'] ) : 99.99;
		if ( $price_max < $price_min ) {
			$price_max = $price_min;
		}
		$regular_price = (string) ( $price_min + ( ( $price_max - $price_min ) * wp_rand( 0, 100 ) / 100 ) );
		$product->set_regular_price( $regular_price );

		$sale_price_min = isset( $product_options['sale_price_min'] ) ? floatval( $product_options['sale_price_min'] ) : null;
		$sale_price_max = isset( $product_options['sale_price_max'] ) ? floatval( $product_options['sale_price_max'] ) : null;
		if ( null !== $sale_price_min && null !== $sale_price_max && $sale_price_max >= $sale_price_min && $sale_price_max < (float) $regular_price ) {
			$sale_price = (string) ( $sale_price_min + ( ( $sale_price_max - $sale_price_min ) * wp_rand( 0, 100 ) / 100 ) );
			$product->set_sale_price( $sale_price );
		}

		if ( ! empty( $product_options['generate_sku'] ) ) {
			$prefix = isset( $product_options['sku_prefix'] ) ? sanitize_text_field( $product_options['sku_prefix'] ) : 'CF-';
			$sku    = $prefix . $post_id . '-' . wp_rand( 100, 99999 );
			$product->set_sku( $sku );
		}

		$stock_status_raw = isset( $product_options['stock_status'] ) ? $product_options['stock_status'] : [ 'instock' ];
		$stock_statuses   = is_array( $stock_status_raw ) ? $stock_status_raw : [ $stock_status_raw ];
		$stock_statuses   = array_map( 'sanitize_text_field', $stock_statuses );
		$allowed          = [ 'instock', 'outofstock', 'onbackorder' ];
		$stock_statuses   = array_filter(
            $stock_statuses,
            function ( $s ) use ( $allowed ) {
				return in_array( $s, $allowed, true );
			}
        );
		if ( ! empty( $stock_statuses ) ) {
			$product->set_stock_status( $stock_statuses[ array_rand( $stock_statuses ) ] );
		} else {
			$product->set_stock_status( 'instock' );
		}

		$product->save();
		return true;
	}
}
