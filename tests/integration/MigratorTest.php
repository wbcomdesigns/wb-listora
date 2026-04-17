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
	 * G8 regression — after maybe_migrate runs, the services table (added
	 * in 1.1.0) must exist. We can't reliably DROP-and-recreate inside a
	 * WP test transaction (DDL auto-commits vs the test's transaction
	 * model), so we test the post-condition only.
	 */
	public function test_services_table_exists_after_migrate() {
		global $wpdb;

		Migrator::maybe_migrate();

		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'services';

		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			'Services table must exist after Migrator::maybe_migrate() runs.'
		);
	}

	/**
	 * After migration, the stored db version matches the plugin constant.
	 */
	public function test_db_version_option_matches_constant_after_migrate() {
		Migrator::maybe_migrate();

		$this->assertSame( WB_LISTORA_DB_VERSION, get_option( 'wb_listora_db_version' ) );
	}

	/**
	 * maybe_migrate is idempotent — running twice on a current install
	 * short-circuits and doesn't throw.
	 */
	public function test_migrate_is_idempotent() {
		Migrator::maybe_migrate();
		Migrator::maybe_migrate();

		$this->assertSame( WB_LISTORA_DB_VERSION, get_option( 'wb_listora_db_version' ) );
	}
}
