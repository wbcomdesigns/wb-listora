<?php
/**
 * Expiry off keeps listings up, but end-dated listings still end.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Listing_Type_Defaults;
use WBListora\Core\Listing_Type_Registry;
use WBListora\Workflow\Expiration_Cron;
use WBListora\Workflow\Status_Manager;

/**
 * @group listora
 */
class ExpirationSwitchTest extends WP_UnitTestCase {

	/**
	 * A listing past its date, of the given type.
	 *
	 * @param string $type Type slug.
	 * @return int
	 */
	private function past_listing( string $type ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $id, $type, 'listora_listing_type' );
		update_post_meta( $id, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
		return $id;
	}

	public function test_off_keeps_listings_but_ends_events_and_filter_turns_it_on() {
		$defaults = Listing_Type_Defaults::get_all();
		Listing_Type_Registry::instance()->save_type( 'qa-business', $defaults['business'] );
		Listing_Type_Registry::instance()->save_type( 'qa-event', $defaults['event'] );
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();

		$this->assertFalse( wb_listora_expiration_enabled(), 'A fresh install has expiry off.' );

		$business = $this->past_listing( 'qa-business' );
		$event    = $this->past_listing( 'qa-event' );
		( new Expiration_Cron() )->check_expirations();

		$this->assertSame( 'publish', get_post_status( $business ) );
		$this->assertSame( 'listora_expired', get_post_status( $event ) );

		add_filter( 'wb_listora_expiration_enabled', '__return_true' );
		( new Expiration_Cron() )->check_expirations();
		$this->assertSame( 'listora_expired', get_post_status( $business ) );
		remove_filter( 'wb_listora_expiration_enabled', '__return_true' );
	}

	/**
	 * Register the shipped classified type (60-day duration) and return a draft of it.
	 */
	private function classified_draft(): int {
		$defaults = Listing_Type_Defaults::get_all();
		Listing_Type_Registry::instance()->save_type( 'qa-classified', $defaults['classified'] );
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'draft',
			)
		);
		wp_set_object_terms( $id, 'qa-classified', 'listora_listing_type' );
		return $id;
	}

	public function test_free_directory_stamps_no_duration_expiry_and_shows_none() {
		$id = $this->classified_draft();
		wp_publish_post( $id );

		$this->assertSame( '', get_post_meta( $id, '_listora_expiration_date', true ), 'Expiry off: no 60-day date on a classified listing.' );

		update_post_meta( $id, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS ) );
		$this->assertFalse( Status_Manager::listing_expires( $id ) );
		$this->assertSame( 0, Status_Manager::expiry_timestamp( $id ), 'A date stored before 2.0.0 is not shown as a countdown.' );
	}

	public function test_paid_directory_stamps_the_type_duration() {
		add_filter( 'wb_listora_expiration_enabled', '__return_true' );
		$id = $this->classified_draft();
		wp_publish_post( $id );

		$left = Status_Manager::expiry_timestamp( $id ) - time();
		$this->assertGreaterThan( 59 * DAY_IN_SECONDS, $left );
		$this->assertLessThanOrEqual( 60 * DAY_IN_SECONDS, $left );
		remove_filter( 'wb_listora_expiration_enabled', '__return_true' );
	}

	public function test_expired_listing_moved_back_to_published_gets_a_fresh_date() {
		add_filter( 'wb_listora_expiration_enabled', '__return_true' );
		$id = $this->classified_draft();
		wp_publish_post( $id );
		update_post_meta( $id, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS ) );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'listora_expired' ) );

		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertGreaterThan( time() + 59 * DAY_IN_SECONDS, Status_Manager::expiry_timestamp( $id ), 'Reinstating recomputes, so the next cron run does not expire it again.' );
		remove_filter( 'wb_listora_expiration_enabled', '__return_true' );
	}

	public function test_expiry_timestamp_reads_the_stored_date_as_utc_on_any_site_timezone() {
		add_filter( 'wb_listora_expiration_enabled', '__return_true' );
		update_option( 'timezone_string', 'Pacific/Kiritimati' ); // UTC+14.
		$id = $this->classified_draft();
		update_post_meta( $id, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );

		$this->assertEqualsWithDelta( time() + HOUR_IN_SECONDS, Status_Manager::expiry_timestamp( $id ), 5 );
		remove_filter( 'wb_listora_expiration_enabled', '__return_true' );
	}

	public function test_reinstating_a_lifetime_listing_drops_its_past_date() {
		add_filter( 'wb_listora_expiration_enabled', '__return_true' );
		$defaults = Listing_Type_Defaults::get_all();
		$restaurant = $defaults['restaurant'];
		$restaurant['expiration_days'] = 0;
		Listing_Type_Registry::instance()->save_type( 'qa-lifetime', $restaurant );
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();
		$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		wp_set_object_terms( $id, 'qa-lifetime', 'listora_listing_type' );
		update_post_meta( $id, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS ) );
		update_post_meta( $id, '_listora_expiry_reminded_7d', 1 );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'listora_expired' ) );

		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );

		$this->assertSame( '', get_post_meta( $id, '_listora_expiration_date', true ), 'Lifetime: no past date left for the cron to act on.' );
		$this->assertSame( '', get_post_meta( $id, '_listora_expiry_reminded_7d', true ) );
		remove_filter( 'wb_listora_expiration_enabled', '__return_true' );
	}

	public function test_renewal_only_for_listings_that_expire_after_a_duration() {
		do_action( 'rest_api_init' );
		$owner = self::factory()->user->create();
		wp_set_current_user( $owner );
		$defaults = Listing_Type_Defaults::get_all();
		Listing_Type_Registry::instance()->save_type( 'qa-classified', $defaults['classified'] );
		Listing_Type_Registry::instance()->save_type( 'qa-event', $defaults['event'] );
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();
		$make = function ( string $type ) use ( $owner ) {
			$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $owner ) );
			wp_set_object_terms( $id, $type, 'listora_listing_type' );
			update_post_meta( $id, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS ) );
			return $id;
		};
		$quote = function ( int $id ) {
			return rest_do_request( new \WP_REST_Request( 'GET', '/listora/v1/listings/' . $id . '/renewal-quote' ) )->get_data();
		};

		$classified = $make( 'qa-classified' );
		$this->assertFalse( (bool) $quote( $classified )['can_renew_now'], 'Free directory: nothing to renew.' );

		add_filter( 'wb_listora_expiration_enabled', '__return_true' );
		$this->assertTrue( (bool) $quote( $classified )['can_renew_now'], 'Paid directory, 3 days left: renewable.' );
		$this->assertFalse( (bool) $quote( $make( 'qa-event' ) )['can_renew_now'], 'An event ends on its own date.' );
		remove_filter( 'wb_listora_expiration_enabled', '__return_true' );
	}
}

