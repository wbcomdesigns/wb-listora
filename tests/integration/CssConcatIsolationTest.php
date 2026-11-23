<?php
/**
 * CSS concatenation (WordPress.com, Page Optimize) must never bundle a Listora
 * stylesheet: the bundle skips style_loader_tag, so the CSS lands unlayered and
 * the isolation reset reverts it.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class CssConcatIsolationTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		( new \WBListora\Core\Theme_Defenses() )->register();
		wp_register_style( 'listora-test-block', WB_LISTORA_PLUGIN_URL . 'blocks/listing-grid/style.css', array(), '1' );
		wp_register_style( 'theme-test', content_url( 'themes/example/style.css' ), array(), '1' );
	}

	public function tear_down() {
		wp_deregister_style( 'listora-test-block' );
		wp_deregister_style( 'theme-test' );
		parent::tear_down();
	}

	public function test_listora_styles_are_never_concatenated() {
		$this->assertFalse( apply_filters( 'css_do_concat', true, 'listora-test-block' ) );
	}

	public function test_other_styles_keep_the_concat_decision() {
		$this->assertTrue( apply_filters( 'css_do_concat', true, 'theme-test' ) );
		$this->assertTrue( apply_filters( 'css_do_concat', true, 'not-registered' ) );
	}

	public function test_isolation_off_leaves_concat_alone() {
		remove_all_filters( 'css_do_concat' );
		add_filter( 'wb_listora_theme_isolation', '__return_false' );
		( new \WBListora\Core\Theme_Defenses() )->register();

		$this->assertTrue( apply_filters( 'css_do_concat', true, 'listora-test-block' ) );
	}
}
