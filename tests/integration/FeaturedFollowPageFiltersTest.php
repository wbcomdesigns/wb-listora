<?php
/**
 * The Featured block is a showcase unless "Follow page filters" is on.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class FeaturedFollowPageFiltersTest extends WP_UnitTestCase {

	private array $seen = array();

	public function tear_down() {
		$_GET = array();
		parent::tear_down();
	}

	private function query_args( array $attrs ): array {
		$this->seen = array();
		add_filter(
			'wb_listora_featured_query_args',
			function ( $args ) {
				$this->seen = $args;
				return $args;
			},
			99
		);
		$_GET = array( 'listora_keyword' => 'pizza', 'listora_category' => 'italian' );
		render_block( array( 'blockName' => 'listora/listing-featured', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
		return $this->seen;
	}

	public function test_default_ignores_the_page_filters() {
		$args = $this->query_args( array() );

		$this->assertArrayNotHasKey( 'keyword', $args );
		$this->assertArrayNotHasKey( 'category', $args );
	}

	public function test_following_page_filters_narrows_the_query_and_keeps_the_blocks_own_settings() {
		$args = $this->query_args( array( 'followPageFilters' => true, 'count' => 3 ) );

		$this->assertSame( 'pizza', $args['keyword'] );
		$this->assertSame( 'italian', $args['category'] );
		$this->assertSame( 3, $args['per_page'], 'The block count still wins.' );
		$this->assertTrue( $args['featured_only'], 'It is still the featured block.' );
	}
}
