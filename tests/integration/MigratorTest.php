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

	/**
	 * 1.9.1 locks demo accounts still on the shipped password and leaves a
	 * changed password, and non-demo users, alone (card 10379561541).
	 */
	public function test_1_9_1_resets_only_demo_accounts_on_the_shipped_password() {
		$stuck   = self::factory()->user->create( array( 'user_pass' => 'password' ) );
		$changed = self::factory()->user->create( array( 'user_pass' => 'owner-chose-this' ) );
		$real    = self::factory()->user->create( array( 'user_pass' => 'password' ) );
		update_user_meta( $stuck, '_listora_demo_user', true );
		update_user_meta( $changed, '_listora_demo_user', true );

		Migrator::migrate_1_9_1();
		array_map( 'clean_user_cache', array( $stuck, $changed, $real ) );

		$this->assertFalse( wp_check_password( 'password', get_userdata( $stuck )->user_pass, $stuck ) );
		$this->assertTrue( wp_check_password( 'owner-chose-this', get_userdata( $changed )->user_pass, $changed ) );
		$this->assertTrue( wp_check_password( 'password', get_userdata( $real )->user_pass, $real ) );
	}
}
