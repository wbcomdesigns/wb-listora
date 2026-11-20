<?php
/**
 * A listing's title reads the same from every REST list: HTML entities decoded, as /search returns it.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class RestTitlesDecodedTest extends WP_UnitTestCase {

	public function test_favorites_and_dashboard_titles_match_search() {
		global $wpdb;
		$member  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $member,
				'post_title'  => 'Fish &amp; Chips',
			)
		);
		$wpdb->insert(
			$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'favorites',
			array(
				'user_id'    => $member,
				'listing_id' => $listing,
				'collection' => 'default',
				'created_at' => current_time( 'mysql', true ),
			)
		);
		wp_set_current_user( $member );

		$favorites = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/favorites' ) )->get_data();
		$dashboard = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/dashboard/listings' ) )->get_data();

		$this->assertSame( 'Fish & Chips', $favorites['favorites'][0]['title'] );
		$dash_rows = $dashboard['listings'] ?? $dashboard['items'] ?? $dashboard;
		$this->assertSame( 'Fish & Chips', $dash_rows[0]['title'] );
	}
}
