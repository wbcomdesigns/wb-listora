<?php
/**
 * A submission that cannot be paid for is not announced.
 *
 * The payment step (`wb_listora_listing_payment_due`, then Free's charge) runs before
 * `wb_listora_listing_submitted` and `wb_listora_after_create_listing`. Before 2.1.1 the
 * announcement fired first, so a short balance still sent the "new listing" emails and
 * webhooks for a listing that went straight back to draft.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group credits
 */
class SubmissionPaymentOrderTest extends WP_UnitTestCase {

	private int $member = 0;
	private array $seen = array();

	public function set_up() {
		parent::set_up();
		if ( ! wb_listora_credits_ready() ) {
			$this->markTestSkipped( 'The credits library is not ready.' );
		}
		add_filter( 'wb_listora_rate_limit_bypass', '__return_true' );
		$this->member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $this->member );
		$this->reset_ledger( array( $this->member ) );
		$settings = (array) get_option( 'wb_listora_settings', array() );
		update_option( 'wb_listora_settings', array_merge( $settings, array( 'default_listing_credit_cost' => 5 ) ) );
		wb_listora_get_setting( null, null, true ); // The setting is cached for the request; reload it.
		foreach ( array( 'wb_listora_listing_payment_due', 'wb_listora_listing_submitted', 'wb_listora_after_create_listing' ) as $hook ) {
			add_action(
				$hook,
				function () use ( $hook ) {
					$this->seen[] = $hook;
				},
				1
			);
		}
		do_action( 'rest_api_init' );
	}

	/**
	 * The ledger is a custom table that PHPUnit's rollback does not clear, and test user IDs are
	 * reused, so a new user can inherit credits from an earlier run. Start (and leave) each user at zero.
	 *
	 * @param int[] $users User IDs.
	 */
	private function reset_ledger( array $users ): void {
		global $wpdb;
		foreach ( $users as $user ) {
			$wpdb->delete( \Wbcom\Credits\Ledger::table_name( 'listora' ), array( 'user_id' => (int) $user ) );
		}
	}

	private function submit(): WP_REST_Response {
		$types = array_keys( \WBListora\Core\Listing_Type_Registry::instance()->get_all() );
		$type  = $types[0] ?? '';
		$req   = new WP_REST_Request( 'POST', '/listora/v1/submit' );
		$req->set_header( 'content-type', 'application/json' );
		$req->set_body( (string) wp_json_encode( array( 'title' => 'Order test', 'listing_type' => $type, 'type' => $type, 'description' => 'x', 'status' => 'publish', 'agree_terms' => true ) ) );
		return rest_do_request( $req );
	}

	public function tear_down() {
		$this->reset_ledger( array( $this->member ) );
		wb_listora_get_setting( null, null, true );
		parent::tear_down();
	}

	public function test_a_short_balance_is_refused_before_anything_exists() {
		$response = $this->submit();

		$this->assertSame( 402, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertNotContains( 'wb_listora_listing_submitted', $this->seen );
		$this->assertNotContains( 'wb_listora_after_create_listing', $this->seen );
	}

	/**
	 * The balance passes the early check and then drops before the charge (another spend
	 * lands in between). Before 2.1.1 the announcement had already fired by then.
	 */
	public function test_a_charge_that_fails_after_the_early_check_announces_nothing() {
		\Wbcom\Credits\Credits::topup_money( 'wb-listora', $this->member, 20 );
		add_action(
			'wb_listora_listing_payment_due',
			function () {
				\Wbcom\Credits\Credits::adjust_money( 'wb-listora', $this->member, -20, '', 'Concurrent spend' );
			},
			5
		);

		$response = $this->submit();

		$this->assertSame( 402, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( array( 'wb_listora_listing_payment_due' ), $this->seen, 'The payment step ran and nothing was announced.' );
		$this->assertSame( 'draft', get_post_status( (int) ( $response->get_data()['data']['listing_id'] ?? 0 ) ) );
	}

	public function test_a_paid_submission_announces_after_the_payment_step() {
		\Wbcom\Credits\Credits::topup_money( 'wb-listora', $this->member, 20 );

		$response = $this->submit();

		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame(
			array( 'wb_listora_listing_payment_due', 'wb_listora_listing_submitted', 'wb_listora_after_create_listing' ),
			$this->seen
		);
	}
}
