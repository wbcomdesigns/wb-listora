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

	public function test_css_length_keeps_one_length_only() {
		$this->assertSame( '300px', Block_CSS::css_length( '300px', '450px' ) );
		$this->assertSame( '60vh', Block_CSS::css_length( ' 60vh ', '450px' ) );
		$this->assertSame( '400px', Block_CSS::css_length( 400, '450px' ) );
		$this->assertSame( '450px', Block_CSS::css_length( '300px;position:fixed;inset:0;background:url(https://evil.example/p.gif)', '450px' ) );
		$this->assertSame( '450px', Block_CSS::css_length( 'calc(100vh - 10px)', '450px' ) );
		$this->assertSame( '450px', Block_CSS::css_length( '', '450px' ) );
	}

	public function test_map_block_height_cannot_add_declarations() {
		$html = render_block(
			array(
				'blockName'    => 'listora/listing-map',
				'attrs'        => array( 'height' => '300px;position:fixed;inset:0;background:url(https://evil.example/p.gif)' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		$this->assertStringContainsString( 'style="height: 450px;"', $html );
		$this->assertStringNotContainsString( 'position:fixed', $html );
	}
}

