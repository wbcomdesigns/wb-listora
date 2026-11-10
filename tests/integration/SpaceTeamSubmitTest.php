<?php
/**
 * The space team's own listing joins the showcase without review.
 *
 * A space owner, moderator or organiser submitting their own listing got a
 * pending entry they then had to approve themselves (WB Listora card
 * 10453695634, from BuddyNext card 10446374175). Members still go to review.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WBListora\Core\Space_Listings_Model;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group spaces
 */
class SpaceTeamSubmitTest extends WP_UnitTestCase {

	const SPACE_ID = 5151;

	/** @var int[] Users BuddyNext would call the space team. */
	private $team = array();

	/** @var array<int, array<int, mixed>> */
	private $approved = array();

	/** @var array<int, array<int, mixed>> */
	private $submitted = array();

	public function set_up() {
		parent::set_up();
		add_filter( 'wb_listora_user_is_space_member', '__return_true' );
		add_filter( 'wb_listora_space_showcase_enabled', '__return_true' );
		add_filter( 'wb_listora_user_can_moderate_space', array( $this, 'is_team' ), 10, 3 );
		add_action(
			'wb_listora_listing_approved_in_space',
			function ( ...$args ) {
				$this->approved[] = $args;
			},
			10,
			3
		);
		add_action(
			'wb_listora_listing_submitted_to_space',
			function ( ...$args ) {
				$this->submitted[] = $args;
			},
			10,
			3
		);
		rest_get_server();
	}

	public function tear_down() {
		remove_filter( 'wb_listora_user_is_space_member', '__return_true' );
		remove_filter( 'wb_listora_space_showcase_enabled', '__return_true' );
		remove_filter( 'wb_listora_user_can_moderate_space', array( $this, 'is_team' ), 10 );
		parent::tear_down();
	}

	public function is_team( $allowed, $space_id, $user_id ) {
		return in_array( (int) $user_id, $this->team, true );
	}

	/**
	 * @return array{0: \WP_REST_Response, 1: int} Response and listing id.
	 */
	private function submit_own_listing( int $user_id ): array {
		$listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);
		wp_set_current_user( $user_id );
		$request = new WP_REST_Request( 'POST', '/listora/v1/listings/' . $listing . '/spaces' );
		$request->set_param( 'space_id', self::SPACE_ID );
		return array( rest_do_request( $request ), $listing );
	}

	public function test_the_space_team_is_added_approved() {
		$owner        = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->team[] = $owner;

		list( $response, $listing ) = $this->submit_own_listing( $owner );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( Space_Listings_Model::STATUS_APPROVED, $response->get_data()['status'] );
		$this->assertSame( Space_Listings_Model::STATUS_APPROVED, Space_Listings_Model::status_for( self::SPACE_ID, $listing ) );
		$this->assertSame( array( array( $listing, self::SPACE_ID, $owner ) ), $this->approved, 'Listeners hear an approval, as for Approve.' );
		$this->assertSame( array(), $this->submitted, 'Nothing waits in the review queue.' );
	}

	public function test_a_member_still_goes_to_review() {
		$member = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		list( $response ) = $this->submit_own_listing( $member );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( Space_Listings_Model::STATUS_PENDING, $response->get_data()['status'] );
		$this->assertSame( array(), $this->approved );
		$this->assertCount( 1, $this->submitted );
	}

	public function test_the_team_is_not_held_by_the_pending_quota() {
		$owner        = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->team[] = $owner;
		add_filter( 'wb_listora_space_pending_submission_limit', static fn() => 1 );

		$this->assertSame( 201, $this->submit_own_listing( $owner )[0]->get_status() );
		$this->assertSame( 201, $this->submit_own_listing( $owner )[0]->get_status(), 'A second listing is added, not refused by the quota.' );
	}
}
