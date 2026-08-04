<?php
/**
 * Unit tests for Field::normalize_options() — the read-side guarantee that
 * every consumer of field options receives the canonical { value, label }
 * array shape.
 *
 * Locks the 1.4.1 fix for the live-site PHP 8 fatal (BC 10162700303):
 * the pre-1.4.1 Type Editor persisted owner-added select/radio/multiselect
 * options as plain strings inside `_listora_field_groups`, and readers doing
 * `$opt['value']` fataled with "Cannot access offset of type string on
 * string". PHPStan (level 7) cannot see this class of bug because
 * Field::get() returns untyped mixed — this test is the static-analysis
 * backstop.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;
use WBListora\Core\Field;

/**
 * @group listora
 * @group field-options
 */
class FieldNormalizeOptionsTest extends WP_UnitTestCase {

	/**
	 * The customer-fatal shape: plain strings stored by the pre-1.4.1 editor.
	 */
	public function test_string_options_become_value_label_pairs() {
		$normalized = Field::normalize_options( array( 'Trompete', 'Posaune' ) );

		$this->assertSame(
			array(
				array(
					'value' => 'trompete',
					'label' => 'Trompete',
				),
				array(
					'value' => 'posaune',
					'label' => 'Posaune',
				),
			),
			$normalized
		);
	}

	/**
	 * Canonical entries pass through unchanged.
	 */
	public function test_canonical_options_pass_through() {
		$canonical = array(
			array(
				'value' => 'full-time',
				'label' => 'Full Time',
			),
		);

		$this->assertSame( $canonical, Field::normalize_options( $canonical ) );
	}

	/**
	 * Mixed stored data (real corrupted-site shape: old object entries plus
	 * string entries appended by the buggy editor) normalizes every item.
	 */
	public function test_mixed_shapes_all_normalize() {
		$normalized = Field::normalize_options(
			array(
				array(
					'value' => 'red',
					'label' => 'Red',
				),
				'Deep Blue',
			)
		);

		$this->assertCount( 2, $normalized );
		foreach ( $normalized as $opt ) {
			$this->assertIsArray( $opt );
			$this->assertArrayHasKey( 'value', $opt );
			$this->assertArrayHasKey( 'label', $opt );
		}
		$this->assertSame( 'deep-blue', $normalized[1]['value'] );
		$this->assertSame( 'Deep Blue', $normalized[1]['label'] );
	}

	/**
	 * Partial arrays fill the missing key from the other.
	 */
	public function test_partial_arrays_backfill_missing_key() {
		$normalized = Field::normalize_options(
			array(
				array( 'label' => 'Only Label' ),
				array( 'value' => 'only-value' ),
			)
		);

		$this->assertSame( 'only-label', $normalized[0]['value'] );
		$this->assertSame( 'Only Label', $normalized[0]['label'] );
		$this->assertSame( 'only-value', $normalized[1]['value'] );
		$this->assertSame( 'only-value', $normalized[1]['label'] );
	}

	/**
	 * Junk shapes never survive: empty strings, empty arrays, non-arrays.
	 */
	public function test_junk_entries_are_dropped() {
		$this->assertSame( array(), Field::normalize_options( array( '', '   ', array() ) ) );
		$this->assertSame( array(), Field::normalize_options( 'not-an-array' ) );
		$this->assertSame( array(), Field::normalize_options( null ) );
	}

	/**
	 * The constructor itself normalizes — every Field hydrated from stored
	 * `_listora_field_groups` term meta is safe regardless of stored shape.
	 * This is the exact read path of the customer fatal.
	 */
	public function test_constructor_normalizes_stored_shape() {
		$field = Field::from_array(
			array(
				'key'     => 'instrument',
				'label'   => 'Instrument',
				'type'    => 'multiselect',
				'options' => array( 'Trompete', 'Posaune' ),
			)
		);

		foreach ( $field->get( 'options' ) as $opt ) {
			// Pre-1.4.1 this line is where PHP 8 threw the TypeError.
			$this->assertIsString( $opt['value'] );
			$this->assertIsString( $opt['label'] );
		}
	}
}
