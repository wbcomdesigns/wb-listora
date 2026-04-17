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
use WBListora\Core\Listing_Type_Registry;

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
	 * G10 regression — /listora/v1/listing-types/{slug}/categories must
	 * return 200 + array for any registered type. Guards against regressions
	 * that would break the category dropdown on the submission form.
	 *
	 * The test picks the first actually-registered type so it's tolerant of
	 * environments where the default bundle didn't seed (bootstrap ordering,
	 * taxonomy terms dropped between tests, etc.) — the endpoint itself is
	 * still the thing under test.
	 */
	public function test_categories_endpoint_returns_array_for_registered_type() {
		$registry = Listing_Type_Registry::instance();
		$all      = $registry->get_all();

		if ( empty( $all ) ) {
			$this->markTestSkipped( 'No listing types registered in this test env; endpoint can only be 404 here.' );
		}

		$first_type = reset( $all );
		$slug       = $first_type->get_slug();

		$request  = new WP_REST_Request( 'GET', '/listora/v1/listing-types/' . $slug . '/categories' );
		$response = $this->server->dispatch( $request );

		$this->assertSame(
			200,
			$response->get_status(),
			sprintf( 'Categories endpoint must return 200 for registered type "%s".', $slug )
		);

		$data = $response->get_data();
		$this->assertIsArray( $data );

		if ( ! empty( $data ) ) {
			$this->assertArrayHasKey( 'id', $data[0] );
			$this->assertArrayHasKey( 'name', $data[0] );
			$this->assertArrayHasKey( 'slug', $data[0] );
		}
	}

	/**
	 * Unknown slug must 404 — catches any accidental "silently return all"
	 * regression on the REST route.
	 */
	public function test_categories_endpoint_404s_for_unknown_type() {
		$request  = new WP_REST_Request( 'GET', '/listora/v1/listing-types/not-a-real-type-slug-xyz/categories' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}
}
