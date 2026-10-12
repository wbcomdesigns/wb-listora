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
		// Every job in the one list, every group it uses (Pro's too: Pro
		// cannot run without Free, so its jobs would only fail), and the
		// one-off chains, from Action Scheduler and WP-Cron.
		\WBListora\Workflow\Cron_Scheduler::unschedule_all_jobs(
			array(
				\WBListora\Search\Search_Indexer::REINDEX_CRON_HOOK,
				\WBListora\ImportExport\Background_Import::HOOK_BATCH,
				\WBListora\ImportExport\Background_Import::HOOK_FINALIZE,
			)
		);

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Note: We do NOT delete data on deactivation.
		// Data is only deleted on uninstall if the user opted in.
	}
}
