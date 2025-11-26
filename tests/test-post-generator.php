<?php
/**
 * Tests for the Post Generator class.
 *
 * @package ContentForge
 */

/**
 * Test case for ContentForge\Generator\Post class.
 */
class Test_Post_Generator extends CForge_Test_Base
{

    /**
     * Test generating a single post.
     */
    public function test_generate_single_post()
    {
        $generator = new ContentForge\Generator\Post();
        $ids       = $generator->generate( 1 );

        $this->assertCount( 1, $ids, 'Should generate exactly one post.' );
        $this->assertIsInt( $ids[ 0 ], 'Generated ID should be an integer.' );

        $post = get_post( $ids[ 0 ] );
        $this->assertInstanceOf( 'WP_Post', $post, 'Generated ID should correspond to a valid post.' );
        $this->assertEquals( 'publish', $post->post_status, 'Post should be published.' );
        $this->assertNotEmpty( $post->post_title, 'Post should have a title.' );
        $this->assertNotEmpty( $post->post_content, 'Post should have content.' );

        // Verify tracking.
        $this->assertPostIsTracked( $ids[ 0 ] );
    }

    /**
     * Test generating multiple posts.
     */
    public function test_generate_multiple_posts()
    {
        $generator = new ContentForge\Generator\Post();
        $count     = 5;
        $ids       = $generator->generate( $count );

        $this->assertCount( $count, $ids, "Should generate exactly {$count} posts." );

        foreach ( $ids as $id )
        {
            $this->assertIsInt( $id, 'Each generated ID should be an integer.' );
            $post = get_post( $id );
            $this->assertInstanceOf( 'WP_Post', $post, 'Each ID should correspond to a valid post.' );
            $this->assertPostIsTracked( $id );
        }
    }

    /**
     * Test generating posts with custom post type.
     */
    public function test_generate_with_custom_post_type()
    {
        // Register a custom post type for testing.
        register_post_type( 'test_cpt', array( 'public' => true ) );

        $generator = new ContentForge\Generator\Post();
        $ids       = $generator->generate( 1, array( 'post_type' => 'test_cpt' ) );

        $this->assertCount( 1, $ids );
        $post = get_post( $ids[ 0 ] );
        $this->assertEquals( 'test_cpt', $post->post_type, 'Post should have the custom post type.' );
    }

    /**
     * Test title randomization produces varied titles.
     */
    public function test_randomize_title()
    {
        $generator = new ContentForge\Generator\Post();
        $titles    = array();

        // Generate multiple titles.
        for ( $i = 0; $i < 10; $i++ )
        {
            $reflection = new ReflectionClass( $generator );
            $method     = $reflection->getMethod( 'randomize_title' );
            $method->setAccessible( true );
            $titles[] = $method->invoke( $generator );
        }

        // Check that we have some variety (at least 5 unique titles out of 10).
        $unique_titles = array_unique( $titles );
        $this->assertGreaterThanOrEqual( 5, count( $unique_titles ), 'Should generate varied titles.' );

        // Check that titles are not empty.
        foreach ( $titles as $title )
        {
            $this->assertNotEmpty( $title, 'Title should not be empty.' );
        }
    }

    /**
     * Test content randomization produces varied content.
     */
    public function test_randomize_content()
    {
        $generator = new ContentForge\Generator\Post();
        $contents  = array();

        // Generate multiple content blocks.
        for ( $i = 0; $i < 5; $i++ )
        {
            $reflection = new ReflectionClass( $generator );
            $method     = $reflection->getMethod( 'randomize_content' );
            $method->setAccessible( true );
            $contents[] = $method->invoke( $generator );
        }

        // Check that we have some variety.
        $unique_contents = array_unique( $contents );
        $this->assertGreaterThanOrEqual( 3, count( $unique_contents ), 'Should generate varied content.' );

        // Check that content is not empty and contains HTML.
        foreach ( $contents as $content )
        {
            $this->assertNotEmpty( $content, 'Content should not be empty.' );
            $this->assertStringContainsString( '<', $content, 'Content should contain HTML tags.' );
        }
    }

    /**
     * Test different content types are generated.
     */
    public function test_content_types()
    {
        $generator  = new ContentForge\Generator\Post();
        $reflection = new ReflectionClass( $generator );
        $method     = $reflection->getMethod( 'randomize_content' );
        $method->setAccessible( true );

        $content_types = array();

        // Generate multiple content blocks and check for variety.
        for ( $i = 0; $i < 20; $i++ )
        {
            $content = $method->invoke( $generator );

            // Detect content type by markers.
            if ( strpos( $content, '<h3>' ) !== false && strpos( $content, '</h3>' ) !== false )
            {
                $content_types[ 'listicle' ] = true;
            }
            if ( strpos( $content, 'Step' ) !== false )
            {
                $content_types[ 'howto' ] = true;
            }
            if ( strpos( $content, '<blockquote>' ) !== false )
            {
                $content_types[ 'opinion' ] = true;
            }
        }

        // We should have generated at least 2 different content types.
        $this->assertGreaterThanOrEqual( 2, count( $content_types ), 'Should generate different content types.' );
    }

    /**
     * Test deleting generated posts.
     */
    public function test_delete_posts()
    {
        $generator = new ContentForge\Generator\Post();
        $ids       = $generator->generate( 3 );

        $this->assertCount( 3, $ids );

        // Delete the posts.
        $deleted = $generator->delete( $ids );

        $this->assertEquals( 3, $deleted, 'Should delete all 3 posts.' );

        // Verify posts are deleted.
        foreach ( $ids as $id )
        {
            $post = get_post( $id );
            $this->assertNull( $post, 'Post should be deleted.' );
            $this->assertNotTracked( $id, 'post' );
        }
    }

    /**
     * Test tracking generated posts.
     */
    public function test_track_generated()
    {
        $generator = new ContentForge\Generator\Post();
        $ids       = $generator->generate( 2 );

        $this->assertEquals( 2, $this->getTrackedCount( 'post' ), 'Should track 2 posts.' );

        foreach ( $ids as $id )
        {
            $this->assertPostIsTracked( $id );
        }
    }

    /**
     * Test untracking deleted posts.
     */
    public function test_untrack_generated()
    {
        $generator = new ContentForge\Generator\Post();
        $ids       = $generator->generate( 1 );

        $this->assertPostIsTracked( $ids[ 0 ] );

        // Delete the post.
        $generator->delete( $ids );

        // Verify untracked.
        $this->assertNotTracked( $ids[ 0 ], 'post' );
    }
}
