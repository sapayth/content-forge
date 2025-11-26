<?php
/**
 * Base test case for Content Forge plugin tests.
 *
 * @package ContentForge
 */

/**
 * Base test case class that extends WP_UnitTestCase.
 *
 * Provides common setup, teardown, and helper methods for all test cases.
 */
class CForge_Test_Base extends WP_UnitTestCase
{

    /**
     * Set up before each test.
     */
    public function set_up()
    {
        parent::set_up();

        // Ensure the tracking table exists.
        ContentForge\Activator::create_tracking_table();
    }

    /**
     * Tear down after each test.
     */
    public function tear_down()
    {
        // Clean up generated content.
        $this->cleanup_generated_content();

        parent::tear_down();
    }

    /**
     * Clean up all generated content from the tracking table.
     */
    protected function cleanup_generated_content()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        // Get all tracked items.
        $items = $wpdb->get_results(
            "SELECT object_id, data_type FROM {$table}",
            ARRAY_A
        );

        if ( empty( $items ) )
        {
            return;
        }

        // Delete items by type.
        foreach ( $items as $item )
        {
            $object_id = intval( $item[ 'object_id' ] );
            $data_type = $item[ 'data_type' ];

            switch ( $data_type )
            {
                case 'post':
                    wp_delete_post( $object_id, true );
                    break;
                case 'user':
                    if ( function_exists( 'wp_delete_user' ) )
                    {
                        wp_delete_user( $object_id );
                    } else
                    {
                        require_once ABSPATH . 'wp-admin/includes/user.php';
                        wp_delete_user( $object_id );
                    }
                    break;
                case 'comment':
                    wp_delete_comment( $object_id, true );
                    break;
            }
        }

        // Clear the tracking table.
        $wpdb->query( "TRUNCATE TABLE {$table}" );
    }

    /**
     * Assert that a post is tracked in the database.
     *
     * @param int $post_id The post ID to check.
     */
    protected function assertPostIsTracked( $post_id )
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND data_type = %s",
                $post_id,
                'post'
            )
        );

        $this->assertEquals( 1, $count, "Post {$post_id} should be tracked in the database." );
    }

    /**
     * Assert that a user is tracked in the database.
     *
     * @param int $user_id The user ID to check.
     */
    protected function assertUserIsTracked( $user_id )
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND data_type = %s",
                $user_id,
                'user'
            )
        );

        $this->assertEquals( 1, $count, "User {$user_id} should be tracked in the database." );
    }

    /**
     * Assert that a comment is tracked in the database.
     *
     * @param int $comment_id The comment ID to check.
     */
    protected function assertCommentIsTracked( $comment_id )
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND data_type = %s",
                $comment_id,
                'comment'
            )
        );

        $this->assertEquals( 1, $count, "Comment {$comment_id} should be tracked in the database." );
    }

    /**
     * Assert that an item is not tracked in the database.
     *
     * @param int    $object_id The object ID to check.
     * @param string $data_type The data type (post, user, comment).
     */
    protected function assertNotTracked( $object_id, $data_type )
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND data_type = %s",
                $object_id,
                $data_type
            )
        );

        $this->assertEquals( 0, $count, "{$data_type} {$object_id} should not be tracked in the database." );
    }

    /**
     * Get the count of tracked items by type.
     *
     * @param string $data_type The data type (post, user, comment).
     * @return int The count of tracked items.
     */
    protected function getTrackedCount( $data_type )
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE data_type = %s",
                $data_type
            )
        );
    }

    /**
     * Create a test post for use in tests.
     *
     * @param array $args Optional post arguments.
     * @return int The post ID.
     */
    protected function createTestPost( $args = array() )
    {
        $defaults = array(
            'post_title'   => 'Test Post',
            'post_content' => 'Test content',
            'post_status'  => 'publish',
            'post_type'    => 'post',
        );

        $args = wp_parse_args( $args, $defaults );

        return $this->factory->post->create( $args );
    }

    /**
     * Create a test user for use in tests.
     *
     * @param array $args Optional user arguments.
     * @return int The user ID.
     */
    protected function createTestUser( $args = array() )
    {
        $defaults = array(
            'role' => 'subscriber',
        );

        $args = wp_parse_args( $args, $defaults );

        return $this->factory->user->create( $args );
    }
}
