<?php
/**
 * Keep core's Application Passwords authorize screen
 * (`wp-admin/authorize-application.php`) usable for the mobile app:
 *
 * 1. Core passes the return URL through `esc_url()`, which empties any
 *    scheme outside the kses allowlist, so `listoraapp://` must be allowed
 *    or the member is never handed back to the app.
 * 2. WooCommerce (and similar) redirect users without `edit_posts` etc.
 *    away from wp-admin, so plain members could not reach the screen.
 *
 * Both filters are scoped to the authorize request only (a global protocol
 * allowance would make the scheme linkable site-wide).
 *
 * @package WBListora\Auth
 */

namespace WBListora\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Scoped repairs that keep core's authorize screen working for members.
 *
 * @since 1.4.0
 */
class App_Authorize_Access {

	/**
	 * The core screen that mints a member's first Application Password.
	 */
	const AUTHORIZE_SCRIPT = 'authorize-application.php';

	/**
	 * Default deep-link scheme the mobile app is registered for.
	 *
	 * A white-labelled build ships its own scheme, which is why this is a
	 * starting value behind a filter and not a hardcoded constant.
	 */
	const DEFAULT_APP_SCHEME = 'listoraapp';

	/**
	 * Wire both filters.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_prevent_admin_access', array( __CLASS__, 'allow_authorize_screen' ) );
		add_filter( 'kses_allowed_protocols', array( __CLASS__, 'allow_app_scheme' ) );
	}

	/**
	 * Is the current request the core authorize screen?
	 *
	 * Matched on SCRIPT_FILENAME rather than a query arg or referer, because
	 * that is what actually determines which PHP file is executing and is not
	 * something a visitor can spoof into pointing at a different screen.
	 *
	 * @return bool
	 */
	public static function is_authorize_request() {
		if ( ! isset( $_SERVER['SCRIPT_FILENAME'] ) ) {
			return false;
		}

		$script = basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) );

		return self::AUTHORIZE_SCRIPT === $script;
	}

	/**
	 * The app's deep-link scheme.
	 *
	 * @return string Lowercase scheme with no separator, e.g. `listoraapp`.
	 */
	public static function app_scheme() {
		/**
		 * Filter the mobile app's deep-link scheme.
		 *
		 * @since 1.4.0
		 *
		 * @param string $scheme Scheme with no `://` separator.
		 */
		$scheme = (string) apply_filters( 'wb_listora_app_scheme', self::DEFAULT_APP_SCHEME );

		return strtolower( (string) preg_replace( '/[^a-z0-9+.-]/i', '', $scheme ) );
	}

	/**
	 * Stop WooCommerce redirecting a member away from the authorize screen.
	 *
	 * Only ever turns the block OFF, and only for that one script. It never
	 * turns a block ON, so a site that already allows admin access is unchanged.
	 *
	 * @param bool $prevent Whether WooCommerce intends to block this request.
	 * @return bool
	 */
	public static function allow_authorize_screen( $prevent ) {
		if ( ! $prevent ) {
			return $prevent;
		}

		return self::is_authorize_request() ? false : $prevent;
	}

	/**
	 * Let `esc_url()` keep the app scheme on the authorize screen.
	 *
	 * Scoped to that request: everywhere else the allowlist is untouched, so the
	 * scheme never becomes linkable in listing or review content.
	 *
	 * @param string[] $protocols Allowed protocols.
	 * @return string[]
	 */
	public static function allow_app_scheme( $protocols ) {
		if ( ! is_array( $protocols ) || ! self::is_authorize_request() ) {
			return $protocols;
		}

		$scheme = self::app_scheme();

		if ( '' !== $scheme && ! in_array( $scheme, $protocols, true ) ) {
			$protocols[] = $scheme;
		}

		return $protocols;
	}
}
