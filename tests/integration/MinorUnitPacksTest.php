<?php
/**
 * Direct credit packs use each currency's own minor units.
 *
 * @package WBListora\Tests
 */

use WBListora\DB\Migrator;

/**
 * @group listora
 * @group credits
 */
class MinorUnitPacksTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		if ( ! class_exists( '\Wbcom\Credits\Money' ) ) {
			$this->markTestSkipped( 'Credits SDK not loaded.' );
		}
	}

	public function test_jpy_pack_shows_its_real_price() {
		$settings             = (array) get_option( 'wb_listora_settings', array() );
		$settings['currency'] = 'JPY';
		update_option( 'wb_listora_settings', $settings );
		update_option(
			'wb-listora_credit_mappings',
			array(
				array( 'adapter' => 'direct', 'item_id' => 'direct_a', 'item_label' => 'Pack', 'credits' => 100, 'price_cents' => 2500, 'currency' => 'JPY' ),
			)
		);

		$packs = wb_listora_get_purchasable_credit_packs( true );
		$this->assertEqualsWithDelta( 2500.0, (float) $packs[0]['price'], 0.001, '¥2,500 has no minor unit: not 25.' );
	}

	public function test_upgrade_rescales_packs_stored_times_100() {
		update_option(
			'wb-listora_credit_mappings',
			array(
				array( 'adapter' => 'direct', 'price_cents' => 250000, 'currency' => 'JPY' ),
				array( 'adapter' => 'direct', 'price_cents' => 150, 'currency' => 'KWD' ),
				array( 'adapter' => 'direct', 'price_cents' => 999, 'currency' => 'USD' ),
				array( 'adapter' => 'woocommerce', 'price_cents' => 500, 'currency' => 'JPY' ),
			)
		);

		Migrator::migrate_1_9_1_15();
		$rows = get_option( 'wb-listora_credit_mappings' );

		$this->assertSame( 2500, $rows[0]['price_cents'] );
		$this->assertSame( 1500, $rows[1]['price_cents'], 'KWD has three decimals.' );
		$this->assertSame( 999, $rows[2]['price_cents'] );
		$this->assertSame( 500, $rows[3]['price_cents'], 'Only direct packs are rescaled.' );
	}
}
