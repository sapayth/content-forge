<?php

namespace Fakegen\Api;

use WP_REST_Server;
use Fakegen\Generator\Post as GeneratorPost;

class Post extends Fakegen_REST_Controller {
    /**
     * Route base
     *
     * @var string
     */
    protected $base = 'posts';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

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
                        'page'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
                        'per_page' => [ 'default' => 15, 'sanitize_callback' => 'absint' ],
                    ],
                ],
            ]
        );
    }

    /**
     * Handle bulk post/page creation
     */
    public function handle_bulk_create( $request ) {
        $params         = $request->get_json_params();
        $post_type      = isset( $params['post_type'] ) ? sanitize_key( $params['post_type'] ) : 'post';
        $post_status    = isset( $params['post_status'] ) ? sanitize_key( $params['post_status'] ) : 'publish';
        $comment_status = isset( $params['comment_status'] ) ? sanitize_key( $params['comment_status'] ) : 'closed';
        $post_parent    = isset( $params['post_parent'] ) ? intval( $params['post_parent'] ) : 0;
        $created        = [];

        if ( ! in_array( $post_type, [ 'post', 'page' ], true ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid post type.', 'fakegen' ) ], 400 );
        }
        if ( ! in_array( $post_status, [ 'publish', 'pending', 'draft', 'private' ], true ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid post status.', 'fakegen' ) ], 400 );
        }
        if ( ! in_array( $comment_status, [ 'closed', 'open' ], true ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid comment status.', 'fakegen' ) ], 400 );
        }

        $generator = new GeneratorPost( get_current_user_id() );

        // Manual mode: expects post_titles (array) and post_contents (array)
        if ( isset( $params['post_titles'] ) && is_array( $params['post_titles'] ) ) {
            $titles   = array_map( 'sanitize_text_field', $params['post_titles'] );
            $contents = isset( $params['post_contents'] ) && is_array( $params['post_contents'] ) ? array_map(
                'wp_kses_post', $params['post_contents']
            ) : [];

            foreach ( $titles as $i => $title ) {
                $content = isset( $contents[$i] ) ? $contents[$i] : '';
                $args = [
                    'post_type'      => $post_type,
                    'post_status'    => $post_status,
                    'comment_status' => $comment_status,
                    'post_parent'    => $post_parent,
                    'post_title'     => $title,
                    'post_content'   => $content,
                ];
                $ids = $generator->generate( 1, $args );
                if ( empty( $ids ) ) {
                    return new \WP_REST_Response( [ 'message' => __( 'Failed to generate post.', 'fakegen' ) ], 500 );
                }
                $created[] = $ids[0];
            }
        } else {
            // Auto mode: expects post_number (int)
            $post_number = isset( $params['post_number'] ) ? intval( $params['post_number'] ) : 1;
            if ( $post_number < 1 ) {
                return new \WP_REST_Response( [ 'message' => __( 'Number of posts/pages must be at least 1.', 'fakegen' ) ], 400 );
            }
            $args = [
                'post_type'      => $post_type,
                'post_status'    => $post_status,
                'comment_status' => $comment_status,
                'post_parent'    => $post_parent,
            ];
            $ids = $generator->generate( $post_number, $args );
            if ( empty( $ids ) ) {
                return new \WP_REST_Response( [ 'message' => __( 'Failed to generate posts/pages.', 'fakegen' ) ], 500 );
            }
            $created = array_merge( $created, $ids );
        }

        return new \WP_REST_Response( [ 'created' => $created ], 200 );
    }

    /**
     * Handle paginated list of posts/pages
     */
    public function handle_list( $request ) {
        $page     = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page = max( 1, min( 50, absint( $request->get_param( 'per_page' ) ) ) );
        $args = [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => [ 'publish', 'pending', 'draft', 'private' ],
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        $query = new \WP_Query( $args );
        $items = [];
        foreach ( $query->posts as $post ) {
            $items[] = [
                'ID'     => $post->ID,
                'title'  => get_the_title( $post ),
                'author' => get_the_author_meta( 'user_nicename', $post->post_author ),
                'type'   => $post->post_type,
                'date'   => get_date_from_gmt( $post->post_date_gmt, 'Y/m/d H:i A' ),
            ];
        }
        return new \WP_REST_Response( [
            'total' => (int) $query->found_posts,
            'items' => $items,
        ], 200 );
    }
}