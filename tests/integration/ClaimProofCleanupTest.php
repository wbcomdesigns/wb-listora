<?php
/**
 * A claim's proof documents go with the claim, on every path that removes it.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Claim_Proofs;
use WBListora\Core\Claims_Model;

/**
 * @group listora
 * @group claims
 */
class ClaimProofCleanupTest extends WP_UnitTestCase {

	private function proof(): int {
		$id = self::factory()->attachment->create( array( 'post_mime_type' => 'application/pdf', 'file' => 'proof-' . wp_generate_password( 8, false ) . '.pdf' ) );
		update_post_meta( $id, Claim_Proofs::META_IS_PROOF, 1 );
		return $id;
	}

	private function claim( int $listing, int $user, array $files ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'listora_claims',
			array(
				'listing_id'  => $listing,
				'user_id'     => $user,
				'status'      => 'pending',
				'proof_text'  => 'I own it',
				'proof_files' => wp_json_encode( $files ),
				'created_at'  => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public function test_deleting_a_claim_deletes_only_its_proofs() {
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		$proof   = $this->proof();
		$other   = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg', 'file' => 'not-a-proof.jpg' ) );
		$claim   = $this->claim( $listing, self::factory()->user->create(), array( $proof, $other ) );

		$this->assertTrue( Claims_Model::delete( $claim ) );

		$this->assertNull( get_post( $proof ) );
		$this->assertNotNull( get_post( $other ), 'An id that is not a proof is never deleted.' );
	}

	public function test_listing_delete_and_privacy_erase_remove_proofs() {
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		$a       = $this->proof();
		$this->claim( $listing, self::factory()->user->create(), array( $a ) );
		( new \WBListora\Core\Listing_Data_Eraser() )->erase( $listing );
		$this->assertNull( get_post( $a ) );

		$member = self::factory()->user->create( array( 'user_email' => 'claimant@example.com' ) );
		$b      = $this->proof();
		$this->claim( self::factory()->post->create( array( 'post_type' => 'listora_listing' ) ), $member, array( $b ) );
		\WBListora\Privacy\Privacy_Eraser::erase( 'claimant@example.com' );
		$this->assertNull( get_post( $b ) );
	}
}
