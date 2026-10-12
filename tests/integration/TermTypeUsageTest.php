<?php
/**
 * Unrestricted types: what their listings use decides filters and category labels.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Listing_Type_Registry;

/**
 * @group listora
 */
class TermTypeUsageTest extends WP_UnitTestCase {

	public function test_usage_and_feature_filter_map_for_an_unrestricted_type() {
		$registry = Listing_Type_Registry::instance();
		$registry->save_type( 'qa-food', array( 'props' => array( 'name' => 'QA Food' ) ) );
		$registry->save_type( 'qa-stay', array( 'props' => array( 'name' => 'QA Stay' ) ) );
		$registry->flush();
		$registry->init();

		$wifi = self::factory()->term->create( array( 'taxonomy' => 'listora_listing_feature', 'name' => 'QA WiFi' ) );
		$spa  = self::factory()->term->create( array( 'taxonomy' => 'listora_listing_feature', 'name' => 'QA Spa' ) );

		$food = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		wp_set_object_terms( $food, 'qa-food', 'listora_listing_type' );
		wp_set_object_terms( $food, array( $wifi ), 'listora_listing_feature' );
		$stay = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		wp_set_object_terms( $stay, 'qa-stay', 'listora_listing_type' );
		wp_set_object_terms( $stay, array( $wifi, $spa ), 'listora_listing_feature' );

		$usage = wb_listora_term_type_usage( 'listora_listing_feature' );
		$this->assertSame( 1, $usage[ $wifi ]['qa-food'] );
		$this->assertArrayNotHasKey( 'qa-food', $usage[ $spa ] );

		$map = wb_listora_get_feature_filter_map();
		$this->assertSame( array( $wifi ), $map['qa-food'], 'Food offers only what food listings use.' );
		$this->assertEqualsCanonicalizing( array( $wifi, $spa ), $map['qa-stay'] );
	}
}
