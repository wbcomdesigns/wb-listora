<?php
/**
 * Keep WordPress core's Application Passwords authorize screen usable for the
 * mobile app, on every site.
 *
 * The Listora app signs in with WP core Application Passwords, and core's
 * authorize screen (`wp-admin/authorize-application.php`) is the interactive
 * door that mints the first one. Two things break that door in the wild:
 *
 * 1. Core renders the return URL through `esc_url()`, which drops any scheme
 *    not in the kses allowlist. `listoraapp` is not a core protocol, so the
 *    hidden `success_url` field rendered EMPTY: the member approved and landed
 *    on a page showing a raw password instead of being handed back to the app.
 *    Verified on this codebase before the fix — `esc_url( 'listoraapp://…' )`
 *    returned an empty string. That failure hit every role on every site, and
 *    is why the app has carried a manual copy-paste fallback.
 *
 * 2. WooCommerce (and membership plugins like it) redirect every user without
 *    `edit_posts` / `manage_woocommerce` / `view_admin_dashboard` away from all
 *    of wp-admin, exempting only admin-post.php and admin-ajax.php. A plain
 *    member on such a site can neither approve the app nor create a password by
 *    hand. Not a capability problem — core allows Application Passwords for
 *    subscribers — only the screen is unreachable.
 *
 * Both filters are scoped to the authorize request itself. A global protocol
 * allowance would make the app scheme linkable in listing descriptions and
 * reviews site-wide, which is unnecessary surface for a one-screen need. The
 * WooCommerce filter only ever turns the block OFF for that one screen; every
 * other wp-admin script stays blocked.
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
