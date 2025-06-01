<?php

namespace ContentForge\Api;

use WP_REST_Server;
use ContentForge\Generator\Post as GeneratorPost;

class Post extends CForge_REST_Controller
{
    /**
     * Route base
     *
     * @var string
     */
    protected $base = 'posts';

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
                    'callback' => [$this, 'handle_bulk_delete'],
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
     * Handle bulk post/page creation
     */
    public function handle_bulk_create($request)
    {
        $params = $request->get_json_params();
        $post_type = isset($params['post_type']) ? sanitize_key($params['post_type']) : 'post';
        $post_status = isset($params['post_status']) ? sanitize_key($params['post_status']) : 'publish';
        $comment_status = isset($params['comment_status']) ? sanitize_key($params['comment_status']) : 'closed';
        $post_parent = isset($params['post_parent']) ? intval($params['post_parent']) : 0;
        $created = [];

        if (!in_array($post_type, ['post', 'page'], true)) {
            return new \WP_REST_Response(['message' => __('Invalid post type.', 'cforge')], 400);
        }
        if (!in_array($post_status, ['publish', 'pending', 'draft', 'private'], true)) {
            return new \WP_REST_Response(['message' => __('Invalid post status.', 'cforge')], 400);
        }
        if (!in_array($comment_status, ['closed', 'open'], true)) {
            return new \WP_REST_Response(['message' => __('Invalid comment status.', 'cforge')], 400);
        }

        $generator = new GeneratorPost(get_current_user_id());

        // Manual mode: expects post_titles (array) and post_contents (array)
        if (isset($params['post_titles']) && is_array($params['post_titles'])) {
            $titles = array_map('sanitize_text_field', $params['post_titles']);
            $contents = isset($params['post_contents']) && is_array($params['post_contents']) ? array_map(
                'wp_kses_post',
                $params['post_contents']
            ) : [];

            foreach ($titles as $i => $title) {
                $content = isset($contents[$i]) ? $contents[$i] : '';
                $args = [
                    'post_type' => $post_type,
                    'post_status' => $post_status,
                    'comment_status' => $comment_status,
                    'post_parent' => $post_parent,
                    'post_title' => $title,
                    'post_content' => $content,
                ];
                $ids = $generator->generate(1, $args);
                if (empty($ids)) {
                    return new \WP_REST_Response(['message' => __('Failed to generate post.', 'cforge')], 500);
                }
                $created[] = $ids[0];
            }
        } else {
            // Auto mode: expects post_number (int)
            $post_number = isset($params['post_number']) ? intval($params['post_number']) : 1;
            if ($post_number < 1) {
                return new \WP_REST_Response(['message' => __('Number of posts/pages must be at least 1.', 'cforge')], 400);
            }
            $args = [
                'post_type' => $post_type,
                'post_status' => $post_status,
                'comment_status' => $comment_status,
                'post_parent' => $post_parent,
            ];
            $ids = $generator->generate($post_number, $args);
            if (empty($ids)) {
                return new \WP_REST_Response(['message' => __('Failed to generate posts/pages.', 'cforge')], 500);
            }
            $created = array_merge($created, $ids);
        }

        return new \WP_REST_Response(['created' => $created], 200);
    }

    /**
     * Handle paginated list of posts/pages
     *
     * Retrieves a paginated list of tracked posts and pages from the custom database table.
     * Returns formatted data including post details for frontend display.
     *
     * @since 1.0.0
     *
     * @param \WP_REST_Request $request The REST API request object.
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    public function handle_list($request)
    {
        // Validate and sanitize request parameters
        $pagination_params = $this->validate_list_parameters($request);
        if (is_wp_error($pagination_params)) {
            return $pagination_params;
        }

        // Get total count of tracked posts/pages
        $total_count = $this->get_tracked_posts_count();

        if (is_wp_error($total_count)) {
            return $total_count;
        }

        // Get paginated post IDs
        $post_ids = $this->get_tracked_posts_ids($pagination_params);
        if (is_wp_error($post_ids)) {
            return $post_ids;
        }

        // Format post data for response
        $formatted_items = $this->format_post_items($post_ids);
        if (is_wp_error($formatted_items)) {
            return $formatted_items;
        }

        // Prepare and return response
        return $this->prepare_list_response($total_count, $formatted_items);
    }

    /**
     * Validate and sanitize list request parameters
     *
     * @since 1.0.0
     *
     * @param \WP_REST_Request $request The REST API request object.
     * @return array|\WP_Error Array of validated parameters on success, WP_Error on failure.
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
     * Get total count of tracked posts and pages
     *
     * @since 1.0.0
     *
     * @return int|\WP_Error Total count on success, WP_Error on failure.
     */
    private function get_tracked_posts_count()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        $data_types = ['post', 'page'];

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($data_types), '%s'));

        // Prepare query with explicit parameter array
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE data_type IN ({$placeholders})",
            $data_types
        );

        $total = $wpdb->get_var($query);

        if ($wpdb->last_error) {
            return new \WP_Error(
                'cforge_db_error',
                __('Database error occurred while counting posts.', 'cforge'),
                ['status' => 500]
            );
        }

        return absint($total);
    }

    /**
     * Get paginated post IDs from tracked posts
     *
     * @since 1.0.0
     *
     * @param array $pagination_params Pagination parameters (page, per_page, offset).
     * @return array|\WP_Error Array of post IDs on success, WP_Error on failure.
     */
    private function get_tracked_posts_ids($pagination_params)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        $data_types = ['post', 'page'];

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($data_types), '%s'));

        // Prepare query parameters array
        $query_params = array_merge(
            $data_types,
            [$pagination_params['per_page'], $pagination_params['offset']]
        );

        // Prepare query with explicit parameter array
        $query = $wpdb->prepare(
            "SELECT object_id FROM {$table_name} WHERE data_type IN ({$placeholders}) ORDER BY id DESC LIMIT %d OFFSET %d",
            $query_params
        );

        $post_ids = $wpdb->get_col($query);

        if ($wpdb->last_error) {
            return new \WP_Error(
                'cforge_db_error',
                __('Database error occurred while retrieving post IDs.', 'cforge'),
                ['status' => 500]
            );
        }

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CForge handle_list post IDs: ' . print_r($post_ids, true));
        }

        return array_map('absint', $post_ids);
    }

    /**
     * Format post data for API response
     *
     * @since 1.0.0
     *
     * @param array $post_ids Array of post IDs to format.
     * @return array|\WP_Error Array of formatted post items on success, WP_Error on failure.
     */
    private function format_post_items($post_ids)
    {
        if (empty($post_ids)) {
            return [];
        }

        // Fetch post objects maintaining order
        $posts = get_posts([
            'post__in' => $post_ids,
            'orderby' => 'post__in',
            'numberposts' => count($post_ids),
            'post_status' => ['publish', 'pending', 'draft', 'private'],
            'post_type' => ['post', 'page'],
        ]);

        if (empty($posts)) {
            return [];
        }

        $formatted_items = [];

        foreach ($posts as $post) {
            if (!is_object($post) || !isset($post->ID)) {
                continue;
            }

            $formatted_items[] = [
                'ID' => absint($post->ID),
                'title' => sanitize_text_field(get_the_title($post)),
                'author' => sanitize_text_field(get_the_author_meta('user_nicename', $post->post_author)),
                'type' => sanitize_key($post->post_type),
                'date' => sanitize_text_field(get_date_from_gmt($post->post_date_gmt, 'Y/m/d H:i A')),
            ];
        }

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CForge handle_list formatted items: ' . print_r($formatted_items, true));
        }

        return $formatted_items;
    }

    /**
     * Prepare the final API response
     *
     * @since 1.0.0
     *
     * @param int   $total_count Total number of tracked posts.
     * @param array $items       Array of formatted post items.
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    private function prepare_list_response($total_count, $items)
    {
        // Validate response data
        if (!is_array($items)) {
            return new \WP_Error(
                'cforge_invalid_items',
                __('Invalid items array.', 'cforge'),
                ['status' => 500]
            );
        }

        if (!is_numeric($total_count) || $total_count < 0) {
            return new \WP_Error(
                'cforge_invalid_total',
                __('Invalid total count.', 'cforge'),
                ['status' => 500]
            );
        }

        return new \WP_REST_Response(
            [
                'total' => absint($total_count),
                'items' => $items,
            ],
            200
        );
    }

    /**
     * Handle bulk deletion of all tracked posts/pages
     *
     * Deletes all posts and pages that were generated by Content Forge.
     * This operation removes both the posts from WordPress and their tracking entries.
     *
     * @since 1.0.0
     *
     * @param \WP_REST_Request $request The REST API request object.
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    public function handle_bulk_delete($request)
    {
        // Get all tracked post IDs
        $post_ids = $this->get_all_tracked_posts_ids();
        if (is_wp_error($post_ids)) {
            return $post_ids;
        }

        if (empty($post_ids)) {
            return new \WP_REST_Response(
                [
                    'deleted' => 0,
                    'message' => __('No posts found to delete.', 'cforge'),
                ],
                200
            );
        }

        // Use the generator to delete posts
        $generator = new GeneratorPost(get_current_user_id());
        $deleted_count = $generator->delete($post_ids);

        return new \WP_REST_Response(
            [
                'deleted' => $deleted_count,
                'message' => sprintf(
                    /* translators: %d: Number of deleted posts */
                    __('Successfully deleted %d posts/pages.', 'cforge'),
                    $deleted_count
                ),
            ],
            200
        );
    }

    /**
     * Get all tracked posts IDs for deletion
     *
     * @since 1.0.0
     *
     * @return array|\WP_Error Array of post IDs on success, WP_Error on failure.
     */
    private function get_all_tracked_posts_ids()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        $data_types = ['post', 'page'];

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($data_types), '%s'));

        // Prepare query
        $query = $wpdb->prepare(
            "SELECT object_id FROM {$table_name} WHERE data_type IN ({$placeholders}) ORDER BY id DESC",
            $data_types
        );

        $post_ids = $wpdb->get_col($query);

        if ($wpdb->last_error) {
            return new \WP_Error(
                'cforge_db_error',
                __('Database error occurred while retrieving posts for deletion.', 'cforge'),
                ['status' => 500]
            );
        }

        return array_map('absint', $post_ids);
    }

    /**
     * Handle individual post/page deletion
     *
     * Deletes a single post or page that was generated by Content Forge.
     * This operation removes both the post from WordPress and its tracking entry.
     *
     * @since 1.0.0
     *
     * @param \WP_REST_Request $request The REST API request object.
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    public function handle_individual_delete($request)
    {
        $post_id = absint($request->get_param('id'));

        if (!$post_id) {
            return new \WP_Error(
                'cforge_invalid_id',
                __('Invalid post ID provided.', 'cforge'),
                ['status' => 400]
            );
        }

        // Check if the post is tracked by Content Forge
        if (!$this->is_post_tracked($post_id)) {
            return new \WP_Error(
                'cforge_not_tracked',
                __('Post not found or not generated by Content Forge.', 'cforge'),
                ['status' => 404]
            );
        }

        // Use the generator to delete the post
        $generator = new GeneratorPost(get_current_user_id());
        $deleted_count = $generator->delete([$post_id]);

        if ($deleted_count === 0) {
            return new \WP_Error(
                'cforge_delete_failed',
                __('Failed to delete the post.', 'cforge'),
                ['status' => 500]
            );
        }

        return new \WP_REST_Response(
            [
                'deleted' => $deleted_count,
                'message' => __('Post deleted successfully.', 'cforge'),
            ],
            200
        );
    }

    /**
     * Check if a post is tracked by Content Forge
     *
     * @since 1.0.0
     *
     * @param int $post_id The post ID to check.
     * @return bool True if the post is tracked, false otherwise.
     */
    private function is_post_tracked($post_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        $data_types = ['post', 'page'];

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($data_types), '%s'));

        // Prepare query parameters
        $query_params = array_merge([$post_id], $data_types);

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE object_id = %d AND data_type IN ({$placeholders})",
                $query_params
            )
        );

        return $count > 0;
    }
}