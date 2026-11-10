<?php
/**
 * The space showcase pages over the same rows it counts: published approved listings.
 *
 * Links to drafted, expired or trashed listings stayed in the page window while
 * the total left them out, so published businesses became unreachable
 * (WB Listora card 10488592980).
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
class SpaceShowcasePagingTest extends WP_UnitTestCase {

	const SPACE_ID = 9912;

	private function listing( string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => $status,
			)
		);
	}

	public function test_an_unpublished_link_does_not_take_up_a_page_slot() {
		$curator   = self::factory()->user->create();
		$published = $this->listing();
		$draft     = $this->listing();
		Space_Listings_Model::add_approved( self::SPACE_ID, $published, $curator );
		Space_Listings_Model::add_approved( self::SPACE_ID, $draft, $curator );
		wp_update_post(
			array(
				'ID'          => $draft,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( 1, Space_Listings_Model::approved_count( self::SPACE_ID ) );
		$this->assertSame( array( $published ), Space_Listings_Model::approved_listing_ids( self::SPACE_ID, 1, 0 ) );
		$this->assertSame( array(), Space_Listings_Model::approved_listing_ids( self::SPACE_ID, 1, 1 ) );
	}

	public function test_ids_and_count_agree_for_every_page_size() {
		$curator = self::factory()->user->create();
		for ( $i = 0; $i < 5; $i++ ) {
			Space_Listings_Model::add_approved( self::SPACE_ID, $this->listing( 0 === $i % 2 ? 'publish' : 'trash' ), $curator );
		}

		$total = Space_Listings_Model::approved_count( self::SPACE_ID );
		$seen  = array();
		for ( $page = 0; $page < $total; $page++ ) {
			$seen = array_merge( $seen, Space_Listings_Model::approved_listing_ids( self::SPACE_ID, 1, $page ) );
		}

		$this->assertCount( $total, array_unique( $seen ) );
		$this->assertCount( $total, Space_Listings_Model::approved_listing_ids( self::SPACE_ID ) );
	}
}
