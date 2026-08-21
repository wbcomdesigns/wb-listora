<?php
/**
 * Confirmed email changes for the member dashboard.
 *
 * Changing the address on an account is how an account is taken away from
 * someone: move the email, then use "forgot password" to take the password too.
 * The dashboard profile endpoint used to write `user_email` immediately, so a
 * stolen cookie or a leaked application password was enough — neither of which
 * requires knowing the member's password.
 *
 * Two gates, because they stop different things:
 *
 * - The current password must be supplied. A stolen cookie does not carry it,
 *   and neither does an application password, which is the whole point of one.
 * - The new address must confirm. Until it does, nothing on the account moves,
 *   so a change made to an address the member cannot read expires unused — and
 *   a typo is recoverable rather than a lockout.
 *
 * Shape mirrors {@see \WBListora\Workflow\Email_Verification}: generate, verify,
 * consume, with the token hashed at rest.
 *
 * @package WBListora
 * @since 1.7.0
 */

namespace WBListora\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Stages and confirms member email changes.
 */
class Email_Change {

	/**
	 * User meta holding the pending change.
	 */
	const META_PENDING = '_listora_pending_email';

	/**
	 * How long a confirmation link stays usable.
	 */
	const TTL = DAY_IN_SECONDS;

	/**
	 * Register the confirmation handler.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_confirm' ) );
	}

	/**
	 * Stage an email change and send the confirmation link.
	 *
	 * The token is stored as a hash. A readable token in the database is a
	 * second copy of the link, and this row is reachable by anything that can
	 * read user meta.
	 *
	 * @since 1.7.0
	 *
	 * @param int    $user_id   Account to change.
	 * @param string $new_email Address to move to.
	 * @return true|\WP_Error True when the confirmation was sent.
	 */
	public static function request( $user_id, $new_email ) {
		$user_id   = (int) $user_id;
		$new_email = sanitize_email( $new_email );

		if ( ! is_email( $new_email ) ) {
			return new \WP_Error(
				'listora_invalid_email',
				__( 'Please enter a valid email address.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		if ( email_exists( $new_email ) ) {
			return new \WP_Error(
				'listora_email_taken',
				__( 'That email address is already in use.', 'wb-listora' ),
				array( 'status' => 409 )
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'listora_no_user', __( 'Account not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$token = wp_generate_password( 40, false, false );

		update_user_meta(
			$user_id,
			self::META_PENDING,
			array(
				'email'   => $new_email,
				'hash'    => wp_hash( $token ),
				'expires' => time() + self::TTL,
			)
		);

		$link = add_query_arg(
			array(
				'listora-confirm-email' => 1,
				'user'                  => $user_id,
				'token'                 => rawurlencode( $token ),
			),
			home_url( '/' )
		);

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Confirm your new email address on %s', 'wb-listora' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$body = sprintf(
			/* translators: 1: display name, 2: confirmation URL */
			__( "Hi %1\$s,\n\nSomeone asked to change the email address on your account to this one. Confirm it by opening the link below.\n\n%2\$s\n\nIf this was not you, ignore this message. Nothing changes until the link is opened, and your current address stays in place.\n", 'wb-listora' ),
			$user->display_name,
			$link
		);

		// Sent to the NEW address only. Confirming to the old one would prove
		// nothing about whether the member can read the new one.
		wp_mail( $new_email, $subject, $body );

		return true;
	}

	/**
	 * Apply a change when its confirmation link is opened.
	 */
	public static function maybe_confirm() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The token IS the credential; a nonce would require the session this link exists to work without.
		if ( empty( $_GET['listora-confirm-email'] ) ) {
			return;
		}

		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $user_id <= 0 || '' === $token ) {
			return;
		}

		$pending = get_user_meta( $user_id, self::META_PENDING, true );

		if (
			! is_array( $pending )
			|| empty( $pending['hash'] )
			|| empty( $pending['email'] )
			|| empty( $pending['expires'] )
			|| time() > (int) $pending['expires']
		) {
			delete_user_meta( $user_id, self::META_PENDING );
			wp_die(
				esc_html__( 'That confirmation link has expired. Request the change again from your profile.', 'wb-listora' ),
				esc_html__( 'Link expired', 'wb-listora' ),
				array( 'response' => 410 )
			);
		}

		// hash_equals so a wrong token cannot be narrowed down by timing.
		if ( ! hash_equals( (string) $pending['hash'], wp_hash( $token ) ) ) {
			wp_die(
				esc_html__( 'That confirmation link is not valid.', 'wb-listora' ),
				esc_html__( 'Link not valid', 'wb-listora' ),
				array( 'response' => 403 )
			);
		}

		// Re-check on the way in: the address may have been taken by someone
		// else between the request and the click.
		if ( email_exists( $pending['email'] ) ) {
			delete_user_meta( $user_id, self::META_PENDING );
			wp_die(
				esc_html__( 'That email address is already in use.', 'wb-listora' ),
				esc_html__( 'Address unavailable', 'wb-listora' ),
				array( 'response' => 409 )
			);
		}

		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $pending['email'],
			)
		);

		delete_user_meta( $user_id, self::META_PENDING );

		/**
		 * Fires after a member's email address change is confirmed.
		 *
		 * @since 1.7.0
		 *
		 * @param int    $user_id   Account that changed.
		 * @param string $new_email The confirmed address.
		 */
		do_action( 'wb_listora_email_change_confirmed', $user_id, $pending['email'] );

		$redirect = function_exists( 'wb_listora_get_dashboard_url' )
			? wb_listora_get_dashboard_url( 'profile' )
			: home_url( '/' );

		wp_safe_redirect( add_query_arg( 'listora-email-confirmed', 1, $redirect ) );
		exit;
	}
}
