<?php
/**
 * DESCRIPTION: Removes Content Forge tracking rows when the tracked object is
 * DESCRIPTION: deleted outside the plugin (wp-admin, WP-CLI, another plugin).
 *
 * @package ContentForge
 * @since   1.6.1
 */

namespace ContentForge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the tracking table in sync with WordPress core deletions.
 *
 * The generators untrack whatever they delete themselves, but anything deleted
 * elsewhere used to leave an orphan row behind, which inflated the counts and
 * the list views.
 */
class Cleanup {

	/**
	 * Option flag marking the one-time orphan purge as done.
	 */
	const PURGE_OPTION = 'cforge_orphans_purged';

	/**
	 * Register the core deletion hooks.
	 */
	public static function register() {
		add_action( 'deleted_post', [ __CLASS__, 'on_deleted_post' ], 10, 2 );
		add_action( 'deleted_user', [ __CLASS__, 'on_deleted_user' ] );
		add_action( 'deleted_comment', [ __CLASS__, 'on_deleted_comment' ] );
		add_action( 'delete_term', [ __CLASS__, 'on_deleted_term' ], 10, 3 );

		// Late enough that every taxonomy and post type is registered.
		add_action( 'admin_init', [ __CLASS__, 'maybe_purge_orphans' ], 999 );
	}

	/**
	 * Clear out rows orphaned before the deletion hooks existed. Runs once.
	 */
	public static function maybe_purge_orphans() {
		if ( get_option( self::PURGE_OPTION ) ) {
			return;
		}

		self::purge_orphans();
		update_option( self::PURGE_OPTION, 1 );
	}

	/**
	 * Delete every tracking row whose object no longer exists.
	 *
	 * @return int Number of rows removed.
	 */
	public static function purge_orphans() {
		global $wpdb;

		$table      = $wpdb->prefix . CFORGE_DBNAME;
		$taxonomies = get_taxonomies( [], 'names' );
		$non_posts  = array_merge( [ 'user', 'comment' ], array_values( $taxonomies ) );
		$removed    = 0;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Posts, attachments and any custom post type: everything that is not
		// one of the known non-post data types is stored in the posts table.
		$placeholders = implode( ',', array_fill( 0, count( $non_posts ), '%s' ) );
		$removed     += (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE t FROM {$table} t
				LEFT JOIN {$wpdb->posts} p ON p.ID = t.object_id
				WHERE t.data_type NOT IN ({$placeholders}) AND p.ID IS NULL",
				$non_posts
			)
		);

		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE t FROM {$table} t
				LEFT JOIN {$wpdb->users} u ON u.ID = t.object_id
				WHERE t.data_type = %s AND u.ID IS NULL",
				'user'
			)
		);

		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE t FROM {$table} t
				LEFT JOIN {$wpdb->comments} c ON c.comment_ID = t.object_id
				WHERE t.data_type = %s AND c.comment_ID IS NULL",
				'comment'
			)
		);

		// ponytail: terms of a taxonomy that is no longer registered are left
		// alone, since we cannot tell them apart from an unknown post type.
		if ( $taxonomies ) {
			$placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
			$removed     += (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE t FROM {$table} t
					LEFT JOIN {$wpdb->terms} tm ON tm.term_id = t.object_id
					WHERE t.data_type IN ({$placeholders}) AND tm.term_id IS NULL",
					array_values( $taxonomies )
				)
			);
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $removed;
	}

	/**
	 * Untrack a permanently deleted post (includes attachments and any CPT).
	 *
	 * @param int      $post_id Deleted post ID.
	 * @param \WP_Post $post    The post object, as it was before deletion.
	 */
	public static function on_deleted_post( $post_id, $post = null ) {
		$post_type = ( $post && isset( $post->post_type ) ) ? $post->post_type : 'post';

		self::untrack( $post_id, $post_type );
	}

	/**
	 * Untrack a deleted user.
	 *
	 * @param int $user_id Deleted user ID.
	 */
	public static function on_deleted_user( $user_id ) {
		self::untrack( $user_id, 'user' );
	}

	/**
	 * Untrack a deleted comment.
	 *
	 * @param int $comment_id Deleted comment ID.
	 */
	public static function on_deleted_comment( $comment_id ) {
		self::untrack( $comment_id, 'comment' );
	}

	/**
	 * Untrack a deleted term.
	 *
	 * Terms are tracked with the taxonomy name as their data type.
	 *
	 * @param int    $term_id  Deleted term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public static function on_deleted_term( $term_id, $tt_id, $taxonomy ) {
		self::untrack( $term_id, $taxonomy );
	}

	/**
	 * Delete the tracking row for an object, if one exists.
	 *
	 * @param int    $object_id The object ID.
	 * @param string $data_type The tracked data type.
	 */
	protected static function untrack( $object_id, $data_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$wpdb->prefix . CFORGE_DBNAME,
			[
				'object_id' => (int) $object_id,
				'data_type' => $data_type,
			],
			[ '%d', '%s' ]
		);
	}
}
