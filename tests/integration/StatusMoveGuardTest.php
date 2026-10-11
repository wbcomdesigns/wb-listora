<?php
/**
 * Every entry point follows the listing status map, not only change_status().
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Workflow\Status_Manager;

/**
 * @group listora
 */
class StatusMoveGuardTest extends WP_UnitTestCase {

	private function listing( $status ) {
		return self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => $status ) );
	}

	public function test_raw_save_cannot_publish_a_rejected_listing() {
		$id = $this->listing( 'listora_rejected' );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		$this->assertSame( 'listora_rejected', get_post_status( $id ), 'Quick Edit / Bulk Edit / WP-CLI path.' );
		$this->assertSame( '', (string) get_post_meta( $id, '_listora_expiration_date', true ) );

		$this->assertWPError( Status_Manager::change_status( $id, 'publish' ) );
	}

	public function test_core_rest_refuses_the_same_move() {
		$id       = $this->listing( 'listora_rejected' );
		$prepared = (object) array( 'ID' => $id, 'post_status' => 'publish' );
		$result   = ( new Status_Manager() )->refuse_invalid_move_over_rest( $prepared, new \WP_REST_Request() );
		$this->assertWPError( $result );
		$this->assertSame( 409, $result->get_error_data()['status'] );
	}

	public function test_valid_moves_and_trash_still_work() {
		$rejected = $this->listing( 'listora_rejected' );
		wp_update_post( array( 'ID' => $rejected, 'post_status' => 'pending' ) );
		$this->assertSame( 'pending', get_post_status( $rejected ) );

		$other = $this->listing( 'listora_rejected' );
		wp_trash_post( $other );
		$this->assertSame( 'trash', get_post_status( $other ) );

		$this->assertTrue( Status_Manager::change_status( $rejected, 'publish' ) );
		$this->assertSame( 'publish', get_post_status( $rejected ) );
	}
}
