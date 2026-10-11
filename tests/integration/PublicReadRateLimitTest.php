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

	private function read( $route ) {
		return Rate_Limiter::limit_public_reads( null, rest_get_server(), new WP_REST_Request( 'GET', $route ) );
	}

	public function test_guest_is_limited_on_listing_routes_only() {
		wp_set_current_user( 0 );
		$this->assertNull( $this->read( '/listora/v1/listings/5' ) );
		$this->assertNull( $this->read( '/listora/v1/search' ) );
		$this->assertWPError( $this->read( '/listora/v1/listings/5/detail' ) );
		$this->assertNull( $this->read( '/listora/v1/search/suggest' ), 'Typeahead is not counted.' );
	}

	public function test_signed_in_member_is_not_limited() {
		wp_set_current_user( self::factory()->user->create() );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertNull( $this->read( '/listora/v1/listings' ) );
		}
	}
}
