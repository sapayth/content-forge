<?php
/**
 * Taxonomy generator class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge\Generator;

use WP_Error;

if ( !defined( 'ABSPATH' ) )
{
    exit;
}

/**
 * Generator for fake taxonomy terms.
 */
class Taxonomy extends Generator
{

    /**
     * Generate fake terms.
     *
     * @param int   $count Number of terms to generate.
     * @param array $args  Arguments array with 'taxonomy'.
     *
     * @return array Array of generated term IDs.
     */
    public function generate( $count = 1, $args = [] )
    {
        $ids      = [];
        $taxonomy = isset( $args[ 'taxonomy' ] ) ? sanitize_key( $args[ 'taxonomy' ] ) : 'category';

        if ( !taxonomy_exists( $taxonomy ) )
        {
            return $ids;
        }

        for ( $i = 0; $i < $count; $i++ )
        {
            $term_name = $this->randomize_name();
            $term      = wp_insert_term( $term_name, $taxonomy );

            if ( !is_wp_error( $term ) && isset( $term[ 'term_id' ] ) )
            {
                $term_id = $term[ 'term_id' ];
                $ids[]   = $term_id;
                $this->track_generated( $term_id, $taxonomy );
            }
        }

        return $ids;
    }

    /**
     * Generate a random term name.
     *
     * @return string
     */
    private function randomize_name()
    {
        // Single-word categories
        $single_words = [
            'Technology',
            'Business',
            'Lifestyle',
            'Health',
            'Travel',
            'Food',
            'Fashion',
            'Sports',
            'Entertainment',
            'Education',
            'Science',
            'Politics',
            'Culture',
            'Finance',
            'Marketing',
            'Design',
            'Photography',
            'Music',
            'Art',
            'Nature',
        ];

        // Two-word categories
        $prefixes = [
            'Digital',
            'Modern',
            'Creative',
            'Personal',
            'Professional',
            'Social',
            'Global',
            'Local',
            'Sustainable',
            'Innovative',
            'Traditional',
            'Contemporary',
            'Urban',
            'Rural',
            'Outdoor',
            'Indoor',
            'Online',
            'Mobile',
        ];

        $topics = [
            'Marketing',
            'Development',
            'Design',
            'Strategy',
            'Media',
            'Commerce',
            'Finance',
            'Wellness',
            'Fitness',
            'Cooking',
            'Photography',
            'Writing',
            'Gaming',
            'Gardening',
            'Parenting',
            'Productivity',
            'Leadership',
            'Innovation',
        ];

        // Specific topic categories
        $specific_categories = [
            'Web Development',
            'Mobile Apps',
            'Artificial Intelligence',
            'Machine Learning',
            'Data Science',
            'Cybersecurity',
            'Cloud Computing',
            'E-commerce',
            'Content Marketing',
            'SEO & SEM',
            'Graphic Design',
            'UI/UX Design',
            'Video Production',
            'Podcast',
            'Book Reviews',
            'Movie Reviews',
            'Product Reviews',
            'How-to Guides',
            'Case Studies',
            'Industry News',
            'Career Advice',
            'Startup Stories',
            'Home Improvement',
            'Interior Design',
            'Healthy Recipes',
            'Workout Routines',
            'Mental Health',
            'Personal Finance',
            'Investment Tips',
            'DIY Projects',
        ];

        // Randomly choose a pattern
        $pattern = wp_rand( 1, 3 );

        switch ( $pattern )
        {
            case 1:
                // Single word
                $name = $single_words[ array_rand( $single_words ) ];
                break;
            case 2:
                // Prefix + Topic
                $name = $prefixes[ array_rand( $prefixes ) ] . ' ' . $topics[ array_rand( $topics ) ];
                break;
            case 3:
                // Specific category
                $name = $specific_categories[ array_rand( $specific_categories ) ];
                break;
            default:
                $name = $single_words[ array_rand( $single_words ) ];
        }

        // Check if term already exists, if so, try again (max 5 attempts)
        $attempts      = 0;
        $original_name = $name;
        while ( term_exists( $name ) && $attempts < 5 )
        {
            // Try a different pattern or add a subtle variation
            $attempts++;
            if ( $attempts === 1 )
            {
                $name = $prefixes[ array_rand( $prefixes ) ] . ' ' . $original_name;
            } elseif ( $attempts === 2 )
            {
                $name = $specific_categories[ array_rand( $specific_categories ) ];
            } elseif ( $attempts === 3 )
            {
                $name = $single_words[ array_rand( $single_words ) ];
            } else
            {
                // Last resort: add a number but make it look more natural
                $name = $original_name . ' ' . wp_rand( 2020, 2025 );
            }
        }

        return $name;
    }

    /**
     * Delete generated terms by IDs.
     *
     * @param array $object_ids Array of term IDs to delete.
     *
     * @return int Number of items deleted.
     */
    public function delete( array $object_ids )
    {
        $deleted = 0;
        // We need to know the taxonomy to delete a term, but delete() interface only accepts IDs.
        // However, wp_delete_term requires taxonomy.
        // First try to get taxonomy from tracking table, then fallback to WordPress term_taxonomy table.

        global $wpdb;
        $table_name = $wpdb->prefix . CFORGE_DBNAME;

        foreach ( $object_ids as $term_id )
        {
            // Get taxonomy from tracking table
            $taxonomy = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT data_type FROM $table_name WHERE object_id = %d",
                    $term_id
                )
            );

            // If taxonomy from tracking table doesn't exist or is invalid, query WordPress directly
            if ( !$taxonomy || !taxonomy_exists( $taxonomy ) )
            {
                // Get taxonomy from WordPress term_taxonomy table
                $term_taxonomy = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d LIMIT 1",
                        $term_id
                    )
                );

                if ( $term_taxonomy && taxonomy_exists( $term_taxonomy ) )
                {
                    $taxonomy = $term_taxonomy;
                    
                    // Update tracking table with correct taxonomy (we know it had invalid data since we're here)
                    // Delete old invalid entry if it exists
                    $wpdb->delete(
                        $table_name,
                        [ 'object_id' => $term_id ],
                        [ '%d' ]
                    );
                    // Insert correct entry
                    $wpdb->insert(
                        $table_name,
                        [
                            'object_id'  => $term_id,
                            'data_type'  => $taxonomy,
                            'created_at' => current_time( 'mysql' ),
                            'created_by' => intval( $this->user_id ),
                        ],
                        [ '%d', '%s', '%s', '%d' ]
                    );
                } else
                {
                    // Clean up invalid tracking entry if it exists
                    if ( $taxonomy )
                    {
                        $wpdb->delete(
                            $table_name,
                            [ 'object_id' => $term_id, 'data_type' => $taxonomy ],
                            [ '%d', '%s' ]
                        );
                    }
                    continue;
                }
            }

            // At this point, we have a valid taxonomy
            if ( $taxonomy && taxonomy_exists( $taxonomy ) )
            {
                $delete_result = wp_delete_term( $term_id, $taxonomy );

                if ( $delete_result && !is_wp_error( $delete_result ) )
                {
                    ++$deleted;
                    $this->untrack_generated( $term_id, $taxonomy );
                }
            }
        }

        return $deleted;
    }
}
