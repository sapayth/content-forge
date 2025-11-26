<?php
/**
 * Tests for the User REST API endpoints.
 *
 * @package ContentForge
 */

/**
 * Test case for ContentForge\Api\User REST API endpoints.
 */
class Test_User_API extends CForge_Test_Base
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
        $request = new WP_REST_Request( 'POST', '/cforge/v1/users/bulk-create' );
        $request->set_param( 'count', 3 );
        $request->set_param( 'roles', array( 'subscriber' ) );

        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status(), 'Response should be successful.' );
        $this->assertTrue( $data[ 'success' ], 'Response should indicate success.' );
        $this->assertCount( 3, $data[ 'ids' ], 'Should return 3 user IDs.' );

        // Verify users were created.
        foreach ( $data[ 'ids' ] as $id )
        {
            $user = get_user_by( 'id', $id );
            $this->assertInstanceOf( 'WP_User', $user );
            $this->assertUserIsTracked( $id );
        }
    }

    /**
     * Test list endpoint.
     */
    public function test_list_endpoint()
    {
        // Generate some users first.
        $generator = new ContentForge\Generator\User();
        $generator->generate( 5 );

        $request  = new WP_REST_Request( 'GET', '/cforge/v1/users' );
        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertArrayHasKey( 'items', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertGreaterThanOrEqual( 5, $data[ 'total' ], 'Should have at least 5 tracked users.' );
    }

    /**
     * Test bulk delete endpoint.
     */
    public function test_bulk_delete_endpoint()
    {
        // Generate users.
        $generator = new ContentForge\Generator\User();
        $ids       = $generator->generate( 3 );

        $request  = new WP_REST_Request( 'DELETE', '/cforge/v1/users/bulk-delete' );
        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $data[ 'success' ] );
        $this->assertEquals( 3, $data[ 'deleted' ], 'Should delete 3 users.' );

        // Verify users are deleted.
        foreach ( $ids as $id )
        {
            $user = get_user_by( 'id', $id );
            $this->assertFalse( $user );
            $this->assertNotTracked( $id, 'user' );
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

        // Try to create users.
        $request = new WP_REST_Request( 'POST', '/cforge/v1/users/bulk-create' );
        $request->set_param( 'count', 1 );
        $response = rest_do_request( $request );

        $this->assertEquals( 403, $response->get_status(), 'Subscriber should not have permission.' );
    }
}
