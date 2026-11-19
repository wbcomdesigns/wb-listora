<?php
/**
 * The currency dropdown and the formatter share one list that includes the whole SDK catalogue.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class CurrencyRegistryTest extends WP_UnitTestCase {

	public function test_krw_and_nzd_are_offered_and_formatted() {
		$list = wb_listora_get_currencies();

		$this->assertArrayHasKey( 'KRW', $list );
		$this->assertArrayHasKey( 'NZD', $list );
		$this->assertSame( 'A$', $list['AUD'], "Listora's own symbol still wins." );
		$this->assertSame( 0, wb_listora_get_currency_format( 'KRW' )['decimals'] );
		$this->assertNotSame( 'KRW ', wb_listora_get_currency_format( 'KRW' )['symbol'] );
	}
}
