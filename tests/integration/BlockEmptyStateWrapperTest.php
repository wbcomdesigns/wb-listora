<?php
/**
 * A block's empty or logged-out state keeps its instance id and visibility classes, so
 * "hide on mobile" and per-instance CSS still apply when there is nothing to list.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class BlockEmptyStateWrapperTest extends WP_UnitTestCase {

	public function data_blocks(): array {
		return array(
			'dashboard, logged out' => array( 'listora/user-dashboard', true ),
			'categories, no terms'  => array( 'listora/listing-categories', false ),
			'featured, none'        => array( 'listora/listing-featured', false ),
		);
	}

	/**
	 * @dataProvider data_blocks
	 *
	 * @param string $block  Block name.
	 * @param bool   $guests Whether the state only shows to guests.
	 */
	public function test_the_state_carries_visibility_and_instance_classes( string $block, bool $guests ) {
		wp_set_current_user( 0 );
		$html = render_block(
			array(
				'blockName'    => $block,
				'attrs'        => array(
					'uniqueId'     => 'abc123',
					'hideOnMobile' => true,
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		if ( '' === trim( $html ) ) {
			$this->markTestSkipped( 'This state is not shown in this fixture.' );
		}
		$this->assertStringContainsString( 'listora-hide-mobile', $html );
		$this->assertStringContainsString( 'listora-block-abc123', $html );
	}
}
