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
		// Clear scheduled cron events from BOTH Action Scheduler (group:
		// wb-listora) and WP-Cron. Cron_Scheduler::unschedule_all() guards
		// AS calls with function_exists() so this is safe even when AS
		// is not loaded.
		\WBListora\Workflow\Cron_Scheduler::unschedule_all(
			array(
				'wb_listora_check_expirations',
				'wb_listora_daily_maintenance',
				'wb_listora_draft_reminder_cron',
				'wb_listora_daily_cleanup',
				'wb_listora_expire_featured',
				'wb_listora_cleanup_unverified_listings',
			)
		);

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Note: We do NOT delete data on deactivation.
		// Data is only deleted on uninstall if the user opted in.
	}
}
