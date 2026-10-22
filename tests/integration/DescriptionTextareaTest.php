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
}
