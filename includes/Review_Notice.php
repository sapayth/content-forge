<?php
/**
 * Review/feature-request admin notice for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.5.1
 */

namespace ContentForge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a dismissible admin notice on Content Forge pages, 24 hours after
 * activation, asking users for a 5-star review or a feature idea.
 *
 * @since 1.5.1
 */
class Review_Notice {

	/**
	 * Option storing the activation timestamp.
	 *
	 * @var string
	 */
	const INSTALLED_AT_OPTION = 'cforge_installed_at';

	/**
	 * Option storing whether the notice has been dismissed.
	 *
	 * @var string
	 */
	const DISMISSED_OPTION = 'cforge_review_notice_dismissed';

	/**
	 * AJAX action for dismissal.
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'cforge_dismiss_review_notice';

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.5.1
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, [ $this, 'handle_dismiss' ] );
	}

	/**
	 * Render the notice if it's eligible to be shown.
	 *
	 * @since 1.5.1
	 *
	 * @return void
	 */
	public function maybe_render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'cforge' ) ) {
			return;
		}

		if ( get_option( self::DISMISSED_OPTION ) ) {
			return;
		}

		$installed_at = get_option( self::INSTALLED_AT_OPTION );
		if ( ! $installed_at ) {
			// Existing install with no recorded activation time; start the clock now.
			add_option( self::INSTALLED_AT_OPTION, time() );
			return;
		}

		if ( time() < (int) $installed_at + DAY_IN_SECONDS ) {
			return;
		}

		$nonce = wp_create_nonce( self::DISMISS_ACTION );
		?>
		<div class="notice notice-info is-dismissible cforge-review-notice">
			<p>
				<strong><?php esc_html_e( 'Enjoying Content Forge?', 'content-forge' ); ?></strong>
				<?php esc_html_e( 'A quick 5-star review helps more people discover it. And if there\'s something you\'d love to see next, send us your idea.', 'content-forge' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( CFORGE_REVIEW_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
					<?php esc_html_e( 'Leave a 5-star review', 'content-forge' ); ?>
				</a>
				<a href="<?php echo esc_url( CFORGE_FEATURE_REQUEST_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
					<?php esc_html_e( 'Suggest a feature', 'content-forge' ); ?>
				</a>
			</p>
		</div>
		<script>
		(function() {
			var notice = document.querySelector('.cforge-review-notice');
			if ( ! notice ) {
				return;
			}
			notice.addEventListener('click', function (evt) {
				if ( ! evt.target.classList.contains('notice-dismiss') ) {
					return;
				}
				var body = new FormData();
				body.append('action', '<?php echo esc_js( self::DISMISS_ACTION ); ?>');
				body.append('nonce', '<?php echo esc_js( $nonce ); ?>');
				fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: body, credentials: 'same-origin' });
			});
		})();
		</script>
		<?php
	}

	/**
	 * AJAX handler for dismissal.
	 *
	 * @since 1.5.1
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

		update_option( self::DISMISSED_OPTION, true, false );
		wp_send_json_success();
	}
}
