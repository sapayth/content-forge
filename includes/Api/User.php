<?php
/**
 * User REST API controller for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge\Api;

use WP_REST_Server;
use ContentForge\Generator\User as GeneratorUser;

class User extends CForge_REST_Controller {

    /**
     * Route base
     *
     * @var string
     */
    protected $base = 'users';

    /**
     * Register REST API routes for user operations.
     *
     * @since 1.0.0
     */
    public function register_routes()
    {
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
                    'callback'            => [ $this, 'handle_bulk_delete_all' ],
                    'permission_callback' => [ $this, 'permission_check' ],
                ],
            ]
        );

        // Add list endpoint
        register_rest_route(
            $this->namespace,
            '/' . $this->base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_list' ],
                    'permission_callback' => [ $this, 'permission_check' ],
                    'args'                => [
                        'page'     => [
							'default'           => 1,
							'sanitize_callback' => 'absint',
						],
                        'per_page' => [
							'default'           => 15,
							'sanitize_callback' => 'absint',
						],
                    ],
                ],
            ]
        );

        // Add delete endpoint (for individual user deletion)
        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/delete',
            [
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'handle_bulk_delete' ],
                    'permission_callback' => [ $this, 'permission_check' ],
                    'args'                => [
                        'user_ids' => [
                            'required'          => true,
                            'type'              => 'array',
                            'sanitize_callback' => function ( $param ) {
                                return array_map( 'absint', $param );
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
     * Handle bulk user creation
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function handle_bulk_create( $request )
    {
        $params = $request->get_json_params();

        $user_number = isset( $params['user_number'] ) ? intval( $params['user_number'] ) : 1;
        $roles       = isset( $params['roles'] ) ? (array) $params['roles'] : [ 'subscriber' ];

        // Validate user number
        if ( $user_number < 1 ) {
            return new \WP_REST_Response( [ 'message' => __( 'Number of users must be at least 1.', 'content-forge' ) ], 400 );
        }

        // Validate roles
        $all_roles = array_keys( wp_roles()->get_names() );
        foreach ( $roles as $role ) {
            if ( ! in_array( $role, $all_roles, true ) ) {
                return new \WP_REST_Response( [ 'message' => __( 'Invalid role selected.', 'content-forge' ) ], 400 );
            }
        }

        $generator = new GeneratorUser( get_current_user_id() );
        $args      = [
            'roles' => $roles,
        ];
        $created   = $generator->generate( $user_number, $args );

        if ( empty( $created ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Failed to generate users.', 'content-forge' ) ], 500 );
        }

        return new \WP_REST_Response( [ 'created' => $created ], 200 );
    }

    /**
     * Handle paginated list of users
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function get_list( $request )
    {
        $pagination_params = $this->validate_list_parameters( $request );
        if ( is_wp_error( $pagination_params ) ) {
            return $pagination_params;
        }

        $total_count = $this->get_tracked_users_count();
        if ( is_wp_error( $total_count ) ) {
            return $total_count;
        }

        $user_ids = $this->get_tracked_users_ids( $pagination_params );
        if ( is_wp_error( $user_ids ) ) {
            return $user_ids;
        }

        $formatted_items = $this->format_user_items( $user_ids );
        if ( is_wp_error( $formatted_items ) ) {
            return $formatted_items;
        }

        return $this->prepare_list_response( $total_count, $formatted_items );
    }

    /**
     * Handle bulk user deletion
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function handle_bulk_delete( $request )
    {
        $user_ids = $request->get_param( 'user_ids' );

        if ( empty( $user_ids ) || ! is_array( $user_ids ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'No user IDs provided.', 'content-forge' ) ], 400 );
        }

        $generator = new GeneratorUser( get_current_user_id() );
        $deleted   = $generator->delete( $user_ids );

        return new \WP_REST_Response(
            [
				'deleted' => $deleted,
			],
            200
        );
    }

    /**
     * Handle bulk delete all users
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function handle_bulk_delete_all( $request )
    {
        $user_ids = $this->get_all_tracked_users_ids();
        if ( empty( $user_ids ) ) {
            return new \WP_REST_Response( [ 'deleted' => 0 ], 200 );
        }
        $generator = new GeneratorUser( get_current_user_id() );
        $deleted   = $generator->delete( $user_ids );
        return new \WP_REST_Response( [ 'deleted' => $deleted ], 200 );
    }

    /**
     * Handle individual user deletion
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function handle_individual_delete( $request )
    {
        $id = absint( $request->get_param( 'id' ) );
        if ( ! $id ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid user ID.', 'content-forge' ) ], 400 );
        }
        $generator = new GeneratorUser( get_current_user_id() );
        $deleted   = $generator->delete( [ $id ] );
        return new \WP_REST_Response( [ 'deleted' => $deleted ], 200 );
    }

    /**
     * Get all tracked user IDs from the database.
     *
     * @return array Array of user IDs.
     */
    private function get_all_tracked_users_ids()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';
        $ids   = $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->prefix}cforge WHERE data_type = %s", 'user' ) );
        return array_map( 'absint', $ids );
    }

    /**
     * Validate and sanitize list parameters.
     *
     * @param \WP_REST_Request $request The REST API request object.
     * @return array Validated parameters.
     */
    private function validate_list_parameters( $request )
    {
        $page     = absint( $request->get_param( 'page' ) );
        $per_page = absint( $request->get_param( 'per_page' ) );
        if ( $page < 1 ) {
            $page = 1;
        }
        if ( $per_page < 1 ) {
            $per_page = 15;
        }
        return [
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * Get the total count of tracked users.
     *
     * @return int Total count of tracked users.
     */
    private function get_tracked_users_count()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cforge';
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}cforge WHERE data_type = %s", 'user' ) );
    }

    /**
     * Get tracked user IDs with pagination.
     *
     * @param array $pagination_params Pagination parameters.
     * @return array Array of user IDs.
     */
    private function get_tracked_users_ids( $pagination_params )
    {
        global $wpdb;
        $table  = $wpdb->prefix . 'cforge';
        $offset = ( $pagination_params['page'] - 1 ) * $pagination_params['per_page'];
        $ids    = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT object_id FROM {$wpdb->prefix}cforge WHERE data_type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                'user',
                $pagination_params['per_page'],
                $offset
            )
        );
        return array_map( 'absint', $ids );
    }

    /**
     * Format user items for API response.
     *
     * @param array $user_ids Array of user IDs.
     * @return array Formatted user items.
     */
    private function format_user_items( $user_ids )
    {
        $items = [];
        foreach ( $user_ids as $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                continue;
            }
            $role    = is_array( $user->roles ) && ! empty( $user->roles ) ? $user->roles[0] : '';
            $items[] = [
                'ID'         => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'role'       => $role,
            ];
        }
        return $items;
    }

    /**
     * Prepare the final list response.
     *
     * @param int   $total_count Total number of items.
     * @param array $items       Array of formatted items.
     * @return array Final response array.
     */
    private function prepare_list_response( $total_count, $items )
    {
        return [
            'total' => $total_count,
            'items' => $items,
        ];
    }
}
