<?php
/**
 * Email Verification — token-gated guest listing publishing.
 *
 * Manages the verification token lifecycle for the guest-submission flow:
 *  - generate_token()   creates a 64-char hex token + expiry timestamp.
 *  - verify_token()     checks a presented token against stored meta.
 *  - consume_token()    removes the token after a successful verification.
 *  - resend_verification() rate-limited token rotation + fresh email.
 *  - cleanup_unverified_listings() daily cron that drops abandoned drafts.
 *
 * Wires:
 *  - REST: GET /listora/v1/submission/verify
 *  - REST: POST /listora/v1/submission/resend-verification
 *  - URL : /?listora-verify=1&listing=<id>&token=<token> (template_redirect)
 *  - Cron: wb_listora_cleanup_unverified_listings (daily)
 *
 * @package WBListora\Workflow
 */

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates the email-verification flow for guest listing submissions.
 */
class Email_Verification {

	/**
	 * Post-meta key for the active verification token.
	 *
	 * @var string
	 */
	const META_TOKEN = '_listora_verify_token';

	/**
	 * Post-meta key for the token's expiration timestamp (UTC, MySQL format).
	 *
	 * @var string
	 */
	const META_EXPIRES = '_listora_verify_expires_at';

	/**
	 * Post-meta key for the timestamp of the last sent verification email.
	 * Used to enforce the 5-minute resend rate-limit.
	 *
	 * @var string
	 */
	const META_LAST_SENT = '_listora_verify_last_sent_at';

	/**
	 * Post-meta key recording when the listing was successfully verified.
	 *
	 * @var string
	 */
	const META_VERIFIED_AT = '_listora_verified_at';

	/**
	 * Cron hook for the daily unverified-listings cleanup job.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'wb_listora_cleanup_unverified_listings';

	/**
	 * Resend rate-limit window (seconds).
	 *
	 * @var int
	 */
	const RESEND_COOLDOWN = 300;

	/**
	 * Bootstrap the verification system — adds the public verify URL handler,
	 * registers the cleanup cron, and queues the first run on plugin activation.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'handle_verify_url' ), 1 );
		add_action( self::CRON_HOOK, array( $this, 'cleanup_unverified_listings' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Generate a fresh 64-char hex verification token and persist it as post meta.
	 *
	 * The token's expiry is configurable via the
	 * `verification_link_expiry_hours` setting (default 24h, range 1-168).
	 *
	 * @param int $post_id Listing post ID.
	 * @param int $user_id Author user ID (kept for parity with future per-user
	 *                    salting; presently unused).
	 * @return string Plaintext token.
	 */
	public static function generate_token( $post_id, $user_id = 0 ) {
		$token = bin2hex( random_bytes( 32 ) );
		$hours = (int) wb_listora_get_setting( 'verification_link_expiry_hours', 24 );
		$hours = max( 1, min( 168, $hours ) );

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $hours * HOUR_IN_SECONDS ) );

		update_post_meta( $post_id, self::META_TOKEN, $token );
		update_post_meta( $post_id, self::META_EXPIRES, $expires_at );

		// Track author for resend audit trails.
		if ( $user_id > 0 ) {
			update_post_meta( $post_id, '_listora_verify_user_id', (int) $user_id );
		}

		return $token;
	}

	/**
	 * Validate a presented token against the stored meta.
	 *
	 * @param int    $post_id Listing post ID.
	 * @param string $token   Token presented by the user.
	 * @return bool True when the token matches AND has not expired.
	 */
	public static function verify_token( $post_id, $token ) {
		if ( empty( $token ) || ! is_string( $token ) ) {
			return false;
		}

		$stored = (string) get_post_meta( $post_id, self::META_TOKEN, true );
		if ( '' === $stored || ! hash_equals( $stored, $token ) ) {
			return false;
		}

		if ( self::is_expired( $post_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Clear all verification meta after a successful consume.
	 *
	 * @param int $post_id Listing post ID.
	 */
	public static function consume_token( $post_id ) {
		delete_post_meta( $post_id, self::META_TOKEN );
		delete_post_meta( $post_id, self::META_EXPIRES );
		delete_post_meta( $post_id, self::META_LAST_SENT );
		update_post_meta( $post_id, self::META_VERIFIED_AT, current_time( 'mysql', true ) );
	}

	/**
	 * Whether the listing is currently in the pending_verification status.
	 *
	 * @param int $post_id Listing post ID.
	 * @return bool
	 */
	public static function is_pending_verification( $post_id ) {
		$post = get_post( $post_id );
		return $post && 'pending_verification' === $post->post_status;
	}

	/**
	 * Whether the stored token has passed its expiration timestamp.
	 *
	 * @param int $post_id Listing post ID.
	 * @return bool True if expired or no expiry recorded.
	 */
	public static function is_expired( $post_id ) {
		$expires = (string) get_post_meta( $post_id, self::META_EXPIRES, true );
		if ( '' === $expires ) {
			return true;
		}

		$expires_ts = strtotime( $expires . ' UTC' );
		return false === $expires_ts || $expires_ts < time();
	}

	/**
	 * Build the public verification URL for a given post + token.
	 *
	 * @param int    $post_id Listing post ID.
	 * @param string $token   Plaintext token.
	 * @return string Absolute URL.
	 */
	public static function get_verify_url( $post_id, $token ) {
		return add_query_arg(
			array(
				'listora-verify' => 1,
				'listing'        => (int) $post_id,
				'token'          => rawurlencode( $token ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Send (or re-send) a verification email.
	 *
	 * Generates a fresh token, records the send timestamp, and dispatches the
	 * `wb_listora_listing_verify_email` action which is wired to the
	 * Notifications email pipeline.
	 *
	 * @param int $post_id Listing post ID.
	 * @return bool True on dispatch.
	 */
	public static function send_verification_email( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$token = self::generate_token( $post_id, (int) $post->post_author );
		update_post_meta( $post_id, self::META_LAST_SENT, current_time( 'mysql', true ) );

		/**
		 * Fires when a verification email should be sent.
		 *
		 * Notifications hooks this and renders the templated email.
		 *
		 * @param int    $post_id Listing post ID.
		 * @param string $token   Plaintext verification token.
		 */
		do_action( 'wb_listora_listing_verify_email', $post_id, $token );

		return true;
	}

	/**
	 * Re-send a verification email with a brand-new token.
	 *
	 * Rate-limited to 1 send per RESEND_COOLDOWN seconds. Returns a structured
	 * result that the resend endpoint can pass straight back to the client.
	 *
	 * @param int $post_id Listing post ID.
	 * @return array{sent:bool,error?:string,retry_after?:int}
	 */
	public static function resend_verification( $post_id ) {
		if ( ! self::is_pending_verification( $post_id ) ) {
			return array(
				'sent'  => false,
				'error' => 'not_pending',
			);
		}

		$last_sent = (string) get_post_meta( $post_id, self::META_LAST_SENT, true );
		if ( '' !== $last_sent ) {
			$last_ts = strtotime( $last_sent . ' UTC' );
			if ( false !== $last_ts ) {
				$elapsed = time() - $last_ts;
				if ( $elapsed < self::RESEND_COOLDOWN ) {
					return array(
						'sent'        => false,
						'error'       => 'rate_limited',
						'retry_after' => self::RESEND_COOLDOWN - $elapsed,
					);
				}
			}
		}

		self::send_verification_email( $post_id );

		return array( 'sent' => true );
	}

	/**
	 * Handle the public verification URL.
	 *
	 * Accepts /?listora-verify=1&listing=N&token=...
	 * On success transitions the listing into the appropriate moderation
	 * state and renders a friendly confirmation page.
	 */
	public function handle_verify_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public verify link, token is the nonce.
		if ( empty( $_GET['listora-verify'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public verify link.
		$listing_id = isset( $_GET['listing'] ) ? absint( $_GET['listing'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public verify link.
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		$post = $listing_id ? get_post( $listing_id ) : null;

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			$this->render_result_page(
				'invalid',
				__( 'Verification link is invalid', 'wb-listora' ),
				__( 'We could not find the listing this link refers to.', 'wb-listora' ),
				0
			);
			return;
		}

		// Already verified? Show a friendly success page.
		if ( ! self::is_pending_verification( $listing_id ) ) {
			$this->render_result_page(
				'already',
				__( 'This listing is already verified', 'wb-listora' ),
				__( 'No further action needed — your email was confirmed earlier.', 'wb-listora' ),
				$listing_id
			);
			return;
		}

		// Expired token: surface the resend CTA.
		if ( self::is_expired( $listing_id ) ) {
			$this->render_result_page(
				'expired',
				__( 'This verification link has expired', 'wb-listora' ),
				__( 'Use the button below to send yourself a fresh verification email.', 'wb-listora' ),
				$listing_id
			);
			return;
		}

		if ( ! self::verify_token( $listing_id, $token ) ) {
			$this->render_result_page(
				'invalid',
				__( 'Verification link is invalid', 'wb-listora' ),
				__( 'The token in this link does not match. Request a fresh email below.', 'wb-listora' ),
				$listing_id
			);
			return;
		}

		// Verify the token, transition status, fire follow-up notifications.
		$moderation = wb_listora_get_setting( 'moderation', 'manual' );
		$new_status = ( 'auto_approve' === $moderation ) ? 'publish' : 'pending';

		$updated = wp_update_post(
			array(
				'ID'          => $listing_id,
				'post_status' => $new_status,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			$this->render_result_page(
				'invalid',
				__( 'Could not verify your email', 'wb-listora' ),
				$updated->get_error_message(),
				$listing_id
			);
			return;
		}

		self::consume_token( $listing_id );

		/**
		 * Fires after a guest listing's email has been verified.
		 *
		 * @param int    $listing_id Listing post ID.
		 * @param string $new_status New post status (pending or publish).
		 */
		do_action( 'wb_listora_after_email_verified', $listing_id, $new_status );

		// Trigger the standard post-submission notifications now that the
		// listing is no longer in the pre-verification limbo state.
		// Pass an empty WP_REST_Request so listeners can call $request->get_param().
		$synthetic_request = new \WP_REST_Request();
		do_action( 'wb_listora_listing_submitted', $listing_id, $new_status, $synthetic_request );
		if ( 'pending' === $new_status ) {
			do_action( 'wb_listora_listing_pending_admin', $listing_id );
		}

		// Auto-login the verified author for a frictionless follow-up.
		$user_id = (int) get_post_field( 'post_author', $listing_id );
		$auto_logged_in = false;
		if ( $user_id > 0 && ! is_user_logged_in() ) {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
			$auto_logged_in = true;
		}

		$title = ( 'publish' === $new_status )
			? __( 'Your listing is live', 'wb-listora' )
			: __( 'Email verified — your listing is being reviewed', 'wb-listora' );

		$message = ( 'publish' === $new_status )
			? __( 'Thanks for verifying your email. Your listing has been published.', 'wb-listora' )
			: __( 'Thanks for verifying your email. Our team will review and publish your listing shortly.', 'wb-listora' );

		$this->render_result_page(
			'success',
			$title,
			$message,
			$listing_id,
			array(
				'new_status'     => $new_status,
				'auto_logged_in' => $auto_logged_in,
			)
		);
	}

	/**
	 * Render the verification confirmation page.
	 *
	 * Produces a self-contained HTML response — no theme wrapper to avoid
	 * surprises on heavily customised templates.
	 *
	 * @param string $kind    One of: success | already | expired | invalid.
	 * @param string $title   Page heading.
	 * @param string $message Body paragraph.
	 * @param int    $post_id Listing post ID (for the resend CTA).
	 * @param array  $extra   Optional context: new_status, auto_logged_in.
	 */
	private function render_result_page( $kind, $title, $message, $post_id, array $extra = array() ) {
		nocache_headers();

		$listing      = $post_id ? get_post( $post_id ) : null;
		$listing_url  = $listing ? get_permalink( $listing ) : '';
		$dashboard    = function_exists( 'wb_listora_get_dashboard_url' ) ? wb_listora_get_dashboard_url() : home_url( '/' );
		$site_name    = get_bloginfo( 'name' );
		$icon         = 'success' === $kind ? '✓' : ( 'expired' === $kind ? '⏰' : ( 'already' === $kind ? '✓' : '✗' ) );
		$icon_color   = 'success' === $kind || 'already' === $kind ? '#00a32a' : ( 'expired' === $kind ? '#dba617' : '#d63638' );
		$show_resend  = ( 'expired' === $kind || 'invalid' === $kind ) && $post_id > 0 && self::is_pending_verification( $post_id );
		$resend_nonce = wp_create_nonce( 'wp_rest' );

		status_header( 'success' === $kind || 'already' === $kind ? 200 : 410 );

		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php echo esc_html( $title ); ?> — <?php echo esc_html( $site_name ); ?></title>
	<style>
		body { margin: 0; padding: 0; background: #f0f0f1; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, sans-serif; color: #1e1e1e; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
		.card { background: #fff; border-radius: 12px; max-width: 480px; width: 92%; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
		.icon { font-size: 56px; line-height: 1; color: <?php echo esc_attr( $icon_color ); ?>; margin-bottom: 1rem; }
		h1 { margin: 0 0 0.75rem; font-size: 1.5rem; font-weight: 600; }
		p { margin: 0 0 1.25rem; color: #3c434a; line-height: 1.6; }
		.actions { display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; margin-top: 1.5rem; }
		.btn { display: inline-block; padding: 0.75rem 1.4rem; border-radius: 6px; font-weight: 600; font-size: 0.95rem; text-decoration: none; cursor: pointer; border: 0; }
		.btn-primary { background: #2271b1; color: #fff; }
		.btn-secondary { background: #f0f0f1; color: #1e1e1e; }
		.btn:hover { opacity: 0.9; }
		.notice { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 0.75rem 1rem; text-align: left; border-radius: 4px; margin: 1rem 0; font-size: 0.9rem; }
		.muted { color: #757575; font-size: 0.85rem; margin-top: 1.5rem; }
		@media (max-width: 480px) { .card { padding: 2rem 1.25rem; } h1 { font-size: 1.25rem; } }
	</style>
</head>
<body>
	<main class="card" role="main">
		<div class="icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></div>
		<h1><?php echo esc_html( $title ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>

		<?php if ( ! empty( $extra['auto_logged_in'] ) ) : ?>
			<div class="notice"><?php esc_html_e( 'You have been signed in to your account.', 'wb-listora' ); ?></div>
		<?php endif; ?>

		<div class="actions">
			<?php if ( 'success' === $kind && ! empty( $extra['new_status'] ) && 'publish' === $extra['new_status'] && $listing_url ) : ?>
				<a class="btn btn-primary" href="<?php echo esc_url( $listing_url ); ?>"><?php esc_html_e( 'View your listing', 'wb-listora' ); ?></a>
			<?php endif; ?>
			<a class="btn btn-secondary" href="<?php echo esc_url( $dashboard ); ?>"><?php esc_html_e( 'Go to dashboard', 'wb-listora' ); ?></a>

			<?php
			// Resend button — when both $show_resend and $listing are true the
			// runtime values flow to JS via data-* attributes on the button itself
			// (no inline <script>, Rule 11). The external handler at
			// assets/js/frontend/email-verification.js reads btn.dataset and POSTs
			// to the resend endpoint.
			if ( $show_resend ) :
				$resend_endpoint = rest_url( WB_LISTORA_REST_NAMESPACE . '/submission/resend-verification' );
				$listing_id      = $listing ? (int) $post_id : 0;
				?>
				<button
					type="button"
					class="btn btn-primary"
					id="listora-verify-resend"
					data-endpoint="<?php echo esc_url( $resend_endpoint ); ?>"
					data-nonce="<?php echo esc_attr( $resend_nonce ); ?>"
					data-listing-id="<?php echo esc_attr( $listing_id ); ?>"
					data-msg-sending="<?php esc_attr_e( 'Sending…', 'wb-listora' ); ?>"
					data-msg-sent="<?php esc_attr_e( 'A fresh verification email is on the way.', 'wb-listora' ); ?>"
					data-msg-rate-limited="<?php esc_attr_e( 'Please wait a moment before requesting another email.', 'wb-listora' ); ?>"
					data-msg-failed="<?php esc_attr_e( 'Could not send the email. Please try again later.', 'wb-listora' ); ?>"
				><?php esc_html_e( 'Resend verification email', 'wb-listora' ); ?></button>
			<?php endif; ?>
		</div>

		<?php if ( $show_resend && $listing ) : ?>
			<p class="muted" id="listora-verify-resend-msg" hidden></p>
			<script src="<?php echo esc_url( WB_LISTORA_PLUGIN_URL . 'assets/js/frontend/email-verification.js?ver=' . WB_LISTORA_VERSION ); ?>" defer></script>
		<?php endif; ?>

		<p class="muted"><?php echo esc_html( $site_name ); ?></p>
	</main>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Daily cron — purge listings stuck in pending_verification past the
	 * configured grace period (default 7 days).
	 *
	 * Action mode is configurable: 'trash' (default) or 'delete'.
	 * Fires `wb_listora_unverified_listing_cleaned` per listing for
	 * extensions that want to react.
	 */
	public function cleanup_unverified_listings() {
		$max_days = (int) wb_listora_get_setting( 'unverified_listings_max_days', 7 );
		$max_days = max( 1, min( 90, $max_days ) );
		$action   = (string) wb_listora_get_setting( 'unverified_listings_action', 'trash' );
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $max_days * DAY_IN_SECONDS ) );

		$query = new \WP_Query(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'pending_verification',
				'posts_per_page' => 100,
				'date_query'     => array(
					array(
						'before'    => $cutoff,
						'inclusive' => true,
						'column'    => 'post_date_gmt',
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $query->posts as $post_id ) {
			if ( 'delete' === $action ) {
				wp_delete_post( $post_id, true );
			} else {
				wp_trash_post( $post_id );
			}

			/**
			 * Fires after the cleanup cron disposes of an unverified listing.
			 *
			 * @param int    $post_id Listing post ID.
			 * @param string $action  'delete' or 'trash'.
			 */
			do_action( 'wb_listora_unverified_listing_cleaned', $post_id, $action );
		}
	}

	/**
	 * Tear down the cleanup cron. Called from the deactivator.
	 */
	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
