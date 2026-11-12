<?php
/**
 * Update-package signature registration (wb-listora.php, libs/package-signatures).
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;

/**
 * Free registers itself and Pro under the store slugs, the names the release signatures carry.
 * A repo name here (wb-listora) would make every signed update log wrong_product.
 *
 * @group listora
 */
class PackageSignaturesRegistrationTest extends WP_UnitTestCase {

	public function test_free_and_pro_register_under_store_slugs(): void {
		$this->assertTrue( function_exists( 'package_signatures_register' ), 'libs/package-signatures is not bundled' );

		$by_product = array();
		foreach ( (array) ( $GLOBALS['package_signatures_products'] ?? array() ) as $product ) {
			$by_product[ $product['product'] ?? '' ] = $product;
		}

		$expected = array(
			'listora'     => plugin_basename( WB_LISTORA_PLUGIN_FILE ),
			'listora-pro' => 'wb-listora-pro/wb-listora-pro.php',
		);
		foreach ( $expected as $slug => $plugin ) {
			$this->assertArrayHasKey( $slug, $by_product, $slug . ' is not registered' );
			$this->assertSame( $plugin, $by_product[ $slug ]['plugin'], $slug );
			$this->assertSame( 'warn', $by_product[ $slug ]['mode'], $slug );
			$this->assertArrayHasKey( 'wbcom-2026-1', $by_product[ $slug ]['keys'], $slug );
			$this->assertContains( 'updates.wbcomdesigns.com', $by_product[ $slug ]['hosts'], $slug );
		}
		$this->assertArrayNotHasKey( 'wb-listora', $by_product );
		$this->assertArrayNotHasKey( 'wb-listora-pro', $by_product );
	}
}
