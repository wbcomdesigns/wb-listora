<?php
/**
 * "Open now" is one rule for search and the listing page badge.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Business_Hours;

/**
 * The badge kept only the last slot of a day and counted closing time as open,
 * so it disagreed with the search filter (UX audit 2026-10-10).
 *
 * @group listora
 * @group hours
 */
class OpenNowTest extends WP_UnitTestCase {

	/**
	 * Write one hours row in the site timezone, relative to now.
	 *
	 * @param int $id    Listing ID.
	 * @param int $slot  Slot number for the day.
	 * @param int $open  Seconds from now the slot opens (negative = already open).
	 * @param int $close Seconds from now the slot closes.
	 */
	private function slot( $id, $slot, $open, $close ) {
		global $wpdb;
		$now = new \DateTimeImmutable( 'now', wp_timezone() );
		$wpdb->insert(
			$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'hours',
			array(
				'listing_id'  => $id,
				'day_of_week' => (int) $now->format( 'w' ),
				'slot'        => $slot,
				'open_time'   => $now->modify( "{$open} seconds" )->format( 'H:i:s' ),
				'close_time'  => $now->modify( "{$close} seconds" )->format( 'H:i:s' ),
				'is_closed'   => 0,
				'is_24h'      => 0,
				'timezone'    => '',
			)
		);
	}

	public function test_open_in_first_of_two_slots_and_closed_at_closing_time() {
		// Put the site clock at about midday so the relative slots stay on today.
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', 12 - (int) gmdate( 'G' ) );

		$split = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		$this->slot( $split, 0, -3600, 3600 );
		$this->slot( $split, 1, 10800, 18000 );

		$closing = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		$this->slot( $closing, 0, -3600, 0 );

		$this->assertSame( array( $split ), Business_Hours::open_now_ids( array( $split, $closing ) ) );
	}
}
