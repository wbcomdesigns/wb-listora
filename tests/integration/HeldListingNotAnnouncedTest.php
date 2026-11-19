<?php
/**
 * A listing held for credits (`listora_payment`) is not announced, and nothing writes over the hold.
 * The "plan step" is simulated the way Pro does it: a listener on payment_due that parks the listing.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Charge;
use WBListora\Workflow\Email_Verification;

/**
 * @group listora
 */
class HeldListingNotAnnouncedTest extends WP_UnitTestCase {

	private int $author  = 0;
	private int $listing = 0;
	private array $mail  = array();

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

	private function hold_on_payment_due(): void {
		add_action(
			'wb_listora_listing_payment_due',
			static function ( $id ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'listora_payment',
					)
				);
			}
		);
	}

	public function test_announce_skips_a_held_listing_and_fires_for_a_paid_one() {
		wp_update_post( array( 'ID' => $this->listing, 'post_status' => 'pending' ) );
		wp_update_post( array( 'ID' => $this->listing, 'post_status' => 'listora_payment' ) );
		$this->assertSame( 'listora_payment', get_post_status( $this->listing ) );
		$before = did_action( 'wb_listora_listing_submitted' );
		$this->assertFalse( Charge::announce_submitted( $this->listing ) );
		$this->assertSame( $before, did_action( 'wb_listora_listing_submitted' ) );

		wp_update_post( array( 'ID' => $this->listing, 'post_status' => 'pending' ) );
		$this->assertSame( 'pending', get_post_status( $this->listing ) );
		$this->assertTrue( Charge::announce_submitted( $this->listing ) );
		$this->assertSame( $before + 1, did_action( 'wb_listora_listing_submitted' ) );
	}

	public function test_verifying_a_listing_whose_plan_is_unpaid_keeps_the_hold_and_announces_nothing() {
		$this->hold_on_payment_due();
		$token  = Email_Verification::generate_token( $this->listing, $this->author );
		$before = did_action( 'wb_listora_listing_submitted' );
		$admin  = did_action( 'wb_listora_listing_pending_admin' );

		$result = Email_Verification::confirm( $this->listing, $token );

		$this->assertIsArray( $result );
		$this->assertSame( 'listora_payment', $result['status'] );
		$this->assertSame( 'listora_payment', get_post_status( $this->listing ), 'The hold is not overwritten.' );
		$this->assertSame( $before, did_action( 'wb_listora_listing_submitted' ) );
		$this->assertSame( $admin, did_action( 'wb_listora_listing_pending_admin' ) );
	}
}
