<?php
/**
 * Listings, Needs and Plans use their own nouns in save notices and labels.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class PostTypeNoticesTest extends WP_UnitTestCase {

	public function test_listing_save_notices_name_the_listing_not_a_post() {
		$messages = apply_filters( 'post_updated_messages', array() );

		$this->assertSame( 'Listing published.', $messages['listora_listing'][6] );
		$this->assertSame( 'Listing updated.', $messages['listora_listing'][1] );
		$this->assertSame( 'Listing scheduled.', $messages['listora_listing'][9] );
		$this->assertStringNotContainsStringIgnoringCase( 'post', implode( ' ', array_filter( $messages['listora_listing'], 'is_string' ) ) );
	}

	public function test_bulk_notices_use_the_plural_noun() {
		$counts   = array( 'updated' => 3, 'locked' => 0, 'deleted' => 1, 'trashed' => 2, 'untrashed' => 1 );
		$messages = apply_filters( 'bulk_post_updated_messages', array(), $counts );

		$this->assertSame( '3 listings updated.', $messages['listora_listing']['updated'] );
		$this->assertSame( '1 listing permanently deleted.', $messages['listora_listing']['deleted'] );
	}

	public function test_listing_labels_have_no_post_fallbacks() {
		$labels = get_post_type_object( 'listora_listing' )->labels;

		$this->assertSame( 'View Listings', $labels->view_items );
		$this->assertSame( 'Listing scheduled.', $labels->item_scheduled );
	}
}
