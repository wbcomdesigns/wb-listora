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

// The plugin is not loaded during uninstall. Listings go first, while their
// tables, type definitions and meta still exist: the eraser reads all three to
// delete each listing's rows and images. The taxonomies must be registered for
// the type registry and for the term cleanup below.
defined( 'WB_LISTORA_TABLE_PREFIX' ) || define( 'WB_LISTORA_TABLE_PREFIX', 'listora_' );
defined( 'WB_LISTORA_META_PREFIX' ) || define( 'WB_LISTORA_META_PREFIX', '_listora_' );
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}
$taxonomies = array(
	'listora_listing_type',
	'listora_listing_cat',
	'listora_listing_tag',
	'listora_listing_location',
	'listora_listing_feature',
	'listora_service_cat',
);
foreach ( $taxonomies as $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		register_taxonomy( $taxonomy, 'listora_listing', array( 'public' => false ) );
	}
}
// shortcut: without vendor/ (a git checkout with no composer install) listings
// are still deleted but their images stay; release zips always ship vendor/.
$eraser = class_exists( '\WBListora\Core\Listing_Data_Eraser' ) ? new \WBListora\Core\Listing_Data_Eraser() : null;
if ( $eraser ) {
	delete_option( 'wb_listora_needs_defaults' ); // Never seed default types while uninstalling.
	\WBListora\Core\Listing_Type_Registry::instance()->init();
}

// Delete CPT posts. Paginate until the query is empty: a single capped
// get_posts() left orphaned listora_listing rows on any site with more than the
// cap after its tables were dropped. Each batch is deleted before the next
// fetch, so the same first-page query drains the whole set. A hard iteration
// ceiling guards against an unexpected non-draining state.
$batch     = 200;
$max_loops = 100000; // 20M listings — a backstop, never reached in practice.
$loops     = 0;
do {
	// Every status, read straight from the table. `post_status => 'any'` skips
	// statuses registered with exclude_from_search, which all of Listora's are
	// (expired, rejected, deactivated, payment), and during uninstall they are
	// not registered at all - so those listings outlived the plugin.
	$listings = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT %d", 'listora_listing', $batch ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off uninstall cleanup, nothing to cache.

	foreach ( $listings as $listing_id ) {
		if ( $eraser ) {
			$eraser->erase( (int) $listing_id ); // Its rows, then its images; the plugin's own delete hook is not loaded here.
		}
		wp_delete_post( $listing_id, true );
	}

	++$loops;
} while ( count( $listings ) === $batch && $loops < $max_loops );


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
	'space_listings',
	'email_log',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are the site table prefix plus a fixed list.
}

// Bundled Credits SDK tables live under `{$prefix}credit_*` (credit_ledger,
// credit_gateway_log, credit_processed_events, and any future sibling). Sweep
// the whole SDK sub-namespace so a schema addition can't silently leave data
// behind. Underscores in the LIKE pattern are escaped so it matches the literal
// prefix only.
$sdk_like   = $wpdb->esc_like( $prefix . 'credit_' ) . '%';
$sdk_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sdk_like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off uninstall cleanup, nothing to cache.
foreach ( (array) $sdk_tables as $sdk_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$sdk_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names come from SHOW TABLES under our own prefix.
}

// Delete all listora options.
// Pro's own options (wb_listora_pro_*, including its copy of the Delete-data
// opt-in) are Pro's to remove in its own uninstall, whichever order they go in.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE ( option_name LIKE 'wb\\_listora\\_%' OR option_name LIKE 'wb-listora\\_%' ) AND option_name NOT LIKE 'wb\\_listora\\_pro\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.

// The bundled Credits SDK keeps its own options for this consumer: the schema
// version (wbcom_credits_db_version_listora), gateway settings with API keys,
// and pending checkouts (keyed wb-listora). They survived "delete all data"; the
// version row then told the SDK on a reinstall that the dropped credit tables
// still existed, so every credits query failed. Only this consumer's rows.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wbcom\\_credits\\_%' AND ( option_name LIKE '%\\_listora' OR option_name LIKE '%wb-listora%' )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.

// Delete all listora term meta.
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '\\_listora\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.

// Delete all listora user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_listora\\_%' OR ( meta_key LIKE 'wb\\_listora\\_%' AND meta_key NOT LIKE 'wb\\_listora\\_pro\\_%' ) OR meta_key LIKE '\\_wb\\_listora\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.

// The bundled Credits SDK keeps per-member flags for this consumer (low-balance
// notice state) named _wbcom_credits_*_wb-listora.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_wbcom\\_credits\\_%' AND meta_key LIKE '%wb-listora'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.

// Delete transients. Listora stores them under BOTH prefixes: `wb_listora_*`
// (setup / wizard / rewrite / contact-form) and the shorter `listora_*` (dashboard
// stats, facets, search, rate-limit, view-dedupe caches). Sweep both, plus their
// `_transient_timeout_` twins.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wb\\_listora\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_timeout\\_wb\\_listora\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_listora\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_timeout\\_listora\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.

// Delete all listora postmeta.
//
// AFTER the listing loop above, not before. Deleting a listing cascades its
// images (Listing_Data_Eraser), and it finds the gallery ones by reading
// `_listora_gallery`. Wiping that meta first left the cascade nothing to read,
// so every gallery image on a listing predating 1.7.0 — the ones with no
// post_parent to fall back on — was left on disk by an uninstall that reported
// success.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_listora\\_%' OR meta_key = '_wb_listora_page_key'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed query with no user input.

// Delete taxonomy terms (registered above).
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

// Core caches each hierarchical taxonomy's parent map in "{taxonomy}_children"
// and rewrites it while the terms above are deleted.
foreach ( $taxonomies as $taxonomy ) {
	delete_option( $taxonomy . '_children' );
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
