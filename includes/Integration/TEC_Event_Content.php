<?php
/**
 * The Events Calendar event content provider for Content Forge.
 * Hooks into cforge_generate_post_* filters to supply realistic event title, description, and excerpt (non-AI).
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates realistic copy for TEC events via filters.
 */
class TEC_Event_Content {

	/**
	 * Event type prefixes for titles.
	 *
	 * @var string[]
	 */
	private static $event_types = [
		'Workshop',
		'Meetup',
		'Webinar',
		'Conference',
		'Networking',
		'Training',
		'Panel Discussion',
		'Bootcamp',
		'Summit',
		'Hackathon',
		'Demo Day',
		'Breakfast',
		'Lunch & Learn',
		'Happy Hour',
		'Open House',
		'Q&A Session',
		'Masterclass',
		'Retreat',
	];

	/**
	 * Event topics/subjects for titles.
	 *
	 * @var string[]
	 */
	private static $topics = [
		'Product Design',
		'Digital Marketing',
		'Startup Funding',
		'Remote Work',
		'Content Strategy',
		'User Research',
		'Growth Hacking',
		'Leadership',
		'Data Analytics',
		'Customer Success',
		'SEO & SEM',
		'No-Code Tools',
		'AI in Business',
		'Community Building',
		'Sustainability',
		'Health & Wellness',
		'Creative Writing',
		'Financial Planning',
	];

	/**
	 * Suffixes (location type or format).
	 *
	 * @var string[]
	 */
	private static $suffixes = [
		'Online',
		'In Person',
		'Hybrid',
		'2024',
		'2025',
		'Monthly',
		'Quarterly',
		'Annual',
	];

	/**
	 * Description phrases for event content.
	 *
	 * @var string[]
	 */
	private static $features = [
		'Join us for an interactive session',
		'Connect with industry experts and peers',
		'Hands-on activities and group discussions',
		'Light refreshments will be served',
		'Networking opportunity before and after',
		'Bring your questions and ideas',
		'All skill levels welcome',
		'Certificate of attendance provided',
		'Limited seats available',
		'Recorded session available for registrants',
	];

	/**
	 * Short description templates.
	 *
	 * @var string[]
	 */
	private static $short_templates = [
		'Join us for an engaging session. All welcome.',
		'Network, learn, and connect with the community.',
		'An evening of talks and networking.',
		'Hands-on workshop. Register to secure your spot.',
		'Free to attend. Registration required.',
	];

	/**
	 * Register filters for post title, content, and excerpt when tribe_events post type exists.
	 */
	public static function register() {
		if ( ! post_type_exists( 'tribe_events' ) ) {
			return;
		}
		add_filter( 'cforge_generate_post_title', [ __CLASS__, 'filter_title' ], 10, 3 );
		add_filter( 'cforge_generate_post_content', [ __CLASS__, 'filter_content' ], 10, 3 );
		add_filter( 'cforge_generate_post_excerpt', [ __CLASS__, 'filter_excerpt' ], 10, 3 );
	}

	/**
	 * Filter post title: return event-style title when post type is tribe_events.
	 *
	 * @param string $value     Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args      Generator args.
	 * @return string Event title or original value.
	 */
	public static function filter_title( $value, $post_type, $args ) {
		if ( 'tribe_events' !== $post_type ) {
			return $value;
		}
		return self::generate_title();
	}

	/**
	 * Filter post content: return event-style description when post type is tribe_events.
	 *
	 * @param string $value     Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args      Generator args.
	 * @return string Event description HTML or original value.
	 */
	public static function filter_content( $value, $post_type, $args ) {
		if ( 'tribe_events' !== $post_type ) {
			return $value;
		}
		return self::generate_description();
	}

	/**
	 * Filter post excerpt: return event short description when post type is tribe_events.
	 *
	 * @param string $value     Current value (default empty).
	 * @param string $post_type Post type.
	 * @param array  $args      Generator args.
	 * @return string Event short description or original value.
	 */
	public static function filter_excerpt( $value, $post_type, $args ) {
		if ( 'tribe_events' !== $post_type ) {
			return $value;
		}
		return self::generate_short_description();
	}

	/**
	 * Generate a realistic event title.
	 *
	 * @return string
	 */
	private static function generate_title() {
		$type = self::$event_types[ array_rand( self::$event_types ) ];
		$topic = self::$topics[ array_rand( self::$topics ) ];
		$pattern = wp_rand( 0, 2 );
		if ( 0 === $pattern ) {
			return $type . ': ' . $topic;
		}
		if ( 1 === $pattern ) {
			$suffix = self::$suffixes[ array_rand( self::$suffixes ) ];
			return $type . ' – ' . $topic . ' (' . $suffix . ')';
		}
		return $topic . ' ' . $type . ' ' . self::$suffixes[ array_rand( self::$suffixes ) ];
	}

	/**
	 * Generate event long description (HTML).
	 *
	 * @return string
	 */
	private static function generate_description() {
		$intro = '<p>We invite you to this event. Whether you are new to the topic or looking to deepen your knowledge, there will be something for you.</p>';
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
		$footer = '<p><strong>Registration:</strong> Please register in advance. Details and link will be sent after you sign up.</p>';
		return $intro . "\n\n<h3>What to expect</h3>\n" . $list . "\n\n<h3>Details</h3>\n" . $footer;
	}

	/**
	 * Generate event short description.
	 *
	 * @return string
	 */
	private static function generate_short_description() {
		return self::$short_templates[ array_rand( self::$short_templates ) ];
	}
}
