<?php
/**
 * Unit tests for the shared credit-pack builder.
 *
 * Buy Credits and the member dashboard built packs separately. The Buy Credits
 * block drew the site's Stripe/PayPal buttons on every pack, including packs
 * sold through WooCommerce, which the SDK checkout rejects with a raw
 * "Credits 50 out of bounds" error (card 10309975260). One builder now decides
 * per pack; these tests pin which packs get gateway buttons.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;

/**
 * @group listora
 * @group credits
 */
class PurchasableCreditPacksTest extends WP_UnitTestCase {

	/**
	 * Stub gateways the builder should attach to direct packs.
	 *
	 * @var array
	 */
	private $gateway_stub = array(
		array(
			'id'    => 'stripe',
			'label' => 'Stripe',
		),
	);

	public function tear_down(): void {
		delete_option( 'wb-listora_credit_mappings' );
		parent::tear_down();
	}

	/**
	 * Save mappings in the flat shape the admin writes.
	 *
	 * @param array $mappings Mapping rows.
	 */
	private function set_mappings( array $mappings ): void {
		update_option( 'wb-listora_credit_mappings', $mappings );
	}

	/**
	 * Resolve packs, keyed by adapter for readable assertions.
	 *
	 * @return array<string, array>
	 */
	private function packs_by_adapter(): array {
		$out = array();
		foreach ( wb_listora_get_purchasable_credit_packs() as $pack ) {
			$out[ $pack['adapter'] ] = $pack;
		}
		return $out;
	}

	public function test_only_a_priced_direct_pack_can_carry_gateway_buttons(): void {
		$this->set_mappings(
			array(
				array( 'adapter' => 'direct', 'item_id' => 'direct_100', 'item_label' => 'QA 100', 'credits' => 100, 'price_cents' => 999 ),
				array( 'adapter' => 'woocommerce', 'item_id' => 881, 'item_label' => 'Starter', 'credits' => 50 ),
				array( 'adapter' => 'pmpro', 'item_id' => 2, 'item_label' => 'Gold', 'credits' => 500 ),
			)
		);

		$packs = $this->packs_by_adapter();

		$this->assertSame( 999, $packs['direct']['price_cents'] );
		$this->assertSame( array(), $packs['woocommerce']['gateways'], 'A WooCommerce pack must never offer a direct gateway.' );
		$this->assertSame( array(), $packs['pmpro']['gateways'] );
		$this->assertSame( 'Subscribe', $packs['pmpro']['buy_label'] );
	}

	public function test_unpriced_direct_pack_gets_no_gateway_buttons(): void {
		$this->set_mappings(
			array(
				array( 'adapter' => 'direct', 'item_id' => 'direct_free', 'credits' => 100, 'price_cents' => 0 ),
			)
		);

		$packs = $this->packs_by_adapter();

		$this->assertSame( array(), $packs['direct']['gateways'] );
		$this->assertSame( '100 credits', $packs['direct']['item_label'] );
	}

	public function test_both_template_vocabularies_are_present(): void {
		$this->set_mappings(
			array(
				array( 'adapter' => 'direct', 'item_id' => 'direct_100', 'item_label' => 'QA 100', 'credits' => 100, 'price_cents' => 999 ),
			)
		);

		$pack = $this->packs_by_adapter()['direct'];

		$this->assertSame( 'QA 100', $pack['name'] );
		$this->assertSame( $pack['buy_url'], $pack['url'] );
		$this->assertEqualsWithDelta( 9.99, $pack['price'], 0.001 );
	}
}
