<?php
/**
 * Which reviews a member already reported, for a whole page of reviews in one lookup.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Reviews_Model;

/**
 * @group listora
 * @group reviews
 */
class ReviewReportedByUserTest extends WP_UnitTestCase {

	public function tear_down() {
		foreach ( array( 9101, 9102, 9103 ) as $id ) {
			delete_option( Reviews_Model::REPORTS_OPTION_PREFIX . $id );
		}
		parent::tear_down();
	}

	public function test_only_the_reviews_this_member_reported_come_back() {
		update_option(
			Reviews_Model::REPORTS_OPTION_PREFIX . 9101,
			array(
				array( 'user_id' => 7, 'reason' => 'spam' ),
				array( 'user_id' => 8, 'reason' => 'spam' ),
			),
			false
		);
		update_option( Reviews_Model::REPORTS_OPTION_PREFIX . 9102, array( array( 'user_id' => 8, 'reason' => 'spam' ) ), false );

		$this->assertSame( array( 9101 ), Reviews_Model::reported_by_user( array( 9101, 9102, 9103 ), 7 ) );
		$this->assertSame( array( 9101, 9102 ), Reviews_Model::reported_by_user( array( 9101, 9102, 9103 ), 8 ) );
	}

	public function test_a_guest_or_an_empty_page_costs_nothing_and_returns_nothing() {
		$this->assertSame( array(), Reviews_Model::reported_by_user( array( 9101 ), 0 ) );
		$this->assertSame( array(), Reviews_Model::reported_by_user( array(), 7 ) );
	}

	public function test_a_corrupted_option_does_not_break_the_page() {
		update_option( Reviews_Model::REPORTS_OPTION_PREFIX . 9103, 'not-a-list', false );
		$this->assertSame( array(), Reviews_Model::reported_by_user( array( 9103 ), 7 ) );
	}
}
