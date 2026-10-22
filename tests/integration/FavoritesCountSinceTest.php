<?php
/**
 * The Analytics favourites figure is the number of favourites rows in the period.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class FavoritesCountSinceTest extends WP_UnitTestCase {

	private function favourite( int $days_ago ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'favorites',
			array(
				'user_id'    => self::factory()->user->create(),
				'listing_id' => self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) ),
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS ),
			)
		);
	}

	public function test_it_counts_only_the_period_and_matches_the_table() {
		$before = array(
			7  => wb_listora_count_favorites_since( gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ),
			30 => wb_listora_count_favorites_since( gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ),
		);

		$this->favourite( 2 );   // In both windows.
		$this->favourite( 20 );  // Only the 30-day window.
		$this->favourite( 60 );  // Neither.

		$this->assertSame( $before[7] + 1, wb_listora_count_favorites_since( gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
		$this->assertSame( $before[30] + 2, wb_listora_count_favorites_since( gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
	}
}
