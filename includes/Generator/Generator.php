<?php
/**
 * Abstract base generator class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge\Generator;

use ContentForge\Activator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Abstract base class for all Content Forge content generators.
 */
abstract class Generator {
    /**
     * The user ID who triggers the generation.
     *
     * @var int
     */
    protected $user_id;

    /**
     * Constructor.
     *
     * @param int $user_id The user ID who triggers the generation.
     */
    public function __construct( $user_id ) {
        $this->user_id = $user_id;
    }

    /**
     * Generate content.
     *
     * @param int   $count Number of items to generate.
     * @param array $args  Additional arguments for generation.
     * @return array Array of generated object IDs.
     */
    abstract public function generate( $count = 1, $args = [] );

    /**
     * Delete generated content by IDs.
     *
     * @param array $object_ids Array of object IDs to delete.
     * @return int Number of items deleted.
     */
    abstract public function delete( array $object_ids );

    /**
     * Track generated content in the custom DB table.
     *
     * @param int    $object_id The object ID to track.
     * @param string $data_type The data type (post, user, comment, etc.).
     */
    protected function track_generated( $object_id, $data_type ) {
        global $wpdb;
        // We use direct DB access here because we are tracking generated content in a custom table.
        // Ensure table exists
        if ( class_exists( 'ContentForge\\Activator' ) ) {
            Activator::create_tracking_table();
        }
        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        $created_at = current_time( 'mysql' );
        $created_by = intval( $this->user_id );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert(
            $table_name,
            [
                'object_id'  => $object_id,
                'data_type'  => $data_type,
                'created_at' => $created_at,
                'created_by' => $created_by,
            ],
            [ '%d', '%s', '%s', '%d' ]
        );
    }

    /**
     * Remove tracking info for a deleted object.
     *
     * @param int    $object_id The object ID to untrack.
     * @param string $data_type The data type to untrack.
     */
    protected function untrack_generated( $object_id, $data_type ) {
        global $wpdb;
        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->delete(
            $table_name,
            [
                'object_id' => $object_id,
                'data_type' => $data_type,
            ],
            [ '%d', '%s' ]
        );
    }
}
