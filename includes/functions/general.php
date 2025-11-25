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
