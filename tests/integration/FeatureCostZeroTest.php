<?php
/**
 * With no Featured upgrade cost set, owners cannot feature their own listing.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class FeatureCostZeroTest extends WP_UnitTestCase {

	private function feature_as( int $user_id, int $listing_id ): WP_REST_Response {
		wp_set_current_user( $user_id );
		return rest_do_request( new WP_REST_Request( 'POST', '/listora/v1/listings/' . $listing_id . '/feature' ) );
	}

	public function test_owner_is_refused_and_staff_can_feature_at_cost_zero() {
		$settings                         = (array) get_option( 'wb_listora_settings', array() );
		$settings['featured_credit_cost'] = 0;
		update_option( 'wb_listora_settings', $settings );

		$owner   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $owner ) );

		$res = $this->feature_as( $owner, $listing );
		$this->assertSame( 403, $res->get_status(), 'A free self-upgrade let every owner pin their listing to the top.' );
		$this->assertSame( 'listora_feature_unavailable', $res->as_error()->get_error_code() );
		$this->assertFalse( \WBListora\Core\Featured::is_featured( $listing ) );

		$this->assertSame( 200, $this->feature_as( $admin, $listing )->get_status() );
		$this->assertTrue( \WBListora\Core\Featured::is_featured( $listing ) );
	}
}
