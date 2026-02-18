<?php
/**
 * Post REST API controller for Content Forge plugin.
 *
 * @since   1.0.0
 * @package ContentForge
 */

namespace ContentForge\Api;

use WP_REST_Server;
use ContentForge\Generator\Post as GeneratorPost;
use ContentForge\Generator\AI_Scheduled_Generator;

class Post extends CForge_REST_Controller {
    /**
     * Route base
     *
     * @var string
     */
    protected $base = 'posts';

    /**
     * Constructor for Post REST API controller.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->scheduled_generator = new AI_Scheduled_Generator();
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes for post operations.
     *
     * @since 1.0.0
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/bulk',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'handle_bulk_create' ],
                    'permission_callback' => [ $this, 'permission_check' ],
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'handle_bulk_delete' ],
                    'permission_callback' => [ $this, 'permission_check' ],
                ],
            ]
        );
        // Add list endpoint
        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/list',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'handle_list' ],
                    'permission_callback' => [ $this, 'permission_check' ],
                    'args'                => [
                        'page'               => [
                            'default'           => 1,
                            'sanitize_callback' => 'absint',
                        ],
                        'per_page'           => [
                            'default'           => 15,
                            'sanitize_callback' => 'absint',
                        ],
                        'post_types'         => [
                            'default'           => '',
                            'sanitize_callback' => function ( $v ) {
                                return is_string( $v ) ? sanitize_text_field( $v ) : '';
                            },
                        ],
                        'exclude_post_types' => [
                            'default'           => '',
                            'sanitize_callback' => function ( $v ) {
                                return is_string( $v ) ? sanitize_text_field( $v ) : '';
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
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'handle_individual_delete' ],
                    'permission_callback' => [ $this, 'permission_check' ],
                    'args'                => [
                        'id' => [
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Handle bulk post/page creation
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function handle_bulk_create( $request ) {
        $params         = $request->get_json_params();
        $post_type      = isset( $params['post_type'] ) ? sanitize_key( $params['post_type'] ) : 'post';
        $post_status    = isset( $params['post_status'] ) ? sanitize_key( $params['post_status'] ) : 'publish';
        $comment_status = isset( $params['comment_status'] ) ? sanitize_key( $params['comment_status'] ) : 'closed';
        $post_parent    = isset( $params['post_parent'] ) ? intval( $params['post_parent'] ) : 0;

        // Validate parameters
        if ( ! in_array( $post_type, \cforge_get_allowed_post_types(), true ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid post type.', 'content-forge' ) ], 400 );
        }
        if ( ! in_array( $post_status, [ 'publish', 'pending', 'draft', 'private' ], true ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid post status.', 'content-forge' ) ], 400 );
        }
        if ( ! in_array( $comment_status, [ 'closed', 'open' ], true ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid comment status.', 'content-forge' ) ], 400 );
        }

        // Extract AI generation parameters
        $use_ai       = isset( $params['use_ai'] ) && $params['use_ai'];
        $content_type = isset( $params['content_type'] ) ? sanitize_key( $params['content_type'] ) : 'general';
        $ai_prompt    = isset( $params['ai_prompt'] ) ? sanitize_textarea_field( $params['ai_prompt'] ) : '';

        // If AI generation is requested, use scheduled generation
        if ( $use_ai ) {
            // Validate AI content type
            if ( empty( $content_type ) ) {
                return new \WP_REST_Response(
                    [ 'message' => __( 'Content type is required for AI generation.', 'content-forge' ) ],
                    400
                );
            }

            // Auto mode: expects post_number (int) - AI generation only supports auto mode
            $post_number = isset( $params['post_number'] ) ? intval( $params['post_number'] ) : 1;
            if ( $post_number < 1 ) {
                return new \WP_REST_Response(
                    [ 'message' => __( 'Number of posts/pages must be at least 1.', 'content-forge' ) ],
                    400
                );
            }

            // Prepare AI generation args
            $ai_args = [
                'post_number'  => $post_number,
                'post_type'    => $post_type,
                'post_status'  => $post_status,
                'content_type' => $content_type,
                'ai_prompt'    => $ai_prompt,
                'editor_type'  => isset( $params['editor_type'] ) ? sanitize_key( $params['editor_type'] ) : 'block',
            ];
            if ( 'product' === $post_type && ! empty( $params['product_options'] ) && is_array( $params['product_options'] ) ) {
                $ai_args['product_options'] = $params['product_options'];
            }

            // Schedule AI generation
            $result = $this->scheduled_generator->schedule_generation( $ai_args );

            if ( is_wp_error( $result ) ) {
                return new \WP_REST_Response(
                    [
                       	'message' => $result->get_error_message(),
						'code'    => $result->get_error_code(),
                    ],
                    400
                );
            }

            return new \WP_REST_Response( $result, 200 );
        }

        // Traditional (non-AI) generation continues as before
        $generate_image   = isset( $params['generate_image'] ) && $params['generate_image'];
        $image_sources    = isset( $params['image_sources'] ) && is_array( $params['image_sources'] ) ? array_map( 'sanitize_text_field', $params['image_sources'] ) : [];
        $generate_excerpt = isset( $params['generate_excerpt'] ) ? (bool) $params['generate_excerpt'] : true;
        $created          = [];
        $generator        = new GeneratorPost( get_current_user_id() );

        // Manual mode: expects post_titles (array) and post_contents (array)
        if ( isset( $params['post_titles'] ) && is_array( $params['post_titles'] ) ) {
            $titles   = array_map( 'sanitize_text_field', $params['post_titles'] );
            $contents = isset( $params['post_contents'] ) && is_array( $params['post_contents'] ) ? array_map(
                'wp_kses_post',
                $params['post_contents']
            ) : [];
            foreach ( $titles as $i => $title ) {
                $content = isset( $contents[ $i ] ) ? $contents[ $i ] : '';
                $args    = [
                    'post_type'      => $post_type,
                    'post_status'    => $post_status,
                    'comment_status' => $comment_status,
                    'post_parent'    => $post_parent,
                    'post_title'     => $title,
                    'post_content'   => $content,
                ];
                if ( 'product' === $post_type && ! empty( $params['product_options'] ) && is_array( $params['product_options'] ) ) {
                    $args['product_options'] = $params['product_options'];
                }
                // Add image generation parameters if requested
                if ( $generate_image ) {
                    $args['generate_image'] = true;
                    if ( ! empty( $image_sources ) ) {
                        $args['image_sources'] = $image_sources;
                    }
                }
                // Add excerpt generation parameter
                $args['generate_excerpt'] = $generate_excerpt;
                $ids                      = $generator->generate( 1, $args );
                if ( empty( $ids ) ) {
                    return new \WP_REST_Response(
                        [ 'message' => __( 'Failed to generate post.', 'content-forge' ) ],
                        500
                    );
                }
                $created[] = $ids[0];
            }
        } else {
            // Auto mode: expects post_number (int)
            $post_number = isset( $params['post_number'] ) ? intval( $params['post_number'] ) : 1;

            $docs_options         = isset( $params['docs_options'] ) && is_array( $params['docs_options'] ) ? $params['docs_options'] : [];
            $generation_mode      = isset( $docs_options['generation_mode'] ) ? sanitize_key( $docs_options['generation_mode'] ) : 'random';
            $is_hierarchical_docs = ( 'docs' === $post_type );

            // For docs in random mode, generate realistic amounts automatically
            if ( $is_hierarchical_docs && 'random' === $generation_mode ) {
                $post_number = 0; // Will be set based on generated hierarchy
                $level_counts = $this->generate_realistic_docs_counts();
            } elseif ( $is_hierarchical_docs && 'manual' === $generation_mode ) {
                // Manual mode: validate and use provided level_counts
                if ( isset( $docs_options['level_counts'] ) && is_array( $docs_options['level_counts'] ) ) {
                    $level_counts = array_map( 'intval', $docs_options['level_counts'] );
                    // Ensure exactly 5 levels
                    while ( count( $level_counts ) < 5 ) {
                        $level_counts[] = 0;
                    }
                    $level_counts = array_slice( $level_counts, 0, 5 );
                    $post_number = array_sum( $level_counts );
                } else {
                    return new \WP_REST_Response(
                        [ 'message' => __( 'Level counts required for manual mode.', 'content-forge' ) ],
                        400
                    );
                }
            } elseif ( $post_number < 1 ) {
                return new \WP_REST_Response(
                    [ 'message' => __( 'Number of posts/pages must be at least 1.', 'content-forge' ) ],
                    400
                );
            }

            if ( $is_hierarchical_docs && ! empty( $level_counts ) ) {
                // Filter out empty levels for generation
                $active_levels = array_filter( $level_counts, fn( $c ) => $c > 0 );
                if ( empty( $active_levels ) ) {
                    return new \WP_REST_Response(
                        [ 'message' => __( 'No items to generate. All level counts are zero.', 'content-forge' ) ],
                        400
                    );
                }

                // WeDocs 5-level hierarchy generation
                // Level 0: Documentation (root, parent=0)
                // Level 1: Sections (count is PER documentation)
                // Level 2: Articles (count is PER section)
                // Level 3: Nested Articles (count is PER article)
                // Level 4: Deeper Nesting (count is PER nested article)

                $prev_parents  = [];  // Parents from previous level

                foreach ( $level_counts as $level => $per_parent_count ) {
                    if ( $per_parent_count < 1 ) {
                        continue; // Skip empty levels
                    }

                    $current_parents = [];

                    // Level 0: Create $per_parent_count docs with no parent
                    // Level 1+: Create $per_parent_count children FOR EACH parent
                    if ( 0 === $level ) {
                        // Root level - create items with no parent
                        for ( $i = 0; $i < $per_parent_count; $i++ ) {
                            $args = [
                                'post_type'        => $post_type,
                                'post_status'      => $post_status,
                                'comment_status'   => $comment_status,
                                'post_parent'      => 0,
                                'generate_excerpt' => $generate_excerpt,
                            ];
                            if ( $generate_image ) {
                                $args['generate_image'] = true;
                                if ( ! empty( $image_sources ) ) {
                                    $args['image_sources'] = $image_sources;
                                }
                            }

                            $ids = $generator->generate( 1, $args );
                            if ( empty( $ids ) ) {
                                return new \WP_REST_Response(
                                    [ 'message' => __( 'Failed to generate docs.', 'content-forge' ) ],
                                    500
                                );
                            }

                            $created[]         = $ids[0];
                            $current_parents[] = $ids[0];
                        }
                    } else {
                        // Child levels - create $per_parent_count items FOR EACH parent
                        if ( empty( $prev_parents ) ) {
                            continue; // No parents available
                        }

                        foreach ( $prev_parents as $parent_id ) {
                            for ( $i = 0; $i < $per_parent_count; $i++ ) {
                                $args = [
                                    'post_type'        => $post_type,
                                    'post_status'      => $post_status,
                                    'comment_status'   => $comment_status,
                                    'post_parent'      => $parent_id,
                                    'generate_excerpt' => $generate_excerpt,
                                ];
                                if ( $generate_image ) {
                                    $args['generate_image'] = true;
                                    if ( ! empty( $image_sources ) ) {
                                        $args['image_sources'] = $image_sources;
                                    }
                                }

                                $ids = $generator->generate( 1, $args );
                                if ( empty( $ids ) ) {
                                    return new \WP_REST_Response(
                                        [ 'message' => __( 'Failed to generate docs.', 'content-forge' ) ],
                                        500
                                    );
                                }

                                $created[]         = $ids[0];
                                $current_parents[] = $ids[0];
                            }
                        }
                    }

                    // Current level's parents become the source for next level
                    $prev_parents = $current_parents;
                }
            } else {
                $args = [
                    'post_type'      => $post_type,
                    'post_status'    => $post_status,
                    'comment_status' => $comment_status,
                    'post_parent'    => $post_parent,
                ];
                if ( 'product' === $post_type && ! empty( $params['product_options'] ) && is_array( $params['product_options'] ) ) {
                    $args['product_options'] = $params['product_options'];
                }
                if ( $generate_image ) {
                    $args['generate_image'] = true;
                    if ( ! empty( $image_sources ) ) {
                        $args['image_sources'] = $image_sources;
                    }
                }
                $args['generate_excerpt'] = $generate_excerpt;
                $ids                      = $generator->generate( $post_number, $args );
                if ( empty( $ids ) ) {
                    return new \WP_REST_Response(
                        [ 'message' => __( 'Failed to generate posts/pages.', 'content-forge' ) ],
                        500
                    );
                }
                $created = array_merge( $created, $ids );
            }
        }
        return new \WP_REST_Response( [ 'created' => $created ], 200 );
    }

    /**
     * Resolve per-level counts: use request level_counts if valid, else computed counts.
     *
     * @param int   $total        Total number of docs to create.
     * @param int   $levels       Number of hierarchy levels (1 = flat).
     * @param array $docs_options Request docs_options (may contain level_counts).
     * @return array<int,int>|\WP_Error Count per level, or WP_Error if level_counts invalid.
     */
    private function resolve_docs_level_counts( $total, $levels, $docs_options ) {
        $request_counts = isset( $docs_options['level_counts'] ) && is_array( $docs_options['level_counts'] ) ? $docs_options['level_counts'] : null;
        if ( null === $request_counts || count( $request_counts ) !== $levels ) {
            return $this->get_docs_level_counts( $total, $levels );
        }
        $counts = array_map(
            function ( $c ) {
                return max( 1, (int) $c );
            },
            $request_counts
        );
        $sum    = array_sum( $counts );
        if ( $sum !== $total ) {
            return new \WP_Error( 'invalid_level_counts', __( 'Level counts must total the number to generate.', 'content-forge' ) );
        }
        return $counts;
    }

    /**
     * Get per-level doc counts for hierarchical WeDocs generation.
     * Level 0 = one root; remaining (total - 1) distributed across remaining levels.
     *
     * @param int $total Total number of docs to create.
     * @param int $levels Number of hierarchy levels (1 = flat).
     * @return array<int,int> Count per level (index 0 = root level).
     */
    private function get_docs_level_counts( $total, $levels ) {
        if ( $levels < 2 || $total < 2 ) {
            return [ min( 1, $total ) ];
        }
        $remaining = $total - 1;
        $result    = [ 1 ];
        for ( $l = 1; $l < $levels; $l++ ) {
            $levels_left = $levels - $l;
            $take        = (int) ceil( $remaining / $levels_left );
            $result[]    = $take;
            $remaining  -= $take;
        }
        return $result;
    }

    /**
     * Generate realistic WeDocs hierarchy counts for random mode.
     * Creates a natural-looking hierarchy: ~3-5 docs, ~2-4 sections each, ~5-15 articles, some nested.
     *
     * @since 1.0.0
     *
     * @return array<int,int> Array of 5 counts: [docs, sections, articles, nested, deeper]
     */
    private function generate_realistic_docs_counts() {
        // 3-5 documentation roots
        $docs_count = rand( 3, 5 );

        // 2-4 sections per doc
        $sections_count = $docs_count * rand( 2, 4 );

        // Articles distributed somewhat randomly across sections
        // Total articles: roughly 15-30
        $articles_count = rand( 15, 30 );

        // Some nested articles (10-30% of articles)
        $nested_count = rand( (int) ( $articles_count * 0.1 ), (int) ( $articles_count * 0.3 ) );

        // Deeper nesting (optional, 0-10)
        $deeper_count = rand( 0, 10 );

        return [ $docs_count, $sections_count, $articles_count, $nested_count, $deeper_count ];
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
     *
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    public function handle_list( $request ) {
        $pagination_params = $this->validate_list_parameters( $request );
        if ( is_wp_error( $pagination_params ) ) {
            return $pagination_params;
        }
        $total_count = $this->get_tracked_posts_count( $pagination_params );
        if ( is_wp_error( $total_count ) ) {
            return $total_count;
        }
        $post_ids = $this->get_tracked_posts_ids( $pagination_params );
        if ( is_wp_error( $post_ids ) ) {
            return $post_ids;
        }
        $post_types      = $this->get_list_post_types( $pagination_params );
        $formatted_items = $this->format_post_items( $post_ids, $post_types );
        if ( is_wp_error( $formatted_items ) ) {
            return $formatted_items;
        }
        return $this->prepare_list_response( $total_count, $formatted_items );
    }

    /**
     * Validate and sanitize list request parameters
     *
     * @since 1.0.0
     *
     * @param \WP_REST_Request $request The REST API request object.
     *
     * @return array|\WP_Error Array of validated parameters on success, WP_Error on failure.
     */
    private function validate_list_parameters( $request ) {
        $page     = absint( $request->get_param( 'page' ) );
        $per_page = absint( $request->get_param( 'per_page' ) );
        if ( $page < 1 ) {
            $page = 1;
        }
        if ( $per_page < 1 ) {
            $per_page = 15;
        } elseif ( $per_page > 50 ) {
            $per_page = 50;
        }
        $offset = ( $page - 1 ) * $per_page;

        $allowed             = \cforge_get_allowed_post_types();
        $post_types_str      = is_string( $request->get_param( 'post_types' ) ) ? $request->get_param( 'post_types' ) : '';
        $exclude_str         = is_string( $request->get_param( 'exclude_post_types' ) ) ? $request->get_param( 'exclude_post_types' ) : '';
        $include_list        = array_filter( array_map( 'sanitize_key', explode( ',', $post_types_str ) ) );
        $exclude_list        = array_filter( array_map( 'sanitize_key', explode( ',', $exclude_str ) ) );
        $include_list        = array_values( array_intersect( $include_list, $allowed ) );
        $exclude_list        = array_values( array_intersect( $exclude_list, $allowed ) );
        $base                = ! empty( $include_list ) ? $include_list : $allowed;
        $post_types_resolved = array_values( array_diff( $base, $exclude_list ) );

        return [
            'page'                => $page,
            'per_page'            => $per_page,
            'offset'              => $offset,
            'post_types_resolved' => $post_types_resolved,
        ];
    }

    /**
     * Get post type slugs to use for list query (resolved from post_types / exclude_post_types).
     *
     * @since 1.0.0
     *
     * @param array $pagination_params Validated list parameters including 'post_types_resolved'.
     *
     * @return array List of post type slugs.
     */
    private function get_list_post_types( $pagination_params ) {
        return isset( $pagination_params['post_types_resolved'] ) ? $pagination_params['post_types_resolved'] : [];
    }

    /**
     * Get total count of tracked posts for resolved post types only (DB filtered by type).
     *
     * @since 1.0.0
     *
     * @param array $pagination_params Validated list parameters including 'post_types_resolved'.
     *
     * @return int|\WP_Error Total count on success, WP_Error on failure.
     */
    private function get_tracked_posts_count( $pagination_params ) {
        $data_types = $this->get_list_post_types( $pagination_params );
        if ( empty( $data_types ) ) {
            return 0;
        }
        global $wpdb;
        $table_name          = $wpdb->prefix . CFORGE_DBNAME;
        $posts_table         = $wpdb->posts;
        $placeholders        = implode( ',', array_fill( 0, count( $data_types ), '%s' ) );
        $statuses            = [ 'publish', 'pending', 'draft', 'private' ];
        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $query_params        = array_merge( $data_types, $statuses, $data_types );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} t
                INNER JOIN {$posts_table} p ON p.ID = t.object_id AND p.post_type IN ({$placeholders}) AND p.post_status IN ({$status_placeholders})
                WHERE t.data_type IN ({$placeholders})",
                $query_params
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        if ( $wpdb->last_error ) {
            return new \WP_Error(
                'cforge_db_error',
                __( 'Database error occurred while counting posts.', 'content-forge' ),
                [ 'status' => 500 ]
            );
        }
        return absint( $total );
    }

    /**
     * Get paginated post IDs from tracked posts
     *
     * @since 1.0.0
     *
     * @param array $pagination_params Pagination parameters (page, per_page, offset).
     *
     * @return array|\WP_Error Array of post IDs on success, WP_Error on failure.
     */
    private function get_tracked_posts_ids( $pagination_params ) {
        $data_types = $this->get_list_post_types( $pagination_params );
        if ( empty( $data_types ) ) {
            return [];
        }
        global $wpdb;
        $table_name          = $wpdb->prefix . CFORGE_DBNAME;
        $posts_table         = $wpdb->posts;
        $placeholders        = implode( ',', array_fill( 0, count( $data_types ), '%s' ) );
        $statuses            = [ 'publish', 'pending', 'draft', 'private' ];
        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $query_params        = array_merge(
            $data_types,
            $statuses,
            $data_types,
            [ $pagination_params['per_page'], $pagination_params['offset'] ]
        );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT t.object_id FROM {$table_name} t
                INNER JOIN {$posts_table} p ON p.ID = t.object_id AND p.post_type IN ({$placeholders}) AND p.post_status IN ({$status_placeholders})
                WHERE t.data_type IN ({$placeholders})
                ORDER BY t.id DESC LIMIT %d OFFSET %d",
                $query_params
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        if ( $wpdb->last_error ) {
            return new \WP_Error(
                'cforge_db_error',
                __( 'Database error occurred while retrieving post IDs.', 'content-forge' ),
                [ 'status' => 500 ]
            );
        }
        return array_map( 'absint', $post_ids );
    }

    /**
     * Format post data for API response
     *
     * @since 1.0.0
     *
     * @param array $post_ids   Array of post IDs to format.
     * @param array $post_types Optional. Post type slugs to include. Defaults to all allowed.
     *
     * @return array|\WP_Error Array of formatted post items on success, WP_Error on failure.
     */
    private function format_post_items( $post_ids, $post_types = null ) {
        if ( empty( $post_ids ) ) {
            return [];
        }
        if ( null === $post_types ) {
            $post_types = \cforge_get_allowed_post_types();
        }
        if ( empty( $post_types ) ) {
            return [];
        }
        $posts = get_posts(
            [
                'post__in'    => $post_ids,
                'orderby'     => 'post__in',
                'numberposts' => count( $post_ids ),
                'post_status' => [ 'publish', 'pending', 'draft', 'private' ],
                'post_type'   => $post_types,
            ]
        );
        if ( empty( $posts ) ) {
            return [];
        }
        $formatted_items = [];
        foreach ( $posts as $post ) {
            if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
                continue;
            }
            $raw_title         = sanitize_text_field( get_the_title( $post ) );
            $formatted_items[] = [
                'ID'     => absint( $post->ID ),
                'title'  => html_entity_decode( $raw_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
                'author' => sanitize_text_field( get_the_author_meta( 'user_nicename', $post->post_author ) ),
                'type'   => sanitize_key( $post->post_type ),
                'date'   => sanitize_text_field( get_date_from_gmt( $post->post_date_gmt, 'Y/m/d H:i A' ) ),
            ];
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
     *
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    private function prepare_list_response( $total_count, $items ) {
        // Validate response data
        if ( ! is_array( $items ) ) {
            return new \WP_Error(
                'cforge_invalid_items',
                __( 'Invalid items array.', 'content-forge' ),
                [ 'status' => 500 ]
            );
        }
        if ( ! is_numeric( $total_count ) || $total_count < 0 ) {
            return new \WP_Error(
                'cforge_invalid_total',
                __( 'Invalid total count.', 'content-forge' ),
                [ 'status' => 500 ]
            );
        }
        return new \WP_REST_Response(
            [
                'total' => absint( $total_count ),
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
     *
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    public function handle_bulk_delete( $request ) {
        // Get all tracked post IDs
        $post_ids = $this->get_all_tracked_posts_ids();
        if ( is_wp_error( $post_ids ) ) {
            return $post_ids;
        }
        if ( empty( $post_ids ) ) {
            return new \WP_REST_Response(
                [
                    'deleted' => 0,
                    'message' => __( 'No posts found to delete.', 'content-forge' ),
                ],
                200
            );
        }
        // Use the generator to delete posts
        $generator     = new GeneratorPost( get_current_user_id() );
        $deleted_count = $generator->delete( $post_ids );
        return new \WP_REST_Response(
            [
                'deleted' => $deleted_count,
                'message' => sprintf(
                    /* translators: %d: Number of deleted posts */
                    __( 'Successfully deleted %d posts/pages.', 'content-forge' ),
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
    private function get_all_tracked_posts_ids() {
        global $wpdb;
        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        $data_types = \cforge_get_allowed_post_types();
        // Build placeholders for IN clause
        $placeholders = implode( ',', array_fill( 0, count( $data_types ), '%s' ) );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $post_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT object_id FROM {$table_name} WHERE data_type IN ({$placeholders}) ORDER BY id DESC", $data_types )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        if ( $wpdb->last_error ) {
            return new \WP_Error(
                'cforge_db_error',
                __( 'Database error occurred while retrieving posts for deletion.', 'content-forge' ),
                [ 'status' => 500 ]
            );
        }
        return array_map( 'absint', $post_ids );
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
     *
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    public function handle_individual_delete( $request ) {
        $post_id = absint( $request->get_param( 'id' ) );
        if ( ! $post_id ) {
            return new \WP_Error(
                'cforge_invalid_id',
                __( 'Invalid post ID provided.', 'content-forge' ),
                [ 'status' => 400 ]
            );
        }
        // Check if the post is tracked by Content Forge
        if ( ! $this->is_post_tracked( $post_id ) ) {
            return new \WP_Error(
                'cforge_not_tracked',
                __( 'Post not found or not generated by Content Forge.', 'content-forge' ),
                [ 'status' => 404 ]
            );
        }
        // Use the generator to delete the post
        $generator     = new GeneratorPost( get_current_user_id() );
        $deleted_count = $generator->delete( [ $post_id ] );
        if ( 0 === $deleted_count ) {
            return new \WP_Error(
                'cforge_delete_failed',
                __( 'Failed to delete the post.', 'content-forge' ),
                [ 'status' => 500 ]
            );
        }
        return new \WP_REST_Response(
            [
                'deleted' => $deleted_count,
                'message' => __( 'Post deleted successfully.', 'content-forge' ),
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
     *
     * @return bool True if the post is tracked, false otherwise.
     */
    private function is_post_tracked( $post_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        $data_types = \cforge_get_allowed_post_types();
        // Build placeholders for IN clause
        $placeholders = implode( ',', array_fill( 0, count( $data_types ), '%s' ) );
        // Prepare query parameters
        $query_params = array_merge( [ $post_id ], $data_types );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE object_id = %d AND data_type IN ({$placeholders})", $query_params )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $count > 0;
    }
}
