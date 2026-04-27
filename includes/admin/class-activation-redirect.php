<?php
/**
 * Auto-redirect to the setup wizard immediately after plugin activation.
 *
 * Fires once on the first admin page-load after activation, then deletes its
 * own transient so it never fires again.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight admin_init hook that consumes the activation transient.
 */
class Activation_Redirect {

	const TRANSIENT    = 'wb_listora_show_wizard_redirect';
	const USER_DISMISS = '_wb_listora_wizard_dismissed';
	const SETUP_OPTION = 'wb_listora_setup_complete';
	const WIZARD_PAGE  = 'listora-setup';

	/**
	 * Wire the hook.
	 *
	 * Priority 1 so we redirect before any other admin_init handlers
	 * (settings pages, etc.) can output headers.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ), 1 );
	}

	/**
	 * Check the transient and redirect to the setup wizard.
	 *
	 * Skips:
	 *  - Network admin (multisite super-admin redirects break activation flow).
	 *  - Bulk plugin activation (`?activate-multi=true`).
	 *  - AJAX / cron / REST contexts.
	 *  - Users who have explicitly dismissed the wizard (`_wb_listora_wizard_dismissed` user meta).
	 *  - Sites where setup is already marked complete.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		if ( ! get_transient( self::TRANSIENT ) ) {
			return;
		}

		// Always consume — even if we skip the redirect, we don't want to
		// retry it on the next request. Activation only happens once.
		delete_transient( self::TRANSIENT );

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( is_network_admin() ) {
			return;
		}

		// Bulk plugin activation: WordPress passes `activate-multi=true` and
		// `checked[]=...` — redirecting in the middle of that aborts the loop
		// and looks like a fatal to the admin.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag check.
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Setup already complete? No reason to redirect.
		if ( '1' === (string) get_option( self::SETUP_OPTION ) || true === get_option( self::SETUP_OPTION ) ) {
			return;
		}

		// Respect a per-user dismissal.
		$user_id = get_current_user_id();
		if ( $user_id && get_user_meta( $user_id, self::USER_DISMISS, true ) ) {
			return;
		}

		// Need permission to actually access the wizard, otherwise the redirect
		// dumps users into a "you don't have access" screen.
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::WIZARD_PAGE ) );
		exit;
	}
}
