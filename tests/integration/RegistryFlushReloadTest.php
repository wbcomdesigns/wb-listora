<?php
/**
 * A flush reloads a loaded registry, so the same request still sees its types.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Listing_Type_Registry;
use WBListora\Search\Search_Indexer;

/**
 * @group listora
 */
class RegistryFlushReloadTest extends WP_UnitTestCase {

	public function test_types_survive_a_flush_in_the_same_request() {
		$registry = Listing_Type_Registry::instance();
		$registry->init();
		$registry->save_type( 'qa-bakery', array( 'props' => array( 'name' => 'QA Bakery' ) ) );

		$this->assertNotNull( $registry->get( 'qa-bakery' ), 'save_type() flushes; the new type must be readable straight after.' );
		$this->assertArrayHasKey( 'qa-bakery', $registry->get_all() );
	}

	public function test_a_listing_saved_after_a_type_change_is_indexed_with_its_type() {
		global $wpdb;

		$registry = Listing_Type_Registry::instance();
		$registry->init();
		$registry->save_type( 'qa-florist', array( 'props' => array( 'name' => 'QA Florist' ) ) );

		$post_id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_title' => 'Petal & Stem' ) );
		wp_set_object_terms( $post_id, 'qa-florist', 'listora_listing_type' );
		( new Search_Indexer() )->index_listing( $post_id, get_post( $post_id ) );

		$type = $wpdb->get_var( $wpdb->prepare( "SELECT listing_type FROM {$wpdb->prefix}listora_search_index WHERE listing_id = %d", $post_id ) );
		$this->assertSame( 'qa-florist', $type, 'The demo seeder indexed every listing with an empty type this way.' );
	}
}
