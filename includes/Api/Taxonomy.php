<?php
/**
 * Taxonomy REST API controller for Content Forge plugin.
 *
 * @since   1.0.0
 * @package ContentForge
 */

namespace ContentForge\Api;

use WP_REST_Server;
use ContentForge\Generator\Taxonomy as GeneratorTaxonomy;

class Taxonomy extends CForge_REST_Controller {
    /**
     * Route base
     *
     * @var string
     */
    protected $base = 'taxonomy';

    /**
     * Constructor for Taxonomy REST API controller.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes for taxonomy operations.
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
     * Handle bulk taxonomy term creation
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function handle_bulk_create( $request ) {
        $params   = $request->get_json_params();
        $taxonomy = isset( $params['taxonomy'] ) ? sanitize_key( $params['taxonomy'] ) : 'category';
        $count    = isset( $params['count'] ) ? intval( $params['count'] ) : 1;
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid taxonomy.', 'content-forge' ) ], 400 );
        }
        if ( $count < 1 ) {
            return new \WP_REST_Response(
                [ 'message' => __( 'Number of terms must be at least 1.', 'content-forge' ) ],
                400
            );
        }
        $generator = new GeneratorTaxonomy( get_current_user_id() );
        $args      = [
            'taxonomy' => $taxonomy,
        ];
        $ids       = $generator->generate( $count, $args );
        if ( empty( $ids ) ) {
            return new \WP_REST_Response(
                [ 'message' => __( 'Failed to generate terms.', 'content-forge' ) ],
                500
            );
        }
        return new \WP_REST_Response( [ 'created' => $ids ], 200 );
    }

    /**
     * Handle paginated list of terms
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function handle_list( $request ) {
        // Validate and sanitize request parameters
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
        // Get total count and items
        global $wpdb;
        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        // We need to filter by taxonomy types. For now, we assume any non-post/user/comment type is a taxonomy?
        // Or better, we query for known taxonomies.
        // Actually, in track_generated for Taxonomy, we store the taxonomy name as data_type.
        // So we should query for data_types that are valid taxonomies.
        $taxonomies = get_taxonomies();
        if ( empty( $taxonomies ) ) {
            return new \WP_REST_Response(
                [
                    'total' => 0,
                    'items' => [],
                ],
                200
            );
        }
        // Build placeholders for IN clause
        $placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $total_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE data_type IN ({$placeholders})",
                array_keys( $taxonomies )
            )
        );
        $results     = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_id, data_type, created_at FROM {$table_name} WHERE data_type IN ({$placeholders}) ORDER BY id DESC LIMIT %d OFFSET %d",
                array_merge( array_keys( $taxonomies ), [ $per_page, $offset ] )
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $formatted_items = [];
        foreach ( $results as $row ) {
            $term = get_term( $row->object_id, $row->data_type );
            if ( is_wp_error( $term ) || ! $term ) {
                continue;
            }
            $formatted_items[] = [
                'ID'       => absint( $term->term_id ),
                'id'       => absint( $term->term_id ),
                'title'    => sanitize_text_field( $term->name ),
                'taxonomy' => sanitize_text_field( $row->data_type ),
                'date'     => sanitize_text_field( $row->created_at ),
            ];
        }
        return new \WP_REST_Response(
            [
                'total' => absint( $total_count ),
                'items' => $formatted_items,
            ],
            200
        );
    }

    /**
     * Handle bulk deletion of all tracked terms
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function handle_bulk_delete( $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        $taxonomies = get_taxonomies();
        if ( empty( $taxonomies ) ) {
            return new \WP_REST_Response(
                [
                    'deleted' => 0,
                    'message' => 'No taxonomies found.',
                ],
                200
            );
        }
        $placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $term_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT object_id FROM {$table_name} WHERE data_type IN ({$placeholders})",
                array_keys( $taxonomies )
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        if ( empty( $term_ids ) ) {
            return new \WP_REST_Response(
                [
                    'deleted' => 0,
                    'message' => __( 'No terms found to delete.', 'content-forge' ),
                ],
                200
            );
        }
        $generator     = new GeneratorTaxonomy( get_current_user_id() );
        $deleted_count = $generator->delete( array_map( 'absint', $term_ids ) );
        return new \WP_REST_Response(
            [
                'deleted' => $deleted_count,
                'message' => sprintf(
                    /* translators: %d: Number of deleted terms */
                    __( 'Successfully deleted %d terms.', 'content-forge' ),
                    $deleted_count
                ),
            ],
            200
        );
    }

    /**
     * Handle individual term deletion
     *
     * @param \WP_REST_Request $request The REST API request object.
     */
    public function handle_individual_delete( $request ) {
        $term_id = absint( $request->get_param( 'id' ) );
        if ( ! $term_id ) {
            return new \WP_Error(
                'cforge_invalid_id',
                __( 'Invalid term ID provided.', 'content-forge' ),
                [ 'status' => 400 ]
            );
        }
        $generator     = new GeneratorTaxonomy( get_current_user_id() );
        $deleted_count = $generator->delete( [ $term_id ] );
        if ( 0 === $deleted_count ) {
            return new \WP_Error(
                'cforge_delete_failed',
                __( 'Failed to delete the term.', 'content-forge' ),
                [ 'status' => 500 ]
            );
        }
        return new \WP_REST_Response(
            [
                'deleted' => $deleted_count,
                'message' => __( 'Term deleted successfully.', 'content-forge' ),
            ],
            200
        );
    }
}
