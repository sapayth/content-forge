<?php
/**
 * AI Content Generator class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Generator;

use WP_Error;
use ContentForge\Settings\AI_Settings_Manager;
use ContentForge\Content\Content_Type_Data;
use ContentForge\Generator\Providers\AI_Provider_Base;
use ContentForge\Generator\Providers\AI_Provider_OpenAI;
use ContentForge\Generator\Providers\AI_Provider_Anthropic;
use ContentForge\Generator\Providers\AI_Provider_Google;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AI API communication and content generation.
 */
class AI_Content_Generator {
	/**
	 * Provider instance.
	 *
	 * @var AI_Provider_Base
	 */
	protected AI_Provider_Base $provider;

    /**
     * Editor type (block or classic).
     *
     * @var string
     */
    protected string $editor_type;

    /**
     * Model slug.
     *
     * @var string
     */
    protected string $model;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param string $provider    Provider slug.
	 * @param string $model       Model slug.
	 * @param string $api_key     API key.
	 * @param string $editor_type Editor type (block/classic).
	 */
    public function __construct( string $provider, string $model, string $api_key, string $editor_type = 'block' ) {
        $this->editor_type = $editor_type;
        $this->model       = $model;
        $this->provider    = $this->create_provider( $provider, $model, $api_key );
    }

    /**
     * Get model slug.
     *
     * @since 1.2.0
     *
     * @return string Model slug.
     */
    protected function get_model() {
        return $this->model;
    }

	/**
	 * Create provider instance.
	 *
	 * @since 1.2.0
	 *
	 * @param string $provider Provider slug.
	 * @param string $model    Model slug.
	 * @param string $api_key  API key.
	 * @return AI_Provider_Base Provider instance.
	 */
	protected function create_provider( string $provider, string $model, string $api_key ) {
		switch ( $provider ) {
			case AI_Settings_Manager::PROVIDER_OPENAI:
				return new AI_Provider_OpenAI( $api_key, $model );

			case AI_Settings_Manager::PROVIDER_ANTHROPIC:
				return new AI_Provider_Anthropic( $api_key, $model );

			case AI_Settings_Manager::PROVIDER_GOOGLE:
				return new AI_Provider_Google( $api_key, $model );

			default:
				// Default to OpenAI.
				return new AI_Provider_OpenAI( $api_key, $model );
		}
	}

	/**
	 * Generate both title and content in a single API call.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content_type  Content type slug.
	 * @param string $custom_prompt Optional custom prompt.
	 * @return array|WP_Error Array with 'title' and 'content' keys, or WP_Error on failure.
	 */
	public function generate( string $content_type, string $custom_prompt = '' ) {
        /**
         * Fired before AI content generation starts.
         *
         * @since 1.2.0
         *
         * @param string $content_type Content type slug.
         * @param string $custom_prompt Custom prompt.
         * @param string $provider     Provider slug.
         * @param string $model         Model slug.
         */
        $provider_slug = method_exists( $this->provider, 'get_provider_slug' ) ? $this->provider->get_provider_slug() : '';
        $model_slug = $this->get_model();
        do_action( 'cforge_ai_before_generation', $content_type, $custom_prompt, $provider_slug, $model_slug );

		$prompt = $this->build_prompt( $content_type, $custom_prompt );

		$params = [
			'prompt'      => $prompt,
			'content_type' => $content_type,
			'editor_type' => $this->editor_type,
		];

		$response = $this->provider->generate( $params );

		if ( is_wp_error( $response ) ) {
            /**
             * Fired when AI generation fails.
             *
             * @since 1.2.0
             *
             * @param WP_Error $error       Error object.
             * @param string   $content_type Content type slug.
             * @param string   $provider    Provider slug.
             */
            $provider_slug = method_exists( $this->provider, 'get_provider_slug' ) ? $this->provider->get_provider_slug() : '';
            do_action( 'cforge_ai_generation_error', $response, $content_type, $provider_slug );

			return $response;
		}

		// Log raw AI response for debugging
		$raw_title   = isset( $response['title'] ) ? mb_substr( $response['title'], 0, 100 ) : '(no title)';
		$raw_content = isset( $response['content'] ) ? mb_substr( $response['content'], 0, 300 ) : '(no content)';

		// Format content for editor type.
		if ( isset( $response['content'] ) ) {
			$response['content'] = $this->format_content( $response['content'], $this->editor_type );
		}

        $provider_slug = method_exists( $this->provider, 'get_provider_slug' ) ? $this->provider->get_provider_slug() : '';

		/**
         * Fired after AI content generation completes successfully.
         *
         * @since 1.2.0
         *
         * @param array  $result       Generated result with 'title' and 'content'.
         * @param string $content_type Content type slug.
         * @param string $provider     Provider slug.
         */
        do_action( 'cforge_ai_after_generation', $response, $content_type, $provider_slug );

		return $response;
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.2.0
	 *
	 * @return array Array with 'success' and 'message' keys.
	 */
	public function test_connection() {
		$result = $this->provider->test_connection();

		// If the provider already returns a proper array format, use it directly
		if ( is_array( $result ) && isset( $result['success'] ) ) {
			return $result;
		}

		// Fallback for providers that still return boolean
		if ( $result ) {
			return [
				'success' => true,
				'message' => __( 'Connection successful', 'content-forge' ),
			];
		}

		return [
			'success' => false,
			'message' => __( 'Connection failed. Please check your API key and try again.', 'content-forge' ),
		];
	}

	/**
	 * Build prompt for AI generation.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content_type  Content type slug.
	 * @param string $custom_prompt Optional custom prompt.
	 * @return string Generated prompt.
	 */
	protected function build_prompt( string $content_type, string $custom_prompt = '' ) {
		$type_label   = Content_Type_Data::get_type_label( $content_type );
		$type_context = Content_Type_Data::get_type_context( $content_type );
		$type_keywords = Content_Type_Data::get_type_keywords( $content_type );

		$prompt = sprintf(
			"Generate a WordPress blog post with the following requirements:\n\n" .
			"1. Create an engaging, SEO-friendly title (maximum 60 characters)\n" .
			"2. Write comprehensive content (minimum 500 words)\n\n" .
			"Content Type: %s\n" .
			"Context: %s\n" .
			"Keywords to consider: %s\n",
			$type_label,
			$type_context,
			implode( ', ', $type_keywords )
		);

		if ( ! empty( $custom_prompt ) ) {
			$prompt .= "\nAdditional Instructions: " . $custom_prompt . "\n";
		}

		// Build editor-specific format instructions
		if ( 'block' === $this->editor_type ) {
			$editor_instruction = "WordPress Block Editor (FSE/Block Editor format).\n\n" .
				"IMPORTANT: Format the content using WordPress block grammar syntax with HTML comments.\n" .
				"Use the following block format:\n" .
				"- Paragraphs: <!-- wp:paragraph -->\n<p>Your text here</p>\n<!-- /wp:paragraph -->\n" .
				"- Headings: <!-- wp:heading {\"level\":2} -->\n<h2>Heading text</h2>\n<!-- /wp:heading -->\n" .
				"- Lists: <!-- wp:list -->\n<ul><li>Item</li></ul>\n<!-- /wp:list -->\n" .
				"- Blockquotes: <!-- wp:quote -->\n<blockquote><p>Quote text</p></blockquote>\n<!-- /wp:quote -->\n\n" .
				"Every content element must be wrapped in block comment markers. " .
				"Separate blocks with blank lines. Use proper block grammar format throughout.";
		} else {
			$editor_instruction = "Classic HTML Editor format.\n\n" .
				"IMPORTANT: Format the content using standard HTML tags without block comment markers.\n" .
				"Use standard HTML elements:\n" .
				"- Paragraphs: <p>Your text here</p>\n" .
				"- Headings: <h1>, <h2>, <h3>, etc.\n" .
				"- Lists: <ul><li>Item</li></ul> or <ol><li>Item</li></ol>\n" .
				"- Blockquotes: <blockquote><p>Quote text</p></blockquote>\n\n" .
				"Do NOT use WordPress block comment markers (<!-- wp: -->). " .
				"Use clean, standard HTML formatting throughout.";
		}

		$prompt .= "\n\nFormat the response as JSON with 'title' and 'content' keys.\n" .
				   "Content Format: {$editor_instruction}";

		/**
		 * Filter AI generation prompt.
		 *
		 * @since 1.2.0
		 *
		 * @param string $prompt        The generated prompt.
		 * @param string $content_type  Content type slug.
		 * @param string $custom_prompt User-provided custom prompt.
		 * @param string $editor_type   Editor type (block/classic).
		 * @return string Filtered prompt.
		 */
		return apply_filters( 'cforge_ai_generation_prompt', $prompt, $content_type, $custom_prompt, $this->editor_type );
	}

	/**
	 * Format content for the appropriate editor type.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content     Raw content.
	 * @param string $editor_type Editor type (block/classic).
	 * @return string Formatted content.
	 */
	protected function format_content( string $content, string $editor_type ) {
		if ( 'block' === $editor_type ) {
			return $this->format_for_block_editor( $content );
		}

		return $this->format_for_classic_editor( $content );
	}

	/**
	 * Format content for Block Editor.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content Raw content.
	 * @return string Block-formatted content.
	 */
	protected function format_for_block_editor( string $content ) {
		// If content is already in block format, return as is.
		if ( strpos( $content, '<!-- wp:' ) !== false ) {
			return $content;
		}

		// Convert HTML to block format.
		$blocks = [];
		$paragraphs = preg_split( '/\n\s*\n/', trim( $content ) );

		foreach ( $paragraphs as $para ) {
			$para = trim( $para );

			if ( empty( $para ) ) {
				continue;
			}

			// Check if it's a heading.
			if ( preg_match( '/^<h([1-6])>(.*?)<\/h[1-6]>$/', $para, $matches ) ) {
				$level = $matches[1];
				$text  = $matches[2];
				$blocks[] = "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level}>{$text}</h{$level}>\n<!-- /wp:heading -->";
			} elseif ( preg_match( '/^<h([1-6])>(.*?)<\/h[1-6]>/', $para, $matches ) ) {
				$level = $matches[1];
				$text  = strip_tags( $para );
				$blocks[] = "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level}>{$text}</h{$level}>\n<!-- /wp:heading -->";
			} else {
				// Regular paragraph.
				$text = wp_strip_all_tags( $para );
				$blocks[] = "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
			}
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Format content for Classic Editor.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content Raw content.
	 * @return string HTML-formatted content.
	 */
	protected function format_for_classic_editor( string $content ) {
		// If content is in block format, convert to HTML.
		if ( strpos( $content, '<!-- wp:' ) !== false ) {
			// Remove block comments.
			$content = preg_replace( '/<!-- \/?wp:.*? -->/', '', $content );
		}

		// Ensure proper HTML formatting.
		$paragraphs = preg_split( '/\n\s*\n/', trim( $content ) );
		$formatted = [];

		foreach ( $paragraphs as $para ) {
			$para = trim( $para );

			if ( empty( $para ) ) {
				continue;
			}

			// If already has HTML tags, keep as is.
			if ( preg_match( '/^<[^>]+>/', $para ) ) {
				$formatted[] = $para;
			} else {
				// Wrap in paragraph tag.
				$formatted[] = '<p>' . wp_kses_post( $para ) . '</p>';
			}
		}

		return implode( "\n\n", $formatted );
	}
}
