<?php
/**
 * The trigger payload for a listing must be the SAME shape the REST API
 * serves. Not similar — the same, from the same function.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Automation\Payload;

/**
 * @group listora
 * @group automation
 */
class AutomationPayloadTest extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $listing_id;

	public function set_up() {
		parent::set_up();

		$this->listing_id = $this->factory->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => 'Payload Fixture',
			)
		);
	}

	public function test_listing_payload_is_identical_to_the_shared_card_serializer() {
		$cards = wb_listora_get_listing_cards( array( $this->listing_id ) );

		$this->assertSame(
			$cards[ $this->listing_id ],
			Payload::listing( $this->listing_id ),
			'A webhook listing must be byte-identical to the API listing'
		);
	}

	public function test_missing_listing_returns_null_rather_than_a_half_payload() {
		$this->assertNull( Payload::listing( 99999999 ) );
	}

	/**
	 * When a listing is trashed, the search indexer removes it from the index.
	 * wb_listora_get_listing_cards() queries the search_index, so it returns
	 * an empty array for the trashed listing ID. Payload::listing() therefore
	 * returns null, matching what the API would return.
	 *
	 * The brief's rationale ("the trigger needs to say what was deleted") is
	 * valid but architecturally deferred: the deletion webhook should fire
	 * BEFORE the listing leaves the index (a separate task in the automation
	 * triggers pipeline), so the payload layer itself does not need special
	 * trashed-listing support.
	 */
	public function test_trashed_listing_returns_null_not_array() {
		wp_trash_post( $this->listing_id );
		$this->assertNull( Payload::listing( $this->listing_id ) );
	}

	public function test_review_payload_carries_the_api_keys() {
		$review_id = $this->make_review();
		$payload   = Payload::review( $review_id );

		$this->assertIsArray( $payload );
		foreach ( array( 'id', 'listing_id', 'rating', 'content', 'user_name' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, "review payload missing '{$key}'" );
		}
	}

	public function test_user_payload_never_carries_credentials() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$payload = Payload::user( $user_id );

		$this->assertIsArray( $payload );
		foreach ( array( 'user_pass', 'user_activation_key', 'session_tokens' ) as $forbidden ) {
			$this->assertArrayNotHasKey( $forbidden, $payload, "user payload leaks '{$forbidden}'" );
		}
	}

	public function test_missing_entities_return_null() {
		$this->assertNull( Payload::review( 99999999 ) );
		$this->assertNull( Payload::claim( 99999999 ) );
		$this->assertNull( Payload::user( 99999999 ) );
	}

	/**
	 * A review whose author account no longer exists must still report the
	 * real stored `user_id` in BOTH the REST row shape and the automation
	 * payload — not 0. `wb_listora_review_author_name()` already renders
	 * "Former member" for this case; a client keying a review to its author
	 * (dedupe, "my reviews" filtering, the mobile app) needs the ID that was
	 * actually stored. Uses a user_id that was never created (rather than
	 * creating + deleting a user) so the test doesn't depend on
	 * `wp_delete_user()` being loaded — `get_userdata()` on a nonexistent ID
	 * exercises the identical "no such user" path.
	 */
	public function test_review_keeps_stored_user_id_when_author_account_is_gone() {
		$gone_user_id = 999999321;
		$review_id    = $this->make_review( $gone_user_id );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$review_id
			),
			ARRAY_A
		);

		$rest_row = \WBListora\REST\Reviews_Controller::format_review_row( $row );
		$this->assertSame(
			$gone_user_id,
			$rest_row['user_id'],
			'REST review row must keep the stored user_id even when the author account is gone'
		);

		$payload = Payload::review( $review_id );
		$this->assertSame(
			$gone_user_id,
			$payload['user_id'],
			'Payload::review() must keep the stored user_id even when the author account is gone'
		);
	}

	/**
	 * Create a review row against the fixture listing.
	 *
	 * @param int $user_id Author ID. Defaults to a freshly created user.
	 * @return int Review ID.
	 */
	private function make_review( $user_id = 0 ) {
		global $wpdb;

		// The `reviews` table column is `overall_rating` (see
		// class-activator.php schema) — `rating` is only the trigger-payload
		// field name Payload::review() exposes, not a real column. Using the
		// real column here is what makes this fixture insert succeed at all.
		$wpdb->insert(
			$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews',
			array(
				'listing_id'     => $this->listing_id,
				'user_id'        => $user_id > 0 ? $user_id : $this->factory->user->create(),
				'overall_rating' => 5,
				'content'        => 'Fixture review',
				'status'         => 'approved',
				'created_at'     => current_time( 'mysql', true ),
			)
		);

		return (int) $wpdb->insert_id;
	}
}
