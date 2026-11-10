<?php
/**
 * A listing owner sees the reviews left on their listings, never the reviewer's IP address.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Dashboard_Data;

/**
 * @group listora
 * @group privacy
 */
class DashboardReviewPrivacyTest extends WP_UnitTestCase {

	public function test_reviews_received_and_written_carry_no_ip_address() {
		global $wpdb;
		$owner    = self::factory()->user->create();
		$reviewer = self::factory()->user->create();
		$listing  = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $owner ) );
		$wpdb->insert(
			$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews',
			array(
				'listing_id'     => $listing,
				'user_id'        => $reviewer,
				'overall_rating' => 5,
				'title'          => 'Great',
				'content'        => 'Really great',
				'status'         => 'approved',
				'ip_address'     => '203.0.113.7',
			)
		);

		$received = Dashboard_Data::list_reviews_received( $owner, 10 );
		$written  = Dashboard_Data::list_reviews_written( $reviewer, 10 );

		$this->assertCount( 1, $received );
		$this->assertArrayNotHasKey( 'ip_address', $received[0] );
		$this->assertSame( 'Great', $received[0]['title'] );
		$this->assertCount( 1, $written );
		$this->assertArrayNotHasKey( 'ip_address', $written[0] );
	}
}
