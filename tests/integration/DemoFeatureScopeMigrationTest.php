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

	/**
	 * Older seeders never flagged their feature terms: demo features are known
	 * from the packs' own lists, and each type keeps only the demo features
	 * its own listings use.
	 */
	public function test_unflagged_demo_features_follow_their_type() {
		$diner  = (int) wp_insert_term( 'QA Diner', 'listora_listing_type', array( 'slug' => 'qa-diner' ) )['term_id'];
		$clinic = (int) wp_insert_term( 'QA Clinic', 'listora_listing_type', array( 'slug' => 'qa-clinic' ) )['term_id'];
		$events = (int) wp_insert_term( 'QA Events', 'listora_listing_type', array( 'slug' => 'qa-events' ) )['term_id'];
		$term   = static function ( string $slug, string $name ): int {
			$found = term_exists( $slug, 'listora_listing_feature' );
			return (int) ( $found ? $found['term_id'] : wp_insert_term( $name, 'listora_listing_feature', array( 'slug' => $slug ) )['term_id'] );
		};
		$delivery = $term( 'delivery', 'Delivery' );         // Restaurant pack.
		$telemed  = $term( 'telemedicine', 'Telemedicine' ); // Healthcare pack.
		$valet    = $term( 'qa-owner-valet', 'QA Valet' );   // The owner's own, in no pack.

		$listing = function ( int $type, array $features, bool $demo ) {
			$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
			if ( $demo ) {
				update_post_meta( $id, '_listora_demo_content', 1 );
			}
			wp_set_object_terms( $id, $type, 'listora_listing_type' );
			wp_set_object_terms( $id, $features, 'listora_listing_feature' );
		};
		$listing( $diner, array( $delivery ), true );
		$listing( $clinic, array( $telemed ), true );
		$listing( $diner, array( $telemed ), false ); // One stray real listing.

		Migrator::migrate_1_9_1_13();

		$allowed = static function ( int $type ): array {
			return array_map( 'intval', (array) get_term_meta( $type, '_listora_allowed_features', true ) );
		};
		$this->assertContains( $delivery, $allowed( $diner ) );
		$this->assertContains( $valet, $allowed( $diner ), 'The owner\'s feature stays on every type.' );
		$this->assertContains( $telemed, $allowed( $diner ), 'The stray listing keeps its feature, on its own type.' );
		$this->assertContains( $telemed, $allowed( $clinic ) );
		$this->assertNotContains( $delivery, $allowed( $clinic ), 'The restaurant pack\'s feature stays off the clinic.' );
		$this->assertNotEmpty( $allowed( $events ), 'A type with no demo listings is scoped too.' );
		$this->assertNotContains( $telemed, $allowed( $events ) );
		$this->assertContains( $valet, $allowed( $events ) );

		$before = get_term_meta( $diner, '_listora_allowed_features', true );
		Migrator::migrate_1_9_1_13();
		$this->assertSame( $before, get_term_meta( $diner, '_listora_allowed_features', true ), 'A second run changes nothing.' );
	}
}

