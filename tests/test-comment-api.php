<?php
/**
 * Tests for the Comment REST API endpoints.
 *
 * @package ContentForge
 */

/**
 * Test case for ContentForge\Api\Comment REST API endpoints.
 */
class Test_Comment_API extends CForge_Test_Base
{

    /**
     * Administrator user ID for testing.
     *
     * @var int
     */
    protected $admin_id;

    /**
     * Set up before each test.
     */
    public function set_up()
    {
        parent::set_up();

        // Create an administrator user for API requests.
        $this->admin_id = $this->createTestUser( array( 'role' => 'administrator' ) );
        wp_set_current_user( $this->admin_id );

        // Create a post for comments.
        $this->createTestPost();
    }

    /**
     * Test bulk create endpoint.
     */
    public function test_bulk_create_endpoint()
    {
        $request = new WP_REST_Request( 'POST', '/cforge/v1/comments/bulk-create' );
        $request->set_param( 'count', 3 );
        $request->set_param( 'post_types', array( 'post' ) );

        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status(), 'Response should be successful.' );
        $this->assertTrue( $data[ 'success' ], 'Response should indicate success.' );
        $this->assertCount( 3, $data[ 'ids' ], 'Should return 3 comment IDs.' );

        // Verify comments were created.
        foreach ( $data[ 'ids' ] as $id )
        {
            $comment = get_comment( $id );
            $this->assertInstanceOf( 'WP_Comment', $comment );
            $this->assertCommentIsTracked( $id );
        }
    }

    /**
     * Test list endpoint.
     */
    public function test_list_endpoint()
    {
        // Generate some comments first.
        $generator = new ContentForge\Generator\Comment();
        $generator->generate( 5 );

        $request  = new WP_REST_Request( 'GET', '/cforge/v1/comments' );
        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertArrayHasKey( 'items', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertGreaterThanOrEqual( 5, $data[ 'total' ], 'Should have at least 5 tracked comments.' );
    }

    /**
     * Test bulk delete endpoint.
     */
    public function test_bulk_delete_endpoint()
    {
        // Generate comments.
        $generator = new ContentForge\Generator\Comment();
        $ids       = $generator->generate( 3 );

        $request  = new WP_REST_Request( 'DELETE', '/cforge/v1/comments/bulk-delete' );
        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $data[ 'success' ] );
        $this->assertEquals( 3, $data[ 'deleted' ], 'Should delete 3 comments.' );

        // Verify comments are deleted.
        foreach ( $ids as $id )
        {
            $comment = get_comment( $id );
            $this->assertNull( $comment );
            $this->assertNotTracked( $id, 'comment' );
        }
    }

    /**
     * Test permissions for endpoints.
     */
    public function test_permissions()
    {
        // Set current user to subscriber (no permissions).
        $subscriber_id = $this->createTestUser( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber_id );

        // Try to create comments.
        $request = new WP_REST_Request( 'POST', '/cforge/v1/comments/bulk-create' );
        $request->set_param( 'count', 1 );
        $response = rest_do_request( $request );

        $this->assertEquals( 403, $response->get_status(), 'Subscriber should not have permission.' );
    }
}
