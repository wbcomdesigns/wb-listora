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
}
