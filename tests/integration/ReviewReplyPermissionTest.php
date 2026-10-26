<?php
/**
 * Who may reply to a review: one rule for the endpoint and the Reply button.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ReviewReplyPermissionTest extends WP_UnitTestCase {

	private function listing_by( int $author ): int {
		return self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $author ) );
	}

	public function test_author_administrator_and_moderator_may_reply_others_may_not() {
		$author = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$listing = $this->listing_by( $author );

		wp_set_current_user( $author );
		$this->assertTrue( wb_listora_user_can_reply_to_review( $listing ), 'The listing author.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( wb_listora_user_can_reply_to_review( $listing ), 'A site administrator, who had no Reply button.' );

		$moderator = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		( new WP_User( $moderator ) )->add_cap( 'moderate_listora_reviews' );
		wp_set_current_user( $moderator );
		$this->assertTrue( wb_listora_user_can_reply_to_review( $listing ), 'A review moderator.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( wb_listora_user_can_reply_to_review( $listing ), 'Another member.' );

		wp_set_current_user( 0 );
		$this->assertFalse( wb_listora_user_can_reply_to_review( $listing ), 'A guest.' );
	}

	public function test_replies_switched_off_closes_it_for_everyone() {
		$settings            = get_option( 'wb_listora_settings', array() );
		$settings['reviews'] = array( 'allow_reply' => false );
		update_option( 'wb_listora_settings', $settings );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertFalse( wb_listora_user_can_reply_to_review( $this->listing_by( 1 ) ) );
	}
}
