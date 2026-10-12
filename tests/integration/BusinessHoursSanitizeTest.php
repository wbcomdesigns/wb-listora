<?php
/**
 * Business hours keep their shape on save, and no text in them keeps markup.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Field;

/**
 * @group listora
 */
class BusinessHoursSanitizeTest extends WP_UnitTestCase {

	public function test_markup_is_stripped_and_shape_kept() {
		$field = new Field( array( 'key' => 'business_hours', 'type' => 'business_hours', 'label' => 'Hours' ) );
		$in    = array(
			array( 'day' => 1, 'open' => '09:00', 'close' => '</script><script>alert(1)</script>17:00', 'closed' => false, 'is_24h' => false ),
			array( 'day' => 2, 'open' => '<b>10:00</b>', 'close' => '18:00', 'closed' => true ),
		);

		$out = $field->sanitize_json( $in );

		$this->assertSame( array_keys( $in[0] ), array_keys( $out[0] ), 'Shape is kept.' );
		$this->assertSame( '09:00', $out[0]['open'] );
		$this->assertStringNotContainsString( '<', $out[0]['close'] );
		$this->assertSame( '10:00', $out[1]['open'] );
		$this->assertTrue( $out[1]['closed'], 'Booleans are untouched.' );
		$this->assertSame( 1, $out[0]['day'] );

		$this->assertSame( $out, $field->sanitize_json( wp_json_encode( $in ) ), 'A JSON string is sanitized the same way.' );
		$this->assertSame( array(), $field->sanitize_json( 'not json' ) );
	}
}
