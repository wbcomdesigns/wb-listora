<?php
/**
 * Every "Hide on ..." class a block can emit has a CSS rule in the served base stylesheet.
 * The rules were once deleted as "dead source" while the toggles kept adding the classes,
 * so Hide on Mobile silently did nothing on every block.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class VisibilityClassesHaveRulesTest extends WP_UnitTestCase {

	public function test_each_emitted_class_is_styled_in_every_served_base_file() {
		$emitted = explode(
			' ',
			\WBListora\Block_CSS::visibility_classes(
				array(
					'hideOnDesktop' => true,
					'hideOnTablet'  => true,
					'hideOnMobile'  => true,
				)
			)
		);
		$this->assertCount( 3, $emitted );

		foreach ( array( 'listora-base.css', 'listora-base-rtl.css', 'listora-base.layer.css', 'listora-base-rtl.layer.css' ) as $file ) {
			$css = (string) file_get_contents( WB_LISTORA_PLUGIN_DIR . 'assets/css/' . $file );
			foreach ( $emitted as $class ) {
				$this->assertMatchesRegularExpression( '/\.' . preg_quote( $class, '/' ) . '\s*\{[^}]*display:\s*none\s*!important/', $css, "$class has no hiding rule in $file" );
			}
		}
	}
}
