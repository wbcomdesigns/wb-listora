<?php
/**
 * Regression tests for the DB Migrator (G8).
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   db
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\DB\Migrator;

/**
 * @group listora
 * @group db
 */
class MigratorTest extends WP_UnitTestCase {

	/**
	 * G8 regression — if someone upgrades from 1.0.0, running the migrator
	 * MUST create the wp_listora_services table that Activator added in
	 * 1.1.0. Sites that predate Services should never hit "Table doesn't
	 * exist" on listing detail.
	 */
	public function test_services_table_exists_after_migrate() {
		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'services';

		// Simulate a pre-1.1.0 install by dropping the services table and
		// downgrading the stored db version.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		update_option( 'wb_listora_db_version', '1.0.0' );

		$this->assertNull(
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			'Preconditions: services table should be missing before migrate.'
		);

		Migrator::maybe_migrate();

		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			'Services table should exist after Migrator::maybe_migrate() runs from 1.0.0.'
		);

		$this->assertSame(
			WB_LISTORA_DB_VERSION,
			get_option( 'wb_listora_db_version' ),
			'DB version option should be updated to the constant after migration.'
		);
	}

	/**
	 * Migrator is idempotent — running twice on a current install is a no-op
	 * and doesn't throw.
	 */
	public function test_migrate_is_idempotent() {
		Migrator::maybe_migrate();
		Migrator::maybe_migrate(); // Second call should early-return.
		$this->assertSame( WB_LISTORA_DB_VERSION, get_option( 'wb_listora_db_version' ) );
	}
}
