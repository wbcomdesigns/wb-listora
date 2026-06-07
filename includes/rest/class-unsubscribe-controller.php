<?php
/**
 * REST Unsubscribe Controller.
 *
 * One-click email opt-out for the per-event notification preferences, driven by
 * a stateless signed token (RFC 8058 "token-as-auth"). The link is emailed in
 * the footer of every marketing/nudge notification, so the recipient does NOT
 * need to be logged in to act on it — the token IS the authorization.
 *
 * Flow:
 *   1. Notifications::unsubscribe_url( $user_id, $event ) builds
 *      GET /listora/v1/unsubscribe?uid=<id>&event=<event>&token=<hmac>
 *   2. Recipient clicks the footer link.
 *   3. This controller re-derives the HMAC for (uid, event) and compares it in
 *      constant time. On match it flips the per-user meta
 *      `_listora_notify_{event}` to '0' (the same key the dashboard profile and
 *      Settings -> Notifications toggles write) and renders a friendly
 *      confirmation page.
 *
 * Because the link must work for logged-out recipients, there is intentionally
 * NO `current_user_can()` / nonce gate here — the signed token is the only
 * credential. (See wp-plugin-development gate S6 / RFC 8058.)
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

/**
 * Handles signed-token, one-click email unsubscribe links.
 */
class Unsubscribe_Controller {

	/**
	 * REST namespace ("listora/v1").
	 *
	 * @var string
	 */
	protected $namespace = WB_LISTORA_REST_NAMESPACE;

	/**
	 * REST base path segment.
	 *
	 * @var string
	 */
	protected $rest_base = 'unsubscribe';

	/**
	 * Events that expose a one-click unsubscribe link. These are the
	 * non-transactional "nudge"/marketing notifications — transactional mail
	 * (approval, rejection, claim decisions) never carries an opt-out link.
	 *
	 * Kept in lockstep with the `is_marketing` list inside Notifications::send().
	 *
	 * @return array<int, string>
	 */
	public static function unsubscribable_events(): array {
		/**
		 * Filter the set of events a recipient may one-click unsubscribe from.
		 *
		 * @param array<int, string> $events Event keys.
		 */
		return (array) apply_filters(
			'wb_listora_unsubscribable_events',
			array(
				'draft_reminder',
				'listing_expiring_soon',
				'review_helpful',
				'review_reminder',
			)
		);
	}

	/**
	 * Build a stateless signed unsubscribe URL for a recipient + event.
	 *
	 * The token is an HMAC of "{user_id}|{event}" keyed by a dedicated WP salt
	 * so it cannot be forged without server secrets and needs no DB row to
	 * validate. The same (user, event) pair always yields the same token, which
	 * keeps the footer link stable across resends.
	 *
	 * @param int    $user_id Recipient user ID.
	 * @param string $event   Notification event key.
	 * @return string Absolute unsubscribe URL, or '' when inputs are invalid.
	 */
	public static function build_url( $user_id, $event ): string {
		$user_id = (int) $user_id;
		$event   = (string) $event;

		if ( $user_id <= 0 || '' === $event ) {
			return '';
		}

		$url = add_query_arg(
			array(
				'uid'   => $user_id,
				'event' => rawurlencode( $event ),
				'token' => self::make_token( $user_id, $event ),
			),
			rest_url( WB_LISTORA_REST_NAMESPACE . '/unsubscribe' )
		);

		/**
		 * Filter the generated one-click unsubscribe URL.
		 *
		 * @param string $url     Absolute unsubscribe URL.
		 * @param int    $user_id Recipient user ID.
		 * @param string $event   Notification event key.
		 */
		return (string) apply_filters( 'wb_listora_unsubscribe_url', $url, $user_id, $event );
	}

	/**
	 * Compute the HMAC token for a (user, event) pair.
	 *
	 * @param int    $user_id Recipient user ID.
	 * @param string $event   Notification event key.
	 * @return string Hex HMAC.
	 */
	private static function make_token( $user_id, $event ): string {
		$payload = (int) $user_id . '|' . (string) $event;
		return hash_hmac( 'sha256', $payload, wp_salt( 'wb_listora_unsubscribe' ) );
	}

	/**
	 * Register the public unsubscribe route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_unsubscribe' ),
					// Public by design — the signed token is the credential.
					'permission_callback' => '__return_true',
					'args'                => array(
						'uid'   => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
						'event' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'token' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Validate the signed token and apply the opt-out.
	 *
	 * Always renders an HTML confirmation page (the recipient reached this URL
	 * from an email client, not an API consumer), then exits.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return void
	 */
	public function handle_unsubscribe( WP_REST_Request $request ): void {
		$user_id = (int) $request->get_param( 'uid' );
		$event   = (string) $request->get_param( 'event' );
		$token   = (string) $request->get_param( 'token' );

		if ( $user_id <= 0 || '' === $event || '' === $token ) {
			$this->render_page(
				false,
				__( 'Invalid unsubscribe link', 'wb-listora' ),
				__( 'This link is missing required information. Please use the link from a recent email.', 'wb-listora' )
			);
			return;
		}

		if ( ! in_array( $event, self::unsubscribable_events(), true ) ) {
			$this->render_page(
				false,
				__( 'Unsupported preference', 'wb-listora' ),
				__( 'This notification type cannot be managed from a one-click link. Manage it from your dashboard instead.', 'wb-listora' )
			);
			return;
		}

		$expected = self::make_token( $user_id, $event );
		if ( ! hash_equals( $expected, $token ) ) {
			$this->render_page(
				false,
				__( 'Invalid unsubscribe link', 'wb-listora' ),
				__( 'We could not verify this link. It may have been altered. Manage your email preferences from your dashboard instead.', 'wb-listora' )
			);
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->render_page(
				false,
				__( 'Account not found', 'wb-listora' ),
				__( 'We could not find the account this link refers to.', 'wb-listora' )
			);
			return;
		}

		// Flip the same per-user preference key the profile + admin toggles write.
		update_user_meta( $user_id, '_listora_notify_' . $event, '0' );

		/**
		 * Fires after a recipient one-click unsubscribes from an event.
		 *
		 * @param int    $user_id Recipient user ID.
		 * @param string $event   Notification event key.
		 */
		do_action( 'wb_listora_after_unsubscribe', $user_id, $event );

		$label = self::event_label( $event );

		$this->render_page(
			true,
			__( 'You have been unsubscribed', 'wb-listora' ),
			sprintf(
				/* translators: %s: human-readable notification label. */
				__( 'You will no longer receive "%s" emails. You can re-enable any email preference any time from your dashboard.', 'wb-listora' ),
				$label
			)
		);
	}

	/**
	 * Human-readable label for an event key (used on the confirmation page).
	 *
	 * @param string $event Event key.
	 * @return string
	 */
	private static function event_label( $event ): string {
		$labels = array(
			'draft_reminder'        => __( 'Draft reminder', 'wb-listora' ),
			'listing_expiring_soon' => __( 'Listing expiration reminder', 'wb-listora' ),
			'review_helpful'        => __( 'Helpful-vote milestone', 'wb-listora' ),
			'review_reminder'       => __( 'Review reply reminder', 'wb-listora' ),
		);

		return $labels[ $event ] ?? $event;
	}

	/**
	 * Render a self-contained confirmation page and exit.
	 *
	 * Mirrors Email_Verification::render_result_page() — a pre-bootstrap
	 * standalone HTML document that calls exit() before the theme template
	 * chain runs, so wp_enqueue_style() has no queue to participate in. The
	 * inline <style> is the documented architectural exception for standalone
	 * pages (same class as email templates / OAuth callbacks).
	 *
	 * @param bool   $success Whether the opt-out succeeded.
	 * @param string $title   Page heading.
	 * @param string $message Body paragraph.
	 * @return void
	 */
	private function render_page( $success, $title, $message ): void {
		nocache_headers();
		status_header( $success ? 200 : 400 );

		$site_name  = get_bloginfo( 'name' );
		$dashboard  = function_exists( 'wb_listora_get_dashboard_url' ) ? wb_listora_get_dashboard_url( 'profile' ) : home_url( '/' );
		$icon       = $success ? '✓' : '✗';
		$icon_color = $success ? '#00a32a' : '#d63638';

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
		.btn-secondary { background: #f0f0f1; color: #1e1e1e; }
		.btn:hover { opacity: 0.9; }
		.muted { color: #757575; font-size: 0.85rem; margin-top: 1.5rem; }
		@media (max-width: 480px) { .card { padding: 2rem 1.25rem; } h1 { font-size: 1.25rem; } }
	</style>
</head>
<body>
	<main class="card" role="main">
		<div class="icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></div>
		<h1><?php echo esc_html( $title ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>
		<div class="actions">
			<a class="btn btn-secondary" href="<?php echo esc_url( $dashboard ); ?>"><?php esc_html_e( 'Manage email preferences', 'wb-listora' ); ?></a>
		</div>
		<p class="muted"><?php echo esc_html( $site_name ); ?></p>
	</main>
</body>
</html>
		<?php
		exit;
	}
}
