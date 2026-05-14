<?php
// DESCRIPTION: Persistent in-admin notice store for Autopilot events.
// Notices live in a single option until users dismiss or auto-expire.

namespace ContentForge\Autopilot\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores Autopilot notices in a bounded list and renders them on admin pages.
 *
 * The list is capped at 20 most-recent notices. Newer events push older ones out.
 *
 * @since 1.5.0
 */
class Admin_Notices {

	/**
	 * Option key for the persistent notice store.
	 *
	 * @var string
	 */
	const OPTION = 'cforge_autopilot_admin_notices';

	/**
	 * AJAX action for dismissal.
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'cforge_autopilot_dismiss_notice';

	/**
	 * Max notices to retain.
	 *
	 * @var int
	 */
	const MAX_NOTICES = 20;

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, [ $this, 'handle_dismiss' ] );
	}

	/**
	 * Record a new notice.
	 *
	 * @since 1.5.0
	 *
	 * @param string $type       One of: error | warning | success | info.
	 * @param string $message    Plain-text message (HTML escaped on render).
	 * @param array  $context    Optional: ['schedule_id' => int, 'event' => string, 'persistent' => bool].
	 * @return string             Notice ID.
	 */
	public function add( $type, $message, array $context = [] ) {
		$notices = $this->all();

		$id     = uniqid( 'cforge_n_', false );
		$notice = [
			'id'          => $id,
			'type'        => in_array( $type, [ 'error', 'warning', 'success', 'info' ], true ) ? $type : 'info',
			'message'     => (string) $message,
			'schedule_id' => isset( $context['schedule_id'] ) ? (int) $context['schedule_id'] : 0,
			'event'       => isset( $context['event'] ) ? (string) $context['event'] : '',
			'persistent'  => ! empty( $context['persistent'] ),
			'created_at'  => time(),
		];

		array_unshift( $notices, $notice );
		$notices = array_slice( $notices, 0, self::MAX_NOTICES );
		update_option( self::OPTION, $notices, false );

		return $id;
	}

	/**
	 * All stored notices, newest first.
	 *
	 * @since 1.5.0
	 *
	 * @return array
	 */
	public function all() {
		$raw = get_option( self::OPTION, [] );
		return is_array( $raw ) ? $raw : [];
	}

	/**
	 * Remove a notice by ID.
	 *
	 * @since 1.5.0
	 *
	 * @param string $id Notice ID.
	 * @return void
	 */
	public function dismiss( $id ) {
		$id      = (string) $id;
		$notices = array_values(
			array_filter(
				$this->all(),
				static function ( $n ) use ( $id ) {
					return ( $n['id'] ?? '' ) !== $id;
				}
			)
		);
		update_option( self::OPTION, $notices, false );
	}

	/**
	 * Render notices on admin pages.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notices = $this->all();
		if ( empty( $notices ) ) {
			return;
		}

		$autopilot_url = admin_url( 'admin.php?page=cforge-autopilot' );
		$ajax_url      = admin_url( 'admin-ajax.php' );
		$nonce         = wp_create_nonce( self::DISMISS_ACTION );

		foreach ( $notices as $notice ) {
			$type      = isset( $notice['type'] ) ? $notice['type'] : 'info';
			$id        = isset( $notice['id'] ) ? $notice['id'] : '';
			$message   = isset( $notice['message'] ) ? $notice['message'] : '';
			$css_class = 'notice notice-' . esc_attr( $type ) . ' is-dismissible';

			printf(
				'<div class="%s" data-cforge-notice-id="%s"><p>%s</p></div>',
				esc_attr( $css_class ),
				esc_attr( $id ),
				wp_kses_post(
					sprintf(
						'<strong>%s</strong> %s &nbsp;<a href="%s">%s</a>',
						esc_html__( 'Autopilot:', 'content-forge' ),
						esc_html( $message ),
						esc_url( $autopilot_url ),
						esc_html__( 'Open Autopilot', 'content-forge' )
					)
				)
			);
		}

		// Tiny inline JS to call the AJAX dismissal when the user clicks "X".
		?>
		<script>
		(function() {
			document.querySelectorAll('.notice[data-cforge-notice-id]').forEach(function (el) {
				el.addEventListener('click', function (evt) {
					if ( ! evt.target.classList.contains('notice-dismiss') ) return;
					var id = el.getAttribute('data-cforge-notice-id');
					var body = new FormData();
					body.append('action', '<?php echo esc_js( self::DISMISS_ACTION ); ?>');
					body.append('nonce', '<?php echo esc_js( $nonce ); ?>');
					body.append('id', id);
					fetch('<?php echo esc_js( $ajax_url ); ?>', { method: 'POST', body: body, credentials: 'same-origin' });
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * AJAX handler for dismissal.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::DISMISS_ACTION ) ) {
			wp_send_json_error( null, 403 );
		}
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( $id === '' ) {
			wp_send_json_error( null, 400 );
		}
		$this->dismiss( $id );
		wp_send_json_success();
	}
}
