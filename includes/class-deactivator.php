<?php
/**
 * Plugin deactivator.
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation.
 */
class Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		// Clear scheduled cron events.
		wp_clear_scheduled_hook( 'wb_listora_check_expirations' );
		wp_clear_scheduled_hook( 'wb_listora_daily_maintenance' );

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Note: We do NOT delete data on deactivation.
		// Data is only deleted on uninstall if the user opted in.
	}
}
