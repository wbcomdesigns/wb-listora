<?php
/**
 * The web card and the REST card helper say the same place for one listing.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ListingLocationTextTest extends WP_UnitTestCase {

	public function test_the_location_text_is_city_and_state_everywhere() {
		\WBListora\Core\Listing_Type_Registry::instance()->register(
			'shape_loc',
			new \WBListora\Core\Listing_Type(
				'shape_loc',
				array( 'name' => 'Shape test' ),
				array(
					array(
						'key'    => 'main',
						'label'  => 'Main',
						'fields' => array(
							array(
								'key'   => 'address',
								'type'  => 'address',
								'label' => 'Address',
							),
						),
					),
				)
			)
		);
		$listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => 'Place',
			)
		);
		wp_set_object_terms( $listing, 'shape_loc', 'listora_listing_type' );
		\WBListora\Core\Meta_Handler::set_value(
			$listing,
			'address',
			array(
				'address' => '1 Main St',
				'city'    => 'Austin',
				'state'   => 'TX',
				'country' => 'US',
			)
		);
		( new \WBListora\Search\Search_Indexer() )->index_listing( $listing );

		$web  = wb_listora_prepare_card_data( $listing );
		$rest = wb_listora_get_listing_cards( array( $listing ) );

		$this->assertSame( 'Austin, TX', $web['location'] );
		$this->assertSame( 'Austin, TX', $rest[ $listing ]['location'] );
	}

	public function test_a_listing_without_a_state_shows_city_only() {
		$this->assertSame( 'Paris', wb_listora_listing_location_text( array( 'city' => 'Paris', 'country' => 'FR' ) ) );
		$this->assertSame( '', wb_listora_listing_location_text( 'not an address' ) );
	}
}
