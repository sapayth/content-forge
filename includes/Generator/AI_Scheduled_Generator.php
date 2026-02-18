<?php
/**
 * AI Scheduled Generator class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.2.0
 */

namespace ContentForge\Generator;

use WP_Error;
use Exception;
use ContentForge\Settings\AI_Settings_Manager;
use ContentForge\Generator\Post as PostGenerator;
use ContentForge\Generator\AI_Content_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles scheduled AI content generation using Action Scheduler.
 */
class AI_Scheduled_Generator {

	/**
	 * Action hook name for sequential AI generation.
	 *
	 * @var string
	 */
	const SEQUENTIAL_HOOK = 'cforge_generate_sequential_ai_content';

	/**
	 * Option prefix for batch tracking.
	 *
	 * @var string
	 */
	const BATCH_OPTION_PREFIX = 'cforge_batch_';

	/**
	 * Initialize the scheduled generator.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		add_action( self::SEQUENTIAL_HOOK, [ $this, 'handle_sequential_generation_wrapper' ], 10, 1 );
		add_action( 'cforge_cleanup_batch_data', [ $this, 'cleanup_batch_data' ], 10, 1 );
	}

	/**
	 * Schedule sequential AI generation.
	 *
	 * @since 1.2.0
	 *
	 * @param array $args Generation arguments.
	 * @return array|WP_Error Scheduled action info or error.
	 */
	public function schedule_generation( $args ) {
		// Validate required parameters
		if ( empty( $args['post_number'] ) || $args['post_number'] < 1 ) {
			return new WP_Error(
				'invalid_post_number',
				__( 'Number of posts must be at least 1.', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}

		if ( empty( $args['content_type'] ) ) {
			return new WP_Error(
				'invalid_content_type',
				__( 'Content type is required.', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}

		$batch_id   = uniqid( 'batch_', true );
		$total_jobs = intval( $args['post_number'] );

		// Initialize batch tracking
		$batch_data = [
			'total'         => $total_jobs,
			'completed'     => 0,
			'pending'       => $total_jobs,
			'posts_created' => [],
			'errors'        => [],
			'status'        => 'processing',
			'created_at'    => time(),
			'user_id'       => get_current_user_id(),
		];

		update_option( self::BATCH_OPTION_PREFIX . $batch_id, $batch_data );

		// Prepare arguments array
		$action_args = [
			'batch_id'      => $batch_id,
			'current_index' => 0,
			'total_count'   => $total_jobs,
			'post_type'     => $args['post_type'] ?? 'post',
			'post_status'   => $args['post_status'] ?? 'draft',
			'content_type'  => $args['content_type'],
			'ai_prompt'     => $args['ai_prompt'] ?? '',
			'editor_type'   => $args['editor_type'] ?? 'block',
			'user_id'       => get_current_user_id(),
		];
		if ( ! empty( $args['product_options'] ) && is_array( $args['product_options'] ) ) {
			$action_args['product_options'] = $args['product_options'];
		}

		// Schedule ONLY the first action
		$action_id = \as_schedule_single_action(
			time(),
			self::SEQUENTIAL_HOOK,
			[ $action_args ], // Pass the arguments array directly
			'cforge_batch_' . $batch_id, // Use batch-specific group
			true // unique
		);

		if ( ! $action_id ) {
			delete_option( self::BATCH_OPTION_PREFIX . $batch_id );
			return new WP_Error(
				'schedule_failed',
				__( 'Failed to schedule AI generation.', 'content-forge' ),
				[ 'status' => 500 ]
			);
		}

		return [
			'batch_id'        => $batch_id,
			'total_jobs'      => $total_jobs,
			'first_action_id' => $action_id,
			'message'         => sprintf(
				// translators: %d is the number of AI posts to generate.
				__( 'Started generating %d AI posts...', 'content-forge' ),
				$total_jobs
			),
		];
	}

	/**
	 * Wrapper function to handle Action Scheduler callback.
	 *
	 * @since 1.2.0
	 *
	 * @param array $args Arguments array from Action Scheduler.
	 */
	public function handle_sequential_generation_wrapper( $args ) {
		// Action Scheduler passes arguments as individual parameters when you use arrays
		// So if we got an array, we need to extract the actual arguments array from it
		if ( is_array( $args ) && isset( $args[0] ) && is_array( $args[0] ) ) {
			$actual_args = $args[0];
		} elseif ( is_array( $args ) && isset( $args['batch_id'] ) ) {
			// Direct array passed
			$actual_args = $args;
		} else {
			return;
		}

		// Check if all required keys exist
		$required_keys = [ 'batch_id', 'current_index', 'total_count', 'post_type', 'post_status', 'content_type', 'ai_prompt', 'editor_type', 'user_id' ];
		foreach ( $required_keys as $key ) {
			if ( ! isset( $actual_args[ $key ] ) ) {
				return;
			}
		}

		// Extract arguments from the array passed by Action Scheduler
		$batch_id        = $actual_args['batch_id'];
		$current_index   = $actual_args['current_index'];
		$total_count     = $actual_args['total_count'];
		$post_type       = $actual_args['post_type'];
		$post_status     = $actual_args['post_status'];
		$content_type    = $actual_args['content_type'];
		$ai_prompt       = $actual_args['ai_prompt'];
		$editor_type     = $actual_args['editor_type'];
		$user_id         = $actual_args['user_id'];
		$product_options = ! empty( $actual_args['product_options'] ) && is_array( $actual_args['product_options'] ) ? $actual_args['product_options'] : [];

		return $this->handle_sequential_generation( $batch_id, $current_index, $total_count, $post_type, $post_status, $content_type, $ai_prompt, $editor_type, $user_id, $product_options );
	}

	/**
	 * Handle sequential AI generation.
	 *
	 * @since 1.2.0
	 *
	 * @param string $batch_id      Batch ID.
	 * @param int    $current_index Current item index.
	 * @param int    $total_count   Total number of items.
	 * @param string $post_type    Post type.
	 * @param string $post_status  Post status.
	 * @param string $content_type Content type.
	 * @param string $ai_prompt    AI prompt.
	 * @param string $editor_type   Editor type.
	 * @param int    $user_id       User ID.
	 * @param array  $product_options Optional product options for WooCommerce products.
	 * @throws Exception If AI generation fails.
	 */
	public function handle_sequential_generation( $batch_id, $current_index, $total_count, $post_type, $post_status, $content_type, $ai_prompt, $editor_type, $user_id, $product_options = [] ) {

		// Get batch tracking
		$batch_data = get_option( self::BATCH_OPTION_PREFIX . $batch_id );
		if ( ! $batch_data ) {
			return;
		}

		// Ensure batch data is an array (might be serialized string)
		if ( is_string( $batch_data ) ) {
			$batch_data = maybe_unserialize( $batch_data );
		}

		if ( ! is_array( $batch_data ) ) {
			return;
		}

		// Get AI settings
		$ai_settings = AI_Settings_Manager::get_settings();
		if ( empty( $ai_settings['api_key'] ) ) {
			$this->record_error( $batch_id, $current_index, __( 'AI API key not configured', 'content-forge' ) );

			// Update batch data to mark as failed since AI is not configured
			$batch_data = get_option( self::BATCH_OPTION_PREFIX . $batch_id );
			if ( $batch_data ) {
				if ( is_string( $batch_data ) ) {
					$batch_data = maybe_unserialize( $batch_data );
				}
				if ( is_array( $batch_data ) ) {
					$batch_data['status']    = 'failed';
					$batch_data['failed_at'] = time();
					update_option( self::BATCH_OPTION_PREFIX . $batch_id, $batch_data );
				}
			}

			// Don't schedule next action - AI generation can't continue
			return;
		}

		try {
			// Initialize AI generator
			$ai_generator = new AI_Content_Generator(
				$ai_settings['provider'],
				$ai_settings['model'],
				$ai_settings['api_key'],
				$editor_type
			);

			// Generate AI content
			$result = $ai_generator->generate(
				$content_type,
				$ai_prompt
			);

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Create the post
			$post_generator = new PostGenerator( $user_id );
			$post_args      = [
				'post_type'    => $post_type,
				'post_status'  => $post_status,
				'post_title'   => $result['title'],
				'post_content' => $result['content'],
			];
			if ( ! empty( $product_options ) ) {
				$post_args['product_options'] = $product_options;
			}

			$post_ids = $post_generator->generate( 1, $post_args );

			if ( empty( $post_ids ) ) {
				throw new Exception( __( 'Failed to create post from generated content', 'content-forge' ) );
			}

			// Update tracking with success
			++$batch_data['completed'];
			--$batch_data['pending'];
			$batch_data['posts_created'][] = [
				'index'      => $current_index,
				'post_id'    => $post_ids[0],
				'title'      => $result['title'],
				'created_at' => time(),
			];

		} catch ( Exception $e ) {
			// Log error but continue with next
			$this->record_error( $batch_id, $current_index, $e->getMessage() );
		}

		// Save updated batch data
		update_option( self::BATCH_OPTION_PREFIX . $batch_id, $batch_data );

		// Schedule next action or mark as complete
		$this->schedule_next_or_complete( $batch_id, $current_index, $total_count, $post_type, $post_status, $content_type, $ai_prompt, $editor_type, $user_id, $product_options );
	}

	/**
	 * Schedule the next action or mark batch as complete.
	 *
	 * @since 1.2.0
	 *
	 * @param string $batch_id         Batch ID.
	 * @param int    $current_index    Current item index.
	 * @param int    $total_count      Total number of items.
	 * @param string $post_type        Post type.
	 * @param string $post_status      Post status.
	 * @param string $content_type     Content type.
	 * @param string $ai_prompt        AI prompt.
	 * @param string $editor_type      Editor type.
	 * @param int    $user_id          User ID.
	 * @param array  $product_options  Optional product options for WooCommerce.
	 */
	protected function schedule_next_or_complete( $batch_id, $current_index, $total_count, $post_type, $post_status, $content_type, $ai_prompt, $editor_type, $user_id, $product_options = [] ) {
		$next_index = $current_index + 1;

		if ( $next_index < $total_count ) {
			// Prepare arguments for the next action
			$action_args = [
				'batch_id'      => $batch_id,
				'current_index' => $next_index,
				'total_count'   => $total_count,
				'post_type'     => $post_type,
				'post_status'   => $post_status,
				'content_type'  => $content_type,
				'ai_prompt'     => $ai_prompt,
				'editor_type'   => $editor_type,
				'user_id'       => $user_id,
			];
			if ( ! empty( $product_options ) ) {
				$action_args['product_options'] = $product_options;
			}

			// Schedule the next action
			\as_schedule_single_action(
				time() + 5, // 5 second delay to ensure proper execution order
				self::SEQUENTIAL_HOOK,
				[ $action_args ], // Pass the arguments array
				'cforge_batch_' . $batch_id, // Use batch-specific group
				false // Don't enforce uniqueness for sequential actions
			);
		} else {
			// Mark batch as complete
			$batch_data = get_option( self::BATCH_OPTION_PREFIX . $batch_id );
			if ( $batch_data ) {
				if ( is_string( $batch_data ) ) {
					$batch_data = maybe_unserialize( $batch_data );
				}
				if ( is_array( $batch_data ) ) {
					$batch_data['status']       = 'completed';
					$batch_data['completed_at'] = time();
					update_option( self::BATCH_OPTION_PREFIX . $batch_id, $batch_data );
				}

				// Schedule cleanup after 24 hours
				wp_schedule_single_event(
					time() + DAY_IN_SECONDS,
					'cforge_cleanup_batch_data',
					[ $batch_id ]
				);
			}
		}
	}

	/**
	 * Record an error for a batch.
	 *
	 * @since 1.2.0
	 *
	 * @param string $batch_id Batch ID.
	 * @param int    $index    Item index.
	 * @param string $error    Error message.
	 */
	protected function record_error( $batch_id, $index, $error ) {
		$batch_data = get_option( self::BATCH_OPTION_PREFIX . $batch_id );
		if ( $batch_data ) {
			if ( is_string( $batch_data ) ) {
				$batch_data = maybe_unserialize( $batch_data );
			}
			if ( is_array( $batch_data ) ) {
				$batch_data['errors'][] = [
					'index'     => $index,
					'error'     => $error,
					'timestamp' => time(),
				];
				update_option( self::BATCH_OPTION_PREFIX . $batch_id, $batch_data );
			}
		}
	}

	/**
	 * Get batch status.
	 *
	 * @since 1.2.0
	 *
	 * @param string $batch_id Batch ID.
	 * @return array|WP_Error Batch data or error.
	 */
	public function get_batch_status( $batch_id ) {
		$batch_data = get_option( self::BATCH_OPTION_PREFIX . $batch_id );

		if ( ! $batch_data ) {
			return new WP_Error(
				'batch_not_found',
				__( 'Batch not found or expired', 'content-forge' ),
				[ 'status' => 404 ]
			);
		}

		// Ensure batch data is an array (might be serialized string)
		if ( is_string( $batch_data ) ) {
			$batch_data = maybe_unserialize( $batch_data );
		}

		if ( ! is_array( $batch_data ) ) {
			return new WP_Error(
				'invalid_batch_data',
				__( 'Invalid batch data format', 'content-forge' ),
				[ 'status' => 400 ]
			);
		}

		$progress = 0;
		if ( $batch_data['total'] > 0 ) {
			$progress = round( ( $batch_data['completed'] / $batch_data['total'] ) * 100 );
		}

		return [
			'batch_id'            => $batch_id,
			'total'               => $batch_data['total'],
			'completed'           => $batch_data['completed'],
			'pending'             => $batch_data['pending'],
			'progress_percentage' => $progress,
			'status'              => $batch_data['status'],
			'posts_created'       => $batch_data['posts_created'],
			'errors'              => $batch_data['errors'],
			'created_at'          => $batch_data['created_at'],
		];
	}

	/**
	 * Clean up old batch data.
	 *
	 * @since 1.2.0
	 *
	 * @param string $batch_id Batch ID to clean up.
	 */
	public function cleanup_batch_data( $batch_id ) {
		delete_option( self::BATCH_OPTION_PREFIX . $batch_id );
	}
}
