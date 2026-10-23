<?php
/**
 * Two Listora blocks on one page never share an HTML id.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class UniqueBlockIdsTest extends WP_UnitTestCase {

	/**
	 * Ids that appear more than once in the markup.
	 *
	 * @param string $html Rendered markup.
	 * @return string[]
	 */
	private function duplicate_ids( string $html ): array {
		preg_match_all( '/\sid="([^"]+)"/', $html, $found );

		return array_keys( array_filter( array_count_values( $found[1] ), static fn( $n ) => $n > 1 ) );
	}

	public function test_two_search_blocks_have_no_duplicate_ids_and_each_label_points_at_its_own_field() {
		$html = do_blocks( '<!-- wp:listora/listing-search /--><!-- wp:listora/listing-search /-->' );

		$this->assertStringContainsString( 'listora-search__radius-select', $html, 'The search block rendered.' );
		$this->assertSame( array(), $this->duplicate_ids( $html ) );

		preg_match_all( '/<label[^>]*\sfor="([^"]+)"/', $html, $labels );
		foreach ( $labels[1] as $target ) {
			$this->assertSame( 1, substr_count( $html, 'id="' . $target . '"' ), "The label for $target has exactly one target." );
		}
	}
}
