<?php
/**
 * General utility functions for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

/**
 * Get post types allowed for Content Forge generation (bulk create, list, delete).
 * Supported: WeDocs (docs), WooCommerce (product), EDD (download),
 * WP User Frontend (wpuf_subscription), The Events Calendar (tribe_events).
 *
 * @return array List of post type slugs.
 */
function cforge_get_allowed_post_types() {
	$default = [ 'post', 'page' ];
	if ( function_exists( 'wedocs' ) ) {
		$default[] = 'docs';
	}
	if ( class_exists( 'WooCommerce', false ) ) {
		$default[] = 'product';
	}
	if ( post_type_exists( 'download' ) ) {
		$default[] = 'download';
	}
	if ( post_type_exists( 'wpuf_subscription' ) ) {
		$default[] = 'wpuf_subscription';
	}
	if ( post_type_exists( 'tribe_events' ) ) {
		$default[] = 'tribe_events';
	}
	return apply_filters( 'cforge_allowed_post_types', $default );
}

/**
 * Get the IDs of users eligible to be assigned as authors for a post type.
 *
 * Used by the "random authors" assignment mode. Mirrors the author list shown
 * in the admin UI by filtering to users who can publish the given post type.
 *
 * @param string $post_type Post type slug. Default 'post'.
 * @param int    $limit     Maximum number of users to return. Default 100.
 *
 * @return array<int, int> List of user IDs.
 */
function cforge_get_eligible_author_ids( $post_type = 'post', $limit = 100 ) {
	$post_type_object = get_post_type_object( $post_type );
	$capability       = ( $post_type_object && isset( $post_type_object->cap->publish_posts ) )
		? $post_type_object->cap->publish_posts
		: 'publish_posts';

	$user_ids = get_users(
		[
			'capability' => $capability,
			'number'     => $limit,
			'fields'     => 'ID',
		]
	);

	return array_map( 'intval', $user_ids );
}

/**
 * Get random post id's from selected post types.
 *
 * @param array $post_types Array of post types to get posts from.
 * @param int   $count      Number of posts to retrieve.
 *
 * @return array
 */
function cforge_get_random_post_ids( $post_types, $count )
{
    $args = [
        'post_type'      => $post_types,
        'posts_per_page' => $count * 3, // Get more than needed for randomness
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'orderby'        => 'rand',
    ];

    return get_posts( $args );
}

/**
 * Detect if Block Editor or Classic Editor is active for a post type.
 *
 * @param string $post_type Post type to check. Default 'post'.
 * @return string 'block' if Block Editor is active, 'classic' if Classic Editor is active.
 */
function cforge_detect_editor_type( $post_type = 'post' )
{
    // Check if Classic Editor plugin is active
    $classic_editor_active = false;

    // Method 1: Check if Classic_Editor class exists (Classic Editor plugin)
    if ( class_exists( 'Classic_Editor' ) ) {
        // Check the Classic Editor option
        $classic_editor_option = get_option( 'classic-editor-replace' );
        // 'block' means use block editor, 'classic' means use classic editor
        // If option is 'classic' or not set (defaults to classic), Classic Editor is active
        if ( 'block' !== $classic_editor_option ) {
            $classic_editor_active = true;
        }
    }

    // Method 2: Check if filter is set to disable block editor
    if ( ! $classic_editor_active && has_filter( 'use_block_editor_for_post_type' ) ) {
        $use_block_editor = apply_filters( 'use_block_editor_for_post_type', true, $post_type );
        if ( ! $use_block_editor ) {
            $classic_editor_active = true;
        }
    }

    // Method 3: Check WordPress core function (if available)
    if ( ! $classic_editor_active && function_exists( 'use_block_editor_for_post_type' ) ) {
        $use_block_editor = use_block_editor_for_post_type( $post_type );
        if ( ! $use_block_editor ) {
            $classic_editor_active = true;
        }
    }

    // Default to block editor if WordPress 5.0+ and no Classic Editor detected
    if ( ! $classic_editor_active && function_exists( 'use_block_editor_for_post_type' ) ) {
        $use_block_editor = use_block_editor_for_post_type( $post_type );
        if ( $use_block_editor ) {
            return 'block';
        }
    }

    return $classic_editor_active ? 'classic' : 'block';
}

/**
 * Get the taxonomies that generated content can be assigned to for a post type.
 *
 * Excludes non-public and internal taxonomies (post formats, theme metadata),
 * which are never useful as generated test data.
 *
 * @since 1.7.0
 *
 * @param string $post_type Post type slug.
 *
 * @return array<int, \WP_Taxonomy> Assignable taxonomy objects.
 */
function cforge_get_assignable_taxonomies( $post_type ) {
	$excluded = [ 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category' ];
	$result   = [];

	foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
		if ( in_array( $taxonomy->name, $excluded, true ) || ! $taxonomy->public ) {
			continue;
		}
		$result[] = $taxonomy;
	}

	/**
	 * Filter the taxonomies offered for assignment to generated content.
	 *
	 * @since 1.7.0
	 *
	 * @param array<int, \WP_Taxonomy> $result    Assignable taxonomy objects.
	 * @param string                   $post_type Post type slug.
	 */
	return apply_filters( 'cforge_assignable_taxonomies', $result, $post_type );
}
