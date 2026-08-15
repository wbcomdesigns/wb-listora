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
}
