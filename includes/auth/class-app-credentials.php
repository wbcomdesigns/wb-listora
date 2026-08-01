<?php
/**
 * Trade a member's ordinary WordPress login for an Application Password.
 *
 * WordPress core will not do this exchange: its Basic auth handler validates
 * against stored application passwords ONLY, so the core route that mints one
 * already requires one, and every core path to a member's FIRST credential
 * runs through a wp-admin screen under cookie auth. Members have a WordPress
 * password and expect to type it — this route is what lets them.
 *
 * NOT a second authentication system. `wp_authenticate()` does the actual
 * authentication, exactly as wp-login.php does, so every `authenticate` filter
 * on the site still runs — security plugins, membership gates and 2FA all get
 * their say. This class only decides what to hand back once core has said yes.
 *
 * THE AUTHORIZE FLOW REMAINS PREFERRED, and the app treats it as the default.
 * Core's screen runs the site's real login, so it can complete an interactive
 * second factor; a single REST call cannot. When this exchange detects that
 * the site wanted more than a password it answers 409 and tells the app to
 * send the member through the interactive flow rather than pretending the
 * password was wrong or, worse, letting them past a factor the owner
 * configured.
 *
 * THE RISK, STATED PLAINLY: this route accepts a real account password, which
 * makes it a brute-force oracle in a way wp-login.php is not (that page
 * inherits whatever protection the site has installed). It is therefore
 * TLS-gated, rate-limited per IP AND per username before any credential is
 * read (failures only — an honest member's typo is cleared on success),
 * uniform in its failure message so it cannot enumerate accounts, off when the
 * owner turns it off, and silent about the password in every log and error
 * path.
 *
 * @package WBListora\Auth
 */

namespace WBListora\Auth;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_User;

/**
 * The credentials-for-app-password exchange.
 *
 * @since 1.3.1
 */
class App_Credentials {

	/**
	 * Failed attempts allowed per bucket before lockout.
	 */
	const MAX_FAILURES = 5;

	/**
	 * Failure-count window (and lockout length) in seconds.
	 */
	const FAILURE_WINDOW = 900;

	/**
	 * Error codes that mean "those credentials were wrong".
	 *
	 * Anything else from `wp_authenticate()` means the site rejected the
	 * sign-in for its OWN reason — a second factor, a security plugin — which
	 * is a different answer and gets a different status. Collapsed so the
	 * response cannot answer "does this account exist?".
	 */
	const CREDENTIAL_FAILURE_CODES = array(
		'invalid_username',
		'invalid_email',
		'incorrect_password',
		'authentication_failed',
	);

	/**
	 * Is the credentials exchange available on this site?
	 *
	 * Owner switch, default ON. An owner running 2FA will want every member on
	 * the interactive flow, and turning this off is how they say so.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$on = (bool) get_option( 'wb_listora_app_password_login', true );

		/**
		 * Filter whether members may exchange a WordPress password for an
		 * Application Password.
		 *
		 * @since 1.3.1
		 *
		 * @param bool $on Whether the exchange is available.
		 */
		return (bool) apply_filters( 'wb_listora_app_password_login_enabled', $on );
	}

	/**
	 * Exchange credentials for an Application Password.
	 *
	 * @param string $username Username or email.
	 * @param string $password The member's account password.
	 * @param string $app_name Label shown in the member's profile.
	 * @param string $app_id   Stable per-install id; `App_Connect`'s pruner
	 *                         keys reconnect-replacement on it.
	 * @return array{user_login: string, password: string, app_id: string}|WP_Error
	 */
	public static function exchange( $username, $password, $app_name, $app_id ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error(
				'wb_listora_app_passwords_off',
				__( 'This site has turned off app sign-in. Ask the site owner to enable it.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		// Core refuses to issue Application Passwords without TLS, and so must
		// we: this request carries the real account password. A local dev
		// environment is the documented exception, and it is core's own.
		if ( ! wp_is_application_passwords_available() ) {
			return new WP_Error(
				'wb_listora_app_passwords_off',
				__( 'This site cannot issue app passwords. It needs a secure (https) connection.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return self::translate_auth_error( $user );
		}

		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'wb_listora_login_failed',
				__( 'The username or password you entered is incorrect.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		// `wp_authenticate()` does not log anyone in, but a site's own filters
		// may have primed a session. The app is a stateless client; the
		// credential it gets back IS the session.
		wp_clear_auth_cookie();

		if ( ! wp_is_application_passwords_available_for_user( $user ) ) {
			return new WP_Error(
				'wb_listora_app_passwords_off',
				__( 'App sign-in is not available for this account.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		// No pre-prune needed: App_Connect::forget_older_installs() runs on
		// core's wp_create_application_password action and replaces older rows
		// for this app_id, whichever door minted the new one.
		$created = \WP_Application_Passwords::create_new_application_password(
			$user->ID,
			array(
				'name'   => $app_name,
				'app_id' => $app_id,
			)
		);

		if ( is_wp_error( $created ) ) {
			return new WP_Error(
				'wb_listora_app_passwords_off',
				__( 'This site could not issue an app password. Ask the site owner to check their settings.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		/**
		 * Fires after a member exchanges their password for an app credential.
		 *
		 * The credential itself is deliberately NOT passed: a listener that
		 * logged it would undo the reason this class is careful.
		 *
		 * @since 1.3.1
		 *
		 * @param int    $user_id  Member who signed in.
		 * @param string $app_id   Stable per-install id.
		 * @param string $app_name Label stored on the credential.
		 */
		do_action( 'wb_listora_app_credential_issued', $user->ID, $app_id, $app_name );

		return array(
			'user_login' => $user->user_login,
			'password'   => $created[0],
			'app_id'     => $app_id,
		);
	}

	/**
	 * Is this bucket (ip:… or user:…) currently locked out?
	 *
	 * @param string $bucket Bucket key.
	 * @return bool
	 */
	public static function is_locked_out( $bucket ) {
		$data = get_transient( self::failure_key( $bucket ) );

		if ( ! is_array( $data ) ) {
			return false;
		}

		$count = isset( $data['count'] ) ? (int) $data['count'] : 0;
		$start = isset( $data['start'] ) ? (int) $data['start'] : 0;

		return $count >= self::MAX_FAILURES && ( time() - $start ) < self::FAILURE_WINDOW;
	}

	/**
	 * Count a rejected CREDENTIAL against a bucket.
	 *
	 * Only wrong passwords count — a 2FA hand-off or a disabled switch are
	 * both "your password was fine", and counting those would lock out members
	 * who did nothing wrong.
	 *
	 * @param string $bucket Bucket key.
	 * @return void
	 */
	public static function record_failure( $bucket ) {
		$key  = self::failure_key( $bucket );
		$data = get_transient( $key );

		$start = is_array( $data ) && isset( $data['start'] ) ? (int) $data['start'] : 0;

		if ( ! is_array( $data ) || ( time() - $start ) >= self::FAILURE_WINDOW ) {
			$data = array(
				'count' => 0,
				'start' => time(),
			);
		}

		$data['count'] = ( isset( $data['count'] ) ? (int) $data['count'] : 0 ) + 1;

		set_transient( $key, $data, self::FAILURE_WINDOW );
	}

	/**
	 * An honest member who mistyped twice should not carry those failures.
	 *
	 * @param string $bucket Bucket key.
	 * @return void
	 */
	public static function clear_failures( $bucket ) {
		delete_transient( self::failure_key( $bucket ) );
	}

	/**
	 * Transient key for a bucket's failure counter.
	 *
	 * @param string $bucket Bucket key.
	 * @return string
	 */
	private static function failure_key( $bucket ) {
		return 'wb_listora_apw_fail_' . md5( $bucket );
	}

	/**
	 * Decide what a failed `wp_authenticate()` actually means.
	 *
	 * A wrong password and "this site wants a second factor" are different
	 * answers and must not collapse into one: reporting a 2FA block as a bad
	 * password sends the member round a failing loop; reporting it as success
	 * walks them past a factor the owner configured. Known credential codes
	 * become a uniform 401; everything else becomes a 409 telling the app to
	 * hand off to the interactive flow, which CAN complete a second step.
	 *
	 * @param WP_Error $error The error `wp_authenticate()` returned.
	 * @return WP_Error
	 */
	private static function translate_auth_error( WP_Error $error ) {
		foreach ( $error->get_error_codes() as $code ) {
			if ( in_array( $code, self::CREDENTIAL_FAILURE_CODES, true ) ) {
				// Uniform on purpose: `invalid_username` vs
				// `incorrect_password` is exactly the oracle that answers
				// "does this account exist?".
				return new WP_Error(
					'wb_listora_login_failed',
					__( 'The username or password you entered is incorrect.', 'wb-listora' ),
					array( 'status' => 401 )
				);
			}
		}

		return new WP_Error(
			'wb_listora_second_factor',
			__( 'This site needs another step to sign you in. Use the WordPress approval screen instead.', 'wb-listora' ),
			array( 'status' => 409 )
		);
	}
}
