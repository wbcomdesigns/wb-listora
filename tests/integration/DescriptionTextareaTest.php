<?php
/**
 * The frontend form's Description box shows text, not block markup.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class DescriptionTextareaTest extends WP_UnitTestCase {

	public function test_block_markup_becomes_paragraphs() {
		$stored = "<!-- wp:paragraph -->\n<p>test one</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>second <strong>bold</strong></p>\n<!-- /wp:paragraph -->";

		$this->assertSame( "test one\n\nsecond bold", wb_listora_description_for_textarea( $stored ) );
	}

	public function test_plain_text_is_untouched() {
		$this->assertSame( "Plain text\nline two", wb_listora_description_for_textarea( "Plain text\nline two" ) );
	}

	private function edit( int $post_id, string $description ) {
		do_action( 'rest_api_init' );
		$request = new WP_REST_Request( 'PUT', '/listora/v1/submit/' . $post_id );
		$request->set_param( 'id', $post_id );
		$request->set_param( 'description', $description );

		return rest_do_request( $request );
	}

	public function test_an_untouched_description_keeps_its_markup_on_a_frontend_save() {
		$owner  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$stored = "<!-- wp:heading -->\n<h2>Menu</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>We serve <strong>fresh</strong> pasta.</p>\n<!-- /wp:paragraph -->";
		$id     = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $owner, 'post_content' => $stored ) );
		wp_set_current_user( $owner );

		$this->edit( $id, wb_listora_description_for_textarea( $stored ) );

		$this->assertSame( $stored, get_post( $id )->post_content, 'Saving the form unchanged must not flatten the markup.' );
	}

	public function test_an_edited_description_is_saved() {
		$owner = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$id    = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $owner, 'post_content' => '<p>Old</p>' ) );
		wp_set_current_user( $owner );

		$this->edit( $id, "New first line\n\nNew second line" );

		$this->assertSame( "New first line\n\nNew second line", get_post( $id )->post_content );
	}
}
