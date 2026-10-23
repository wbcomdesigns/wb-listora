<?php
/**
 * Every search filter reaches every listing, not only the newest
 * MAX_PHASE_1_CANDIDATES of them.
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
		$this->index_fields( $this->old_id );
		update_post_meta( $this->old_id, '_listora_start_date', gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( current_time( 'Y-m-d' ) ) ) ) );
		update_post_meta( $this->old_id, '_listora_end_date', gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( current_time( 'Y-m-d' ) ) ) ) );
		$this->open_all_week( $this->old_id );

		// More newer, unfiled listings than the candidate cap.
		$rows = array();
		for ( $i = 1; $i <= Search_Engine::MAX_PHASE_1_CANDIDATES + 1; $i++ ) {
			$rows[]  = $wpdb->prepare( "(%d, 'publish', 'Filler', '', '', 40.1, -73.1)", 900000 + $i );
			$hours[] = $wpdb->prepare( "(%d, 9, 0, '09:00:00', '17:00:00', 0, 0, 'UTC')", 900000 + $i );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- rows prepared above
		$wpdb->query( "INSERT INTO {$wpdb->prefix}listora_search_index (listing_id, status, title, content_text, meta_text, lat, lng) VALUES " . implode( ',', $rows ) );
		// Filler hours on a day that never comes: read by the Open-now scan, never open.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- rows prepared above
		$wpdb->query( "INSERT INTO {$wpdb->prefix}listora_hours (listing_id, day_of_week, slot, open_time, close_time, is_24h, is_closed, timezone) VALUES " . implode( ',', $hours ) );
	}

	private function index_fields( int $listing_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'listora_field_index';
		$wpdb->replace( $table, array( 'listing_id' => $listing_id, 'field_key' => 'insurance', 'field_value' => 'Aetna' ) );
		$wpdb->replace( $table, array( 'listing_id' => $listing_id, 'field_key' => 'bedrooms', 'field_value' => '3', 'numeric_value' => 3 ) );
	}

	private function open_all_week( int $listing_id ): void {
		global $wpdb;
		for ( $day = 0; $day < 7; $day++ ) {
			$wpdb->replace( $wpdb->prefix . 'listora_hours', array( 'listing_id' => $listing_id, 'day_of_week' => $day, 'slot' => 0, 'open_time' => '00:00:00', 'close_time' => '00:00:00', 'is_24h' => 1, 'is_closed' => 0, 'timezone' => 'UTC' ) );
		}
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
			'field value'   => array( array( 'field_filters' => array( 'insurance' => 'Aetna' ) ) ),
			'field any-of'  => array( array( 'field_filters' => array( 'insurance' => array( 'Cigna', 'Aetna' ) ) ) ),
			'field range'   => array( array( 'field_filters' => array( 'bedrooms' => array( 'min' => 2, 'max' => 4 ) ) ) ),
			'field max'     => array( array( 'field_filters' => array( 'bedrooms' => array( 'max' => 3 ) ) ) ),
			'event today'   => array( array( 'date_filter' => 'today' ) ),
			'event range'   => array( array( 'date_from' => '2000-01-01' ) ),
			'open now'      => array( array( 'open_now' => true ) ),
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

	public function test_open_now_reaches_past_the_first_batch() {
		$late = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'import_id'   => 990000,
			)
		);
		( new Search_Indexer() )->index_listing( $late );
		$this->open_all_week( $late );

		$this->assertGreaterThan( 900000 + Search_Engine::MAX_PHASE_1_CANDIDATES, $late );
		$result = ( new Search_Engine() )->search( array( 'open_now' => true ) );
		$this->assertSame( 2, $result['total'] );
	}

	public function test_a_radius_is_a_circle_not_its_box() {
		global $wpdb;
		// Inside the 10 km box around the centre, about 12.6 km away from it.
		$wpdb->insert( $wpdb->prefix . 'listora_search_index', array( 'listing_id' => 899999, 'status' => 'publish', 'title' => 'Corner', 'content_text' => '', 'meta_text' => '', 'lat' => 40.18, 'lng' => -72.99 ) );

		$result = ( new Search_Engine() )->search( array( 'lat' => 40.1, 'lng' => -73.1, 'radius' => 10, 'radius_unit' => 'km' ) );
		$this->assertSame( Search_Engine::MAX_PHASE_1_CANDIDATES + 2, $result['total'], 'The old listing and the fillers, not the corner.' );
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
