<?php
/**
 * Plugin uninstall handler.
 *
 * Fired when the plugin is deleted via WordPress admin.
 * Only deletes data if the user opted in via Settings → Advanced → Delete data on uninstall.
 *
 * @package WBListora
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Check if user opted in to data deletion.
$settings = get_option( 'wb_listora_settings', array() );

if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$prefix = $wpdb->prefix . 'listora_';

// Drop all custom tables.
//
// Free owns its own tables (below) AND the bundled Wbcom Credits SDK's tables,
// because the SDK ships composer-free inside Free (libs/wbcom-credits-sdk) and
// writes into the same `{$wpdb->prefix}listora_*` namespace. Those SDK tables
// (credit_ledger + gateway/processed-event siblings) only exist when credits
// were used, but if the owner opted into data deletion they must go too — they
// were surviving a "delete all data" uninstall.
//
// Pro's OWN tables (audit_log, saved_searches, coupon_usage, webhook_log,
// need_responses, search_terms) are dropped by Pro's uninstall.php, which runs
// when Pro is deleted. `payments` is created by both, so Free drops it here.
$tables = array(
	'geo',
	'search_index',
	'field_index',
	'reviews',
	'review_votes',
	'favorites',
	'claims',
	'hours',
	'analytics',
	'payments',
	'services',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Bundled Credits SDK tables live under `{$prefix}credit_*` (credit_ledger,
// credit_gateway_log, credit_processed_events, and any future sibling). Sweep
// the whole SDK sub-namespace so a schema addition can't silently leave data
// behind. Underscores in the LIKE pattern are escaped so it matches the literal
// prefix only.
$sdk_like   = $wpdb->esc_like( $prefix . 'credit_' ) . '%';
$sdk_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sdk_like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
foreach ( (array) $sdk_tables as $sdk_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$sdk_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete all listora postmeta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete all listora options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wb_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete all listora term meta.
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete all listora user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete transients. Listora stores them under BOTH prefixes: `wb_listora_*`
// (setup / wizard / rewrite / contact-form) and the shorter `listora_*` (dashboard
// stats, facets, search, rate-limit, view-dedupe caches). Sweep both, plus their
// `_transient_timeout_` twins.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wb_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wb_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete CPT posts. Paginate until the query is empty: a single capped
// get_posts() left orphaned listora_listing rows on any site with more than the
// cap after its tables were dropped. Each batch is deleted before the next
// fetch, so the same first-page query drains the whole set. A hard iteration
// ceiling guards against an unexpected non-draining state.
$batch     = 200;
$max_loops = 100000; // 20M listings — a backstop, never reached in practice.
$loops     = 0;
do {
	$listings = get_posts(
		array(
			'post_type'        => 'listora_listing',
			'post_status'      => 'any',
			'posts_per_page'   => $batch,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'cache_results'    => false,
			'suppress_filters' => true,
		)
	);

	foreach ( $listings as $listing_id ) {
		wp_delete_post( $listing_id, true );
	}

	++$loops;
} while ( count( $listings ) === $batch && $loops < $max_loops );

// Delete taxonomy terms.
$taxonomies = array(
	'listora_listing_type',
	'listora_listing_cat',
	'listora_listing_tag',
	'listora_listing_location',
	'listora_listing_feature',
	'listora_service_cat',
);

foreach ( $taxonomies as $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_array( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( $term_id, $taxonomy );
		}
	}
}

// Remove capabilities from all roles. Delegate to Capabilities::remove_caps(),
// whose list is derived from the single grant map — no third hand-maintained copy
// of the cap list here (AUDIT-M). uninstall.php runs standalone (no autoloader), so
// require the class explicitly; it has no load-time dependencies.
require_once __DIR__ . '/includes/core/class-capabilities.php';
\WBListora\Core\Capabilities::remove_caps();

// Clear any scheduled events. Deactivation clears these first (and the Action
// Scheduler group is unscheduled there), but uninstall must not rely on a clean
// deactivation having run — a lingering WP-Cron hook would otherwise fire into a
// missing callback. Clear the known Free recurring hooks defensively.
$cron_hooks = array(
	'wb_listora_check_expirations',
	'wb_listora_draft_reminder_cron',
	'wb_listora_review_reminder_cron',
	'wb_listora_daily_cleanup',
	'wb_listora_prune_email_log',
	'wb_listora_bg_import_batch',
	'wb_listora_bg_import_finalize',
);
foreach ( $cron_hooks as $cron_hook ) {
	wp_clear_scheduled_hook( $cron_hook );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( $cron_hook );
	}
}

// Flush rewrite rules.
flush_rewrite_rules();
