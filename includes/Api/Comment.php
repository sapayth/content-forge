<?php

namespace ContentForge\Api;

use WP_REST_Server;
use ContentForge\Generator\Comment as GeneratorComment;

class Comment extends CForge_REST_Controller
{
	/**
	 * Route base
	 *
	 * @var string
	 */
	protected $base = 'comments';

	public function __construct()
	{
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	public function register_routes()
	{
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/bulk',
			[
				[
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => [$this, 'handle_bulk_create'],
					'permission_callback' => [$this, 'permission_check'],
					'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
				],
				[
					'methods' => WP_REST_Server::DELETABLE,
					'callback' => [$this, 'handle_bulk_delete_all'],
					'permission_callback' => [$this, 'permission_check'],
				],
			]
		);

		// Add list endpoint
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/list',
			[
				[
					'methods' => WP_REST_Server::READABLE,
					'callback' => [$this, 'handle_list'],
					'permission_callback' => [$this, 'permission_check'],
					'args' => [
						'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
						'per_page' => ['default' => 15, 'sanitize_callback' => 'absint'],
					],
				],
			]
		);

		// Add delete endpoint (for individual comment deletion)
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/delete',
			[
				[
					'methods' => WP_REST_Server::DELETABLE,
					'callback' => [$this, 'handle_bulk_delete'],
					'permission_callback' => [$this, 'permission_check'],
					'args' => [
						'comment_ids' => [
							'required' => true,
							'type' => 'array',
							'sanitize_callback' => function ($param) {
								return array_map('absint', $param);
							},
						],
					],
				],
			]
		);

		// Add individual delete endpoint
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			[
				[
					'methods' => WP_REST_Server::DELETABLE,
					'callback' => [$this, 'handle_individual_delete'],
					'permission_callback' => [$this, 'permission_check'],
					'args' => [
						'id' => [
							'required' => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	/**
	 * Handle bulk comment creation
	 */
	public function handle_bulk_create($request)
	{
		$params = $request->get_json_params();

		$comment_number = isset($params['comment_number']) ? intval($params['comment_number']) : 1;
		$comment_post_ID = isset($params['comment_post_ID']) ? intval($params['comment_post_ID']) : 0;
		$comment_status = isset($params['comment_status']) ? sanitize_key($params['comment_status']) : 'approve';
		$allow_replies = isset($params['allow_replies']) ? (bool) $params['allow_replies'] : false;
		$reply_probability = isset($params['reply_probability']) ? intval($params['reply_probability']) : 30;

		// Validate comment number
		if ($comment_number < 1) {
			return new \WP_REST_Response(['message' => __('Number of comments must be at least 1.', 'cforge')], 400);
		}

		// Validate comment status
		if (!in_array($comment_status, ['approve', 'hold', 'spam'], true)) {
			return new \WP_REST_Response(['message' => __('Invalid comment status.', 'cforge')], 400);
		}

		// Validate reply probability
		if ($reply_probability < 0 || $reply_probability > 100) {
			$reply_probability = 30;
		}

		// If specific post ID provided, validate it exists and allows comments
		if ($comment_post_ID > 0) {
			$post = get_post($comment_post_ID);
			if (!$post || $post->post_status !== 'publish' || $post->comment_status !== 'open') {
				return new \WP_REST_Response(['message' => __('Invalid post ID or post does not allow comments.', 'cforge')], 400);
			}
		}

		$generator = new GeneratorComment(get_current_user_id());

		$args = [
			'comment_post_ID' => $comment_post_ID,
			'comment_status' => $comment_status,
			'allow_replies' => $allow_replies,
			'reply_probability' => $reply_probability,
		];

		$created = $generator->generate($comment_number, $args);

		if (empty($created)) {
			return new \WP_REST_Response(['message' => __('Failed to generate comments. No suitable posts found or other error occurred.', 'cforge')], 500);
		}

		return new \WP_REST_Response(['created' => $created], 200);
	}

	/**
	 * Handle paginated list of comments
	 */
	public function handle_list($request)
	{
		// Validate and sanitize request parameters
		$pagination_params = $this->validate_list_parameters($request);
		if (is_wp_error($pagination_params)) {
			return $pagination_params;
		}

		// Get total count of tracked comments
		$total_count = $this->get_tracked_comments_count();
		if (is_wp_error($total_count)) {
			return $total_count;
		}

		// Get paginated comment IDs
		$comment_ids = $this->get_tracked_comments_ids($pagination_params);
		if (is_wp_error($comment_ids)) {
			return $comment_ids;
		}

		// Format comment data for response
		$formatted_items = $this->format_comment_items($comment_ids);
		if (is_wp_error($formatted_items)) {
			return $formatted_items;
		}

		// Prepare and return response
		return $this->prepare_list_response($total_count, $formatted_items);
	}

	/**
	 * Handle bulk comment deletion
	 */
	public function handle_bulk_delete($request)
	{
		$comment_ids = $request->get_param('comment_ids');

		if (empty($comment_ids) || !is_array($comment_ids)) {
			return new \WP_REST_Response(['message' => __('No comment IDs provided.', 'cforge')], 400);
		}

		$generator = new GeneratorComment(get_current_user_id());
		$deleted = $generator->delete($comment_ids);

		return new \WP_REST_Response([
			'deleted' => $deleted,
			'message' => sprintf(__('Successfully deleted %d comments.', 'cforge'), $deleted),
		], 200);
	}

	/**
	 * Handle bulk deletion of all tracked comments
	 *
	 * Deletes all comments that were generated by Content Forge.
	 * This operation removes both the comments from WordPress and their tracking entries.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
	 */
	public function handle_bulk_delete_all($request)
	{
		// Get all tracked comment IDs
		$comment_ids = $this->get_all_tracked_comments_ids();
		if (is_wp_error($comment_ids)) {
			return $comment_ids;
		}

		if (empty($comment_ids)) {
			return new \WP_REST_Response(
				[
					'deleted' => 0,
					'message' => __('No comments found to delete.', 'cforge'),
				],
				200
			);
		}

		// Use the generator to delete comments
		$generator = new GeneratorComment(get_current_user_id());
		$deleted_count = $generator->delete($comment_ids);

		return new \WP_REST_Response(
			[
				'deleted' => $deleted_count,
				'message' => sprintf(
					/* translators: %d: Number of deleted comments */
					__('Successfully deleted %d comments.', 'cforge'),
					$deleted_count
				),
			],
			200
		);
	}

	/**
	 * Get all tracked comment IDs for deletion
	 *
	 * @since 1.0.0
	 *
	 * @return array|\WP_Error Array of comment IDs on success, WP_Error on failure.
	 */
	private function get_all_tracked_comments_ids()
	{
		global $wpdb;

		$comment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT object_id FROM {$wpdb->prefix}" . CFORGE_DBNAME . " WHERE data_type = %s ORDER BY id DESC",
				'comment'
			)
		);

		if ($wpdb->last_error) {
			return new \WP_Error(
				'cforge_db_error',
				__('Database error occurred while retrieving comments for deletion.', 'cforge'),
				['status' => 500]
			);
		}

		return array_map('absint', $comment_ids);
	}

	/**
	 * Handle individual comment deletion
	 *
	 * Deletes a single comment that was generated by Content Forge.
	 * This operation removes both the comment from WordPress and its tracking entry.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
	 */
	public function handle_individual_delete($request)
	{
		$comment_id = absint($request->get_param('id'));

		if (!$comment_id) {
			return new \WP_Error(
				'cforge_invalid_id',
				__('Invalid comment ID provided.', 'cforge'),
				['status' => 400]
			);
		}

		// Check if the comment is tracked by Content Forge
		if (!$this->is_comment_tracked($comment_id)) {
			return new \WP_Error(
				'cforge_not_tracked',
				__('Comment not found or not generated by Content Forge.', 'cforge'),
				['status' => 404]
			);
		}

		// Use the generator to delete the comment
		$generator = new GeneratorComment(get_current_user_id());
		$deleted_count = $generator->delete([$comment_id]);

		if ($deleted_count === 0) {
			return new \WP_Error(
				'cforge_delete_failed',
				__('Failed to delete the comment.', 'cforge'),
				['status' => 500]
			);
		}

		return new \WP_REST_Response(
			[
				'deleted' => $deleted_count,
				'message' => __('Comment deleted successfully.', 'cforge'),
			],
			200
		);
	}

	/**
	 * Check if a comment is tracked by Content Forge
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id The comment ID to check.
	 * @return bool True if the comment is tracked, false otherwise.
	 */
	private function is_comment_tracked($comment_id)
	{
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}" . CFORGE_DBNAME . " WHERE object_id = %d AND data_type = %s",
				$comment_id,
				'comment'
			)
		);

		return $count > 0;
	}

	/**
	 * Validate and sanitize list request parameters
	 */
	private function validate_list_parameters($request)
	{
		$page = absint($request->get_param('page'));
		$per_page = absint($request->get_param('per_page'));

		// Validate page number
		if ($page < 1) {
			$page = 1;
		}

		// Validate per_page with reasonable limits
		if ($per_page < 1) {
			$per_page = 15; // Default value
		} elseif ($per_page > 50) {
			$per_page = 50; // Maximum allowed
		}

		$offset = ($page - 1) * $per_page;

		return [
			'page' => $page,
			'per_page' => $per_page,
			'offset' => $offset,
		];
	}

	/**
	 * Get total count of tracked comments
	 */
	private function get_tracked_comments_count()
	{
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}" . CFORGE_DBNAME . " WHERE data_type = %s",
				'comment'
			)
		);

		if ($wpdb->last_error) {
			return new \WP_Error('database_error', __('Database error occurred while counting comments.', 'cforge'));
		}

		return (int) $count;
	}

	/**
	 * Get paginated comment IDs from tracking table
	 */
	private function get_tracked_comments_ids($pagination_params)
	{
		global $wpdb;

		$comment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT object_id FROM {$wpdb->prefix}" . CFORGE_DBNAME . " 
				 WHERE data_type = %s 
				 ORDER BY created_at DESC 
				 LIMIT %d OFFSET %d",
				'comment',
				$pagination_params['per_page'],
				$pagination_params['offset']
			)
		);

		if ($wpdb->last_error) {
			return new \WP_Error('database_error', __('Database error occurred while fetching comment IDs.', 'cforge'));
		}

		return array_map('intval', $comment_ids);
	}

	/**
	 * Format comment data for API response
	 */
	private function format_comment_items($comment_ids)
	{
		if (empty($comment_ids)) {
			return [];
		}

		$formatted_items = [];

		foreach ($comment_ids as $comment_id) {
			$comment = get_comment($comment_id);

			if (!$comment) {
				continue; // Skip if comment no longer exists
			}

			$post = get_post($comment->comment_post_ID);
			$post_title = $post ? $post->post_title : __('Unknown Post', 'cforge');
			$post_edit_link = $post ? get_edit_post_link($post->ID) : '';

			$formatted_items[] = [
				'id' => (int) $comment->comment_ID,
				'content' => wp_trim_words($comment->comment_content, 15),
				'author_name' => $comment->comment_author,
				'author_email' => $comment->comment_author_email,
				'post_id' => (int) $comment->comment_post_ID,
				'post_title' => $post_title,
				'post_edit_link' => $post_edit_link,
				'status' => wp_get_comment_status($comment->comment_ID),
				'date' => $comment->comment_date,
				'parent' => (int) $comment->comment_parent,
				'edit_link' => admin_url('comment.php?action=editcomment&c=' . $comment->comment_ID),
			];
		}

		return $formatted_items;
	}

	/**
	 * Prepare the final list response
	 */
	private function prepare_list_response($total_count, $items)
	{
		return new \WP_REST_Response([
			'items' => $items,
			'total_count' => $total_count,
			'page_info' => [
				'has_more' => count($items) === 15, // Assuming default per_page is 15
			],
		], 200);
	}
}