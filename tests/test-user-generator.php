<?php
/**
 * Tests for the User Generator class.
 *
 * @package ContentForge
 */

/**
 * Test case for ContentForge\Generator\User class.
 */
class Test_User_Generator extends CForge_Test_Base
{

    /**
     * Test generating a single user.
     */
    public function test_generate_single_user()
    {
        $generator = new ContentForge\Generator\User();
        $ids       = $generator->generate( 1 );

        $this->assertCount( 1, $ids, 'Should generate exactly one user.' );
        $this->assertIsInt( $ids[ 0 ], 'Generated ID should be an integer.' );

        $user = get_user_by( 'id', $ids[ 0 ] );
        $this->assertInstanceOf( 'WP_User', $user, 'Generated ID should correspond to a valid user.' );
        $this->assertNotEmpty( $user->user_login, 'User should have a login.' );
        $this->assertNotEmpty( $user->user_email, 'User should have an email.' );

        // Verify tracking.
        $this->assertUserIsTracked( $ids[ 0 ] );
    }

    /**
     * Test generating multiple users.
     */
    public function test_generate_multiple_users()
    {
        $generator = new ContentForge\Generator\User();
        $count     = 5;
        $ids       = $generator->generate( $count );

        $this->assertCount( $count, $ids, "Should generate exactly {$count} users." );

        foreach ( $ids as $id )
        {
            $this->assertIsInt( $id, 'Each generated ID should be an integer.' );
            $user = get_user_by( 'id', $id );
            $this->assertInstanceOf( 'WP_User', $user, 'Each ID should correspond to a valid user.' );
            $this->assertUserIsTracked( $id );
        }
    }

    /**
     * Test generating users with different roles.
     */
    public function test_generate_with_roles()
    {
        $generator = new ContentForge\Generator\User();
        $ids       = $generator->generate( 3, array( 'roles' => array( 'editor', 'author' ) ) );

        $this->assertCount( 3, $ids );

        foreach ( $ids as $id )
        {
            $user = get_user_by( 'id', $id );
            $this->assertTrue(
                in_array( 'editor', $user->roles ) || in_array( 'author', $user->roles ),
                'User should have either editor or author role.'
            );
        }
    }

    /**
     * Test login randomization produces unique logins.
     */
    public function test_randomize_login()
    {
        $generator = new ContentForge\Generator\User();
        $logins    = array();

        // Generate multiple users and collect logins.
        for ( $i = 0; $i < 5; $i++ )
        {
            $ids      = $generator->generate( 1 );
            $user     = get_user_by( 'id', $ids[ 0 ] );
            $logins[] = $user->user_login;
        }

        // All logins should be unique.
        $unique_logins = array_unique( $logins );
        $this->assertEquals( count( $logins ), count( $unique_logins ), 'All logins should be unique.' );

        // Logins should not be empty.
        foreach ( $logins as $login )
        {
            $this->assertNotEmpty( $login, 'Login should not be empty.' );
        }
    }

    /**
     * Test email randomization produces unique emails.
     */
    public function test_randomize_email()
    {
        $generator = new ContentForge\Generator\User();
        $emails    = array();

        // Generate multiple users and collect emails.
        for ( $i = 0; $i < 5; $i++ )
        {
            $ids      = $generator->generate( 1 );
            $user     = get_user_by( 'id', $ids[ 0 ] );
            $emails[] = $user->user_email;
        }

        // All emails should be unique.
        $unique_emails = array_unique( $emails );
        $this->assertEquals( count( $emails ), count( $unique_emails ), 'All emails should be unique.' );

        // Emails should be valid.
        foreach ( $emails as $email )
        {
            $this->assertNotEmpty( $email, 'Email should not be empty.' );
            $this->assertTrue( is_email( $email ), 'Email should be valid.' );
        }
    }

    /**
     * Test deleting generated users.
     */
    public function test_delete_users()
    {
        $generator = new ContentForge\Generator\User();
        $ids       = $generator->generate( 3 );

        $this->assertCount( 3, $ids );

        // Delete the users.
        $deleted = $generator->delete( $ids );

        $this->assertEquals( 3, $deleted, 'Should delete all 3 users.' );

        // Verify users are deleted.
        foreach ( $ids as $id )
        {
            $user = get_user_by( 'id', $id );
            $this->assertFalse( $user, 'User should be deleted.' );
            $this->assertNotTracked( $id, 'user' );
        }
    }

    /**
     * Test tracking generated users.
     */
    public function test_track_generated()
    {
        $generator = new ContentForge\Generator\User();
        $ids       = $generator->generate( 2 );

        $this->assertEquals( 2, $this->getTrackedCount( 'user' ), 'Should track 2 users.' );

        foreach ( $ids as $id )
        {
            $this->assertUserIsTracked( $id );
        }
    }

    /**
     * Test untracking deleted users.
     */
    public function test_untrack_generated()
    {
        $generator = new ContentForge\Generator\User();
        $ids       = $generator->generate( 1 );

        $this->assertUserIsTracked( $ids[ 0 ] );

        // Delete the user.
        $generator->delete( $ids );

        // Verify untracked.
        $this->assertNotTracked( $ids[ 0 ], 'user' );
    }
}
