<?php
/**
 * Block attributes cannot carry CSS rules into the scoped style tag.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Block_CSS;

/**
 * @group listora
 * @group blocks
 */
class BlockCssTest extends WP_UnitTestCase {

	public function test_valid_values_render() {
		$css = Block_CSS::generate(
			'abc123',
			array(
				'padding'      => array(
					'top'    => 10,
					'right'  => '1.5',
					'bottom' => 0,
					'left'   => 'auto',
				),
				'paddingUnit'  => 'rem',
				'boxShadow'    => true,
				'shadowColor'  => 'rgba(0, 0, 0, 0.2)',
			)
		);
		$this->assertStringContainsString( '.listora-block-abc123 {', $css );
		$this->assertStringContainsString( 'padding: 10rem 1.5rem 0rem auto;', $css );
		$this->assertStringContainsString( 'rgba(0, 0, 0, 0.2);', $css );
	}

	public function test_injected_rules_are_dropped() {
		$css = Block_CSS::generate(
			'x{} body{display:none} .y',
			array(
				'margin'      => array(
					'top'    => '0;} body{display:none',
					'right'  => 1,
					'bottom' => 1,
					'left'   => 1,
				),
				'padding'     => array(
					'top'    => 1,
					'right'  => 1,
					'bottom' => 1,
					'left'   => 1,
				),
				'paddingUnit' => 'px;} body{display:none',
				'boxShadow'   => true,
				'shadowColor' => 'red;} body{background:url(https://evil.example/p.gif)',
			)
		);
		$this->assertStringNotContainsString( 'display:none', $css );
		$this->assertStringNotContainsString( 'url(', $css );
		$this->assertStringContainsString( '.listora-block-xbodydisplaynoney {', $css );
		$this->assertStringContainsString( 'padding: 1px 1px 1px 1px;', $css );
		$this->assertStringContainsString( 'rgba(0,0,0,0.12);', $css, 'An invalid colour falls back to the default shadow.' );
		$this->assertStringNotContainsString( 'margin', $css, 'A side that is not a length drops the declaration.' );
	}
}
