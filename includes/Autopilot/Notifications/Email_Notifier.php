<?php
// DESCRIPTION: Sends short plain-text emails for Autopilot events via wp_mail.
// Recipient + opt-in level come from the schedule's notifications.* config.

namespace ContentForge\Autopilot\Notifications;

use ContentForge\Autopilot\Schedule;
use ContentForge\Autopilot\Run;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends per-event email notifications for Autopilot.
 *
 * Three events: 'success' (run completed with posts), 'failure' (run failed or partial),
 * 'auto_paused' (schedule auto-paused by the system).
 *
 * @since 1.5.0
 */
class Email_Notifier {

	const EVENT_SUCCESS     = 'success';
	const EVENT_FAILURE     = 'failure';
	const EVENT_AUTO_PAUSED = 'auto_paused';

	/**
	 * Send notification for a completed run, if subscribed.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @param Run|null $run      Run row (may be null if not loaded).
	 * @param string   $status   Run status: success | partial | failed | skipped.
	 * @return bool              True if an email was sent.
	 */
	public function notify_run_completed( Schedule $schedule, $run, $status ) {
		$mode = (string) $schedule->config_get( 'notifications.email_mode', 'none' );
		if ( $mode === 'none' ) {
			return false;
		}

		$is_failure = ( $status === Run::STATUS_FAILED || $status === Run::STATUS_PARTIAL );

		// 'failure' opt-in only sends for failures/partials.
		if ( $mode === 'failure' && ! $is_failure ) {
			return false;
		}

		$event   = $is_failure ? self::EVENT_FAILURE : self::EVENT_SUCCESS;
		$subject = $this->build_subject( $schedule, $event, $status );
		$body    = $this->build_body( $schedule, $run, $event, $status );

		return $this->send( $schedule, $event, $subject, $body, $run );
	}

	/**
	 * Send notification when a schedule is auto-paused.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @param string   $reason   Reason key.
	 * @return bool
	 */
	public function notify_auto_paused( Schedule $schedule, $reason ) {
		$mode = (string) $schedule->config_get( 'notifications.email_mode', 'none' );
		if ( $mode === 'none' ) {
			return false;
		}

		$subject = $this->build_subject( $schedule, self::EVENT_AUTO_PAUSED, $reason );
		$body    = $this->build_auto_paused_body( $schedule, $reason );

		return $this->send( $schedule, self::EVENT_AUTO_PAUSED, $subject, $body, null );
	}

	/**
	 * Resolve the recipient list for a schedule + event, then send via wp_mail.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule.
	 * @param string   $event    Event key.
	 * @param string   $subject  Subject line.
	 * @param string   $body     Plain-text body.
	 * @param Run|null $run      Run row when applicable.
	 * @return bool              wp_mail return.
	 */
	protected function send( Schedule $schedule, $event, $subject, $body, $run ) {
		$configured = trim( (string) $schedule->config_get( 'notifications.email_to', '' ) );

		$default_to = $configured !== '' && is_email( $configured )
			? $configured
			: $this->fallback_recipient( $schedule );

		if ( ! $default_to ) {
			return false;
		}

		$recipients = [ $default_to ];

		/**
		 * Filter the recipient list for autopilot emails.
		 *
		 * @since 1.5.0
		 *
		 * @param string[] $recipients Email addresses.
		 * @param string   $event      Event key.
		 * @param Schedule $schedule   Schedule.
		 */
		$recipients = (array) apply_filters( 'cforge_autopilot_email_recipients', $recipients, $event, $schedule );

		/**
		 * Filter the email body.
		 *
		 * @since 1.5.0
		 *
		 * @param string   $body     Body.
		 * @param string   $event    Event key.
		 * @param Schedule $schedule Schedule.
		 * @param Run|null $run      Run row.
		 */
		$body = (string) apply_filters( 'cforge_autopilot_email_body', $body, $event, $schedule, $run );

		if ( empty( $recipients ) ) {
			return false;
		}

		return (bool) wp_mail( $recipients, $subject, $body );
	}

	/**
	 * Resolve fallback recipient when config doesn't specify one.
	 *
	 * @param Schedule $schedule Schedule.
	 * @return string             Email address, or '' if none.
	 */
	protected function fallback_recipient( Schedule $schedule ) {
		$user = get_userdata( $schedule->created_by() );
		if ( $user && is_email( $user->user_email ) ) {
			return $user->user_email;
		}
		$admin = get_option( 'admin_email' );
		return is_email( $admin ) ? $admin : '';
	}

	/**
	 * Build the subject line for an event.
	 *
	 * @param Schedule $schedule Schedule.
	 * @param string   $event    Event key.
	 * @param string   $detail   Status string or reason key.
	 * @return string
	 */
	protected function build_subject( Schedule $schedule, $event, $detail ) {
		$site = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$name = $schedule->name();

		switch ( $event ) {
			case self::EVENT_SUCCESS:
				return sprintf(
					/* translators: 1: site name, 2: autopilot name */
					__( '[%1$s] Autopilot "%2$s" generated a new post', 'content-forge' ),
					$site,
					$name
				);
			case self::EVENT_FAILURE:
				return sprintf(
					/* translators: 1: site name, 2: autopilot name */
					__( '[%1$s] Autopilot "%2$s" run failed', 'content-forge' ),
					$site,
					$name
				);
			case self::EVENT_AUTO_PAUSED:
				return sprintf(
					/* translators: 1: site name, 2: autopilot name */
					__( '[%1$s] Autopilot "%2$s" was auto-paused', 'content-forge' ),
					$site,
					$name
				);
		}
		return sprintf( '[%s] Autopilot event', $site );
	}

	/**
	 * Build the body for run-completed events.
	 *
	 * @param Schedule $schedule Schedule.
	 * @param Run|null $run      Run row.
	 * @param string   $event    Event key.
	 * @param string   $status   Run status.
	 * @return string
	 */
	protected function build_body( Schedule $schedule, $run, $event, $status ) {
		$lines = [];
		$lines[] = sprintf( __( 'Autopilot: %s', 'content-forge' ), $schedule->name() );
		$lines[] = sprintf( __( 'Status: %s', 'content-forge' ), $status );

		if ( $run instanceof Run ) {
			if ( $run->topic_used ) {
				$lines[] = sprintf( __( 'Topic: %s', 'content-forge' ), $run->topic_used );
			}
			if ( is_array( $run->posts_created ) && ! empty( $run->posts_created ) ) {
				$lines[] = sprintf(
					/* translators: %s: list of post URLs separated by newlines */
					__( "Posts created:\n%s", 'content-forge' ),
					implode( "\n", array_map( [ $this, 'edit_url' ], $run->posts_created ) )
				);
			}
			if ( $run->error_message ) {
				$lines[] = sprintf( __( 'Error: %s', 'content-forge' ), $this->summarize_error( $run->error_message ) );
			}
		}

		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: admin URL for the autopilot */
			__( 'View this autopilot: %s', 'content-forge' ),
			admin_url( 'admin.php?page=cforge-autopilot' )
		);

		return implode( "\n", $lines );
	}

	/**
	 * Build the body for an auto-pause event.
	 *
	 * @param Schedule $schedule Schedule.
	 * @param string   $reason   Reason key.
	 * @return string
	 */
	protected function build_auto_paused_body( Schedule $schedule, $reason ) {
		$reason_label = $this->human_reason( $reason );

		$lines = [
			sprintf( __( 'Autopilot: %s', 'content-forge' ), $schedule->name() ),
			sprintf( __( 'Reason: %s', 'content-forge' ), $reason_label ),
			'',
			__( 'No further runs will fire until you resume the autopilot.', 'content-forge' ),
			'',
			sprintf(
				/* translators: %s: admin URL for the autopilot */
				__( 'Review and resume: %s', 'content-forge' ),
				admin_url( 'admin.php?page=cforge-autopilot' )
			),
		];

		return implode( "\n", $lines );
	}

	/**
	 * Format a post ID as an edit URL.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	protected function edit_url( $post_id ) {
		return admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
	}

	/**
	 * Translate a reason key to a short human label.
	 *
	 * @param string $reason Reason key.
	 * @return string
	 */
	protected function human_reason( $reason ) {
		switch ( $reason ) {
			case 'consecutive_failures':
				return __( 'Consecutive failures hit the configured threshold', 'content-forge' );
			case 'queue_empty':
				return __( 'Topic queue is empty', 'content-forge' );
			case 'ai_not_configured':
				return __( 'AI provider is not configured', 'content-forge' );
			case 'category_missing':
				return __( 'Target category no longer exists', 'content-forge' );
		}
		return $reason;
	}

	/**
	 * Reduce a raw error_message JSON string to a one-line summary.
	 *
	 * @param string $raw Error.
	 * @return string
	 */
	protected function summarize_error( $raw ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) && ! empty( $decoded ) ) {
			return (string) reset( $decoded );
		}
		return (string) $raw;
	}
}
