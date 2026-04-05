<?php
/**
 * Integration tests for WB Listora REST API endpoints.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 */

namespace WBListora\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group rest-api
 */
class RestApiTest extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var \WP_REST_Server
	 */
	private $server;

	/**
	 * Set up the REST server before each test.
	 */
	public function set_up() {
		parent::set_up();

		// Ensure REST server is available.
		global $wp_rest_server;

		$this->server = rest_get_server();

		// Re-dispatch rest_api_init to ensure our routes are registered.
		do_action( 'rest_api_init', $this->server );
	}

	/**
	 * GET /listora/v1/search should return HTTP 200.
	 */
	public function test_search_endpoint_returns_200() {
		$request  = new WP_REST_Request( 'GET', '/listora/v1/search' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Search endpoint should return 200.' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'listings', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'pages', $data );
	}

	/**
	 * GET /listora/v1/favorites without authentication should return 401.
	 */
	public function test_favorites_endpoint_requires_authentication() {
		// Ensure no user is logged in.
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/listora/v1/favorites' );
		$response = $this->server->dispatch( $request );

		$this->assertSame(
			401,
			$response->get_status(),
			'Favorites endpoint should require authentication and return 401.'
		);
	}

	/**
	 * GET /listora/v1/listing-types should return 200 with an array.
	 */
	public function test_listing_types_endpoint_returns_types() {
		$request  = new WP_REST_Request( 'GET', '/listora/v1/listing-types' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Listing types endpoint should return 200.' );

		$data = $response->get_data();
		$this->assertIsArray( $data, 'Response should be an array of listing types.' );

		// If types are registered (via plugin activation/defaults), verify structure.
		if ( ! empty( $data ) ) {
			$first = $data[0];
			$this->assertArrayHasKey( 'slug', $first );
			$this->assertArrayHasKey( 'name', $first );
		}
	}
}
