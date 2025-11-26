<?php
/**
 * Tests for the Post REST API endpoints.
 *
 * @package ContentForge
 */

/**
 * Test case for ContentForge\Api\Post REST API endpoints.
 */
class Test_Post_API extends CForge_Test_Base
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
    }

    /**
     * Test bulk create endpoint.
     */
    public function test_bulk_create_endpoint()
    {
        $request = new WP_REST_Request( 'POST', '/cforge/v1/posts/bulk-create' );
        $request->set_param( 'count', 3 );
        $request->set_param( 'post_type', 'post' );

        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status(), 'Response should be successful.' );
        $this->assertTrue( $data[ 'success' ], 'Response should indicate success.' );
        $this->assertCount( 3, $data[ 'ids' ], 'Should return 3 post IDs.' );

        // Verify posts were created.
        foreach ( $data[ 'ids' ] as $id )
        {
            $post = get_post( $id );
            $this->assertInstanceOf( 'WP_Post', $post );
            $this->assertPostIsTracked( $id );
        }
    }

    /**
     * Test list endpoint.
     */
    public function test_list_endpoint()
    {
        // Generate some posts first.
        $generator = new ContentForge\Generator\Post();
        $generator->generate( 5 );

        $request  = new WP_REST_Request( 'GET', '/cforge/v1/posts' );
        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertArrayHasKey( 'items', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertGreaterThanOrEqual( 5, $data[ 'total' ], 'Should have at least 5 tracked posts.' );
    }

    /**
     * Test list pagination.
     */
    public function test_list_pagination()
    {
        // Generate posts.
        $generator = new ContentForge\Generator\Post();
        $generator->generate( 10 );

        // Request first page.
        $request = new WP_REST_Request( 'GET', '/cforge/v1/posts' );
        $request->set_param( 'page', 1 );
        $request->set_param( 'per_page', 5 );

        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertCount( 5, $data[ 'items' ], 'First page should have 5 items.' );

        // Request second page.
        $request->set_param( 'page', 2 );
        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertGreaterThanOrEqual( 1, count( $data[ 'items' ] ), 'Second page should have items.' );
    }

    /**
     * Test bulk delete endpoint.
     */
    public function test_bulk_delete_endpoint()
    {
        // Generate posts.
        $generator = new ContentForge\Generator\Post();
        $ids       = $generator->generate( 3 );

        $request  = new WP_REST_Request( 'DELETE', '/cforge/v1/posts/bulk-delete' );
        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $data[ 'success' ] );
        $this->assertEquals( 3, $data[ 'deleted' ], 'Should delete 3 posts.' );

        // Verify posts are deleted.
        foreach ( $ids as $id )
        {
            $post = get_post( $id );
            $this->assertNull( $post );
            $this->assertNotTracked( $id, 'post' );
        }
    }

    /**
     * Test individual delete endpoint.
     */
    public function test_individual_delete_endpoint()
    {
        // Generate a post.
        $generator = new ContentForge\Generator\Post();
        $ids       = $generator->generate( 1 );
        $post_id   = $ids[ 0 ];

        $request  = new WP_REST_Request( 'DELETE', '/cforge/v1/posts/' . $post_id );
        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $data[ 'success' ] );

        // Verify post is deleted.
        $post = get_post( $post_id );
        $this->assertNull( $post );
        $this->assertNotTracked( $post_id, 'post' );
    }

    /**
     * Test permissions for endpoints.
     */
    public function test_permissions()
    {
        // Set current user to subscriber (no permissions).
        $subscriber_id = $this->createTestUser( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber_id );

        // Try to create posts.
        $request = new WP_REST_Request( 'POST', '/cforge/v1/posts/bulk-create' );
        $request->set_param( 'count', 1 );
        $response = rest_do_request( $request );

        $this->assertEquals( 403, $response->get_status(), 'Subscriber should not have permission.' );

        // Try to delete posts.
        $request  = new WP_REST_Request( 'DELETE', '/cforge/v1/posts/bulk-delete' );
        $response = rest_do_request( $request );

        $this->assertEquals( 403, $response->get_status(), 'Subscriber should not have permission.' );
    }

    /**
     * Test invalid parameters.
     */
    public function test_invalid_parameters()
    {
        // Test with invalid count.
        $request = new WP_REST_Request( 'POST', '/cforge/v1/posts/bulk-create' );
        $request->set_param( 'count', -5 );

        $response = rest_do_request( $request );

        $this->assertNotEquals( 200, $response->get_status(), 'Should reject negative count.' );

        // Test with missing required parameters.
        $request  = new WP_REST_Request( 'POST', '/cforge/v1/posts/bulk-create' );
        $response = rest_do_request( $request );

        $this->assertNotEquals( 200, $response->get_status(), 'Should reject missing count parameter.' );
    }
}
