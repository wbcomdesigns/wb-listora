<?php
/**
 * One call path per rule: exact money formatting and claim delete.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Claims_Model;

/**
 * @group listora
 */
class SharedWritePathsTest extends WP_UnitTestCase {

	public function test_exact_currency_never_abbreviates() {
		$this->assertStringContainsString( 'K', wb_listora_format_currency( 1500, 'USD' ), 'Cards still abbreviate.' );
		$this->assertSame( '$1,500.00', wb_listora_format_currency( 1500, 'USD', true ) );
	}

	public function test_claim_delete_fires_hooks_and_can_be_blocked() {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'claims',
			array(
				'listing_id' => self::factory()->post->create( array( 'post_type' => 'listora_listing' ) ),
				'user_id'    => self::factory()->user->create(),
				'status'     => 'pending',
				'proof_text' => 'Owner',
			)
		);
		$id = (int) $wpdb->insert_id;

		$block = static function () {
			return new \WP_Error( 'no', 'blocked' );
		};
		add_filter( 'wb_listora_before_delete_claim', $block );
		$this->assertWPError( Claims_Model::delete( $id ) );
		$this->assertNotNull( Claims_Model::get( $id ), 'A blocked delete leaves the row.' );
		remove_filter( 'wb_listora_before_delete_claim', $block );

		$fired = 0;
		add_action(
			'wb_listora_after_delete_claim',
			static function () use ( &$fired ) {
				++$fired;
			}
		);
		$this->assertTrue( Claims_Model::delete( $id ) );
		$this->assertNull( Claims_Model::get( $id ) );
		$this->assertSame( 1, $fired );
		$this->assertWPError( Claims_Model::delete( $id ), 'Deleting twice reports not found.' );
	}
}
