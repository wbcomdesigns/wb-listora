<?php
/**
 * One display path for money: a converter changes what is shown, never what is stored.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Field;

/**
 * @group listora
 */
class DisplayAmountTest extends WP_UnitTestCase {

	public function tear_down() {
		remove_all_filters( 'wb_listora_display_amount' );
		parent::tear_down();
	}

	public function test_converter_changes_display_but_not_charged_amounts() {
		add_filter(
			'wb_listora_display_amount',
			static function ( $display, $exact ) {
				return $exact ? $display : array(
					'amount'   => $display['amount'] * 2,
					'currency' => 'EUR',
				);
			},
			10,
			2
		);

		$this->assertSame( '€100', wb_listora_format_currency( 50 ) );
		$this->assertSame( '$25.00', wb_listora_format_currency( 25, 'USD', true ), 'Charged money stays as recorded.' );
	}

	public function test_bad_filter_return_is_ignored() {
		add_filter( 'wb_listora_display_amount', '__return_null' );
		$this->assertSame( '$50', wb_listora_format_currency( 50, 'USD' ) );
	}

	public function test_number_field_with_money_option_uses_site_currency() {
		$salary = new Field(
			array(
				'key'      => 'salary_min',
				'type'     => 'number',
				'currency' => true,
			)
		);
		$plain  = new Field(
			array(
				'key'  => 'bedrooms',
				'type' => 'number',
			)
		);

		$this->assertSame( wb_listora_format_currency( 950 ), wb_listora_format_card_value( $salary, 950 ) );
		$this->assertSame( '3', wb_listora_format_card_value( $plain, 3 ) );
	}
}
