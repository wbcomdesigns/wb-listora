<?php
/**
 * A claim whose status cannot be written does not hand the listing over.
 *
 * @package WBListora\Tests
 */

use WBListora\REST\Claims_Controller;

/**
 * @group listora
 * @group claims
 */
class ClaimApprovalOrderTest extends WP_UnitTestCase {

	private function claim( int $listing, int $user ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'listora_claims',
			array(
				'listing_id' => $listing,
				'user_id'    => $user,
				'status'     => 'pending',
				'proof_text' => 'I own it',
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public function test_failed_status_write_leaves_the_owner_alone() {
		$owner    = self::factory()->user->create();
		$claimant = self::factory()->user->create();
		$listing  = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $owner ) );
		$claim    = $this->claim( $listing, $claimant );

		// Make the status UPDATE fail the way a locked or broken table would.
		$break = static fn ( $sql ) => ( false !== strpos( $sql, 'UPDATE' ) && false !== strpos( $sql, 'listora_claims' ) ) ? 'SELECT * FROM listora_no_such_table_xyz' : $sql;
		add_filter( 'query', $break );
		$suppress = $GLOBALS['wpdb']->suppress_errors( true );
		$result   = Claims_Controller::change_status( $claim, 'approved', 'test' );
		$GLOBALS['wpdb']->suppress_errors( $suppress );
		remove_filter( 'query', $break );

		$this->assertSame( 'failed', $result );
		$this->assertSame( $owner, (int) get_post_field( 'post_author', $listing ), 'Ownership must not move when the status was not saved.' );
		$this->assertEmpty( get_post_meta( $listing, '_listora_is_claimed', true ) );
	}

	public function test_normal_approval_still_transfers_ownership() {
		$owner    = self::factory()->user->create();
		$claimant = self::factory()->user->create();
		$listing  = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $owner ) );
		$claim    = $this->claim( $listing, $claimant );

		$this->assertSame( 'updated', Claims_Controller::change_status( $claim, 'approved', 'test' ) );
		$this->assertSame( $claimant, (int) get_post_field( 'post_author', $listing ) );
	}
}
