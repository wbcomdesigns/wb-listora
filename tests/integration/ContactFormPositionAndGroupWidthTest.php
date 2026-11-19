<?php
/**
 * The contact form sits in the main column unless the site moves it; a Group's content width reaches Listora blocks.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ContactFormPositionAndGroupWidthTest extends WP_UnitTestCase {

	private function renders( string $hook ): bool {
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => self::factory()->user->create() ) );
		wp_set_current_user( self::factory()->user->create() );
		add_filter( 'wb_listora_render_contact_form', '__return_true' );
		ob_start();
		do_action( $hook, $listing, '' );
		$out = ob_get_clean();
		return false !== strpos( $out, 'wb-listora-contact-' );
	}

	public function test_default_is_main_column_only() {
		$this->assertTrue( $this->renders( 'wb_listora_detail_main_end' ) );
		$this->assertFalse( $this->renders( 'wb_listora_after_listing_fields' ) );
	}

	public function test_filter_restores_the_sidebar() {
		add_filter( 'wb_listora_contact_form_position', fn() => 'sidebar' );

		$this->assertTrue( $this->renders( 'wb_listora_after_listing_fields' ) );
		$this->assertFalse( $this->renders( 'wb_listora_detail_main_end' ) );
	}

	public function test_a_groups_content_size_is_published_to_listora_blocks_only() {
		$plugin = \WBListora\Plugin::instance();
		$block  = array( 'attrs' => array( 'layout' => array( 'type' => 'constrained', 'contentSize' => '700px' ) ) );

		$with = $plugin->pass_group_width_to_blocks( '<div class="wp-block-group"><div class="wp-block-listora-listing-search"></div></div>', $block );
		$none = $plugin->pass_group_width_to_blocks( '<div class="wp-block-group"><p>x</p></div>', $block );

		$this->assertStringContainsString( '--listora-group-content-size:700px', $with );
		$this->assertStringNotContainsString( '--listora-group-content-size', $none );

		$bad = array( 'attrs' => array( 'layout' => array( 'type' => 'constrained', 'contentSize' => 'url(x)' ) ) );
		$this->assertStringNotContainsString( '--listora-group', $plugin->pass_group_width_to_blocks( '<div class="wp-block-listora-x"></div>', $bad ) );
	}
}
