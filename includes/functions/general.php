<?php
/**
 * General utility functions for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

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
    if ( class_exists( 'Classic_Editor' ) )
    {
        // Check the Classic Editor option
        $classic_editor_option = get_option( 'classic-editor-replace' );
        // 'block' means use block editor, 'classic' means use classic editor
        // If option is 'classic' or not set (defaults to classic), Classic Editor is active
        if ( 'block' !== $classic_editor_option )
        {
            $classic_editor_active = true;
        }
    }
    
    // Method 2: Check if filter is set to disable block editor
    if ( !$classic_editor_active && has_filter( 'use_block_editor_for_post_type' ) )
    {
        $use_block_editor = apply_filters( 'use_block_editor_for_post_type', true, $post_type );
        if ( !$use_block_editor )
        {
            $classic_editor_active = true;
        }
    }
    
    // Method 3: Check WordPress core function (if available)
    if ( !$classic_editor_active && function_exists( 'use_block_editor_for_post_type' ) )
    {
        $use_block_editor = use_block_editor_for_post_type( $post_type );
        if ( !$use_block_editor )
        {
            $classic_editor_active = true;
        }
    }
    
    // Default to block editor if WordPress 5.0+ and no Classic Editor detected
    if ( !$classic_editor_active && function_exists( 'use_block_editor_for_post_type' ) )
    {
        $use_block_editor = use_block_editor_for_post_type( $post_type );
        if ( $use_block_editor )
        {
            return 'block';
        }
    }
    
    return $classic_editor_active ? 'classic' : 'block';
}
