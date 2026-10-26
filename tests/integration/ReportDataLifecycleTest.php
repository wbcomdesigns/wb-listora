<?php
/**
 * Who reported a review or listing does not outlive the review, the listing or the member.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Listing_Data_Eraser;
use WBListora\Core\Reviews_Model;
use WBListora\Privacy\Privacy_Eraser;

/**
 * @group listora
 */
class ReportDataLifecycleTest extends WP_UnitTestCase {

	private function report( int $user_id ): array {
		return array( 'user_id' => $user_id, 'reason' => 'spam', 'details' => 'Phone 555-0100', 'date' => '2026-10-22 10:00:00' );
	}

	public function test_a_members_erasure_request_removes_their_reports_and_keeps_others() {
		$member = self::factory()->user->create( array( 'user_email' => 'reporter@example.test' ) );
		$other  = self::factory()->user->create();
		update_option( Reviews_Model::REPORTS_OPTION_PREFIX . '901', array( $this->report( $member ), $this->report( $other ) ), false );
		update_option( Reviews_Model::REPORTS_OPTION_PREFIX . '902', array( $this->report( $member ) ), false );
		update_option( '_listora_listing_reports_903', array( $this->report( $member ) ), false );

		Privacy_Eraser::erase( 'reporter@example.test' );

		$left = get_option( Reviews_Model::REPORTS_OPTION_PREFIX . '901' );
		$this->assertCount( 1, $left );
		$this->assertSame( $other, (int) $left[0]['user_id'] );
		$this->assertFalse( get_option( Reviews_Model::REPORTS_OPTION_PREFIX . '902' ), 'An option left empty is deleted.' );
		$this->assertFalse( get_option( '_listora_listing_reports_903' ) );
	}

	public function test_erasing_a_listing_removes_its_and_its_reviews_reports() {
		global $wpdb;
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		$wpdb->insert( $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews', array( 'listing_id' => $listing, 'user_id' => 1, 'overall_rating' => 4, 'title' => 't', 'content' => 'c', 'status' => 'approved', 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) );
		$review = (int) $wpdb->insert_id;
		update_option( Reviews_Model::REPORTS_OPTION_PREFIX . $review, array( $this->report( 5 ) ), false );
		update_option( '_listora_listing_reports_' . $listing, array( $this->report( 5 ) ), false );

		( new Listing_Data_Eraser() )->erase( $listing );

		$this->assertFalse( get_option( Reviews_Model::REPORTS_OPTION_PREFIX . $review ) );
		$this->assertFalse( get_option( '_listora_listing_reports_' . $listing ) );
	}
}
