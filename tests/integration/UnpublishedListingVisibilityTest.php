<?php
/**
 * An unpublished listing is not found by strangers on any public read route.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group rest
 */
class UnpublishedListingVisibilityTest extends WP_UnitTestCase {

	private function status( string $path ): int {
		add_filter( 'wb_listora_rate_limit_bypass', '__return_true' );
		return rest_do_request( new WP_REST_Request( 'GET', $path ) )->get_status();
	}

	public function test_visitor_gets_404_even_when_the_listing_has_no_author() {
		// Imported listings can have author 0, which is also a visitor's id.
		foreach ( array( 0, self::factory()->user->create() ) as $author ) {
			$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'pending', 'post_author' => $author ) );
			wp_set_current_user( 0 );
			$this->assertSame( 404, $this->status( "/listora/v1/listings/$id/detail" ), "detail, author $author" );
			$this->assertSame( 404, $this->status( "/listora/v1/listings/$id/related" ), "related, author $author" );
		}
	}

	public function test_another_member_gets_404_and_the_author_does_not() {
		$author = self::factory()->user->create();
		$id     = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'draft', 'post_author' => $author ) );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertSame( 404, $this->status( "/listora/v1/listings/$id/related" ) );

		wp_set_current_user( $author );
		$this->assertSame( 200, $this->status( "/listora/v1/listings/$id/related" ) );
	}

	public function test_duplicate_check_and_contact_form_ignore_other_members_unpublished_listings() {
		add_filter( 'wb_listora_rate_limit_bypass', '__return_true' );
		$owner   = self::factory()->user->create();
		$pending = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'pending', 'post_author' => $owner, 'post_title' => 'Secret Riverside Bakery' ) );

		wp_set_current_user( self::factory()->user->create() );
		$check = new WP_REST_Request( 'POST', '/listora/v1/submit/check-duplicate' );
		$check->set_body_params( array( 'title' => 'Secret Riverside Bakery' ) );
		$this->assertFalse( rest_do_request( $check )->get_data()['has_match'], "Another member's pending title is not revealed." );

		$contact = new WP_REST_Request( 'POST', "/listora/v1/listings/$pending/contact-form" );
		$contact->set_body_params( array( 'name' => 'QA', 'email' => 'qa@example.com', 'message' => 'Hello there, is this open?' ) );
		$this->assertSame( 404, rest_do_request( $contact )->get_status() );

		wp_set_current_user( $owner );
		$this->assertTrue( rest_do_request( $check )->get_data()['has_match'], 'The owner is still warned about their own pending listing.' );
	}
}
