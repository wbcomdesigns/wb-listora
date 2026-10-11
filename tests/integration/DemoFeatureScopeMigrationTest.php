<?php
/**
 * Demo imports from before per-type features get them on upgrade.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WBListora\DB\Migrator;
use WP_UnitTestCase;

/**
 * @group listora
 */
class DemoFeatureScopeMigrationTest extends WP_UnitTestCase {

	public function test_types_get_their_own_demo_features_plus_the_owners() {
		$type = wp_insert_term( 'QA Restaurant', 'listora_listing_type', array( 'slug' => 'qa-restaurant' ) );
		$this->assertNotWPError( $type );
		$type = (int) $type['term_id'];
		$wifi    = (int) wp_insert_term( 'QA Owner Wifi', 'listora_listing_feature' )['term_id'];
		$outdoor = (int) wp_insert_term( 'QA Outdoor Seating', 'listora_listing_feature' )['term_id'];
		$telemed = (int) wp_insert_term( 'QA Telemedicine', 'listora_listing_feature' )['term_id'];
		update_term_meta( $outdoor, '_listora_demo_content', 1 );
		update_term_meta( $telemed, '_listora_demo_content', 1 );

		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		update_post_meta( $listing, '_listora_demo_content', 1 );
		wp_set_object_terms( $listing, $type, 'listora_listing_type' );
		wp_set_object_terms( $listing, array( $outdoor ), 'listora_listing_feature' );

		Migrator::migrate_1_9_1_9();

		$allowed = array_map( 'intval', (array) get_term_meta( $type, '_listora_allowed_features', true ) );
		$this->assertContains( $wifi, $allowed, 'The owner\'s own features stay offered.' );
		$this->assertContains( $outdoor, $allowed );
		$this->assertNotContains( $telemed, $allowed, 'Another pack\'s feature is not offered.' );
	}
}
