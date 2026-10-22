<?php
/**
 * The stored DB version moves only when the schema is whole.
 *
 * @package WBListora\Tests
 */

use WBListora\DB\Migrator;

/**
 * @group listora
 */
class MigratorStampTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_transient( 'wb_listora_migration_retry' );
	}

	/**
	 * Make the existence probe see the leads table as absent (no DDL, so the
	 * test transaction stays intact).
	 */
	private function hide_leads_table(): callable {
		global $wpdb;
		$probe = static function ( $sql ) use ( $wpdb ) {
			return false !== strpos( $sql, "SHOW TABLES LIKE '" . $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . "leads'" ) ? "SHOW TABLES LIKE 'listora_hidden_for_test'" : $sql;
		};
		add_filter( 'query', $probe );
		return $probe;
	}

	public function test_missing_table_keeps_the_upgrade_pending_then_a_whole_schema_stamps() {
		// Every migration up to the last has run; only the newest is outstanding.
		update_option( 'wb_listora_db_version', '1.9.1.18' );

		$probe = $this->hide_leads_table();
		Migrator::maybe_migrate();
		remove_filter( 'query', $probe );

		$this->assertSame( '1.9.1.18', get_option( 'wb_listora_db_version' ), 'A schema with a table missing is not stamped as upgraded.' );
		$this->assertNotFalse( get_transient( 'wb_listora_migration_retry' ), 'The retry is throttled, not hammered on every request.' );

		// The next attempt, with the table back, completes.
		delete_transient( 'wb_listora_migration_retry' );
		Migrator::maybe_migrate();

		$this->assertSame( WB_LISTORA_DB_VERSION, get_option( 'wb_listora_db_version' ) );
	}
}
