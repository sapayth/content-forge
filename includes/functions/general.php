<?php

/**
 * Get random post id's from selected post types.
 *
 * @param array $post_types
 * @param int   $count
 *
 * @return array
 */
function cforge_get_random_post_ids($post_types, $count)
{
    $args = [
        'post_type' => $post_types,
        'posts_per_page' => $count * 3, // Get more than needed for randomness
        'post_status' => 'publish',
        'fields' => 'ids',
        'orderby' => 'rand',
    ];

    return get_posts($args);
}