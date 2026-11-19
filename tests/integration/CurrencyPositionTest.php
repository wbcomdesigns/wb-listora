<?php
/**
 * The currency symbol position (before / after) is one setting beside the currency.
 *
 * @package WBListora\Tests
 */

namespace WBListora\Tests\Integration;

use WBListora\Admin\Settings_Page;
use WP_UnitTestCase;

/**
 * @group listora
 * @group currency
 */
class CurrencyPositionTest extends WP_UnitTestCase {

	/** @var mixed */
	private $original;

	public function set_up() {
		parent::set_up();
		$this->original = get_option( 'wb_listora_settings', null );
	}

	public function tear_down() {
		if ( null === $this->original ) {
			delete_option( 'wb_listora_settings' );
		} else {
			update_option( 'wb_listora_settings', $this->original );
		}
		parent::tear_down();
	}

	private function save( array $values ): void {
		$current = get_option( 'wb_listora_settings', array() );
		update_option( 'wb_listora_settings', array_merge( is_array( $current ) ? $current : array(), $values ) );
	}

	public function test_the_symbol_goes_before_by_default() {
		$this->save( array( 'currency' => 'KRW' ) );
		$this->assertSame( 'before', wb_listora_get_currency_format()['position'] );
		$this->assertSame( '₩15,000', wb_listora_format_currency( 15000, '', true ) );
	}

	public function test_after_puts_the_symbol_behind_the_amount() {
		$this->save(
			array(
				'currency'          => 'KRW',
				'currency_position' => 'after',
			)
		);
		$this->assertSame( 'after', wb_listora_get_currency_format()['position'] );
		$this->assertSame( '15,000₩', wb_listora_format_currency( 15000, '', true ) );
	}

	public function test_another_currency_keeps_the_symbol_in_front() {
		$this->save(
			array(
				'currency'          => 'KRW',
				'currency_position' => 'after',
			)
		);
		$this->assertSame( 'before', wb_listora_get_currency_format( 'USD' )['position'] );
	}

	public function test_a_value_other_than_before_or_after_is_not_saved() {
		$clean = Settings_Page::sanitize( array( 'currency_position' => 'sideways' ) );
		$this->assertSame( 'before', $clean['currency_position'] );

		$clean = Settings_Page::sanitize( array( 'currency_position' => 'after' ) );
		$this->assertSame( 'after', $clean['currency_position'] );
	}
}
