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
}
