<?php
/**
 * Leaving a space says HOW it left.
 *
 * Card 10317739747: `DELETE /spaces/{space_id}/listings/{id}` serves three
 * different events - a curator declining a pending submission, a curator taking
 * an approved listing down, and a member withdrawing their own - and fired one
 * action for all three, after the row had already been deleted. "Your
 * submission was declined", "your listing was removed" and "you withdrew your
 * listing" are not interchangeable, and the fact that told them apart was in
 * the row that had just gone.
 *
 * BuddyNext is the consumer of this API, so the seam has to carry the meaning.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   spaces
 */

namespace WBListora\Tests\Integration;

use WBListora\Core\Space_Listings_Model;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group spaces
 */
class SpaceListingRemovalContextTest extends WP_UnitTestCase {

	const SPACE_ID = 4242;

	/**
	 * Listing author.
	 *
	 * @var int
	 */
	private $author = 0;

	/**
	 * Space curator.
	 *
	 * @var int
	 */
	private $curator = 0;

	/**
	 * Captured hook arguments.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private $fired = array();

	/**
	 * Captured rejected-hook arguments.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private $rejected = array();

	public function set_up() {
		parent::set_up();

		$this->author  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->curator = self::factory()->user->create( array( 'role' => 'editor' ) );

		// Spaces delegate authority to the host community plugin; without a
		// listener both checks default to false and every request is a 403.
		add_filter( 'wb_listora_user_can_moderate_space', '__return_true' );
		add_filter( 'wb_listora_user_can_view_space', '__return_true' );

		add_action(
			'wb_listora_listing_removed_from_space',
			function ( ...$args ) {
				$this->fired[] = $args;
			},
			10,
			5
		);

		add_action(
			'wb_listora_listing_rejected_in_space',
			function ( ...$args ) {
				$this->rejected[] = $args;
			},
			10,
			3
		);

		rest_get_server();
	}

	public function tear_down() {
		remove_filter( 'wb_listora_user_can_moderate_space', '__return_true' );
		remove_filter( 'wb_listora_user_can_view_space', '__return_true' );
		parent::tear_down();
	}

	/**
	 * @param string $status Space status to seed.
	 * @return int Listing ID.
	 */
	private function listing_in_space( string $status ): int {
		$listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $this->author,
			)
		);

		if ( Space_Listings_Model::STATUS_APPROVED === $status ) {
			Space_Listings_Model::add_approved( self::SPACE_ID, $listing, $this->curator );
		} else {
			Space_Listings_Model::submit( self::SPACE_ID, $listing, $this->author );
		}

		return $listing;
	}

	/**
	 * @param int $listing Listing to remove.
	 * @return \WP_REST_Response
	 */
	private function remove( int $listing ) {
		return rest_do_request(
			new WP_REST_Request( 'DELETE', '/listora/v1/spaces/' . self::SPACE_ID . '/listings/' . $listing )
		);
	}

	/**
	 * A curator declining something still pending.
	 */
	public function test_rejecting_a_pending_submission(): void {
		$listing = $this->listing_in_space( Space_Listings_Model::STATUS_PENDING );
		wp_set_current_user( $this->curator );

		$response = $this->remove( $listing );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'reject', $response->get_data()['context'] );

		$this->assertCount( 1, $this->fired );
		$this->assertSame( Space_Listings_Model::STATUS_PENDING, $this->fired[0][3] );
		$this->assertSame( 'reject', $this->fired[0][4] );

		$this->assertCount( 1, $this->rejected, 'The dedicated reject hook must fire too.' );
	}

	/**
	 * A curator taking down something already approved. This is the case that
	 * could NOT be told from a rejection before, because both look identical
	 * once the row is deleted.
	 */
	public function test_taking_down_an_approved_listing(): void {
		$listing = $this->listing_in_space( Space_Listings_Model::STATUS_APPROVED );
		wp_set_current_user( $this->curator );

		$response = $this->remove( $listing );

		$this->assertSame( 'takedown', $response->get_data()['context'] );
		$this->assertSame( Space_Listings_Model::STATUS_APPROVED, $this->fired[0][3] );
		$this->assertSame( 'takedown', $this->fired[0][4] );

		$this->assertEmpty( $this->rejected, 'A takedown is not a rejection.' );
	}

	/**
	 * The author removing their own listing - nothing to apologise for.
	 */
	public function test_an_author_withdrawing_their_own(): void {
		$listing = $this->listing_in_space( Space_Listings_Model::STATUS_APPROVED );
		wp_set_current_user( $this->author );

		$response = $this->remove( $listing );

		$this->assertSame( 'withdraw', $response->get_data()['context'] );
		$this->assertSame( 'withdraw', $this->fired[0][4] );
		$this->assertEmpty( $this->rejected );
	}

	/**
	 * An author pulling a submission back before anyone looked at it is a
	 * withdrawal, not a rejection - the prior status alone would say "reject".
	 */
	public function test_an_author_withdrawing_a_pending_submission(): void {
		$listing = $this->listing_in_space( Space_Listings_Model::STATUS_PENDING );
		wp_set_current_user( $this->author );

		$this->remove( $listing );

		$this->assertSame( 'withdraw', $this->fired[0][4] );
		$this->assertSame( Space_Listings_Model::STATUS_PENDING, $this->fired[0][3] );
		$this->assertEmpty( $this->rejected, 'Withdrawing your own submission must not look like a rejection.' );
	}

	/**
	 * The first three arguments are unchanged, so a listener written before
	 * 1.8.0 keeps working.
	 */
	public function test_the_original_three_arguments_are_unchanged(): void {
		$listing = $this->listing_in_space( Space_Listings_Model::STATUS_APPROVED );
		wp_set_current_user( $this->curator );

		$this->remove( $listing );

		$this->assertSame( $listing, (int) $this->fired[0][0] );
		$this->assertSame( self::SPACE_ID, (int) $this->fired[0][1] );
		$this->assertSame( $this->curator, (int) $this->fired[0][2] );
	}
}
