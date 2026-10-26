<?php
/**
 * DESCRIPTION: WP-CLI `wp cforge generate` commands for posts, users, comments and terms.
 * DESCRIPTION: Turns CLI flags into the same REST request bodies the admin screens send.
 *
 * @package ContentForge
 * @since   1.8.0
 */

namespace ContentForge\CLI;

use WP_CLI;
use WP_CLI\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate posts, users, comments and terms.
 *
 * @since 1.8.0
 */
class Generate_Command {

	/**
	 * Items created per REST request, so long runs can show progress.
	 *
	 * @since 1.8.0
	 *
	 * @var int
	 */
	const BATCH_SIZE = 10;

	/**
	 * Generates posts, pages or any supported post type.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<n>]
	 * : How many to create. Ignored for weDocs `docs`, which builds a random hierarchy.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--post_type=<type>]
	 * : Post type, e.g. post, page, product, download, tribe_events, docs.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--status=<status>]
	 * : Post status: publish, draft, pending, private or future.
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--comment_status=<status>]
	 * : Whether the posts accept comments.
	 * ---
	 * default: closed
	 * options:
	 *   - open
	 *   - closed
	 * ---
	 *
	 * [--parent=<id>]
	 * : Parent post ID.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--from=<date>]
	 * : Earliest random post date (Y-m-d). Needs --to.
	 *
	 * [--to=<date>]
	 * : Latest random post date (Y-m-d). Needs --from.
	 *
	 * [--authors=<authors>]
	 * : `me`, `random` (any user who can publish), or comma-separated user IDs.
	 * ---
	 * default: me
	 * ---
	 *
	 * [--featured-image[=<sources>]]
	 * : Attach a featured image. Optional comma-separated sources, e.g. picsum,placehold.
	 *
	 * [--random-terms=<terms>]
	 * : Assign random existing terms, e.g. category:1-2,post_tag:2-5.
	 *
	 * [--[no-]excerpt]
	 * : Generate an excerpt. On by default.
	 *
	 * [--editor=<editor>]
	 * : Content format.
	 * ---
	 * default: block
	 * options:
	 *   - block
	 *   - classic
	 * ---
	 *
	 * [--porcelain]
	 * : Print only the created IDs, one per line.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp cforge generate posts --count=40 --random-terms=category:1-2,post_tag:2-4 --featured-image
	 *     $ wp cforge generate posts --post_type=product --count=25 --featured-image=picsum
	 *     $ wp cforge generate posts --post_type=page --count=5 --status=draft --porcelain
	 *
	 * @since 1.8.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function posts( $args, $assoc_args ) {
		try {
			$authors = self::parse_authors( Utils\get_flag_value( $assoc_args, 'authors', 'me' ) );
			$terms   = self::parse_random_terms( Utils\get_flag_value( $assoc_args, 'random-terms', '' ) );
		} catch ( \InvalidArgumentException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$post_type = Utils\get_flag_value( $assoc_args, 'post_type', 'post' );
		$from      = Utils\get_flag_value( $assoc_args, 'from', '' );
		$to        = Utils\get_flag_value( $assoc_args, 'to', '' );
		if ( (bool) $from !== (bool) $to ) {
			WP_CLI::error( __( '--from and --to must be used together.', 'content-forge' ) );
		}

		$params = [
			'post_type'        => $post_type,
			'post_status'      => Utils\get_flag_value( $assoc_args, 'status', 'publish' ),
			'comment_status'   => Utils\get_flag_value( $assoc_args, 'comment_status', 'closed' ),
			'post_parent'      => (int) Utils\get_flag_value( $assoc_args, 'parent', 0 ),
			'author_mode'      => $authors['mode'],
			'authors'          => $authors['ids'],
			'generate_excerpt' => (bool) Utils\get_flag_value( $assoc_args, 'excerpt', true ),
			'editor_type'      => Utils\get_flag_value( $assoc_args, 'editor', 'block' ),
		];
		if ( $from ) {
			$params['date_from'] = $from;
			$params['date_to']   = $to;
		}

		if ( $terms ) {
			$assignable = wp_list_pluck( \cforge_get_assignable_taxonomies( $post_type ), 'name' );
			foreach ( array_diff( array_keys( $terms ), $assignable ) as $taxonomy ) {
				/* translators: 1: taxonomy slug, 2: post type */
				WP_CLI::warning( sprintf( __( 'Ignoring %1$s: not a taxonomy of %2$s.', 'content-forge' ), $taxonomy, $post_type ) );
			}
			$params['taxonomy_options'] = $terms;
		}

		$image = Utils\get_flag_value( $assoc_args, 'featured-image', false );
		if ( $image ) {
			$params['generate_image'] = true;
			if ( is_string( $image ) ) {
				$params['image_sources'] = array_values( array_filter( array_map( 'trim', explode( ',', $image ) ) ) );
			}
		}

		// The docs hierarchy is built in one request; splitting it would build several.
		if ( 'docs' === $post_type ) {
			$data = Command::dispatch( 'POST', 'posts/bulk', $params );
			$this->report( $data['created'], 'posts', $assoc_args );
			return;
		}

		$this->run_batches( 'posts/bulk', 'post_number', $params, $assoc_args, 'posts' );
	}

	/**
	 * Generates users.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<n>]
	 * : How many to create.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--roles=<roles>]
	 * : Comma-separated roles to pick from.
	 * ---
	 * default: subscriber
	 * ---
	 *
	 * [--porcelain]
	 * : Print only the created IDs, one per line.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp cforge generate users --count=10 --roles=author,editor
	 *
	 * @since 1.8.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function users( $args, $assoc_args ) {
		$roles = array_values( array_filter( array_map( 'trim', explode( ',', Utils\get_flag_value( $assoc_args, 'roles', 'subscriber' ) ) ) ) );

		$this->run_batches( 'users/bulk', 'user_number', [ 'roles' => $roles ], $assoc_args, 'users' );
	}

	/**
	 * Generates comments on published posts that have comments open.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<n>]
	 * : How many to create.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--status=<status>]
	 * : Comment status.
	 * ---
	 * default: hold
	 * options:
	 *   - approve
	 *   - hold
	 *   - spam
	 * ---
	 *
	 * [--replies[=<probability>]]
	 * : Make some comments replies. Optional chance per comment, 0-100 (default 30).
	 *
	 * [--porcelain]
	 * : Print only the created IDs, one per line.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp cforge generate comments --count=120 --status=approve --replies
	 *
	 * @since 1.8.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function comments( $args, $assoc_args ) {
		$statuses = [
			'approve' => '1',
			'hold'    => '0',
			'spam'    => 'spam',
		];
		$replies  = Utils\get_flag_value( $assoc_args, 'replies', false );

		$params = [
			'comment_status'    => $statuses[ Utils\get_flag_value( $assoc_args, 'status', 'hold' ) ],
			'allow_replies'     => (bool) $replies,
			'reply_probability' => is_string( $replies ) ? (int) $replies : 30,
		];

		$this->run_batches( 'comments/bulk', 'comment_number', $params, $assoc_args, 'comments' );
	}

	/**
	 * Generates taxonomy terms.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<n>]
	 * : How many to create.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--taxonomy=<taxonomy>]
	 * : Taxonomy slug.
	 * ---
	 * default: category
	 * ---
	 *
	 * [--porcelain]
	 * : Print only the created IDs, one per line.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp cforge generate terms --count=8 --taxonomy=post_tag
	 *
	 * @since 1.8.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function terms( $args, $assoc_args ) {
		$params = [ 'taxonomy' => Utils\get_flag_value( $assoc_args, 'taxonomy', 'category' ) ];

		$this->run_batches( 'taxonomy/bulk', 'count', $params, $assoc_args, 'terms' );
	}

	/**
	 * Parse --authors into the REST author_mode and authors params.
	 *
	 * @since 1.8.0
	 *
	 * @param string $value `me`, `random`, or comma-separated user IDs.
	 *
	 * @throws \InvalidArgumentException When the value is none of those.
	 *
	 * @return array{mode:string,ids:int[]}
	 */
	public static function parse_authors( $value ) {
		$value = trim( (string) $value );

		if ( in_array( $value, [ 'me', 'random' ], true ) ) {
			return [
				'mode' => $value,
				'ids'  => [],
			];
		}

		if ( ! preg_match( '/^\d+(\s*,\s*\d+)*$/', $value ) ) {
			throw new \InvalidArgumentException( '--authors must be me, random, or comma-separated user IDs.' );
		}

		return [
			'mode' => 'specific',
			'ids'  => array_values( array_unique( array_map( 'intval', explode( ',', $value ) ) ) ),
		];
	}

	/**
	 * Parse --random-terms into REST taxonomy_options in random mode.
	 *
	 * `category:1-2,post_tag:3,genre` gives category 1-2 terms, post_tag exactly 3,
	 * genre exactly 1. The REST route clamps the counts and drops taxonomies the
	 * post type can't use.
	 *
	 * @since 1.8.0
	 *
	 * @param string $value Comma-separated taxonomy[:min[-max]] entries.
	 *
	 * @throws \InvalidArgumentException On a malformed entry.
	 *
	 * @return array<string, array{mode:string,min:int,max:int}>
	 */
	public static function parse_random_terms( $value ) {
		$options = [];

		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) as $entry ) {
			if ( ! preg_match( '/^([a-z0-9_-]+)(?::(\d+)(?:-(\d+))?)?$/', $entry, $m ) ) {
				throw new \InvalidArgumentException(
					sprintf( 'Invalid --random-terms entry "%s". Use taxonomy:min-max, e.g. category:1-2.', $entry ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI text, not HTML.
				);
			}

			$min = isset( $m[2] ) ? (int) $m[2] : 1;
			$max = isset( $m[3] ) ? (int) $m[3] : $min;
			if ( $min < 1 || $max < $min ) {
				throw new \InvalidArgumentException(
					sprintf( 'Invalid range in --random-terms entry "%s". Use min-max with 1 <= min <= max.', $entry ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI text, not HTML.
				);
			}

			$options[ $m[1] ] = [
				'mode' => 'random',
				'min'  => $min,
				'max'  => $max,
			];
		}

		return $options;
	}

	/**
	 * Create --count items in batches through a bulk route, then report.
	 *
	 * @since 1.8.0
	 *
	 * @param string $route      Bulk create route, relative to cforge/v1.
	 * @param string $count_key  Body param that holds the per-request count.
	 * @param array  $params     The rest of the request body.
	 * @param array  $assoc_args Associative arguments (count, porcelain).
	 * @param string $label      Content kind for messages, e.g. 'posts'.
	 *
	 * @return void
	 */
	private function run_batches( $route, $count_key, $params, $assoc_args, $label ) {
		$total = (int) Utils\get_flag_value( $assoc_args, 'count', 10 );
		if ( $total < 1 ) {
			// Let the route reject it, so the message matches the admin.
			Command::dispatch( 'POST', $route, array_merge( $params, [ $count_key => $total ] ) );
		}

		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );
		$progress  = ( $porcelain || $total <= self::BATCH_SIZE )
			? null
			/* translators: %s: content kind, e.g. posts */
			: Utils\make_progress_bar( sprintf( __( 'Generating %s', 'content-forge' ), $label ), $total );

		$created = [];
		$done    = 0;
		while ( $done < $total ) {
			$batch = min( self::BATCH_SIZE, $total - $done );
			$data  = Command::dispatch( 'POST', $route, array_merge( $params, [ $count_key => $batch ] ), false );

			if ( is_wp_error( $data ) ) {
				$message = $data->get_error_message();
				if ( 'comments' === $label ) {
					$message .= ' ' . __( 'Comments only go on published posts with comments open. Generated posts have them closed by default: run `wp cforge generate posts --comment_status=open` first.', 'content-forge' );
				}
				WP_CLI::error( $message );
			}

			$ids     = array_map( 'intval', $data['created'] );
			$created = array_merge( $created, $ids );
			$done    = count( $created );
			if ( $progress ) {
				$progress->tick( count( $ids ) );
			}

			// A short batch means the generator ran out of something; don't loop forever.
			if ( count( $ids ) < $batch ) {
				break;
			}
		}

		if ( $progress ) {
			$progress->finish();
		}

		$this->report( $created, $label, $assoc_args, $total );
	}

	/**
	 * Print created IDs (porcelain) or a success line.
	 *
	 * @since 1.8.0
	 *
	 * @param int[]    $created    Created IDs.
	 * @param string   $label      Content kind for messages.
	 * @param array    $assoc_args Associative arguments.
	 * @param int|null $requested  How many were asked for, to warn on a shortfall.
	 *
	 * @return void
	 */
	private function report( $created, $label, $assoc_args, $requested = null ) {
		if ( $requested && count( $created ) < $requested ) {
			/* translators: 1: created count, 2: requested count, 3: content kind */
			WP_CLI::warning( sprintf( __( 'Created %1$d of %2$d %3$s.', 'content-forge' ), count( $created ), $requested, $label ) );
		}

		if ( Utils\get_flag_value( $assoc_args, 'porcelain', false ) ) {
			foreach ( $created as $id ) {
				WP_CLI::line( (string) $id );
			}
			return;
		}

		/* translators: 1: created count, 2: content kind */
		WP_CLI::success( sprintf( __( 'Created %1$d %2$s.', 'content-forge' ), count( $created ), $label ) );
	}
}
