<?php
/**
 * Required text is required on every route, not only in the browser form.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group rest
 */
class RequiredTextTest extends WP_UnitTestCase {

	public function test_update_refuses_an_empty_title() {
		$owner   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $owner, 'post_title' => 'Kept Title' ) );
		wp_set_current_user( $owner );

		$request = new WP_REST_Request( 'PUT', '/listora/v1/submit/' . $listing );
		$request->set_body_params( array( 'title' => '   ' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'listora_title_required', $response->get_data()['code'] );
		$this->assertSame( 'Kept Title', get_the_title( $listing ) );
	}

	public function test_contact_form_refuses_an_empty_message_or_name() {
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		wp_set_current_user( self::factory()->user->create() );

		foreach ( array( 'listora_message_required' => array( 'name' => 'Ada', 'message' => ' ' ), 'listora_name_required' => array( 'name' => ' ', 'message' => 'Hello there' ) ) as $code => $fields ) {
			$request = new WP_REST_Request( 'POST', '/listora/v1/listings/' . $listing . '/contact-form' );
			$request->set_body_params( $fields + array( 'email' => 'ada@example.com' ) );
			$response = rest_do_request( $request );
			$this->assertSame( 400, $response->get_status(), $code );
			$this->assertSame( $code, $response->get_data()['code'] );
		}
	}
}
