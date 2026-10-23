<?php
/**
 * Category, location, feature and tag filters reach every listing, not only
 * the newest MAX_PHASE_1_CANDIDATES of them.
 *
 * @package WBListora\Tests
 */

use WBListora\Search\Search_Engine;
use WBListora\Search\Search_Indexer;

/**
 * @group listora
 * @group search
 */
class SearchFiltersPastCapTest extends WP_UnitTestCase {

	private int $old_id;

	public function set_up() {
		parent::set_up();
		global $wpdb;
		add_filter( 'wb_listora_search_cache_ttl', '__return_zero' );

		$this->old_id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => 'Oldest diner',
			)
		);
		wp_set_object_terms( $this->old_id, 'qa-cap-cat', 'listora_listing_cat' );
		wp_set_object_terms( $this->old_id, 'qa-cap-wifi', 'listora_listing_feature' );
		wp_set_object_terms( $this->old_id, 'qa-cap-tag', 'listora_listing_tag' );
		wp_set_object_terms( $this->old_id, 'qa-cap-town', 'listora_listing_location' );
		( new Search_Indexer() )->index_listing( $this->old_id );
		$wpdb->update( $wpdb->prefix . 'listora_search_index', array( 'lat' => 40.1, 'lng' => -73.1 ), array( 'listing_id' => $this->old_id ) );
		$wpdb->replace( $wpdb->prefix . 'listora_geo', array( 'listing_id' => $this->old_id, 'lat' => 40.1, 'lng' => -73.1, 'city' => 'Qacapville' ) );

		// More newer, unfiled listings than the candidate cap.
		$rows = array();
		for ( $i = 1; $i <= Search_Engine::MAX_PHASE_1_CANDIDATES + 1; $i++ ) {
			$rows[] = $wpdb->prepare( "(%d, 'publish', 'Filler', '', '', 40.1, -73.1)", 900000 + $i );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- rows prepared above
		$wpdb->query( "INSERT INTO {$wpdb->prefix}listora_search_index (listing_id, status, title, content_text, meta_text, lat, lng) VALUES " . implode( ',', $rows ) );
	}

	/**
	 * @return array<string, array{0: array<string,mixed>}>
	 */
	public function filters(): array {
		return array(
			'category'      => array( array( 'category' => 'qa-cap-cat' ) ),
			'feature'       => array( array( 'features' => 'qa-cap-wifi' ) ),
			'tag'           => array( array( 'tags' => 'qa-cap-tag' ) ),
			'location term' => array( array( 'location' => 'qa-cap-town' ) ),
			'location text' => array( array( 'location' => 'Qacapville' ) ),
		);
	}

	/**
	 * @dataProvider filters
	 *
	 * @param array<string,mixed> $filter One filter.
	 */
	public function test_an_old_listing_still_matches( array $filter ) {
		$result = ( new Search_Engine() )->search( $filter + array( 'per_page' => 7 ) );

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( array( $this->old_id ), $result['listing_ids'] );
	}

	public function test_an_unknown_term_matches_nothing() {
		$this->assertSame( 0, ( new Search_Engine() )->search( array( 'category' => 'no-such-cat' ) )['total'] );
		$this->assertSame( 0, ( new Search_Engine() )->search( array( 'features' => 'qa-cap-wifi,no-such-feature' ) )['total'] );
	}

	public function test_asking_for_facets_does_not_cap_the_total() {
		$result = ( new Search_Engine() )->search( array( 'facets' => true ) );

		$this->assertGreaterThan( Search_Engine::MAX_PHASE_1_CANDIDATES, $result['total'] );
		$this->assertNotEmpty( $result['facets'] );
	}

	public function test_map_clusters_follow_the_category_filter() {
		$clusters = ( new Search_Engine() )->map_clusters( array( 'category' => 'qa-cap-cat', 'zoom' => 3 ) );

		$this->assertSame( 1, $clusters['total'] );
	}
}
