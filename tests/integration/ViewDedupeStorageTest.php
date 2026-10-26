<?php
/**
 * Counting a listing view dedupes per visitor without writing options rows.
 *
 * @package WBListora\Tests
 */

use WBListora\Features\Analytics_Lite;

/**
 * @group listora
 */
class ViewDedupeStorageTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		unset( $_COOKIE[ Analytics_Lite::VIEWED_COOKIE ] );
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'The cookie path runs only without a persistent object cache.' );
		}
	}

	public function tear_down(): void {
		unset( $_COOKIE[ Analytics_Lite::VIEWED_COOKIE ] );
		parent::tear_down();
	}

	private function options_rows(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function recorded( int $listing_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(count),0) FROM {$wpdb->prefix}listora_analytics WHERE listing_id = %d AND event_type = %s", $listing_id, Analytics_Lite::EVENT_VIEW ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function test_fifty_listings_for_three_visitors_write_no_options_rows() {
		if ( Analytics_Lite::pro_owns_recording() ) {
			$this->markTestSkipped( 'Pro owns recording on this site.' );
		}
		$listings = self::factory()->post->create_many( 50, array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		$before   = $this->options_rows();

		for ( $visitor = 0; $visitor < 3; $visitor++ ) {
			unset( $_COOKIE[ Analytics_Lite::VIEWED_COOKIE ] ); // A new browser.
			foreach ( $listings as $id ) {
				Analytics_Lite::record_view( $id );
				Analytics_Lite::record_view( $id ); // Reload: same browser, same hour.
			}
		}

		$this->assertSame( $before, $this->options_rows(), 'No options rows per visitor.' );
		$this->assertSame( 3, $this->recorded( $listings[0] ), 'Three browsers, one count each, however often they reload.' );
	}
}
