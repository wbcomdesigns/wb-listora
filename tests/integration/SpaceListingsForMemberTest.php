<?php
/**
 * One member's listings in a space, and statuses for many listings at once.
 *
 * BuddyNext could only check the listings it had on screen (one picker page
 * and the first showcase page), one query per row, so an approved listing
 * outside those pages was never shown as listed (BuddyNext card 10463031529,
 * WB Listora card 10463269021).
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WBListora\Core\Space_Listings_Model;
use WP_UnitTestCase;

/**
 * @group listora
 * @group spaces
 */
class SpaceListingsForMemberTest extends WP_UnitTestCase {

	const SPACE_ID = 9911;

	/** @var int */
	private $member;

	/** @var int */
	private $other;

	/** @var int */
	private $curator;

	public function set_up() {
		parent::set_up();
		$this->member  = self::factory()->user->create();
		$this->other   = self::factory()->user->create();
		$this->curator = self::factory()->user->create();
	}

	private function listing( int $author, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => $status,
				'post_author' => $author,
			)
		);
	}

	public function test_a_member_sees_every_own_link_with_its_status() {
		$submitted = $this->listing( $this->member );
		Space_Listings_Model::submit( self::SPACE_ID, $submitted, $this->member );

		// Added by the curator, already approved: still the author's business.
		$curated = $this->listing( $this->member );
		Space_Listings_Model::add_approved( self::SPACE_ID, $curated, $this->curator );

		// Not the member's, not published, or in another space.
		Space_Listings_Model::submit( self::SPACE_ID, $this->listing( $this->other ), $this->other );
		$draft = $this->listing( $this->member, 'publish' );
		Space_Listings_Model::submit( self::SPACE_ID, $draft, $this->member );
		wp_update_post( array( 'ID' => $draft, 'post_status' => 'draft' ) );
		Space_Listings_Model::submit( self::SPACE_ID + 1, $this->listing( $this->member ), $this->member );

		$mine = Space_Listings_Model::for_member( self::SPACE_ID, $this->member );

		$this->assertSame( 2, $mine['total'] );
		$this->assertEqualSets(
			array( $submitted => 'pending', $curated => 'approved' ),
			array_column( $mine['rows'], 'status', 'listing_id' )
		);
		$this->assertSame( 0, Space_Listings_Model::for_member( self::SPACE_ID, $this->curator )['total'], 'The curator does not own what they added.' );
	}

	public function test_pages_cover_every_row_once() {
		$ids = array();
		for ( $i = 0; $i < 45; $i++ ) {
			$ids[] = $id = $this->listing( $this->member );
			Space_Listings_Model::add_approved( self::SPACE_ID, $id, $this->curator );
		}

		$seen = array();
		for ( $offset = 0; $offset < 45; $offset += 20 ) {
			$page = Space_Listings_Model::for_member( self::SPACE_ID, $this->member, 20, $offset );
			$this->assertSame( 45, $page['total'] );
			$seen = array_merge( $seen, array_column( $page['rows'], 'listing_id' ) );
		}

		$this->assertCount( 45, $seen );
		$this->assertEqualSets( $ids, array_unique( $seen ), 'Rows added in the same second still page without repeats or gaps.' );
	}

	public function test_statuses_for_many_listings_is_one_query() {
		global $wpdb;
		$ids = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$ids[] = $id = $this->listing( $this->member );
			if ( 0 === $i % 2 ) {
				Space_Listings_Model::add_approved( self::SPACE_ID, $id, $this->curator );
			} elseif ( 0 === $i % 5 ) {
				Space_Listings_Model::submit( self::SPACE_ID, $id, $this->member );
			}
		}

		$before   = $wpdb->num_queries;
		$statuses = Space_Listings_Model::statuses_for( self::SPACE_ID, $ids );

		$this->assertSame( 1, $wpdb->num_queries - $before, 'One query for 50 listings.' );
		foreach ( $ids as $id ) {
			$this->assertSame( Space_Listings_Model::status_for( self::SPACE_ID, $id ), $statuses[ $id ] ?? '', 'Same answer as status_for().' );
		}
		$this->assertSame( array(), Space_Listings_Model::statuses_for( self::SPACE_ID, array() ) );
	}
}
