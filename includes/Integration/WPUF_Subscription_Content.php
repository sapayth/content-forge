<?php
/**
 * WP User Frontend subscription pack content provider for Content Forge.
 * Hooks into cforge_generate_post_* filters to supply realistic pack title; content/excerpt left empty (subscription supports title only).
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates realistic copy for WPUF subscription packs via filters.
 */
class WPUF_Subscription_Content {

	/**
	 * Pack names: tier-style (Basic, Premium, Gold, Professional, etc.).
	 *
	 * @var string[]
	 */
	private static $pack_names = [
		'Basic',
		'Premium',
		'Gold',
		'Professional',
		'Silver',
		'Platinum',
		'Starter',
		'Standard',
		'Business',
		'Elite',
		'Pro',
		'Enterprise',
	];

	/**
	 * Short pack description lines (one or two sentences).
	 *
	 * @var string[]
	 */
	private static $descriptions = [
		'<p>Ideal for getting started. Includes essential posting and profile features.</p>',
		'<p>Best for regular contributors. More posts and extended access.</p>',
		'<p>For power users and small teams. Higher limits and priority support.</p>',
		'<p>Full access to all features. Unlimited posts and premium options.</p>',
		'<p>Flexible plan for creators. Scale as you grow.</p>',
		'<p>Professional tier with advanced capabilities and support.</p>',
	];

	/**
	 * Optional suffixes (billing period or type). Empty string = pack name only.
	 *
	 * @var string[]
	 */
	private static $suffixes = [
		'Monthly',
		'Yearly',
		'Annual',
		'Pack',
		'Plan',
		'',
	];

	/**
	 * Register filters for post title, content, and excerpt when wpuf_subscription post type exists.
	 */
	public static function register() {
		if ( ! post_type_exists( 'wpuf_subscription' ) ) {
			return;
		}
		add_filter( 'cforge_generate_post_title', [ __CLASS__, 'filter_title' ], 10, 3 );
		add_filter( 'cforge_generate_post_content', [ __CLASS__, 'filter_content' ], 10, 3 );
		add_filter( 'cforge_generate_post_excerpt', [ __CLASS__, 'filter_excerpt' ], 10, 3 );
	}

	/**
	 * Filter post title: return pack-style name when post type is wpuf_subscription.
	 *
	 * @param string $value     Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args      Generator args.
	 * @return string Pack title or original value.
	 */
	public static function filter_title( $value, $post_type, $args ) {
		if ( 'wpuf_subscription' !== $post_type ) {
			return $value;
		}
		return self::generate_title();
	}

	/**
	 * Filter post content: return empty for subscription (pack uses title only).
	 *
	 * @param string $value     Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args      Generator args.
	 * @return string Empty or original value.
	 */
	public static function filter_content( $value, $post_type, $args ) {
		if ( 'wpuf_subscription' !== $post_type ) {
			return $value;
		}
		return self::generate_description();
	}

	/**
	 * Filter post excerpt: return empty for subscription.
	 *
	 * @param string $value     Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args      Generator args.
	 * @return string Empty or original value.
	 */
	public static function filter_excerpt( $value, $post_type, $args ) {
		if ( 'wpuf_subscription' !== $post_type ) {
			return $value;
		}
		return '';
	}

	/**
	 * Generate a realistic subscription pack title.
	 *
	 * @return string
	 */
	private static function generate_title() {
		$name   = self::$pack_names[ array_rand( self::$pack_names ) ];
		$suffix = self::$suffixes[ array_rand( self::$suffixes ) ];
		if ( '' === $suffix ) {
			return $name;
		}
		return $name . ' ' . $suffix;
	}

	/**
	 * Generate a short pack description (HTML).
	 *
	 * @return string
	 */
	private static function generate_description() {
		return self::$descriptions[ array_rand( self::$descriptions ) ];
	}
}
