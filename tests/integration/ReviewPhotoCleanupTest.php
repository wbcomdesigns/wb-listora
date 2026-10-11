<?php
/**
 * A review's photos go with the review: on delete, listing delete and privacy erase.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Listing_Data_Eraser;

/**
 * @group listora
 * @group reviews
 */
class ReviewPhotoCleanupTest extends WP_UnitTestCase {

	private function photo( int $author ): int {
		return self::factory()->attachment->create(
			array(
				'post_author'    => $author,
				'post_mime_type' => 'image/jpeg',
				'file'           => 'photo-' . wp_generate_password( 6, false ) . '.jpg',
			)
		);
	}

	private function review( int $listing, int $user, array $photos ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'listora_reviews',
			array(
				'listing_id'     => $listing,
				'user_id'        => $user,
				'overall_rating' => 5,
				'title'          => 'Nice',
				'content'        => 'Lovely place, would return.',
				'status'         => 'approved',
				'photos'         => wp_json_encode( $photos ),
				'created_at'     => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public function test_helper_deletes_only_the_reviewers_own_attachments() {
		$reviewer = self::factory()->user->create();
		$other    = self::factory()->user->create();
		$mine     = $this->photo( $reviewer );
		$theirs   = $this->photo( $other );

		$this->assertSame( 1, Listing_Data_Eraser::delete_review_photos( wp_json_encode( array( $mine, $theirs ) ), $reviewer ) );
		$this->assertNull( get_post( $mine ) );
		$this->assertNotNull( get_post( $theirs ), 'Someone else\'s attachment referenced by the review stays.' );
	}

	public function test_rest_delete_removes_the_review_photos() {
		$reviewer = self::factory()->user->create();
		$listing  = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		$photo    = $this->photo( $reviewer );
		$review   = $this->review( $listing, $reviewer, array( $photo ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$response = rest_do_request( new WP_REST_Request( 'DELETE', '/listora/v1/reviews/' . $review ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $photo ) );
	}

	public function test_listing_delete_removes_unparented_review_photos() {
		$reviewer = self::factory()->user->create();
		$listing  = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		$photo    = $this->photo( $reviewer );
		$this->review( $listing, $reviewer, array( $photo ) );

		( new Listing_Data_Eraser() )->erase( $listing );

		$this->assertNull( get_post( $photo ) );
	}

	public function test_privacy_erase_removes_the_members_review_photos() {
		$reviewer = self::factory()->user->create( array( 'user_email' => 'erase-me@example.com' ) );
		$listing  = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		$photo    = $this->photo( $reviewer );
		$this->review( $listing, $reviewer, array( $photo ) );

		\WBListora\Privacy\Privacy_Eraser::erase( 'erase-me@example.com' );

		$this->assertNull( get_post( $photo ) );
	}
}
