<?php
/**
 * Reviews_Model::get() is the read door integrations (BuddyNext's feed) use.
 *
 * @package WBListora\Tests
 */

namespace WBListora\Tests\Integration;

use WBListora\Core\Reviews_Model;
use WBListora\Tests\Factories\Factories;
use WP_UnitTestCase;

/**
 * @group listora
 * @group reviews
 */
class ReviewsModelGetTest extends WP_UnitTestCase {

	private function listing(): int {
		return Factories::listing()->create( array( 'title' => 'Reviewed Place', 'type_slug' => 'restaurant' ) );
	}

	public function test_returns_the_row_with_its_listing_and_status() {
		$listing = $this->listing();
		$id      = Factories::review()->create( array( 'listing_id' => $listing, 'overall_rating' => 4, 'title' => 'Good' ) );

		$row = Reviews_Model::get( $id );

		$this->assertSame( $id, (int) $row['id'] );
		$this->assertSame( $listing, (int) $row['listing_id'] );
		$this->assertSame( 4, (int) $row['overall_rating'] );
		$this->assertSame( 'Good', $row['title'] );
		$this->assertSame( 'approved', $row['status'] );
		$this->assertArrayHasKey( 'listing_title', $row );
		$this->assertArrayHasKey( 'listing_type', $row );
	}

	public function test_keeps_a_pending_status_so_callers_can_skip_it() {
		$id = Factories::review()->create( array( 'listing_id' => $this->listing(), 'status' => 'pending' ) );

		$this->assertSame( 'pending', Reviews_Model::get( $id )['status'] );
	}

	public function test_never_returns_the_reviewer_ip() {
		global $wpdb;
		$id = Factories::review()->create( array( 'listing_id' => $this->listing() ) );
		$wpdb->update( $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews', array( 'ip_address' => '203.0.113.7' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->assertArrayNotHasKey( 'ip_address', Reviews_Model::get( $id ) );
	}

	public function test_missing_zero_and_negative_ids_give_null() {
		$this->assertNull( Reviews_Model::get( 99999999 ) );
		$this->assertNull( Reviews_Model::get( 0 ) );
		$this->assertNull( Reviews_Model::get( -3 ) );
	}
}
