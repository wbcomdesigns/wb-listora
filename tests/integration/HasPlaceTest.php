<?php
/**
 * A listing at exactly 0,0 has no place; a single 0 is a real coordinate.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class HasPlaceTest extends WP_UnitTestCase {

	public function test_exactly_zero_zero_is_not_a_place() {
		$this->assertFalse( wb_listora_has_place( 0, 0 ) );
		$this->assertFalse( wb_listora_has_place( '0', '0.0' ) );
	}

	public function test_a_single_zero_is_a_place() {
		$this->assertTrue( wb_listora_has_place( 0, -0.1276 ), 'On the equator.' );
		$this->assertTrue( wb_listora_has_place( 51.5072, 0 ), 'On the Greenwich meridian.' );
	}

	public function test_a_missing_coordinate_is_not_a_place() {
		$this->assertFalse( wb_listora_has_place( '', 10 ) );
		$this->assertFalse( wb_listora_has_place( 10, null ) );
	}

	public function test_an_imported_address_without_coordinates_gets_no_geo_row() {
		$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		\WBListora\Core\Meta_Handler::set_value( $id, 'address', array( 'address' => '1 Main St', 'lat' => 0.0, 'lng' => 0.0 ) );
		( new \WBListora\Search\Search_Indexer() )->index_listing( $id );

		global $wpdb;
		$rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE listing_id = %d', $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'geo', $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( 0, $rows );
	}
}
