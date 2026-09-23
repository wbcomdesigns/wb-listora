<?php
/**
 * The space moderation queue is bounded.
 *
 * Card 10314572968: `GET /spaces/{id}/listings/pending` returned every pending
 * row a space had ever accumulated - 103 on the site where it was found - and
 * hydrated a card for each in one response, with no way for a client to ask for
 * fewer and no total to page against.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   spaces
 */

namespace WBListora\Tests\Integration;

use WBListora\Core\Space_Listings_Model;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group spaces
 */
class SpacePendingQueuePaginationTest extends WP_UnitTestCase {

	const SPACE_ID = 7788;

	/**
	 * Seeded listing IDs, newest first.
	 *
	 * @var int[]
	 */
	private $listings = array();

	public function set_up() {
		parent::set_up();

		add_filter( 'wb_listora_user_can_moderate_space', '__return_true' );
		add_filter( 'wb_listora_user_can_view_space', '__return_true' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		for ( $i = 0; $i < 25; $i++ ) {
			$listing = self::factory()->post->create(
				array(
					'post_type'   => 'listora_listing',
					'post_status' => 'publish',
				)
			);
			Space_Listings_Model::submit( self::SPACE_ID, $listing, 1 );
			$this->listings[] = $listing;
		}

		rest_get_server();
	}

	public function tear_down() {
		remove_filter( 'wb_listora_user_can_moderate_space', '__return_true' );
		remove_filter( 'wb_listora_user_can_view_space', '__return_true' );
		parent::tear_down();
	}

	/**
	 * @param array<string,int> $params Query params.
	 * @return \WP_REST_Response
	 */
	private function queue( array $params = array() ) {
		$request = new WP_REST_Request( 'GET', '/listora/v1/spaces/' . self::SPACE_ID . '/listings/pending' );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * The defect: 25 rows came back in one response.
	 */
	public function test_the_queue_is_bounded_by_default(): void {
		$response = $this->queue();

		$this->assertLessThanOrEqual( 20, count( $response->get_data() ) );
	}

	public function test_it_reports_the_true_total_and_page_count(): void {
		$headers = $this->queue( array( 'per_page' => 20 ) )->get_headers();

		$this->assertSame( 25, (int) $headers['X-WP-Total'], 'The total must count every pending row, not the page.' );
		$this->assertSame( 2, (int) $headers['X-WP-TotalPages'] );
	}

	public function test_the_last_page_returns_the_remainder(): void {
		$this->assertCount( 5, $this->queue( array( 'page' => 2, 'per_page' => 20 ) )->get_data() );
	}

	public function test_page_size_is_honoured(): void {
		$response = $this->queue( array( 'per_page' => 5 ) );

		$this->assertCount( 5, $response->get_data() );
		$this->assertSame( 5, (int) $response->get_headers()['X-WP-TotalPages'] );
	}

	/**
	 * Pages must not overlap, or a curator moderates the same submission twice
	 * and never reaches the ones the duplicate displaced.
	 */
	public function test_pages_do_not_overlap(): void {
		$first  = wp_list_pluck( wp_list_pluck( $this->queue( array( 'page' => 1, 'per_page' => 10 ) )->get_data(), 'listing' ), 'id' );
		$second = wp_list_pluck( wp_list_pluck( $this->queue( array( 'page' => 2, 'per_page' => 10 ) )->get_data(), 'listing' ), 'id' );

		$this->assertCount( 10, $first );
		$this->assertCount( 10, $second );
		$this->assertEmpty( array_intersect( $first, $second ) );
	}

	/**
	 * An empty page past the end still has to say how many there are, or a
	 * client cannot tell "nothing here" from "nothing at all".
	 */
	public function test_a_page_past_the_end_still_reports_the_total(): void {
		$response = $this->queue( array( 'page' => 99, 'per_page' => 20 ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
		$this->assertSame( 25, (int) $response->get_headers()['X-WP-Total'] );
	}

	/**
	 * The unbounded model call is still available for the internal callers
	 * that count rather than render.
	 */
	public function test_the_model_still_supports_an_unbounded_read(): void {
		$this->assertCount( 25, Space_Listings_Model::pending( self::SPACE_ID ) );
	}
}
