<?php
/**
 * Listings indexed with no type before 2.1.0 are re-indexed on upgrade.
 *
 * @package WBListora\Tests
 */

use WBListora\DB\Migrator;
use WBListora\Search\Search_Indexer;
use WBListora\Workflow\Cron_Scheduler;
use WBListora\Tests\Factories\Factories;

/**
 * @group listora
 */
class UntypedIndexRepairTest extends WP_UnitTestCase {

	private function indexed_type( int $id ): string {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT listing_type FROM {$wpdb->prefix}listora_search_index WHERE listing_id = %d", $id ) );
	}

	public function test_untyped_rows_are_queued_and_repaired() {
		global $wpdb;
		$id = Factories::listing()->create( array( 'title' => 'Pad Thai Corner', 'type_slug' => 'restaurant' ) );
		$wpdb->update( $wpdb->prefix . 'listora_search_index', array( 'listing_type' => '' ), array( 'listing_id' => $id ) );
		Cron_Scheduler::unschedule( Search_Indexer::REINDEX_CRON_HOOK );

		Migrator::migrate_1_9_1_20();
		$this->assertTrue( (bool) Cron_Scheduler::has_scheduled( Search_Indexer::REINDEX_CRON_HOOK ), 'A 2.0.0 demo site keeps empty type tabs without this.' );

		( new Search_Indexer() )->process_scheduled_reindex();
		$this->assertSame( 'restaurant', $this->indexed_type( $id ) );
	}

	public function test_a_clean_site_queues_nothing() {
		Factories::listing()->create( array( 'title' => 'Corner Bakery', 'type_slug' => 'restaurant' ) );
		Cron_Scheduler::unschedule( Search_Indexer::REINDEX_CRON_HOOK );

		Migrator::migrate_1_9_1_20();
		$this->assertFalse( (bool) Cron_Scheduler::has_scheduled( Search_Indexer::REINDEX_CRON_HOOK ) );
	}
}
