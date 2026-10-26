<?php
/**
 * A member's helpful vote still shows as theirs after a reload.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Listing_Data;

/**
 * @group listora
 */
class HelpfulVotePersistsTest extends WP_UnitTestCase {

	public function test_reviews_carry_the_current_members_vote() {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$author  = self::factory()->user->create();
		$voter   = self::factory()->user->create();
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );

		$ids = array();
		foreach ( array( 'Great', 'Fine' ) as $title ) {
			$wpdb->insert( $prefix . 'reviews', array( 'listing_id' => $listing, 'user_id' => 'Great' === $title ? $author : self::factory()->user->create(), 'overall_rating' => 4, 'title' => $title, 'content' => 'A long enough review text here.', 'status' => 'approved', 'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ) );
			$ids[ $title ] = (int) $wpdb->insert_id;
		}

		wp_set_current_user( $voter );
		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'POST', '/listora/v1/reviews/' . $ids['Great'] . '/helpful' ) )->get_status() );

		$voted = array_column( Listing_Data::get_reviews( $listing ), 'user_voted', 'title' );
		$this->assertSame( array( 'Great' => true, 'Fine' => false ), array( 'Great' => $voted['Great'], 'Fine' => $voted['Fine'] ) );

		wp_set_current_user( $author );
		$this->assertFalse( array_column( Listing_Data::get_reviews( $listing ), 'user_voted', 'title' )['Great'], 'Another member\'s vote is not yours.' );
	}
}
