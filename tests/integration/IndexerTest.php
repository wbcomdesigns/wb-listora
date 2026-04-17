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
use WBListora\Tests\Factories\Factories;

/**
 * @group listora
 * @group search
 */
class IndexerTest extends WP_UnitTestCase {

	/**
	 * G13 regression — Factory creates a listing and assigns the type term
	 * via wp_set_object_terms. The search_index row must have listing_type
	 * populated (the set_object_terms hook re-indexes after the taxonomy
	 * change).
	 */
	public function test_search_index_has_listing_type_after_factory_create() {
		global $wpdb;

		$listing_id = Factories::listing()->create(
			array(
				'title'     => 'Indexer Test Restaurant',
				'type_slug' => 'restaurant',
			)
		);

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'search_index';
		$row    = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT listing_type, title FROM {$prefix} WHERE listing_id = %d", $listing_id ),
			ARRAY_A
		);

		$this->assertNotEmpty( $row, 'search_index row should exist.' );
		$this->assertSame( 'restaurant', $row['listing_type'], 'listing_type must be populated after taxonomy assignment.' );
		$this->assertSame( 'Indexer Test Restaurant', $row['title'] );
	}

	/**
	 * Deleting a listing cascades — search_index + geo rows are cleaned up.
	 */
	public function test_delete_cleans_search_index_and_geo() {
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

		$search_prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'search_index';
		$geo_prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'geo';

		// Precondition: row exists after factory create.
		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$search_prefix} WHERE listing_id = %d", $listing_id ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		wp_delete_post( $listing_id, true );

		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$search_prefix} WHERE listing_id = %d", $listing_id ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$geo_prefix} WHERE listing_id = %d", $listing_id ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
