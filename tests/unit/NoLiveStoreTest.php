<?php
/**
 * The test suite never reaches the live store (tests/bootstrap.php).
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;

/**
 * Fails if the bootstrap guard that refuses the store hosts is removed.
 *
 * @group listora
 */
class NoLiveStoreTest extends WP_UnitTestCase {

	public function test_store_hosts_are_refused(): void {
		foreach ( array( 'https://wbcomdesigns.com/', 'https://updates.wbcomdesigns.com/' ) as $url ) {
			$response = wp_remote_get( $url );
			$this->assertWPError( $response, $url );
			$this->assertSame( 'wb_listora_tests_no_store', $response->get_error_code(), $url );
		}
	}

	public function test_preset_activation_is_switched_off(): void {
		$this->assertTrue( defined( 'WB_LISTORA_NO_LICENSE_PHONE_HOME' ) && WB_LISTORA_NO_LICENSE_PHONE_HOME );
	}

	public function test_licence_request_gets_no_reply(): void {
		$this->assertFalse(
			wb_listora_licence_request(
				array(
					'edd_action' => 'check_license',
					'item_id'    => 1662779,
				)
			)
		);
	}
}
