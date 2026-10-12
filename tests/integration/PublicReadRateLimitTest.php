<?php
/**
 * Logged-out reads of listing data are rate-limited; members are not.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;
use WBListora\Rate_Limiter;

/**
 * @group listora
 * @group rest-api
 */
class PublicReadRateLimitTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		add_filter(
			'wb_listora_rate_limit_config',
			static function ( $config, $action ) {
				return 'public_read' === $action ? array_merge( $config, array( 'ip_max' => 2 ) ) : $config;
			},
			10,
			2
		);
		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 250 );
	}

	private function read( $object, $method ) {
		return Rate_Limiter::limit_public_reads( null, array( 'callback' => array( $object, $method ) ), new WP_REST_Request( 'GET', '/x' ) );
	}

	public function test_guest_is_limited_on_listing_handlers_only() {
		wp_set_current_user( 0 );
		$listings = new \WBListora\REST\Listings_Controller( 'listora_listing' );
		$search   = new \WBListora\REST\Search_Controller();
		$this->assertNull( $this->read( $listings, 'get_item' ) );
		$this->assertNull( $this->read( $search, 'search' ) );
		$this->assertWPError( $this->read( $listings, 'get_listing' ), 'Third read in the window is refused, whatever the URL spelling.' );
		$this->assertNull( $this->read( $search, 'suggest' ), 'Typeahead is not counted.' );
	}

	public function test_signed_in_member_is_not_limited() {
		wp_set_current_user( self::factory()->user->create() );
		$listings = new \WBListora\REST\Listings_Controller( 'listora_listing' );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertNull( $this->read( $listings, 'get_items' ) );
		}
	}

	public function test_refusal_says_when_to_retry_and_does_not_move_the_window() {
		wp_set_current_user( 0 );
		$listings = new \WBListora\REST\Listings_Controller( 'listora_listing' );
		$this->read( $listings, 'get_item' );
		$this->read( $listings, 'get_item' );
		$first = $this->read( $listings, 'get_item' );
		$this->assertWPError( $first );
		$wait = (int) $first->get_error_data()['retry_after'];
		$this->assertGreaterThan( 0, $wait );
		$this->assertLessThanOrEqual( MINUTE_IN_SECONDS, $wait );
		$again = $this->read( $listings, 'get_item' );
		$this->assertLessThanOrEqual( $wait, (int) $again->get_error_data()['retry_after'], 'A refused read does not extend the block.' );

		$response = \WBListora\Rate_Limiter::retry_after_header( rest_convert_error_to_response( $first ) );
		$this->assertSame( (string) $wait, $response->get_headers()['Retry-After'] );
	}
}
