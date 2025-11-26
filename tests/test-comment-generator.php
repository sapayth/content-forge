<?php
/**
 * Tests for the Comment Generator class.
 *
 * @package ContentForge
 */

/**
 * Test case for ContentForge\Generator\Comment class.
 */
class Test_Comment_Generator extends CForge_Test_Base
{

    /**
     * Test generating a single comment.
     */
    public function test_generate_single_comment()
    {
        // Create a post to comment on.
        $post_id = $this->createTestPost();

        $generator = new ContentForge\Generator\Comment();
        $ids       = $generator->generate( 1 );

        $this->assertCount( 1, $ids, 'Should generate exactly one comment.' );
        $this->assertIsInt( $ids[ 0 ], 'Generated ID should be an integer.' );

        $comment = get_comment( $ids[ 0 ] );
        $this->assertInstanceOf( 'WP_Comment', $comment, 'Generated ID should correspond to a valid comment.' );
        $this->assertNotEmpty( $comment->comment_author, 'Comment should have an author.' );
        $this->assertNotEmpty( $comment->comment_content, 'Comment should have content.' );

        // Verify tracking.
        $this->assertCommentIsTracked( $ids[ 0 ] );
    }

    /**
     * Test generating multiple comments.
     */
    public function test_generate_multiple_comments()
    {
        // Create posts to comment on.
        $this->createTestPost();
        $this->createTestPost();

        $generator = new ContentForge\Generator\Comment();
        $count     = 5;
        $ids       = $generator->generate( $count );

        $this->assertCount( $count, $ids, "Should generate exactly {$count} comments." );

        foreach ( $ids as $id )
        {
            $this->assertIsInt( $id, 'Each generated ID should be an integer.' );
            $comment = get_comment( $id );
            $this->assertInstanceOf( 'WP_Comment', $comment, 'Each ID should correspond to a valid comment.' );
            $this->assertCommentIsTracked( $id );
        }
    }

    /**
     * Test comment status variations.
     */
    public function test_comment_status()
    {
        // Create a post to comment on.
        $post_id = $this->createTestPost();

        $generator = new ContentForge\Generator\Comment();

        // Test approved status (default).
        $ids     = $generator->generate( 1, array( 'comment_status' => 'approve' ) );
        $comment = get_comment( $ids[ 0 ] );
        $this->assertEquals( '1', $comment->comment_approved, 'Comment should be approved.' );

        // Test hold status.
        $ids     = $generator->generate( 1, array( 'comment_status' => 'hold' ) );
        $comment = get_comment( $ids[ 0 ] );
        $this->assertEquals( '0', $comment->comment_approved, 'Comment should be held for moderation.' );

        // Test spam status.
        $ids     = $generator->generate( 1, array( 'comment_status' => 'spam' ) );
        $comment = get_comment( $ids[ 0 ] );
        $this->assertEquals( 'spam', $comment->comment_approved, 'Comment should be marked as spam.' );
    }

    /**
     * Test author randomization produces varied authors.
     */
    public function test_randomize_author()
    {
        // Create a post to comment on.
        $post_id = $this->createTestPost();

        $generator = new ContentForge\Generator\Comment();
        $authors   = array();

        // Generate multiple comments and collect authors.
        for ( $i = 0; $i < 10; $i++ )
        {
            $ids       = $generator->generate( 1 );
            $comment   = get_comment( $ids[ 0 ] );
            $authors[] = $comment->comment_author;
        }

        // Check that we have some variety (at least 5 unique authors out of 10).
        $unique_authors = array_unique( $authors );
        $this->assertGreaterThanOrEqual( 5, count( $unique_authors ), 'Should generate varied authors.' );

        // Authors should not be empty.
        foreach ( $authors as $author )
        {
            $this->assertNotEmpty( $author, 'Author should not be empty.' );
        }
    }

    /**
     * Test content randomization produces varied content.
     */
    public function test_randomize_content()
    {
        // Create a post to comment on.
        $post_id = $this->createTestPost();

        $generator = new ContentForge\Generator\Comment();
        $contents  = array();

        // Generate multiple comments and collect content.
        for ( $i = 0; $i < 10; $i++ )
        {
            $ids        = $generator->generate( 1 );
            $comment    = get_comment( $ids[ 0 ] );
            $contents[] = $comment->comment_content;
        }

        // Check that we have some variety.
        $unique_contents = array_unique( $contents );
        $this->assertGreaterThanOrEqual( 5, count( $unique_contents ), 'Should generate varied content.' );

        // Content should not be empty.
        foreach ( $contents as $content )
        {
            $this->assertNotEmpty( $content, 'Content should not be empty.' );
        }
    }

    /**
     * Test deleting generated comments.
     */
    public function test_delete_comments()
    {
        // Create a post to comment on.
        $post_id = $this->createTestPost();

        $generator = new ContentForge\Generator\Comment();
        $ids       = $generator->generate( 3 );

        $this->assertCount( 3, $ids );

        // Delete the comments.
        $deleted = $generator->delete( $ids );

        $this->assertEquals( 3, $deleted, 'Should delete all 3 comments.' );

        // Verify comments are deleted.
        foreach ( $ids as $id )
        {
            $comment = get_comment( $id );
            $this->assertNull( $comment, 'Comment should be deleted.' );
            $this->assertNotTracked( $id, 'comment' );
        }
    }

    /**
     * Test tracking generated comments.
     */
    public function test_track_generated()
    {
        // Create a post to comment on.
        $post_id = $this->createTestPost();

        $generator = new ContentForge\Generator\Comment();
        $ids       = $generator->generate( 2 );

        $this->assertEquals( 2, $this->getTrackedCount( 'comment' ), 'Should track 2 comments.' );

        foreach ( $ids as $id )
        {
            $this->assertCommentIsTracked( $id );
        }
    }

    /**
     * Test untracking deleted comments.
     */
    public function test_untrack_generated()
    {
        // Create a post to comment on.
        $post_id = $this->createTestPost();

        $generator = new ContentForge\Generator\Comment();
        $ids       = $generator->generate( 1 );

        $this->assertCommentIsTracked( $ids[ 0 ] );

        // Delete the comment.
        $generator->delete( $ids );

        // Verify untracked.
        $this->assertNotTracked( $ids[ 0 ], 'comment' );
    }
}
