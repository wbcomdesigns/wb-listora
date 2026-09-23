<?php
/**
 * Each grid carries its own numbers.
 *
 * Card 10314572173: every grid seeded its type, page size and page count into
 * the single shared `listora/directory` state, so two grids on one page
 * overwrote each other and the last one rendered won. Clicking the first grid's
 * Load More fetched the second grid's type and page size - the restaurants
 * section filled up with hotels.
 *
 * The client half (which grid the cards are appended to) is a browser
 * behaviour and lives in the journey. This locks the server half: the context
 * exists, and it describes THIS grid.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   grid-context
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;

/**
 * @group listora
 * @group grid-context
 */
class GridPerBlockContextTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		foreach ( array( 'qa-alpha', 'qa-beta' ) as $slug ) {
			if ( ! get_term_by( 'slug', $slug, 'listora_listing_type' ) ) {
				wp_insert_term( strtoupper( $slug ), 'listora_listing_type', array( 'slug' => $slug ) );
			}
		}

		// The registry caches the type list, and the indexer resolves a
		// listing's type through it. Without this the index rows are written
		// with an empty `listing_type`, every search returns 0, and the totals
		// asserted below would pass as 0 == 0 while proving nothing.
		\WBListora\Core\Listing_Type_Registry::instance()->flush();
		\WBListora\Core\Listing_Type_Registry::instance()->init();
	}

	/**
	 * @param string $type  Listing type slug.
	 * @param int    $count How many to create.
	 */
	private function seed( string $type, int $count ): void {
		for ( $i = 0; $i < $count; $i++ ) {
			$listing = self::factory()->post->create(
				array(
					'post_type'   => 'listora_listing',
					'post_status' => 'publish',
					'post_title'  => $type . ' listing ' . $i,
				)
			);
			wp_set_object_terms( $listing, $type, 'listora_listing_type' );

			// The grid reads the search index, not wp_posts. The factory
			// bypasses the save_post path that normally indexes a listing, so
			// without this every grid renders zero results and the totals
			// asserted below would all be 0 - passing for the wrong reason.
			( new \WBListora\Search\Search_Indexer() )->index_listing( $listing );
		}
	}

	/**
	 * Pull every grid context out of rendered HTML, in document order.
	 *
	 * @param string $html Rendered page HTML.
	 * @return array<int, array<string, mixed>>
	 */
	private function contexts( string $html ): array {
		preg_match_all( '/data-wp-context="([^"]*gridType[^"]*)"/', $html, $matches );

		return array_map(
			static function ( $raw ) {
				return json_decode( html_entity_decode( $raw, ENT_QUOTES ), true );
			},
			$matches[1]
		);
	}

	/**
	 * The defect, stated on the server side: two grids, two sets of numbers.
	 */
	public function test_two_grids_carry_their_own_type_and_page_size(): void {
		$this->seed( 'qa-alpha', 4 );
		$this->seed( 'qa-beta', 9 );

		$html = do_blocks(
			'<!-- wp:listora/listing-grid {"listingType":"qa-alpha","perPage":3} /-->'
			. '<!-- wp:listora/listing-grid {"listingType":"qa-beta","perPage":6} /-->'
		);

		$contexts = $this->contexts( $html );

		$this->assertCount( 2, $contexts, 'Each grid must emit its own context.' );

		$this->assertSame( 'qa-alpha', $contexts[0]['gridType'] );
		$this->assertSame( 3, $contexts[0]['gridPerPage'] );

		$this->assertSame( 'qa-beta', $contexts[1]['gridType'] );
		$this->assertSame( 6, $contexts[1]['gridPerPage'] );
	}

	/**
	 * The page counts are per grid too - they are what Load More pages against.
	 */
	public function test_each_grid_reports_its_own_totals(): void {
		$this->seed( 'qa-alpha', 4 );
		$this->seed( 'qa-beta', 9 );

		$contexts = $this->contexts(
			do_blocks(
				'<!-- wp:listora/listing-grid {"listingType":"qa-alpha","perPage":3} /-->'
				. '<!-- wp:listora/listing-grid {"listingType":"qa-beta","perPage":6} /-->'
			)
		);

		$this->assertSame( 4, $contexts[0]['gridTotalItems'] );
		$this->assertSame( 2, $contexts[0]['gridTotalPages'], '4 items at 3 per page is 2 pages.' );
		$this->assertSame( 3, $contexts[0]['gridPageTo'] );

		$this->assertSame( 9, $contexts[1]['gridTotalItems'] );
		$this->assertSame( 2, $contexts[1]['gridTotalPages'] );
		$this->assertSame( 6, $contexts[1]['gridPageTo'] );
	}

	/**
	 * The contexts must actually DIFFER. Asserting each one in isolation would
	 * still pass if both carried the same values.
	 */
	public function test_the_two_contexts_are_not_the_same(): void {
		$this->seed( 'qa-alpha', 4 );
		$this->seed( 'qa-beta', 9 );

		$contexts = $this->contexts(
			do_blocks(
				'<!-- wp:listora/listing-grid {"listingType":"qa-alpha","perPage":3} /-->'
				. '<!-- wp:listora/listing-grid {"listingType":"qa-beta","perPage":6} /-->'
			)
		);

		$this->assertNotSame( $contexts[0], $contexts[1] );
	}

	/**
	 * A single grid is the common case and must be unchanged.
	 */
	public function test_a_single_grid_still_carries_a_context(): void {
		$this->seed( 'qa-alpha', 4 );

		$contexts = $this->contexts( do_blocks( '<!-- wp:listora/listing-grid {"listingType":"qa-alpha","perPage":3} /-->' ) );

		$this->assertCount( 1, $contexts );
		$this->assertSame( 'qa-alpha', $contexts[0]['gridType'] );
		$this->assertFalse( $contexts[0]['gridLoadingMore'] );
		$this->assertSame( 1, $contexts[0]['gridLoadedPages'] );
	}

	/**
	 * An unpinned grid carries an empty type rather than omitting the key, so
	 * the client can read it without a guard.
	 */
	public function test_an_unpinned_grid_carries_an_empty_type(): void {
		$this->seed( 'qa-alpha', 2 );

		$contexts = $this->contexts( do_blocks( '<!-- wp:listora/listing-grid /-->' ) );

		$this->assertArrayHasKey( 'gridType', $contexts[0] );
		$this->assertSame( '', $contexts[0]['gridType'] );
	}
}
