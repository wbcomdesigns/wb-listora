<?php
/**
 * A charge settles its own hold, never another feature's hold on the same listing.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group credits
 */
class ChargeHoldCollisionTest extends WP_UnitTestCase {

	private int $owner   = 0;
	private int $listing = 0;

	public function set_up() {
		parent::set_up();
		if ( ! wb_listora_credits_ready() ) {
			$this->markTestSkipped( 'The credits library is not ready.' );
		}
		$this->owner   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $this->owner ) );
		global $wpdb;
		$wpdb->delete( \Wbcom\Credits\Ledger::table_name( 'listora' ), array( 'user_id' => $this->owner ) );
		\Wbcom\Credits\Credits::topup_money( WB_LISTORA_CREDITS_SLUG, $this->owner, 100.0, '', 'test' );
	}

	public function tear_down() {
		global $wpdb;
		$wpdb->delete( \Wbcom\Credits\Ledger::table_name( 'listora' ), array( 'user_id' => $this->owner ) );
		\Wbcom\Credits\Credits::invalidate_cache( WB_LISTORA_CREDITS_SLUG, $this->owner );
		parent::tear_down();
	}

	private function rows(): string {
		$rows = \Wbcom\Credits\Credits::query_ledger( WB_LISTORA_CREDITS_SLUG, array( 'user_id' => $this->owner, 'order' => 'ASC' ) );
		return implode( ' | ', array_map( static fn ( $r ) => "#{$r->id} {$r->entry_type} amt={$r->amount} hold={$r->hold_id} item={$r->item_id}", $rows ) );
	}

	/**
	 * Another feature places its own hold on the same listing while this charge's work runs
	 * (a second request, an approval hook). The charge must settle ITS hold, not the newest one.
	 */
	public function test_a_charge_settles_its_own_hold_when_another_lands_mid_work() {
		$foreign = 0;
		$result  = wb_listora_charge(
			array(
				'listing_id' => $this->listing,
				'amount'     => 5.0,
				'note'       => 'Featured',
				'actor_id'   => $this->owner,
				'work'       => function () use ( &$foreign ) {
					$foreign = (int) \Wbcom\Credits\Credits::hold_money( WB_LISTORA_CREDITS_SLUG, $this->owner, 10.0, $this->listing, '', 'submission' );
					return true;
				},
			)
		);
		$this->assertNotWPError( $result );
		$this->assertGreaterThan( 0, $foreign );

		$settled = fn ( int $hold ) => \Wbcom\Credits\Credits::query_ledger( WB_LISTORA_CREDITS_SLUG, array( 'user_id' => $this->owner, 'hold_id' => $hold, 'entry_type' => 'deduction' ) );
		$this->assertNotEmpty( $settled( (int) $result['hold_id'] ), 'the charge settles its own hold: ' . $this->rows() );
		$this->assertEmpty( $settled( $foreign ), 'the other feature\'s hold stays open: ' . $this->rows() );
	}

	/**
	 * Renewing an expired listing publishes it inside the charge's work. The approval bridge must not
	 * settle every open hold on the listing (the charge's own included): the charge then failed and
	 * the member was debited while told they were not charged.
	 */
	public function test_publishing_inside_the_work_does_not_settle_the_charges_own_hold() {
		$result = wb_listora_charge(
			array(
				'listing_id' => $this->listing,
				'amount'     => 5.0,
				'note'       => 'Renewal',
				'actor_id'   => $this->owner,
				'work'       => function () {
					$this->assertTrue( \WBListora\Core\Charge::in_flight( $this->listing ) );
					do_action( 'wb_listora_after_approve_listing', $this->listing );
					return true;
				},
			)
		);
		$this->assertNotWPError( $result, $this->rows() );
		$this->assertFalse( \WBListora\Core\Charge::in_flight( $this->listing ), 'The flag clears when the work ends.' );
		$this->assertEqualsWithDelta( 95.0, wb_listora_credit_balance( $this->owner ), 0.001, $this->rows() );
	}

	public function test_a_free_submission_never_needs_the_credit_lock() {
		$this->assertTrue( \WBListora\Core\Charge::submission( $this->listing ) );
	}
}
