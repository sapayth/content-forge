<?php
/**
 * Easy Digital Downloads content provider for Content Forge.
 * Hooks into cforge_generate_post_* filters to supply realistic download title, description, and excerpt (non-AI).
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates realistic copy for EDD downloads (digital products) via filters.
 */
class EDD_Download_Content {

	/**
	 * Digital product types for titles.
	 *
	 * @var string[]
	 */
	private static $product_types = [
		'eBook',
		'Template Pack',
		'WordPress Plugin',
		'Icon Set',
		'Lightroom Preset Pack',
		'Video Course',
		'Stock Photo Bundle',
		'Font Family',
		'Notion Template',
		'Figma UI Kit',
		'Resume Template',
		'Spreadsheet Bundle',
		'Sound Effect Pack',
		'PDF Guide',
		'Starter Theme',
		'Email Template Pack',
		'Canva Kit',
		'Printable Planner',
		'LUT Pack',
		'Action Brushes',
	];

	/**
	 * Adjectives for download titles.
	 *
	 * @var string[]
	 */
	private static $adjectives = [
		'Premium',
		'Pro',
		'Starter',
		'Complete',
		'Ultimate',
		'Essential',
		'Modern',
		'Minimal',
		'Creative',
		'Professional',
	];

	/**
	 * Topic/niche suffixes for titles.
	 *
	 * @var string[]
	 */
	private static $topics = [
		'for Bloggers',
		'for Small Business',
		'2024 Edition',
		'Bundle',
		'Collection',
		'Masterclass',
		'Beginners Guide',
		'Advanced',
		'Minimalist',
		'Dark Mode',
	];

	/**
	 * Feature phrases for download descriptions.
	 *
	 * @var string[]
	 */
	private static $features = [
		'Instant access after purchase',
		'Lifetime updates included',
		'Commercial use license',
		'Fully customizable',
		'Works with popular tools',
		'Step-by-step documentation',
		'Download in multiple formats',
		'Designed for quick workflow',
		'No subscription required',
		'Personal and commercial use',
	];

	/**
	 * Short description templates (one sentence).
	 *
	 * @var string[]
	 */
	private static $short_templates = [
		'Instant digital download. Use in your projects right away.',
		'Professional quality. Commercial license included.',
		'Download once, use forever. Free updates included.',
		'Perfect for creators and small teams.',
		'Get instant access. No recurring fees.',
	];

	/**
	 * Register filters for post title, content, and excerpt when EDD download post type exists.
	 */
	public static function register() {
		if ( ! post_type_exists( 'download' ) ) {
			return;
		}
		add_filter( 'cforge_generate_post_title', [ __CLASS__, 'filter_title' ], 10, 3 );
		add_filter( 'cforge_generate_post_content', [ __CLASS__, 'filter_content' ], 10, 3 );
		add_filter( 'cforge_generate_post_excerpt', [ __CLASS__, 'filter_excerpt' ], 10, 3 );
	}

	/**
	 * Filter post title: return download-style title when post type is download.
	 *
	 * @param string $value     Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args      Generator args.
	 * @return string Download title or original value.
	 */
	public static function filter_title( $value, $post_type, $args ) {
		if ( 'download' !== $post_type ) {
			return $value;
		}
		return self::generate_title();
	}

	/**
	 * Filter post content: return download-style description when post type is download.
	 *
	 * @param string $value     Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args      Generator args.
	 * @return string Download description HTML or original value.
	 */
	public static function filter_content( $value, $post_type, $args ) {
		if ( 'download' !== $post_type ) {
			return $value;
		}
		return self::generate_description();
	}

	/**
	 * Filter post excerpt: return download short description when post type is download.
	 *
	 * @param string $value     Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args      Generator args.
	 * @return string Download short description or original value.
	 */
	public static function filter_excerpt( $value, $post_type, $args ) {
		if ( 'download' !== $post_type ) {
			return $value;
		}
		return self::generate_short_description();
	}

	/**
	 * Generate a realistic download title.
	 *
	 * @return string
	 */
	private static function generate_title() {
		$type = self::$product_types[ array_rand( self::$product_types ) ];
		$pattern = wp_rand( 0, 2 );
		if ( 0 === $pattern ) {
			$adj = self::$adjectives[ array_rand( self::$adjectives ) ];
			return $adj . ' ' . $type;
		}
		if ( 1 === $pattern ) {
			$topic = self::$topics[ array_rand( self::$topics ) ];
			return $type . ' ' . $topic;
		}
		$adj   = self::$adjectives[ array_rand( self::$adjectives ) ];
		$topic = self::$topics[ array_rand( self::$topics ) ];
		return $adj . ' ' . $type . ' ' . $topic;
	}

	/**
	 * Generate a download long description (HTML).
	 *
	 * @return string
	 */
	private static function generate_description() {
		$intro = '<p>This digital product is delivered instantly after purchase. Use it in your personal or commercial projects with the included license.</p>';
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
		$list   = '<ul>' . implode( "\n", $items ) . '</ul>';
		$footer = '<p><strong>License:</strong> Single purchase grants you ongoing use. No subscription. Free updates when available.</p>';
		return $intro . "\n\n<h3>What's included</h3>\n" . $list . "\n\n<h3>License &amp; delivery</h3>\n" . $footer;
	}

	/**
	 * Generate a download short description (one or two sentences).
	 *
	 * @return string
	 */
	private static function generate_short_description() {
		return self::$short_templates[ array_rand( self::$short_templates ) ];
	}
}
