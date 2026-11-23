<?php
/**
 * One listing, every producer: the web card, the REST card helper, /search, /favorites and the detail
 * endpoint say the same title, place and image. Add a new producer here when one is added.
 *
 * The fields below are the ones that already drifted once (cards 10531791500, 10533264268, 10533264518).
 * Keys that deliberately differ between producers (link/url, type shape) are not asserted.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ListingProducersParityTest extends WP_UnitTestCase {

	private int $listing = 0;

	public function set_up() {
		parent::set_up();
		global $wpdb;
		\WBListora\Core\Listing_Type_Registry::instance()->register(
			'shape_par',
			new \WBListora\Core\Listing_Type(
				'shape_par',
				array( 'name' => 'Shape test' ),
				array(
					array(
						'key'    => 'main',
						'label'  => 'Main',
						'fields' => array(
							array(
								'key'   => 'address',
								'type'  => 'address',
								'label' => 'Address',
							),
						),
					),
				)
			)
		);
		$member        = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $member,
				'post_title'  => 'Tom &amp; Jerry Diner',
			)
		);
		wp_set_object_terms( $this->listing, 'shape_par', 'listora_listing_type' );
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.jpg', $this->listing );
		set_post_thumbnail( $this->listing, $attachment );
		\WBListora\Core\Meta_Handler::set_value(
			$this->listing,
			'address',
			array(
				'city'    => 'Austin',
				'state'   => 'TX',
				'country' => 'US',
			)
		);
		( new \WBListora\Search\Search_Indexer() )->index_listing( $this->listing );
		$wpdb->insert(
			$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'favorites',
			array(
				'user_id'    => $member,
				'listing_id' => $this->listing,
				'collection' => 'default',
				'created_at' => current_time( 'mysql', true ),
			)
		);
		wp_set_current_user( $member );
	}

	/**
	 * Each producer's view of the listing: title, place (null when the producer has none) and image URL.
	 *
	 * @return array<string, array{title: string, location: ?string, image: string}>
	 */
	private function producers(): array {
		$id   = $this->listing;
		$card = wb_listora_prepare_card_data( $id );
		$rest = wb_listora_get_listing_cards( array( $id ) )[ $id ];

		$search_req = new WP_REST_Request( 'GET', '/listora/v1/search' );
		$search_req->set_param( 'type', 'shape_par' );
		$search    = rest_do_request( $search_req )->get_data();
		$search    = array_values( array_filter( $search['listings'], static fn( $l ) => (int) $l['id'] === $id ) )[0];
		$favorites = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/favorites' ) )->get_data()['favorites'][0];
		$detail    = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/listings/' . $id . '/detail' ) )->get_data();

		return array(
			'web card'    => array( 'title' => $card['title'], 'location' => $card['location'], 'image' => (string) ( $card['image']['full'] ?? '' ) ),
			'card helper' => array( 'title' => $rest['title'], 'location' => $rest['location'], 'image' => (string) ( $rest['featured_image']['full'] ?? '' ) ),
			'/search'     => array( 'title' => $search['title'], 'location' => null, 'image' => (string) ( $search['featured_image']['full'] ?? '' ) ),
			'/favorites'  => array( 'title' => $favorites['title'], 'location' => $favorites['location'], 'image' => (string) ( $favorites['featured_image']['full'] ?? '' ) ),
			'/detail'     => array( 'title' => $detail['title'], 'location' => null, 'image' => (string) ( $detail['featured_image']['full'] ?? '' ) ),
		);
	}

	public function test_every_producer_says_the_same_title_place_and_image() {
		$producers = $this->producers();

		foreach ( $producers as $name => $row ) {
			$this->assertSame( 'Tom & Jerry Diner', $row['title'], "{$name}: title" );
			$this->assertNotSame( '', $row['image'], "{$name}: image" );
			if ( null !== $row['location'] ) {
				$this->assertSame( 'Austin, TX', $row['location'], "{$name}: location" );
			}
		}
		$this->assertCount( 1, array_unique( array_column( $producers, 'image' ) ), 'one image URL everywhere' );
	}
}
