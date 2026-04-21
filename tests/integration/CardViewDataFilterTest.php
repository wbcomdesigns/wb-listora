<?php
/**
 * Integration tests for the wb_listora_card_view_data filter.
 *
 * Protects the Bundle 40 contract: wb_listora_prepare_card_data() MUST
 * fire apply_filters('wb_listora_card_view_data', $card_data, $post_id, $post)
 * so customers can add/override card fields (used by listing-grid,
 * listing-featured, and the standalone listing-card block).
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;

/**
 * @group listora
 * @group card-view-data
 */
class CardViewDataFilterTest extends WP_UnitTestCase {

	/**
	 * Create a published listing for the filter to assemble data from.
	 */
	private function create_listing(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => 'Sample Listing',
				'post_content' => 'Some description.',
			)
		);
	}

	/**
	 * The filter must fire and receive the card data array.
	 */
	public function test_filter_fires_with_card_data_array() {
		$listing_id = $this->create_listing();

		$captured = null;
		add_filter(
			'wb_listora_card_view_data',
			static function ( $data, $post_id, $post ) use ( &$captured ) {
				$captured = compact( 'data', 'post_id', 'post' );
				return $data;
			},
			10,
			3
		);

		$result = wb_listora_prepare_card_data( $listing_id );

		$this->assertIsArray( $result, 'Helper must return array for a valid listing.' );
		$this->assertNotNull( $captured, 'Filter must have fired.' );
		$this->assertSame( $listing_id, $captured['post_id'] );
		$this->assertInstanceOf( \WP_Post::class, $captured['post'] );
		$this->assertArrayHasKey( 'id', $captured['data'] );
		$this->assertArrayHasKey( 'title', $captured['data'] );
		$this->assertArrayHasKey( 'badges', $captured['data'] );
	}

	/**
	 * Filter return value must replace the returned card data.
	 */
	public function test_filter_return_value_replaces_card_data() {
		$listing_id = $this->create_listing();

		add_filter(
			'wb_listora_card_view_data',
			static function ( $data ) {
				$data['custom_field'] = 'injected';
				$data['title']        = 'Filtered Title';
				return $data;
			},
			10
		);

		$result = wb_listora_prepare_card_data( $listing_id );

		$this->assertSame( 'injected', $result['custom_field'] );
		$this->assertSame( 'Filtered Title', $result['title'] );
	}

	/**
	 * Filter must receive the standard card shape — regression catch if keys
	 * are renamed or dropped.
	 */
	public function test_filter_receives_standard_card_shape() {
		$listing_id = $this->create_listing();

		$data = wb_listora_prepare_card_data( $listing_id );

		$expected_keys = array(
			'id',
			'title',
			'link',
			'excerpt',
			'type',
			'meta',
			'image',
			'location',
			'rating',
			'card_fields',
			'features',
			'badges',
		);
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $data, "Card data must include '$key' key." );
		}

		$this->assertArrayHasKey( 'featured', $data['badges'] );
		$this->assertArrayHasKey( 'verified', $data['badges'] );
		$this->assertArrayHasKey( 'claimed', $data['badges'] );

		$this->assertArrayHasKey( 'average', $data['rating'] );
		$this->assertArrayHasKey( 'count', $data['rating'] );
	}

	/**
	 * Helper must return null for missing / deleted posts.
	 */
	public function test_helper_returns_null_for_missing_post() {
		$this->assertNull( wb_listora_prepare_card_data( 9999999 ) );
	}
}
