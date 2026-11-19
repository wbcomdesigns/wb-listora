<?php
/**
 * A member's Featured upgrade is allowed by one function, so the button and the route agree.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group credits
 */
class FeaturedMemberGateTest extends WP_UnitTestCase {

	private int $owner   = 0;
	private int $listing = 0;
	private $settings;

	public function set_up() {
		parent::set_up();
		$this->settings = get_option( 'wb_listora_settings', null );
		$current        = is_array( $this->settings ) ? $this->settings : array();
		update_option( 'wb_listora_settings', array_merge( $current, array( 'featured_credit_cost' => 5 ) ) );

		$this->owner   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_author' => $this->owner,
			)
		);
	}

	public function tear_down() {
		if ( null === $this->settings ) {
			delete_option( 'wb_listora_settings' );
		} else {
			update_option( 'wb_listora_settings', $this->settings );
		}
		remove_all_filters( 'wb_listora_member_can_feature' );
		parent::tear_down();
	}

	private function feature_as( int $user ) {
		wp_set_current_user( $user );
		$controller = new \WBListora\REST\Listings_Controller();
		$request    = new WP_REST_Request( 'POST', '/listora/v1/listings/' . $this->listing . '/feature' );
		$request->set_param( 'id', $this->listing );
		return $controller->feature_listing( $request );
	}

	public function test_a_member_may_feature_by_default() {
		$this->assertTrue( wb_listora_member_can_feature( $this->listing, $this->owner ) );
	}

	public function test_the_filter_can_close_the_upgrade() {
		add_filter( 'wb_listora_member_can_feature', '__return_false' );
		$this->assertFalse( wb_listora_member_can_feature( $this->listing, $this->owner ) );
	}

	public function test_staff_are_not_asked() {
		add_filter( 'wb_listora_member_can_feature', '__return_false' );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertTrue( wb_listora_member_can_feature( $this->listing, $admin ) );
	}

	public function test_the_route_refuses_when_the_upgrade_is_closed() {
		add_filter( 'wb_listora_member_can_feature', '__return_false' );

		$result = $this->feature_as( $this->owner );

		$this->assertWPError( $result );
		$this->assertSame( 'listora_feature_unavailable', $result->get_error_code() );
		$this->assertFalse( \WBListora\Core\Featured::is_featured( $this->listing ) );
	}

	public function test_the_route_is_not_refused_by_the_gate_when_it_is_open() {
		$result = $this->feature_as( $this->owner );

		// No credits here, so it stops at the balance, not at the gate.
		$this->assertWPError( $result );
		$this->assertNotSame( 'listora_feature_unavailable', $result->get_error_code() );
	}
}
