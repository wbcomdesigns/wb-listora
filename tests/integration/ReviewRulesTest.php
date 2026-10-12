<?php
/**
 * One review gate and one content rule for every route that acts on a review.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group rest-api
 */
class ReviewRulesTest extends WP_UnitTestCase {

	private int $author;
	private int $member;
	private int $review;

	public function set_up(): void {
		parent::set_up();
		add_filter( 'wb_listora_rate_limit_bypass', '__return_true' );
		do_action( 'rest_api_init' );

		$owner        = self::factory()->user->create();
		$this->author = self::factory()->user->create();
		$this->member = self::factory()->user->create();
		$listing      = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $owner,
			)
		);

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews',
			array(
				'listing_id'     => $listing,
				'user_id'        => $this->author,
				'overall_rating' => 4,
				'title'          => 'Good',
				'content'        => 'A long enough review text here.',
				'status'         => 'pending',
				'created_at'     => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			)
		);
		$this->review = (int) $wpdb->insert_id;
	}

	private function post( string $path, array $params = array() ) {
		$request = new WP_REST_Request( 'POST', '/listora/v1' . $path );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	public function test_votes_and_reports_wait_until_the_review_is_published() {
		wp_set_current_user( $this->member );
		$this->assertSame( 'listora_review_not_approved', $this->post( "/reviews/{$this->review}/helpful" )->get_data()['code'] );
		$this->assertSame( 'listora_review_not_approved', $this->post( "/reviews/{$this->review}/report", array( 'reason' => 'spam' ) )->get_data()['code'] );
		$this->assertSame( 404, $this->post( '/reviews/999999/helpful' )->get_status(), 'A missing review takes no vote.' );

		global $wpdb;
		$wpdb->update( $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews', array( 'status' => 'approved' ), array( 'id' => $this->review ) );
		$this->assertSame( 200, $this->post( "/reviews/{$this->review}/helpful" )->get_status() );
	}

	public function test_edit_keeps_the_minimum_length() {
		wp_set_current_user( $this->author );
		$response = $this->post( "/reviews/{$this->review}", array( 'content' => 'x' ) );
		$this->assertSame( 'listora_review_too_short', $response->get_data()['code'] );
		$this->assertSame( 200, $this->post( "/reviews/{$this->review}", array( 'content' => 'Still a long enough review.' ) )->get_status() );
	}
}
