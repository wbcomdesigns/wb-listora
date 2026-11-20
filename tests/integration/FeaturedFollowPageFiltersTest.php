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

	private function query_args( array $attrs, array $get = array() ): array {
		$this->seen = array();
		add_filter(
			'wb_listora_featured_query_args',
			function ( $args ) {
				$this->seen = $args;
				return $args;
			},
			99
		);
		$_GET = $get ?: array( 'listora_keyword' => 'pizza', 'listora_category' => 'italian' );
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

	/**
	 * A block pinned to one type reads its type-field filters for THAT type, not the URL's type:
	 * a page filtered on another type must neither drop nor swap them.
	 */
	public function test_a_pinned_block_builds_field_filters_for_its_own_type_not_the_urls() {
		$register = static function ( string $slug, string $field ) {
			\WBListora\Core\Listing_Type_Registry::instance()->register(
				$slug,
				new \WBListora\Core\Listing_Type(
					$slug,
					array( 'name' => $slug ),
					array( array( 'key' => 'main', 'label' => 'Main', 'fields' => array( array( 'key' => $field, 'type' => 'text', 'label' => $field, 'filterable' => true ) ) ) )
				)
			);
		};
		$register( 'pin_food', 'cuisine' );
		$register( 'pin_stay', 'stars' );

		// No type in the URL: the pinned type's filter must still apply.
		$args = $this->query_args( array( 'followPageFilters' => true, 'listingType' => 'pin_food' ), array( 'listora_cuisine' => 'italian' ) );
		$this->assertSame( array( 'cuisine' => 'italian' ), $args['field_filters'] ?? array(), 'pinned type, no URL type' );
		$this->assertSame( 'pin_food', $args['type'] );

		// A different type in the URL: its field keys must not be applied to the pinned block.
		$args = $this->query_args( array( 'followPageFilters' => true, 'listingType' => 'pin_food' ), array( 'listora_type' => 'pin_stay', 'listora_stars' => '5', 'listora_cuisine' => 'italian' ) );
		$this->assertSame( array( 'cuisine' => 'italian' ), $args['field_filters'] ?? array(), 'URL type must not swap the filters' );
		$this->assertSame( 'pin_food', $args['type'] );
	}
}
