<?php
/**
 * Content Type Data class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides content-type-specific data for prompts and generation.
 */
class Content_Type_Data {
	// Content type constants.
	const TYPE_GENERAL    = 'general';
	const TYPE_ECOMMERCE  = 'e-commerce';
	const TYPE_PORTFOLIO  = 'portfolio';
	const TYPE_BUSINESS   = 'business';
	const TYPE_EDUCATION  = 'education';
	const TYPE_HEALTH     = 'health';
	const TYPE_TECHNOLOGY = 'technology';
	const TYPE_FOOD       = 'food';
	const TYPE_TRAVEL     = 'travel';
	const TYPE_FASHION    = 'fashion';

	/**
	 * Get all available content types.
	 *
	 * @since 1.2.0
	 *
	 * @return array Array of content type slugs => data arrays.
	 */
	public static function get_types() {
		$types = [
			self::TYPE_GENERAL    => [
				'label'    => 'General/Blog',
				'context'  => 'General blog posts, articles, and news content',
				'keywords' => [ 'article', 'blog', 'news', 'post', 'writing', 'content' ],
				'examples' => [ 'Blog post', 'News article', 'Opinion piece' ],
			],
			self::TYPE_ECOMMERCE  => [
				'label'    => 'E-commerce',
				'context'  => 'Product descriptions, reviews, shopping guides, and e-commerce content',
				'keywords' => [ 'product', 'review', 'shopping', 'buy', 'purchase', 'customer', 'store' ],
				'examples' => [ 'Product review', 'Shopping guide', 'Buyer\'s guide' ],
			],
			self::TYPE_PORTFOLIO  => [
				'label'    => 'Portfolio',
				'context'  => 'Project showcases, case studies, and creative work presentations',
				'keywords' => [ 'project', 'case study', 'portfolio', 'showcase', 'work', 'creative' ],
				'examples' => [ 'Project showcase', 'Case study', 'Work portfolio' ],
			],
			self::TYPE_BUSINESS   => [
				'label'    => 'Business/Corporate',
				'context'  => 'Business articles, corporate content, and professional insights',
				'keywords' => [ 'business', 'corporate', 'professional', 'company', 'industry', 'market' ],
				'examples' => [ 'Business article', 'Corporate update', 'Industry analysis' ],
			],
			self::TYPE_EDUCATION  => [
				'label'    => 'Education',
				'context'  => 'Educational content, tutorials, courses, and learning materials',
				'keywords' => [ 'education', 'tutorial', 'course', 'learning', 'teaching', 'study' ],
				'examples' => [ 'Tutorial', 'Course content', 'Learning guide' ],
			],
			self::TYPE_HEALTH     => [
				'label'    => 'Health/Medical',
				'context'  => 'Health articles, medical information, and wellness content',
				'keywords' => [ 'health', 'medical', 'wellness', 'fitness', 'treatment', 'care' ],
				'examples' => [ 'Health article', 'Wellness guide', 'Medical information' ],
			],
			self::TYPE_TECHNOLOGY => [
				'label'    => 'Technology',
				'context'  => 'Tech articles, reviews, tutorials, and technology insights',
				'keywords' => [ 'technology', 'tech', 'software', 'hardware', 'digital', 'innovation' ],
				'examples' => [ 'Tech review', 'Software tutorial', 'Technology news' ],
			],
			self::TYPE_FOOD       => [
				'label'    => 'Food/Recipe',
				'context'  => 'Recipe content, food articles, cooking guides, and culinary content',
				'keywords' => [ 'food', 'recipe', 'cooking', 'culinary', 'dish', 'meal' ],
				'examples' => [ 'Recipe', 'Cooking guide', 'Food review' ],
			],
			self::TYPE_TRAVEL     => [
				'label'    => 'Travel',
				'context'  => 'Travel guides, destination content, trip planning, and travel experiences',
				'keywords' => [ 'travel', 'destination', 'trip', 'vacation', 'tour', 'journey' ],
				'examples' => [ 'Travel guide', 'Destination review', 'Trip planning' ],
			],
			self::TYPE_FASHION    => [
				'label'    => 'Fashion',
				'context'  => 'Fashion articles, style guides, trends, and fashion industry content',
				'keywords' => [ 'fashion', 'style', 'trend', 'clothing', 'apparel', 'design' ],
				'examples' => [ 'Fashion article', 'Style guide', 'Trend report' ],
			],
		];

		/**
		 * Filter content types.
		 *
		 * @since 1.2.0
		 *
		 * @param array $types Array of content type slugs => data arrays.
		 * @return array Filtered content types.
		 */
		return apply_filters( 'cforge_content_types', $types );
	}

	/**
	 * Get content type label.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type Content type slug.
	 * @return string Content type label.
	 */
	public static function get_type_label( string $type ) {
		$types = self::get_types();
		return $types[ $type ]['label'] ?? ucfirst( $type );
	}

	/**
	 * Get content type context description.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type Content type slug.
	 * @return string Content type context.
	 */
	public static function get_type_context( string $type ) {
		$types = self::get_types();
		return $types[ $type ]['context'] ?? '';
	}

	/**
	 * Get content type keywords.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type Content type slug.
	 * @return array Array of keywords.
	 */
	public static function get_type_keywords( string $type ) {
		$types = self::get_types();
		return $types[ $type ]['keywords'] ?? [];
	}

	/**
	 * Get content type examples.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type Content type slug.
	 * @return array Array of example titles.
	 */
	public static function get_type_examples( string $type ) {
		$types = self::get_types();
		return $types[ $type ]['examples'] ?? [];
	}

	/**
	 * Check if content type exists.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type Content type slug.
	 * @return bool True if exists, false otherwise.
	 */
	public static function type_exists( string $type ) {
		$types = self::get_types();
		return isset( $types[ $type ] );
	}
}
