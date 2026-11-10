<?php
/**
 * A search centre or a listing sitting on longitude 0 or latitude 0 still counts as a place.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group search
 */
class SearchZeroCoordinateTest extends WP_UnitTestCase {

	private function listing_at( float $lat, float $lng ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_title' => 'Spot ' . $lat . ',' . $lng ) );
		update_post_meta( $id, '_listora_address', array( 'address' => 'x', 'lat' => $lat, 'lng' => $lng ) );
		( new \WBListora\Search\Search_Indexer() )->index_listing( $id );
		return $id;
	}

	private function ids( array $params ): array {
		add_filter( 'wb_listora_search_cache_ttl', '__return_zero' );
		$request = new WP_REST_Request( 'GET', '/listora/v1/search' );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		$data = rest_do_request( $request )->get_data();
		return array_map( 'intval', wp_list_pluck( $data['listings'], 'id' ) );
	}

	public function test_the_helper_treats_zero_as_a_coordinate_and_blank_as_none() {
		$this->assertTrue( wb_listora_has_coordinate( 0 ) );
		$this->assertTrue( wb_listora_has_coordinate( '0' ) );
		$this->assertTrue( wb_listora_has_coordinate( -0.12 ) );
		$this->assertFalse( wb_listora_has_coordinate( null ) );
		$this->assertFalse( wb_listora_has_coordinate( '' ) );
	}

	public function test_radius_search_works_around_longitude_zero() {
		$near = $this->listing_at( 51.5, 0.0 );
		$far  = $this->listing_at( 40.4, -3.7 );

		$found = $this->ids( array( 'lat' => 51.5, 'lng' => 0, 'radius' => 25, 'radius_unit' => 'km' ) );

		$this->assertContains( $near, $found );
		$this->assertNotContains( $far, $found );
	}

	public function test_a_listing_on_latitude_zero_is_indexed_for_distance() {
		$id = $this->listing_at( 0.0, 36.8 );

		$found = $this->ids( array( 'lat' => 0, 'lng' => 36.8, 'radius' => 10, 'radius_unit' => 'km' ) );

		$this->assertContains( $id, $found );
	}

	/**
	 * A latitude with no longitude is half a place; it must not be indexed at longitude 0.
	 */
	public function test_a_latitude_without_a_longitude_is_not_geo_indexed() {
		global $wpdb;
		$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_title' => 'Half place' ) );
		update_post_meta( $id, '_listora_address', array( 'address' => 'x', 'lat' => 51.5 ) );
		( new \WBListora\Search\Search_Indexer() )->index_listing( $id );

		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'geo';
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE listing_id = %d", $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function listing_without_place( ?float $lat ): int {
		$id   = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_title' => 'No place ' . wp_generate_password( 6, false ) ) );
		$addr = array( 'address' => 'x' );
		if ( null !== $lat ) {
			$addr['lat'] = $lat;
		}
		update_post_meta( $id, '_listora_address', $addr );
		( new \WBListora\Search\Search_Indexer() )->index_listing( $id );
		return $id;
	}

	/**
	 * The index stores a missing coordinate as 0, so without the geo-table condition a
	 * listing with no (or half a) place matched any search centred on longitude 0 or on 0,0.
	 */
	public function test_listings_without_a_full_place_are_not_found_by_a_search_centred_on_zero() {
		$half = $this->listing_without_place( 51.5 );
		$none = $this->listing_without_place( null );
		$real = $this->listing_at( 51.5, 0.0 );

		$around_lng_zero = $this->ids( array( 'lat' => 51.5, 'lng' => 0, 'radius' => 25, 'radius_unit' => 'km' ) );
		$around_origin   = $this->ids( array( 'lat' => 0, 'lng' => 0, 'radius' => 25, 'radius_unit' => 'km' ) );

		$this->assertContains( $real, $around_lng_zero );
		$this->assertNotContains( $half, $around_lng_zero );
		$this->assertNotContains( $none, $around_lng_zero );
		$this->assertNotContains( $half, $around_origin );
		$this->assertNotContains( $none, $around_origin );
	}

	public function test_a_listing_on_the_equator_still_counts_as_mappable() {
		$equator = $this->listing_at( 0.0, 36.8 );
		$none    = $this->listing_without_place( null );

		$found = array_map( 'intval', ( new \WBListora\Search\Search_Engine() )->search( array( 'has_geo' => true, 'per_page' => 50 ) )['listing_ids'] );

		$this->assertContains( $equator, $found );
		$this->assertNotContains( $none, $found );
	}

	/**
	 * The index holds 51.5,0 for a lat-only listing, which is distance 0 from a search centred there.
	 */
	public function test_a_listing_without_a_full_place_sorts_last_and_has_no_distance() {
		$half = $this->listing_without_place( 51.5 );
		$near = $this->listing_at( 51.6, 0.2 );

		$result = ( new \WBListora\Search\Search_Engine() )->search( array( 'lat' => 51.5, 'lng' => 0, 'sort' => 'distance', 'per_page' => 50 ) );
		$ids    = array_map( 'intval', $result['listing_ids'] );

		$this->assertContains( $half, $ids );
		$this->assertLessThan( array_search( $half, $ids, true ), array_search( $near, $ids, true ), 'The located listing ranks first.' );
		$this->assertArrayHasKey( $near, $result['distances'] );
		$this->assertArrayNotHasKey( $half, $result['distances'], 'No place, no distance.' );
	}
}
