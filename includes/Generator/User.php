<?php
/**
 * User generator class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge\Generator;

use WP_Error;
use ContentForge\Activator;

global $wpdb;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generator for fake users.
 */
class User extends Generator {

    /**
     * Data type for this generator.
     *
     * @var string
     */
    protected $data_type = 'user';

    /**
     * Generate fake users.
     *
     * @param int   $count Number of users to generate.
     * @param array $args  Arguments array with 'roles'.
     *
     * @return array Array of generated user IDs.
     */
    public function generate( $count = 1, $args = [] )
    {
        $ids   = [];
        $roles = isset( $args['roles'] ) ? (array) $args['roles'] : [ 'subscriber' ];
        for ( $i = 0; $i < $count; $i++ ) {
            $userdata = [
                'user_login' => $this->randomize_login(),
                'user_pass'  => wp_generate_password( 12, true ),
                'user_email' => $this->randomize_email(),
                'first_name' => $this->randomize_first_name(),
                'last_name'  => $this->randomize_last_name(),
                'role'       => $roles[ array_rand( $roles ) ],
            ];
            /**
             * Filter the generated user data before creation.
             */
            $userdata = apply_filters( 'cforge_generate_user_data', $userdata, $i, $args );
            /**
             * Action before user is created.
             */
            do_action( 'cforge_before_generate_user', $userdata, $i, $args );
            $user_id = wp_insert_user( $userdata );
            if ( ! is_wp_error( $user_id ) && $user_id ) {
                $ids[] = $user_id;
                $this->track_generated( $user_id );
                /**
                 * Action after user is created.
                 */
                do_action( 'cforge_after_generate_user', $user_id, $userdata, $i, $args );
            }
        }
        return $ids;
    }

    /**
     * Generate a random login name.
     *
     * @return string
     */
    private function randomize_login()
    {
        return strtolower( $this->randomize_first_name() . $this->randomize_last_name() . wp_rand( 1000, 9999 ) );
    }

    /**
     * Generate a random email address.
     *
     * @return string
     */
    private function randomize_email()
    {
        $domains = [ 'example.com', 'testmail.com', 'mailinator.com', 'demo.org' ];
        return strtolower( $this->randomize_first_name() . '.' . $this->randomize_last_name() . wp_rand( 1000, 9999 ) . '@' . $domains[ array_rand( $domains ) ] );
    }

    /**
     * Generate a random first name.
     *
     * @return string
     */
    private function randomize_first_name()
    {
        $names = [
            'Alex',
            'Jordan',
            'Taylor',
            'Morgan',
            'Casey',
            'Riley',
            'Jamie',
            'Avery',
            'Parker',
            'Quinn',
            'Skyler',
            'Rowan',
            'Sawyer',
            'Dakota',
            'Reese',
            'Emerson',
            'Finley',
            'Harper',
            'Kendall',
            'Logan',
        ];
        return $names[ array_rand( $names ) ];
    }

    /**
     * Generate a random last name.
     *
     * @return string
     */
    private function randomize_last_name()
    {
        $names = [
            'Smith',
            'Johnson',
            'Williams',
            'Brown',
            'Jones',
            'Garcia',
            'Miller',
            'Davis',
            'Martinez',
            'Hernandez',
            'Lopez',
            'Gonzalez',
            'Wilson',
            'Anderson',
            'Thomas',
            'Taylor',
            'Moore',
            'Jackson',
            'Martin',
            'Lee',
        ];
        return $names[ array_rand( $names ) ];
    }

    /**
     * Delete generated users by IDs.
     *
     * @param array $object_ids Array of user IDs to delete.
     *
     * @return int Number of items deleted.
     */
    public function delete( array $object_ids )
    {
        $deleted = 0;

        if ( ! function_exists( 'wp_delete_user' ) ) {
            // require user.php file
            require_once ABSPATH . 'wp-admin/includes/user.php';

        }

        foreach ( $object_ids as $user_id ) {
            if ( wp_delete_user( $user_id ) ) {
                ++$deleted;
                $this->untrack_generated( $user_id );
            }
        }
        return $deleted;
    }

    /**
     * Track generated user in the custom DB table.
     *
     * @param int $user_id The user ID to track.
     */
    protected function track_generated( $user_id )
    {
        global $wpdb;
        // We use direct DB access here because we are tracking generated users in a custom table,
        // and there is no WordPress API for this use case. All data is sanitized and prepared.
        Activator::create_tracking_table();

        $table      = $wpdb->prefix . 'cforge';
        $object_id  = intval( $user_id );
        $data_type  = 'user';
        $created_at = current_time( 'mysql' );
        $created_by = intval( $this->user_id );

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}cforge (object_id, data_type, created_at, created_by) VALUES (%d, %s, %s, %d)",
                $object_id,
                $data_type,
                $created_at,
                $created_by
            )
        );

        if ( false === $result ) {
            // Optionally log or handle the error - removing error_log for production
            // error_log( 'Failed to insert generated user tracking record for user_id: ' . $object_id );
            // For now, we silently handle the error
        }
    }

    /**
     * Remove tracking info for a deleted user.
     *
     * @param int $user_id The user ID to untrack.
     */
    protected function untrack_generated( $user_id )
    {
        global $wpdb;
        $table     = $wpdb->prefix . 'cforge';
        $object_id = intval( $user_id );
        $data_type = 'user';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}cforge WHERE object_id = %d AND data_type = %s",
                $object_id,
                $data_type
            )
        );
    }
}
