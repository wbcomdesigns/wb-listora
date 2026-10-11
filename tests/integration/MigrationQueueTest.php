<?php
/**
 * Competitor migrations run through the background import queue.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;

/**
 * @group listora
 */
class MigrationQueueTest extends WP_UnitTestCase {

	/**
	 * The migrator commits its own transaction, which ends the test's; remove what it indexed.
	 */
	public function tear_down() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}listora_search_index WHERE title LIKE 'QA %'" );
		$wpdb->query( 'COMMIT' );
		parent::tear_down();
	}

	public function test_queued_directorist_migration_imports_each_listing_once() {
		foreach ( array( 'QA Coffee Bar', 'QA Book Shop', 'QA Bike Repair' ) as $title ) {
			self::factory()->post->create( array( 'post_type' => 'at_biz_dir', 'post_title' => $title, 'post_status' => 'publish', 'post_content' => 'A test listing.' ) );
		}

		add_filter( 'wb_listora_bg_import_use_async', '__return_false' );
		$run_id = wb_listora_queue_migration_import( 'directorist', 3 );
		remove_filter( 'wb_listora_bg_import_use_async', '__return_false' );

		$this->assertIsString( $run_id );
		$progress = wb_listora_get_import_progress( $run_id );
		$this->assertTrue( $progress['done'] );
		$this->assertSame( 3, $progress['imported'], implode( ' | ', $progress['messages'] ) );

		$listings = get_posts( array( 'post_type' => 'listora_listing', 'post_status' => 'any', 'title' => 'QA Coffee Bar', 'fields' => 'ids' ) );
		$this->assertCount( 1, $listings );

		// A second run skips what is already migrated.
		add_filter( 'wb_listora_bg_import_use_async', '__return_false' );
		$again = wb_listora_get_import_progress( wb_listora_queue_migration_import( 'directorist', 3 ) );
		remove_filter( 'wb_listora_bg_import_use_async', '__return_false' );
		$this->assertSame( 0, $again['imported'] );
		$this->assertSame( 3, $again['skipped'] );

		$this->assertWPError( wb_listora_queue_migration_import( 'no-such-plugin' ) );
	}
}
