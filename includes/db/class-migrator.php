<?php
/**
 * Database migrator.
 *
 * @package WBListora\DB
 */

namespace WBListora\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Handles versioned database migrations.
 */
class Migrator {

	/**
	 * Check if migration is needed and run it.
	 */
	public static function maybe_migrate() {
		$current_db_version = get_option( 'wb_listora_db_version', '0' );

		if ( version_compare( $current_db_version, WB_LISTORA_DB_VERSION, '>=' ) ) {
			return;
		}

		$migrations = self::get_migrations();

		foreach ( $migrations as $version => $callback ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				call_user_func( $callback );

				if ( function_exists( 'wb_listora_log' ) ) {
					wb_listora_log( "Migration {$version} completed." );
				}
			}
		}

		update_option( 'wb_listora_db_version', WB_LISTORA_DB_VERSION );
	}

	/**
	 * Get ordered list of migrations.
	 *
	 * @return array Version => callable pairs.
	 */
	private static function get_migrations() {
		return array(
			'1.0.0' => array( __CLASS__, 'migrate_1_0_0' ),
			'1.1.0' => array( __CLASS__, 'migrate_1_1_0' ),
			'1.2.0' => array( __CLASS__, 'migrate_1_2_0' ),
			'1.3.0' => array( __CLASS__, 'migrate_1_3_0' ),
			'1.4.0' => array( __CLASS__, 'migrate_1_4_0' ),
		);
	}

	/**
	 * Migration 1.0.0 — Initial table creation.
	 * Tables are created by Activator, so this is a no-op for fresh installs.
	 * For upgrades from a pre-migration version, re-run dbDelta.
	 */
	public static function migrate_1_0_0() {
		// Activator::create_tables() already handles this via dbDelta.
		// dbDelta is safe to run multiple times — it only adds missing columns/indexes.
		\WBListora\Activator::activate();
	}

	/**
	 * Migration 1.1.0 — Adds the services table for the Listing Services feature.
	 *
	 * Sites activated before this feature shipped don't have wp_listora_services,
	 * so listing-detail renders hit "Table doesn't exist" on every load. Re-running
	 * the activator via dbDelta is idempotent and picks up the missing table
	 * without touching existing data.
	 */
	public static function migrate_1_1_0(): void {
		\WBListora\Activator::activate();
	}

	/**
	 * Migration 1.2.0 — Search index now contains taxonomy and address text.
	 *
	 * The 1.2.0 indexer adds the listing type name, location terms and the
	 * full address (city / region / country / postal code) to `meta_text`.
	 * Existing search_index rows still carry the 1.1.0 payload, so multi-word
	 * queries like "italian restaurant" or "manhattan italian" return
	 * inaccurate results until those rows are regenerated.
	 *
	 * We schedule a chunked background rebuild instead of running it inline:
	 * a synchronous full reindex on a directory with tens of thousands of
	 * listings would time out the upgrade flow. The cron chain processes
	 * 200 listings per tick and re-schedules itself until done; in the
	 * meantime the live event-driven indexer keeps fresh writes accurate.
	 */
	public static function migrate_1_2_0(): void {
		\WBListora\Search\Search_Indexer::schedule_full_reindex();
	}

	/**
	 * Migration 1.3.0 — Composite index for per-user review listings.
	 *
	 * The BuddyPress "My Reviews" profile tab (Pro) paginates with
	 * `WHERE user_id = %d AND status = 'approved' ORDER BY created_at DESC`.
	 * The pre-1.3.0 schema only had `idx_user (user_id)`, so that query
	 * filtered on status row-by-row and filesorted by created_at — fine for
	 * a handful of reviews, but a table scan on big directories. The new
	 * `idx_user_status_created (user_id, status, created_at)` covers the
	 * whole predicate + ordering. Re-running the activator via dbDelta adds
	 * the missing index in place without touching existing rows.
	 */
	public static function migrate_1_3_0(): void {
		\WBListora\Activator::activate();
	}

	/**
	 * Migration 1.4.0 — Composite index for the dashboard Claims tab.
	 *
	 * The vendor dashboard "My Claims" tab paginates with
	 * `WHERE user_id = %d ORDER BY created_at DESC`. The pre-1.4.0 schema
	 * only had `idx_user (user_id)`, so the per-user filter resolved on the
	 * index but the `created_at DESC` ordering fell back to a filesort —
	 * fine for a handful of claims, but a sort over the whole user slice on
	 * directories where a power user has claimed many listings. The new
	 * `idx_user_created (user_id, created_at)` covers both the predicate and
	 * the ordering so the paginated read is index-only. Re-running the
	 * activator via dbDelta adds the missing index in place without touching
	 * existing rows.
	 */
	public static function migrate_1_4_0(): void {
		\WBListora\Activator::activate();
	}
}
