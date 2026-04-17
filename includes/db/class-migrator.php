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
}
