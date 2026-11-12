<?php
/**
 * Gallery and video sent in an unexpected shape never fatal the edit route.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class SubmissionMediaParamTypesTest extends WP_UnitTestCase {

	private int $owner;
	private int $listing;
	private int $image;

	public function set_up() {
		parent::set_up();
		$this->owner   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $this->owner ) );
		$this->image   = self::factory()->attachment->create( array( 'post_author' => $this->owner, 'post_mime_type' => 'image/jpeg' ) );
		wp_set_current_user( $this->owner );
		do_action( 'rest_api_init' );
	}

	private function edit( array $params ) {
		$request = new WP_REST_Request( 'PUT', '/listora/v1/submit/' . $this->listing );
		$request->set_param( 'id', $this->listing );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	public function test_gallery_as_an_array_is_saved() {
		$response = $this->edit( array( 'gallery' => array( $this->image ) ) );

		$this->assertLessThan( 500, $response->get_status() );
		$this->assertSame( array( $this->image ), array_map( 'intval', (array) \WBListora\Core\Meta_Handler::get_value( $this->listing, 'gallery' ) ) );
	}

	public function test_gallery_as_a_comma_string_is_saved() {
		$response = $this->edit( array( 'gallery' => (string) $this->image ) );

		$this->assertLessThan( 500, $response->get_status() );
		$this->assertSame( array( $this->image ), array_map( 'intval', (array) \WBListora\Core\Meta_Handler::get_value( $this->listing, 'gallery' ) ) );
	}

	public function test_video_as_an_array_is_ignored_and_keeps_the_saved_url() {
		\WBListora\Core\Meta_Handler::set_value( $this->listing, 'video', 'https://example.com/v' );

		$response = $this->edit( array( 'video' => array( 1, 2 ) ) );

		$this->assertLessThan( 500, $response->get_status() );
		$this->assertSame( 'https://example.com/v', \WBListora\Core\Meta_Handler::get_value( $this->listing, 'video' ) );
	}
}
