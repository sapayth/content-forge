<?php
// DESCRIPTION: Executes a single Autopilot run — resolves topic, calls AI, creates posts.
// Records the run row, updates schedule counters, and triggers auto-pause logic.

namespace ContentForge\Autopilot;

use Exception;
use WP_Error;
use ContentForge\Autopilot\Publishing\Post_Builder;
use ContentForge\Autopilot\Topic\Topic_Resolver;
use ContentForge\Autopilot\Topic\Duplicate_Guard;
use ContentForge\Autopilot\Notifications\Admin_Notices;
use ContentForge\Autopilot\Notifications\Email_Notifier;
use ContentForge\Generator\AI_Content_Generator;
use ContentForge\Settings\AI_Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action Scheduler handler for cforge_autopilot_run_schedule.
 *
 * @since 1.5.0
 */
class Schedule_Runner {

	const HOOK = 'cforge_autopilot_run_schedule';

	/** @var Schedule_Repository */
	protected $schedules;

	/** @var Run_Repository */
	protected $runs;

	/** @var Topic_Resolver */
	protected $topic_resolver;

	/** @var Post_Builder */
	protected $post_builder;

	/** @var Duplicate_Guard */
	protected $duplicate_guard;

	/** @var Admin_Notices */
	protected $admin_notices;

	/** @var Email_Notifier */
	protected $email_notifier;

	/**
	 * Constructor.
	 *
	 * @param Schedule_Repository $schedules       Schedule repo.
	 * @param Run_Repository      $runs            Run repo.
	 * @param Topic_Resolver      $topic_resolver  Topic resolver.
	 * @param Post_Builder        $post_builder    Post builder.
	 * @param Duplicate_Guard     $duplicate_guard Duplicate guard.
	 * @param Admin_Notices       $admin_notices   Admin notice store.
	 * @param Email_Notifier      $email_notifier  Email notifier.
	 */
	public function __construct(
		Schedule_Repository $schedules,
		Run_Repository $runs,
		Topic_Resolver $topic_resolver,
		Post_Builder $post_builder,
		Duplicate_Guard $duplicate_guard,
		Admin_Notices $admin_notices,
		Email_Notifier $email_notifier
	) {
		$this->schedules       = $schedules;
		$this->runs            = $runs;
		$this->topic_resolver  = $topic_resolver;
		$this->post_builder    = $post_builder;
		$this->duplicate_guard = $duplicate_guard;
		$this->admin_notices   = $admin_notices;
		$this->email_notifier  = $email_notifier;
	}

	/**
	 * Register the AS hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK, [ $this, 'handle' ], 10, 2 );
	}

	/**
	 * Run handler.
	 *
	 * @since 1.5.0
	 *
	 * @param int $schedule_id Schedule ID.
	 * @param int $run_id      Run row ID.
	 * @return void
	 */
	public function handle( $schedule_id, $run_id ) {
		$schedule = $this->schedules->find( (int) $schedule_id );
		if ( ! $schedule ) {
			$this->runs->update(
				(int) $run_id,
				[
					'status'        => Run::STATUS_FAILED,
					'finished_at'   => gmdate( 'Y-m-d H:i:s' ),
					'error_message' => 'Schedule not found',
				]
			);
			return;
		}

		if ( $schedule->status() !== 'active' ) {
			$this->runs->update(
				(int) $run_id,
				[
					'status'        => Run::STATUS_SKIPPED,
					'finished_at'   => gmdate( 'Y-m-d H:i:s' ),
					'error_message' => 'Schedule no longer active',
				]
			);
			return;
		}

		// Preflight: AI configured.
		if ( ! AI_Settings_Manager::is_configured() ) {
			$this->fail_and_auto_pause( $schedule, $run_id, 'ai_not_configured', __( 'AI is not configured', 'content-forge' ) );
			return;
		}

		// Preflight: target category exists when post_type=post.
		$category_id = (int) $schedule->config_get( 'targeting.category_id', 0 );
		$post_type   = (string) $schedule->config_get( 'targeting.post_type', 'post' );
		if ( $post_type === 'post' && $category_id > 0 && ! get_term( $category_id, 'category' ) ) {
			$this->fail_and_auto_pause( $schedule, $run_id, 'category_missing', __( 'Target category no longer exists', 'content-forge' ) );
			return;
		}

		// Preflight: per-schedule daily post cap. Skip (not fail) the run if hit.
		$daily_cap = (int) $schedule->config_get( 'safety.daily_post_cap', 5 );
		if ( $daily_cap > 0 ) {
			$since_utc      = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
			$posts_last_24h = $this->runs->count_posts_created_since( $schedule->id(), $since_utc );
			if ( $posts_last_24h >= $daily_cap ) {
				$this->runs->update(
					(int) $run_id,
					[
						'status'        => Run::STATUS_SKIPPED,
						'finished_at'   => gmdate( 'Y-m-d H:i:s' ),
						'error_message' => sprintf(
							/* translators: %d: daily post cap */
							__( 'Daily post cap reached (%d). Run skipped.', 'content-forge' ),
							$daily_cap
						),
					]
				);
				return;
			}
		}

		/**
		 * Fires before a run starts (after preflight, before any AI calls).
		 *
		 * @since 1.5.0
		 *
		 * @param Schedule $schedule Schedule.
		 * @param int      $run_id   Run row ID.
		 */
		do_action( 'cforge_autopilot_before_run', $schedule, $run_id );

		$this->runs->update(
			(int) $run_id,
			[ 'status' => Run::STATUS_RUNNING ]
		);

		$posts_per_run = max( 1, (int) $schedule->config_get( 'posts_per_run', 1 ) );

		$created_post_ids = [];
		$errors           = [];
		$topic_used       = '';
		$ai_provider      = AI_Settings_Manager::get_active_provider();
		$ai_model         = AI_Settings_Manager::get_active_model();

		$avoid_dupes  = (bool) $schedule->config_get( 'avoid_recent_duplicates', true );
		$category_id  = (int) $schedule->config_get( 'targeting.category_id', 0 );

		for ( $i = 0; $i < $posts_per_run; $i++ ) {
			// Refresh the schedule between iterations so cursor mutations from the
			// previous topic resolution are visible.
			$current_schedule = $this->schedules->find( $schedule->id() );
			if ( ! $current_schedule ) {
				$errors[] = 'Schedule disappeared mid-run';
				break;
			}

			$topic = $this->topic_resolver->resolve( $current_schedule );
			if ( $topic === '' ) {
				$errors[] = 'No topic available';
				break;
			}
			if ( $topic_used === '' ) {
				$topic_used = $topic;
			}

			try {
				$ai_result = $this->generate_ai_content( $current_schedule, $topic, $ai_provider, $ai_model );
				if ( is_wp_error( $ai_result ) ) {
					$errors[] = $ai_result->get_error_message();
					continue;
				}

				// Duplicate guard: if proposed title is too similar to recent posts in the target
				// category, regenerate once. If still duplicate, skip this post (not a failure).
				if ( $avoid_dupes && $category_id > 0 ) {
					$proposed_title = isset( $ai_result['title'] ) ? (string) $ai_result['title'] : '';
					if ( $proposed_title !== '' && $this->duplicate_guard->is_duplicate( $proposed_title, $category_id ) ) {
						$retry = $this->generate_ai_content( $current_schedule, $topic, $ai_provider, $ai_model );
						if ( ! is_wp_error( $retry ) ) {
							$retry_title = isset( $retry['title'] ) ? (string) $retry['title'] : '';
							if ( $retry_title !== '' && ! $this->duplicate_guard->is_duplicate( $retry_title, $category_id ) ) {
								$ai_result = $retry;
							} else {
								$errors[] = 'Skipped: duplicate topic';
								continue;
							}
						} else {
							$errors[] = $retry->get_error_message();
							continue;
						}
					}
				}

				$post_id = $this->insert_post( $current_schedule, $topic, $ai_result );
				if ( is_wp_error( $post_id ) ) {
					$errors[] = $post_id->get_error_message();
					continue;
				}

				$created_post_ids[] = (int) $post_id;

				/**
				 * Fires after a post has been successfully created.
				 *
				 * @since 1.5.0
				 *
				 * @param int      $post_id   Post ID.
				 * @param Schedule $schedule  Schedule.
				 * @param int      $run_id    Run ID.
				 */
				do_action( 'cforge_autopilot_post_created', (int) $post_id, $current_schedule, (int) $run_id );
			} catch ( Exception $e ) {
				$errors[] = $e->getMessage();
			}
		}

		$status = $this->compute_run_status( count( $created_post_ids ), count( $errors ), $posts_per_run );

		$this->runs->update(
			(int) $run_id,
			[
				'status'        => $status,
				'finished_at'   => gmdate( 'Y-m-d H:i:s' ),
				'topic_used'    => mb_substr( $topic_used, 0, 500 ),
				'posts_created' => $created_post_ids,
				'error_message' => empty( $errors ) ? null : wp_json_encode( $errors ),
				'ai_provider'   => $ai_provider,
				'ai_model'      => $ai_model,
			]
		);

		$is_failure = ( $status === Run::STATUS_FAILED );

		$this->schedules->record_run(
			$schedule->id(),
			count( $created_post_ids ),
			$is_failure,
			gmdate( 'Y-m-d H:i:s' )
		);

		// Auto-pause when failure streak hits the configured threshold.
		$threshold = (int) $schedule->config_get( 'safety.auto_pause_after_failures', 3 );
		if ( $threshold > 0 ) {
			$current = $this->schedules->find( $schedule->id() );
			if ( $current && $current->failure_streak() >= $threshold ) {
				$this->schedules->auto_pause( $schedule->id(), 'consecutive_failures' );

				$this->record_auto_pause_notification( $current, 'consecutive_failures' );

				/**
				 * Fires when a schedule is auto-paused.
				 *
				 * @since 1.5.0
				 *
				 * @param Schedule $schedule Schedule.
				 * @param string   $reason   Reason key.
				 */
				do_action( 'cforge_autopilot_schedule_auto_paused', $current, 'consecutive_failures' );
			}
		}

		// Queue-mode strategy may have flagged the schedule as drained mid-run.
		// Pause the schedule cleanly now that the run has fully completed.
		if ( (int) get_post_meta( $schedule->id(), '_cforge_queue_drained', true ) === 1 ) {
			delete_post_meta( $schedule->id(), '_cforge_queue_drained' );
			$still_active = $this->schedules->find( $schedule->id() );
			if ( $still_active && $still_active->status() === 'active' ) {
				$this->schedules->auto_pause( $schedule->id(), 'queue_empty' );

				$this->record_auto_pause_notification( $still_active, 'queue_empty' );

				do_action( 'cforge_autopilot_schedule_auto_paused', $still_active, 'queue_empty' );
			}
		}

		// Email notifications for the completed run.
		$final_run = $this->runs->find( (int) $run_id );
		$this->email_notifier->notify_run_completed( $schedule, $final_run, $status );

		/**
		 * Fires after a run completes (success, partial, failed, or skipped).
		 *
		 * @since 1.5.0
		 *
		 * @param Schedule $schedule Schedule.
		 * @param int      $run_id   Run ID.
		 * @param string   $status   Run status.
		 */
		do_action( 'cforge_autopilot_run_completed', $schedule, (int) $run_id, $status );
	}

	/**
	 * Record an in-admin notice and send the auto-pause email for a schedule.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @param string   $reason   Reason key.
	 * @return void
	 */
	protected function record_auto_pause_notification( Schedule $schedule, $reason ) {
		$message = sprintf(
			/* translators: 1: schedule name, 2: reason */
			__( '"%1$s" was auto-paused (%2$s).', 'content-forge' ),
			$schedule->name(),
			$this->human_reason( $reason )
		);
		$this->admin_notices->add(
			'warning',
			$message,
			[
				'schedule_id' => $schedule->id(),
				'event'       => 'auto_paused',
				'persistent'  => true,
			]
		);
		$this->email_notifier->notify_auto_paused( $schedule, $reason );
	}

	/**
	 * Short human label for an auto-pause reason.
	 *
	 * @param string $reason Reason key.
	 * @return string
	 */
	protected function human_reason( $reason ) {
		switch ( $reason ) {
			case 'consecutive_failures':
				return __( 'consecutive failures', 'content-forge' );
			case 'queue_empty':
				return __( 'queue empty', 'content-forge' );
			case 'ai_not_configured':
				return __( 'AI not configured', 'content-forge' );
			case 'category_missing':
				return __( 'category missing', 'content-forge' );
		}
		return $reason;
	}

	/**
	 * Generate AI content for a topic (no DB insertion).
	 *
	 * Split from insertion so the runner can apply the Duplicate_Guard between
	 * generation and persistence.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @param string   $topic    Topic string.
	 * @param string   $provider AI provider slug.
	 * @param string   $model    AI model slug.
	 * @return array|WP_Error    AI result with 'title' and 'content' keys, or error.
	 */
	protected function generate_ai_content( Schedule $schedule, $topic, $provider, $model ) {
		$api_key = AI_Settings_Manager::get_api_key( $provider );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'cforge_autopilot_no_api_key', __( 'AI provider API key missing', 'content-forge' ) );
		}

		$post_type   = (string) $schedule->config_get( 'targeting.post_type', 'post' );
		$editor_type = function_exists( 'cforge_detect_editor_type' ) ? cforge_detect_editor_type( $post_type ) : 'block';

		$custom_prompt = trim( (string) $schedule->config_get( 'ai.custom_prompt', '' ) );
		$prompt        = trim( $topic . ( $custom_prompt !== '' ? "\n\n" . $custom_prompt : '' ) );

		$generator = new AI_Content_Generator( $provider, $model, $api_key, $editor_type );
		return $generator->generate( 'general', $prompt );
	}

	/**
	 * Insert a post built from an AI result.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule  Schedule.
	 * @param string   $topic     Topic used.
	 * @param array    $ai_result AI result with 'title' and 'content'.
	 * @return int|WP_Error
	 */
	protected function insert_post( Schedule $schedule, $topic, array $ai_result ) {
		$post_args = $this->post_builder->build( $schedule, $topic, $ai_result );
		$post_id   = wp_insert_post( $post_args, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		return (int) $post_id;
	}

	/**
	 * Determine run status from counts.
	 *
	 * @since 1.5.0
	 *
	 * @param int $created   Number of posts created.
	 * @param int $errors    Number of errors recorded.
	 * @param int $requested Number of posts requested.
	 * @return string
	 */
	protected function compute_run_status( $created, $errors, $requested ) {
		if ( $created === 0 && $errors === 0 ) {
			return Run::STATUS_SKIPPED;
		}
		if ( $created === 0 ) {
			return Run::STATUS_FAILED;
		}
		if ( $created < $requested || $errors > 0 ) {
			return Run::STATUS_PARTIAL;
		}
		return Run::STATUS_SUCCESS;
	}

	/**
	 * Fail the run and auto-pause the schedule with a stated reason.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @param int      $run_id   Run ID.
	 * @param string   $reason   Reason key.
	 * @param string   $message  Human-readable message.
	 * @return void
	 */
	protected function fail_and_auto_pause( Schedule $schedule, $run_id, $reason, $message ) {
		$this->runs->update(
			(int) $run_id,
			[
				'status'        => Run::STATUS_FAILED,
				'finished_at'   => gmdate( 'Y-m-d H:i:s' ),
				'error_message' => $message,
			]
		);
		$this->schedules->auto_pause( $schedule->id(), $reason );
		$this->record_auto_pause_notification( $schedule, $reason );
		do_action( 'cforge_autopilot_schedule_auto_paused', $schedule, $reason );
	}
}
