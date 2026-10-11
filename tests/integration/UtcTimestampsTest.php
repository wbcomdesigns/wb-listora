<?php
/**
 * Timestamps written to plugin tables and the renewal stamp are UTC on any site timezone.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Space_Listings_Model;

/**
 * @group listora
 */
class UtcTimestampsTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'Pacific/Kiritimati' ); // UTC+14: a site-time write would be 14 hours off.
	}

	public function test_space_submission_time_is_utc() {
		global $wpdb;
		$space   = self::factory()->post->create();
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		Space_Listings_Model::submit( $space, $listing, 1 );

		$created = (string) $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$wpdb->prefix}listora_space_listings WHERE listing_id = %d", $listing ) );
		$this->assertEqualsWithDelta( time(), strtotime( $created . ' UTC' ), 60 );
	}
}
