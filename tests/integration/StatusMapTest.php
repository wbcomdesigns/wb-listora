<?php
/**
 * The status map allows the moves Pro's plan flows rely on.
 *
 * @package WBListora\Tests
 */

use WBListora\Workflow\Status_Manager;

/**
 * @group listora
 */
class StatusMapTest extends WP_UnitTestCase {

	public function test_awaiting_credits_is_reachable_from_pending_and_publish() {
		$this->assertTrue( Status_Manager::is_valid_transition( 'pending', 'listora_payment' ), 'An unaffordable plan parks the submission in Awaiting Credits.' );
		$this->assertTrue( Status_Manager::is_valid_transition( 'publish', 'listora_payment' ), 'A refunded paid listing waits for payment again.' );
		$this->assertFalse( Status_Manager::is_valid_transition( 'listora_rejected', 'publish' ), 'Still refused: rejected straight to live.' );
	}
}
