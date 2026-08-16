<?php
// DESCRIPTION: WP dashboard widget surfacing Autopilot health — last dispatcher tick + counts.
// Renders red if the heartbeat is stale (>5 min); helps diagnose silent WP-Cron failures.

namespace ContentForge\Autopilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the "Content Forge — Autopilot" dashboard widget.
 *
 * @since 1.5.0
 */
class Dashboard_Widget {

	const STALE_SECONDS = 300;

	/**
	 * Register the widget on admin dashboard.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_dashboard_setup', [ $this, 'add_widget' ] );
	}

	/**
	 * Register the widget.
	 *
	 * @return void
	 */
	public function add_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'cforge_autopilot_widget',
			__( 'Content Forge — Autopilot', 'content-forge' ),
			[ $this, 'render' ]
		);
	}

	/**
	 * Render widget body.
	 *
	 * @return void
	 */
	public function render() {
		$enabled    = Plugin::is_enabled();
		$heartbeat  = (int) get_option( Dispatcher::HEARTBEAT_OPTION, 0 );
		$now        = time();
		$stale      = $heartbeat > 0 && ( $now - $heartbeat ) > self::STALE_SECONDS;

		$active_count = $this->count_schedules( 'publish' );
		$paused_count = $this->count_schedules( 'private' );

		echo '<div class="cforge-autopilot-widget" style="font-size:13px;line-height:1.6;">';

		if ( ! $enabled ) {
			printf(
				'<p><strong>%s</strong> %s <a href="%s">%s</a>.</p>',
				esc_html__( 'Autopilot is disabled.', 'content-forge' ),
				esc_html__( 'Enable it on the', 'content-forge' ),
				esc_url( admin_url( 'admin.php?page=cforge-settings' ) ),
				esc_html__( 'Settings page', 'content-forge' )
			);
		} elseif ( $heartbeat === 0 ) {
			printf(
				'<p style="color:#b26200;">%s</p>',
				esc_html__( 'Dispatcher has not run yet. Waiting for the first WP-Cron tick…', 'content-forge' )
			);
		} elseif ( $stale ) {
			$mins = max( 1, (int) round( ( $now - $heartbeat ) / 60 ) );
			printf(
				'<p style="color:#b32d2e;"><strong>%s</strong></p><p>%s</p>',
				sprintf(
					/* translators: %d: number of minutes */
					esc_html( _n( 'Dispatcher last ran %d minute ago.', 'Dispatcher last ran %d minutes ago.', $mins, 'content-forge' ) ),
					$mins
				),
				esc_html__( 'This usually means WP-Cron is not firing. Check that your site is being visited regularly or that a system cron is hitting wp-cron.php.', 'content-forge' )
			);
		} else {
			$mins = max( 0, (int) round( ( $now - $heartbeat ) / 60 ) );
			printf(
				'<p style="color:#1d6b00;">%s</p>',
				sprintf(
					/* translators: %s: minutes-ago text */
					esc_html__( 'Dispatcher healthy — last ran %s.', 'content-forge' ),
					esc_html( $this->minutes_ago( $mins ) )
				)
			);
		}

		printf(
			'<p style="margin-top:8px;"><strong>%s</strong> %d &nbsp; <strong>%s</strong> %d &nbsp; <a href="%s">%s</a></p>',
			esc_html__( 'Active:', 'content-forge' ),
			(int) $active_count,
			esc_html__( 'Paused:', 'content-forge' ),
			(int) $paused_count,
			esc_url( admin_url( 'admin.php?page=cforge-autopilot' ) ),
			esc_html__( 'Open Autopilot', 'content-forge' )
		);

		echo '</div>';
	}

	/**
	 * Autopilot health as data, for callers that render it themselves.
	 *
	 * Mirrors what render() prints, minus the markup. The admin Dashboard page
	 * consumes this over REST.
	 *
	 * @since 1.7.0
	 *
	 * @return array{enabled:bool,heartbeat:int,stale:bool,minutes_since:int|null,active:int,paused:int}
	 */
	public function get_status() {
		$heartbeat = (int) get_option( Dispatcher::HEARTBEAT_OPTION, 0 );
		$elapsed   = $heartbeat > 0 ? ( time() - $heartbeat ) : null;

		return [
			'enabled'       => (bool) Plugin::is_enabled(),
			'heartbeat'     => $heartbeat,
			'stale'         => null !== $elapsed && $elapsed > self::STALE_SECONDS,
			'minutes_since' => null === $elapsed ? null : max( 0, (int) round( $elapsed / 60 ) ),
			'active'        => $this->count_schedules( 'publish' ),
			'paused'        => $this->count_schedules( 'private' ),
		];
	}

	/**
	 * Count schedules by post_status.
	 *
	 * @param string $status post_status.
	 * @return int
	 */
	protected function count_schedules( $status ) {
		$query = new \WP_Query(
			[
				'post_type'      => Schedule::POST_TYPE,
				'post_status'    => $status,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			]
		);
		return (int) $query->found_posts;
	}

	/**
	 * Human-readable minutes-ago label.
	 *
	 * @param int $mins Minutes.
	 * @return string
	 */
	protected function minutes_ago( $mins ) {
		if ( $mins <= 0 ) {
			return __( 'just now', 'content-forge' );
		}
		return sprintf(
			/* translators: %d: minutes */
			_n( '%d minute ago', '%d minutes ago', $mins, 'content-forge' ),
			$mins
		);
	}
}
