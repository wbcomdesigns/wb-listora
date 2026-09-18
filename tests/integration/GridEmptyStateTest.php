<?php
/**
 * The listing grid's empty state must not blame the visitor for the owner's pin.
 *
 * Card 10217484053: every type-aware block asked the editor to hand-type a
 * listing-type slug. One transposed letter rendered an empty grid whose copy
 * read "Try adjusting your filters, or be the first to add a listing" - filters
 * the visitor never set - and offered a Clear All Filters button that reloaded
 * the same empty grid.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   grid-empty-state
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;

/**
 * @group listora
 * @group grid-empty-state
 */
class GridEmptyStateTest extends WP_UnitTestCase {

	/**
	 * Render the grid block with the given attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	private function render( array $attributes ): string {
		// An empty array encodes as `[]`, which the block parser rejects - the
		// attribute comment has to be a JSON object or absent entirely.
		$json  = $attributes ? wp_json_encode( $attributes ) . ' ' : '';
		$block = '<!-- wp:listora/listing-grid ' . $json . '/-->';

		return (string) do_blocks( $block );
	}

	public function tear_down() {
		unset( $_GET['keyword'] );
		parent::tear_down();
	}

	/**
	 * A slug that is not a listing type on this site: name the type, say why
	 * it is empty, and drop the button that cannot help.
	 */
	public function test_a_pinned_type_with_no_listings_explains_itself(): void {
		$html = $this->render( array( 'listingType' => 'restaurnt' ) );

		$this->assertStringContainsString( 'No restaurnt listings yet', $html );
		$this->assertStringContainsString( 'This section only shows listings of one type', $html );
		$this->assertStringNotContainsString( 'Try adjusting your filters', $html );
		$this->assertStringNotContainsString( 'Clear All Filters', $html );
	}

	/**
	 * A real type renders its NAME, not its slug - the owner picked "Education",
	 * so that is the word the visitor should read.
	 */
	public function test_a_known_type_is_named_not_slugged(): void {
		$term = wp_insert_term( 'QA Empty Type', 'listora_listing_type', array( 'slug' => 'qa-empty-type' ) );
		$this->assertIsArray( $term );

		\WBListora\Core\Listing_Type_Registry::instance()->flush();
		\WBListora\Core\Listing_Type_Registry::instance()->init();

		$html = $this->render( array( 'listingType' => 'qa-empty-type' ) );

		$this->assertStringContainsString( 'No QA Empty Type listings yet', $html );
		$this->assertStringNotContainsString( 'No qa-empty-type listings yet', $html );
	}

	/**
	 * The visitor's own filter is a different situation: the old copy and the
	 * Clear All Filters button are exactly right, pinned type or not.
	 */
	public function test_a_visitor_filter_keeps_the_clearable_empty_state(): void {
		$_GET['keyword'] = 'nothing-matches-this-keyword';

		$html = $this->render( array( 'listingType' => 'restaurnt' ) );

		$this->assertStringContainsString( 'Try adjusting your filters', $html );
		$this->assertStringContainsString( 'Clear All Filters', $html );
		$this->assertStringNotContainsString( 'No restaurnt listings yet', $html );
	}

	/**
	 * An unpinned grid is unchanged.
	 */
	public function test_an_unpinned_grid_keeps_the_generic_empty_state(): void {
		$html = $this->render( array() );

		$this->assertStringContainsString( 'No listings found', $html );
		$this->assertStringContainsString( 'Clear All Filters', $html );
	}
}
