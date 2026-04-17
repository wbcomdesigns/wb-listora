<?php
/**
 * Regression test for the categories-by-type REST endpoint (G10).
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   rest-api
 */

namespace WBListora\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group rest-api
 */
class ListingTypesCategoriesRestTest extends WP_UnitTestCase {

	/**
	 * @var \WP_REST_Server
	 */
	private $server;

	public function set_up(): void {
		parent::set_up();
		$this->server = rest_get_server();
		do_action( 'rest_api_init', $this->server );
	}

	/**
	 * G10 regression — the view.js selectSubmissionType action relies on
	 * /listora/v1/listing-types/{slug}/categories returning an array to
	 * populate the Category dropdown. The endpoint must exist AND return
	 * a JSON array for a registered type.
	 */
	public function test_categories_endpoint_returns_array_for_registered_type() {
		$request  = new WP_REST_Request( 'GET', '/listora/v1/listing-types/restaurant/categories' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Categories endpoint must return 200 for a registered type.' );

		$data = $response->get_data();
		$this->assertIsArray( $data, 'Response body must be an array.' );

		if ( ! empty( $data ) ) {
			$first = $data[0];
			$this->assertArrayHasKey( 'id', $first );
			$this->assertArrayHasKey( 'name', $first );
			$this->assertArrayHasKey( 'slug', $first );
		}
	}

	/**
	 * Unknown type slug returns a 404 rather than silently returning all
	 * listings' categories.
	 */
	public function test_categories_endpoint_404s_for_unknown_type() {
		$request  = new WP_REST_Request( 'GET', '/listora/v1/listing-types/not-a-real-type-slug/categories' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}
}
