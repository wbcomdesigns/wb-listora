<?php
/**
 * Admin "Mark verified" pays the submission cost like the member's own verify link:
 * a covered cost is held, a short balance drops the listing to a draft.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group credits
 */
class MarkVerifiedChargesCostTest extends WP_UnitTestCase {

	private int $author = 0;

	public function set_up() {
		parent::set_up();
		if ( ! wb_listora_credits_ready() ) {
			$this->markTestSkipped( 'The credits library is not ready.' );
		}
		$this->author = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		global $wpdb;
		$wpdb->delete( \Wbcom\Credits\Ledger::table_name( 'listora' ), array( 'user_id' => $this->author ) );
		update_option( 'wb_listora_settings', array_merge( (array) get_option( 'wb_listora_settings', array() ), array( 'default_listing_credit_cost' => 5 ) ) );
		wb_listora_get_setting( null, null, true );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down() {
		global $wpdb;
		$wpdb->delete( \Wbcom\Credits\Ledger::table_name( 'listora' ), array( 'user_id' => $this->author ) );
		\Wbcom\Credits\Credits::invalidate_cache( WB_LISTORA_CREDITS_SLUG, $this->author );
		$_GET = array();
		wb_listora_get_setting( null, null, true );
		parent::tear_down();
	}

	private function mark_verified(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'pending_verification', 'post_author' => $this->author ) );
		$_GET = array( 'post' => $id, '_wpnonce' => wp_create_nonce( 'listora_mark_verified_' . $id ) );
		$stop = static function () {
			throw new RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $stop );
		try {
			( new \WBListora\Admin\Listing_Columns() )->handle_mark_verified();
		} catch ( RuntimeException $e ) {
			// The handler redirects and exits; the filter stands in for the exit.
		}
		remove_filter( 'wp_redirect', $stop );
		$this->listing = $id;
	}

	private int $listing = 0;

	public function test_a_short_balance_leaves_the_listing_a_draft() {
		$this->mark_verified();

		$this->assertSame( 'draft', get_post_status( $this->listing ) );
	}

	public function test_a_covered_cost_is_charged_and_the_listing_moves_on() {
		\Wbcom\Credits\Credits::topup_money( WB_LISTORA_CREDITS_SLUG, $this->author, 50.0, '', 'test' );
		$this->mark_verified();

		$this->assertContains( get_post_status( $this->listing ), array( 'pending', 'publish' ) );
		$rows = \Wbcom\Credits\Credits::query_ledger( WB_LISTORA_CREDITS_SLUG, array( 'user_id' => $this->author, 'item_id' => $this->listing ) );
		$this->assertNotEmpty( $rows, 'the submission cost was held or deducted for this listing' );
	}
}
