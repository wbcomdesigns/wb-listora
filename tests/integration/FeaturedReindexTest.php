<?php
/**
 * Featuring and its expiry update the search index straight away.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Featured;

/**
 * @group listora
 */
class FeaturedReindexTest extends WP_UnitTestCase {

	private function indexed_featured( int $id ): string {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT is_featured FROM {$wpdb->prefix}listora_search_index WHERE listing_id = %d", $id ) );
	}

	public function test_feature_unfeature_and_expiry_reach_the_index() {
		$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		$this->assertSame( '0', $this->indexed_featured( $id ) );

		Featured::feature_listing( $id, 30 );
		$this->assertSame( '1', $this->indexed_featured( $id ), 'Featured at once, not at the next edit.' );

		Featured::unfeature_listing( $id );
		$this->assertSame( '0', $this->indexed_featured( $id ) );

		Featured::feature_listing( $id, 30 );
		update_post_meta( $id, Featured::META_FEATURED_UNTIL, time() - HOUR_IN_SECONDS );
		Featured::expire_featured_listings();
		$this->assertSame( '0', $this->indexed_featured( $id ), 'The sweep clears the index too.' );
	}
}
