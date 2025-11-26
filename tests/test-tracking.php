<?php
/**
 * Tests for the tracking system.
 *
 * @package ContentForge
 */

/**
 * Test case for the Content Forge tracking system.
 */
class Test_Tracking extends CForge_Test_Base
{

    /**
     * Test tracking table creation.
     */
    public function test_tracking_table_creation()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        // Ensure table exists.
        ContentForge\Activator::create_tracking_table();

        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

        $this->assertEquals( $table, $table_exists, 'Tracking table should exist.' );

        // Check table structure.
        $columns = $wpdb->get_results( "DESCRIBE {$table}" );

        $column_names = wp_list_pluck( $columns, 'Field' );

        $this->assertContains( 'id', $column_names );
        $this->assertContains( 'object_id', $column_names );
        $this->assertContains( 'data_type', $column_names );
        $this->assertContains( 'created_at', $column_names );
        $this->assertContains( 'created_by', $column_names );
    }

    /**
     * Test tracking posts.
     */
    public function test_track_post()
    {
        $generator = new ContentForge\Generator\Post();
        $ids       = $generator->generate( 1 );

        $this->assertPostIsTracked( $ids[ 0 ] );

        // Verify data in tracking table.
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE object_id = %d AND data_type = %s",
                $ids[ 0 ],
                'post'
            ),
            ARRAY_A
        );

        $this->assertNotNull( $row, 'Tracking entry should exist.' );
        $this->assertEquals( $ids[ 0 ], $row[ 'object_id' ] );
        $this->assertEquals( 'post', $row[ 'data_type' ] );
        $this->assertNotEmpty( $row[ 'created_at' ] );
    }

    /**
     * Test tracking users.
     */
    public function test_track_user()
    {
        $generator = new ContentForge\Generator\User();
        $ids       = $generator->generate( 1 );

        $this->assertUserIsTracked( $ids[ 0 ] );

        // Verify data in tracking table.
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE object_id = %d AND data_type = %s",
                $ids[ 0 ],
                'user'
            ),
            ARRAY_A
        );

        $this->assertNotNull( $row, 'Tracking entry should exist.' );
        $this->assertEquals( $ids[ 0 ], $row[ 'object_id' ] );
        $this->assertEquals( 'user', $row[ 'data_type' ] );
    }

    /**
     * Test tracking comments.
     */
    public function test_track_comment()
    {
        // Create a post for comments.
        $this->createTestPost();

        $generator = new ContentForge\Generator\Comment();
        $ids       = $generator->generate( 1 );

        $this->assertCommentIsTracked( $ids[ 0 ] );

        // Verify data in tracking table.
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE object_id = %d AND data_type = %s",
                $ids[ 0 ],
                'comment'
            ),
            ARRAY_A
        );

        $this->assertNotNull( $row, 'Tracking entry should exist.' );
        $this->assertEquals( $ids[ 0 ], $row[ 'object_id' ] );
        $this->assertEquals( 'comment', $row[ 'data_type' ] );
    }

    /**
     * Test untracking on delete.
     */
    public function test_untrack_on_delete()
    {
        // Test post untracking.
        $post_generator = new ContentForge\Generator\Post();
        $post_ids       = $post_generator->generate( 1 );
        $this->assertPostIsTracked( $post_ids[ 0 ] );
        $post_generator->delete( $post_ids );
        $this->assertNotTracked( $post_ids[ 0 ], 'post' );

        // Test user untracking.
        $user_generator = new ContentForge\Generator\User();
        $user_ids       = $user_generator->generate( 1 );
        $this->assertUserIsTracked( $user_ids[ 0 ] );
        $user_generator->delete( $user_ids );
        $this->assertNotTracked( $user_ids[ 0 ], 'user' );

        // Test comment untracking.
        $this->createTestPost();
        $comment_generator = new ContentForge\Generator\Comment();
        $comment_ids       = $comment_generator->generate( 1 );
        $this->assertCommentIsTracked( $comment_ids[ 0 ] );
        $comment_generator->delete( $comment_ids );
        $this->assertNotTracked( $comment_ids[ 0 ], 'comment' );
    }

    /**
     * Test getting tracked items.
     */
    public function test_get_tracked_items()
    {
        // Generate mixed content.
        $post_generator = new ContentForge\Generator\Post();
        $post_generator->generate( 3 );

        $user_generator = new ContentForge\Generator\User();
        $user_generator->generate( 2 );

        $this->createTestPost();
        $comment_generator = new ContentForge\Generator\Comment();
        $comment_generator->generate( 4 );

        // Verify counts.
        $this->assertEquals( 3, $this->getTrackedCount( 'post' ), 'Should track 3 posts.' );
        $this->assertEquals( 2, $this->getTrackedCount( 'user' ), 'Should track 2 users.' );
        $this->assertEquals( 4, $this->getTrackedCount( 'comment' ), 'Should track 4 comments.' );

        // Verify total count.
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        $this->assertEquals( 9, $total, 'Should track 9 total items.' );
    }
}
