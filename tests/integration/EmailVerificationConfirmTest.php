<?php
/**
 * Email_Verification::confirm(): the one path behind the verify link and the REST route.
 *
 * @package WBListora\Tests
 */

use WBListora\Workflow\Email_Verification;

/**
 * @group listora
 */
class EmailVerificationConfirmTest extends WP_UnitTestCase {

	private int $author  = 0;
	private int $listing = 0;

	public function set_up() {
		parent::set_up();
		$this->author  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'pending_verification',
				'post_author' => $this->author,
			)
		);
	}

	public function test_a_wrong_token_changes_nothing() {
		Email_Verification::generate_token( $this->listing, $this->author );

		$result = Email_Verification::confirm( $this->listing, 'wrong' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
		$this->assertSame( 'pending_verification', get_post_status( $this->listing ) );
	}

	public function test_a_valid_token_moves_the_listing_on_announces_and_signs_nobody_in() {
		$token = Email_Verification::generate_token( $this->listing, $this->author );
		wp_set_current_user( 0 );

		$result = Email_Verification::confirm( $this->listing, $token );

		$this->assertIsArray( $result );
		$this->assertContains( $result['status'], array( 'pending', 'publish' ) );
		$this->assertSame( 1, did_action( 'wb_listora_listing_submitted' ) );
		$this->assertSame( 0, get_current_user_id(), 'A link from an inbox is not a login.' );
		$this->assertWPError( Email_Verification::confirm( $this->listing, $token ), 'The token is single use.' );
	}

	/**
	 * Two opens of one link: the second passes the token check (it read the token first) but loses the
	 * claim because the first already deleted the row. It must change nothing and announce nothing.
	 */
	public function test_a_second_open_that_lost_the_claim_changes_nothing() {
		$token = Email_Verification::generate_token( $this->listing, $this->author );
		global $wpdb;
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $this->listing, 'meta_key' => Email_Verification::META_TOKEN ) );
		wp_cache_delete( $this->listing, 'post_meta' );
		$listing = $this->listing;
		$stale   = static fn ( $value, $id, $key ) => ( $id === $listing && Email_Verification::META_TOKEN === $key ) ? array( $token ) : $value;
		add_filter( 'get_post_metadata', $stale, 10, 3 );
		$before = did_action( 'wb_listora_listing_submitted' );

		$result = Email_Verification::confirm( $this->listing, $token );
		remove_filter( 'get_post_metadata', $stale, 10 );

		$this->assertWPError( $result );
		$this->assertSame( 'not_pending', $result->get_error_code() );
		$this->assertSame( 'pending_verification', get_post_status( $this->listing ) );
		$this->assertSame( $before, did_action( 'wb_listora_listing_submitted' ) );
	}
}
