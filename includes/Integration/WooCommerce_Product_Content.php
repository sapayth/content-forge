<?php
/**
 * WooCommerce product content provider for Content Forge.
 * Hooks into cforge_generate_post_* filters to supply realistic product title, description, and excerpt (non-AI).
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates realistic product copy for WooCommerce products via filters.
 */
class WooCommerce_Product_Content {

	/**
	 * Product type nouns for titles.
	 *
	 * @var string[]
	 */
	private static $product_types = [
		'Wireless Bluetooth Headphones',
		'Running Shoes',
		'Leather Wallet',
		'Stainless Steel Water Bottle',
		'Organic Cotton T-Shirt',
		'Desk Lamp',
		'Kitchen Knife Set',
		'Yoga Mat',
		'Portable Power Bank',
		'Ceramic Coffee Mug',
		'Backpack',
		'Mechanical Keyboard',
		'Noise-Cancelling Earbuds',
		'Wooden Cutting Board',
		'Insulated Lunch Box',
		'Fitness Tracker',
		'Canvas Tote Bag',
		'LED Desk Organizer',
		'Hand Cream',
		'USB-C Hub',
	];

	/**
	 * Adjectives for product titles.
	 *
	 * @var string[]
	 */
	private static $adjectives = [
		'Premium',
		'Classic',
		'Professional',
		'Essential',
		'Modern',
		'Eco-Friendly',
		'Compact',
		'Ergonomic',
		'Lightweight',
		'Durable',
	];

	/**
	 * Variants (color, size, style) for title suffix.
	 *
	 * @var string[]
	 */
	private static $variants = [
		'Navy',
		'Black',
		'White',
		'Grey',
		'Blue',
		'Large',
		'Medium',
		'500ml',
		'12oz',
		'Classic Fit',
	];

	/**
	 * Feature phrases for product descriptions.
	 *
	 * @var string[]
	 */
	private static $features = [
		'High-quality materials built to last',
		'Designed for everyday use',
		'Easy to clean and maintain',
		'Comfortable and practical',
		'Suitable for home and travel',
		'Backed by our satisfaction guarantee',
		'Eco-conscious packaging',
		'Thoughtful design details',
	];

	/**
	 * Short description templates (one sentence).
	 *
	 * @var string[]
	 */
	private static $short_templates = [
		'Perfect for everyday use. Durable and stylish.',
		'Quality craftsmanship meets modern design.',
		'A practical choice for your daily routine.',
		'Designed to perform. Built to last.',
		'Simple, reliable, and ready when you need it.',
	];

	/**
	 * Register filters for post title, content, and excerpt when WooCommerce is active.
	 */
	public static function register() {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return;
		}
		add_filter( 'cforge_generate_post_title', [ __CLASS__, 'filter_title' ], 10, 3 );
		add_filter( 'cforge_generate_post_content', [ __CLASS__, 'filter_content' ], 10, 3 );
		add_filter( 'cforge_generate_post_excerpt', [ __CLASS__, 'filter_excerpt' ], 10, 3 );
	}

	/**
	 * Filter post title: return product-style title when post type is product.
	 *
	 * @param string $value    Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args     Generator args.
	 * @return string Product title or original value.
	 */
	public static function filter_title( $value, $post_type, $args ) {
		if ( 'product' !== $post_type ) {
			return $value;
		}
		return self::generate_title();
	}

	/**
	 * Filter post content: return product-style description when post type is product.
	 *
	 * @param string $value    Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args     Generator args.
	 * @return string Product description HTML or original value.
	 */
	public static function filter_content( $value, $post_type, $args ) {
		if ( 'product' !== $post_type ) {
			return $value;
		}
		return self::generate_description();
	}

	/**
	 * Filter post excerpt: return product short description when post type is product.
	 *
	 * @param string $value    Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args     Generator args.
	 * @return string Product short description or original value.
	 */
	public static function filter_excerpt( $value, $post_type, $args ) {
		if ( 'product' !== $post_type ) {
			return $value;
		}
		return self::generate_short_description();
	}

	/**
	 * Generate a realistic product title.
	 *
	 * @return string
	 */
	private static function generate_title() {
		$pattern = wp_rand( 0, 1 );
		$type    = self::$product_types[ array_rand( self::$product_types ) ];
		if ( 0 === $pattern ) {
			$adj = self::$adjectives[ array_rand( self::$adjectives ) ];
			return $adj . ' ' . $type;
		}
		$variant = self::$variants[ array_rand( self::$variants ) ];
		return $type . ' - ' . $variant;
	}

	/**
	 * Generate a product long description (HTML).
	 *
	 * @return string
	 */
	private static function generate_description() {
		$intro         = '<p>This product combines quality materials with thoughtful design. Ideal for daily use, it delivers reliability and style.</p>';
		$feature_count = wp_rand( 3, 5 );
		$keys          = array_rand( self::$features, $feature_count );
		if ( ! is_array( $keys ) ) {
			$keys = [ $keys ];
		}
		$items = array_map(
            function ( $key ) {
                return '<li>' . esc_html( self::$features[ $key ] ) . '</li>';
            },
            $keys
        );
		$list  = '<ul>' . implode( "\n", $items ) . '</ul>';
		$specs = '<p><strong>Care:</strong> Wipe clean with a damp cloth. Store in a dry place.</p>';
		return $intro . "\n\n<h3>Features</h3>\n" . $list . "\n\n<h3>Details</h3>\n" . $specs;
	}

	/**
	 * Generate a product short description (one or two sentences).
	 *
	 * @return string
	 */
	private static function generate_short_description() {
		return self::$short_templates[ array_rand( self::$short_templates ) ];
	}
}
