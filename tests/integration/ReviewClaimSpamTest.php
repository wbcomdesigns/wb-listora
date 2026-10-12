<?php
/**
 * Reviews, replies and claims run the shared spam check.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ReviewClaimSpamTest extends WP_UnitTestCase {

	public function test_member_text_runs_keyword_and_link_checks_per_source() {
		wp_set_current_user( self::factory()->user->create() );

		$this->assertWPError( \WBListora\Anti_Spam::check_member_text( 'review', 'Great', 'Visit my casino site' ) );

		$three_links = 'a https://a.example b https://b.example c https://c.example';
		$this->assertWPError( \WBListora\Anti_Spam::check_member_text( 'review', '', $three_links ), 'Reviews allow 2 links.' );
		$this->assertTrue( \WBListora\Anti_Spam::check_member_text( 'claim', '', $three_links ), 'Claims allow 3.' );
		$this->assertTrue( \WBListora\Anti_Spam::check_member_text( 'review', 'Lovely', 'Friendly staff and great coffee.' ) );
	}

	public function test_review_route_refuses_spam() {
		do_action( 'rest_api_init' );
		wp_set_current_user( self::factory()->user->create() );
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );

		$request = new WP_REST_Request( 'POST', '/listora/v1/listings/' . $listing . '/reviews' );
		$request->set_param( 'overall_rating', 5 );
		$request->set_param( 'title', 'Best place' );
		$request->set_param( 'content', 'casino viagra https://a.example https://b.example https://c.example https://d.example https://e.example' );
		$response = rest_do_request( $request );

		$this->assertGreaterThanOrEqual( 400, $response->get_status(), 'The card\'s spam review is refused.' );
	}
}
