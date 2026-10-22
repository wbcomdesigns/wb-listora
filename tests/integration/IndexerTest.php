<?php
/**
 * Regression tests for the Search Indexer (G13).
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   search
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Search\Search_Indexer;
use WBListora\Tests\Factories\Factories;

/**
 * @group listora
 * @group search
 */
class IndexerTest extends WP_UnitTestCase {

	/**
	 * Guard — WP's test DB uses transactions; our custom tables are
	 * InnoDB-committed during bootstrap's Activator::activate(). Skip the
	 * suite if the tables aren't present (e.g. permissions issue), rather
	 * than failing on a precondition that isn't the code under test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'search_index';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $table !== $exists ) {
			$this->markTestSkipped( 'search_index table missing — run Activator::activate() in bootstrap.' );
		}
	}

	/**
	 * G13 regression — the indexer reads listing_type from the taxonomy,
	 * not from any cached state. Call index_listing() directly after
	 * setting terms and assert the row reflects the term.
	 */
	public function test_indexer_reads_listing_type_from_taxonomy() {
		global $wpdb;

		$listing_id = Factories::listing()->create(
			array(
				'title'     => 'Indexer Type Test',
				'type_slug' => 'restaurant',
			)
		);

		// Make sure the type term was applied by the factory — otherwise
		// this test is meaningless regardless of indexer behaviour.
		$terms = wp_get_object_terms( $listing_id, 'listora_listing_type', array( 'fields' => 'slugs' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			$this->markTestSkipped( 'listora_listing_type taxonomy not registered in this test env.' );
		}

		// Index directly — avoids dependence on hook firing order inside
		// the WP test-lib transaction.
		$indexer = new Search_Indexer();
		$indexer->index_listing( $listing_id, get_post( $listing_id ) );

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'search_index';
		$row    = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT listing_type, title FROM {$prefix} WHERE listing_id = %d", $listing_id ),
			ARRAY_A
		);

		$this->assertNotEmpty( $row, 'search_index row should exist after index_listing().' );
		$this->assertSame( 'restaurant', $row['listing_type'] );
		$this->assertSame( 'Indexer Type Test', $row['title'] );
	}

	/**
	 * The empty shell WordPress creates when Add New opens is not a listing:
	 * it must not take a row in the index (it inflated the Health Check count
	 * to 98 against 68 published listings).
	 */
	public function test_auto_draft_is_not_indexed() {
		global $wpdb;

		$listing_id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'auto-draft',
			)
		);

		( new Search_Indexer() )->index_listing( $listing_id, get_post( $listing_id ) );

		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'search_index';
		$rows  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE listing_id = %d", $listing_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test reads the plugin table.

		$this->assertSame( 0, $rows );
	}

	/**
	 * remove_from_index cleans both search_index and geo for a given listing.
	 */
	public function test_remove_from_index_cleans_geo_too() {
		global $wpdb;

		$listing_id = Factories::listing()->create(
			array(
				'title'   => 'Delete Cascade Test',
				'address' => array(
					'address' => '1 Test St',
					'lat'     => '40.71',
					'lng'     => '-74.00',
					'city'    => 'NYC',
					'country' => 'USA',
				),
			)
		);

		$indexer = new Search_Indexer();
		$indexer->index_listing( $listing_id, get_post( $listing_id ) );

		$search_prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'search_index';
		$geo_prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'geo';

		// Precondition.
		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$search_prefix} WHERE listing_id = %d", $listing_id ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$indexer->remove_from_index( $listing_id );

		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$search_prefix} WHERE listing_id = %d", $listing_id ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$geo_prefix} WHERE listing_id = %d", $listing_id ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Claiming and verifying write only post meta, so the search index follows the
	 * meta (otherwise Pro's badge counts and the Claimed filter disagree with the
	 * listing page).
	 */
	public function test_index_follows_the_claimed_and_verified_meta() {
		global $wpdb;
		$listing_id = Factories::listing()->create( array( 'title' => 'Claim Sync Test', 'type_slug' => 'restaurant' ) );

		$flags = static function () use ( $wpdb, $listing_id ) {
			$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'search_index';
			return $wpdb->get_row( $wpdb->prepare( "SELECT is_claimed, is_verified FROM {$table} WHERE listing_id = %d", $listing_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		};

		update_post_meta( $listing_id, '_listora_is_claimed', 1 );
		$this->assertSame( '1', (string) $flags()['is_claimed'] );

		delete_post_meta( $listing_id, '_listora_is_claimed' );
		$this->assertSame( '0', (string) $flags()['is_claimed'], 'Un-claiming clears the index flag.' );

		update_post_meta( $listing_id, '_listora_is_verified', 1 );
		$this->assertSame( '1', (string) $flags()['is_verified'] );
	}
}
