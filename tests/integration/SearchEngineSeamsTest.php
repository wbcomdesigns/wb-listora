<?php
/**
 * An extension can scope the directory search on every surface.
 *
 * Card 10156792825. The engine had two `apply_filters` against seventeen
 * `$wpdb` calls, and neither could touch the query: one is a telemetry array
 * passed by value after caching, the other an on/off boolean for a LIKE
 * branch. The one real args filter, `wb_listora_search_args`, lives in the REST
 * controller - so the grid, map and featured blocks, which call the engine
 * directly, bypassed it and could not be scoped at all.
 *
 * BuddyNext is the consumer: a space that wants to show only its own listings
 * needs a seam on the surfaces a visitor actually sees.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   search-seams
 */

namespace WBListora\Tests\Integration;

use WBListora\Search\Search_Engine;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group search-seams
 */
class SearchEngineSeamsTest extends WP_UnitTestCase {

	/**
	 * Listings that a scoping filter should keep.
	 *
	 * @var int[]
	 */
	private $kept = array();

	/**
	 * Listings it should drop.
	 *
	 * @var int[]
	 */
	private $dropped = array();

	public function set_up() {
		parent::set_up();

		// Caching would serve an entry written before the filter was added.
		add_filter( 'wb_listora_search_cache_ttl', '__return_zero' );
		add_filter( 'option_wb_listora_settings', array( $this, 'disable_search_cache' ) );

		foreach ( range( 1, 3 ) as $i ) {
			$this->kept[] = $this->make_listing( 'Keep ' . $i, true );
		}
		foreach ( range( 1, 4 ) as $i ) {
			$this->dropped[] = $this->make_listing( 'Drop ' . $i, false );
		}

		rest_get_server();
	}

	public function tear_down() {
		remove_filter( 'option_wb_listora_settings', array( $this, 'disable_search_cache' ) );
		parent::tear_down();
	}

	/**
	 * @param array<string, mixed>|mixed $settings Stored settings.
	 * @return array<string, mixed>
	 */
	public function disable_search_cache( $settings ) {
		$settings                     = (array) $settings;
		$settings['search_cache_ttl'] = 0;

		return $settings;
	}

	/**
	 * @param string $title Listing title.
	 * @param bool   $keep  Whether the scoping filter keeps it.
	 * @return int
	 */
	private function make_listing( string $title, bool $keep ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		if ( $keep ) {
			update_post_meta( $id, '_qa_seam_keep', 1 );
		}

		( new \WBListora\Search\Search_Indexer() )->index_listing( $id );

		return $id;
	}

	/**
	 * The scoping an integration would actually write.
	 */
	private function add_scoping_filter(): void {
		add_filter(
			'wb_listora_search_where_clauses',
			static function ( $where ) {
				global $wpdb;
				$where[] = "s.listing_id IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_qa_seam_keep' )";

				return $where;
			}
		);
	}

	public function test_parse_args_filter_fires_for_the_engine(): void {
		$seen = null;
		add_filter(
			'wb_listora_search_parse_args',
			static function ( $args ) use ( &$seen ) {
				$seen = $args;

				return $args;
			}
		);

		( new Search_Engine() )->search( array( 'per_page' => 5 ) );

		$this->assertIsArray( $seen, 'The filter must fire for a direct engine call, not only through REST.' );
		$this->assertSame( 5, $seen['per_page'], 'It must receive the PARSED args, defaults applied.' );
	}

	/**
	 * The acceptance test named on the card: one filter, every surface.
	 */
	public function test_one_filter_scopes_the_engine_and_the_rest_route_identically(): void {
		$this->add_scoping_filter();

		$engine = ( new Search_Engine() )->search( array( 'per_page' => 50 ) );

		$request = new WP_REST_Request( 'GET', '/listora/v1/search' );
		$request->set_param( 'per_page', 50 );
		$rest = (array) rest_do_request( $request )->get_data();

		$this->assertSame( 3, (int) $engine['total'], 'The engine must see only the kept listings.' );
		$this->assertSame( 3, (int) $rest['total'], 'REST must agree with the engine.' );

		foreach ( $this->dropped as $id ) {
			$this->assertNotContains( $id, $engine['listing_ids'] );
		}
	}

	/**
	 * The three blocks are the surfaces that had no seam at all - they call the
	 * engine directly and never saw the REST-only args filter.
	 */
	public function test_the_grid_block_is_scoped_too(): void {
		$this->add_scoping_filter();

		$html = do_blocks( '<!-- wp:listora/listing-grid {"perPage":50} /-->' );

		foreach ( $this->kept as $id ) {
			$this->assertStringContainsString( get_the_title( $id ), $html );
		}
		foreach ( $this->dropped as $id ) {
			$this->assertStringNotContainsString( get_the_title( $id ), $html );
		}
	}

	public function test_where_params_can_be_filtered_alongside_the_clauses(): void {
		add_filter(
			'wb_listora_search_where_clauses',
			static function ( $where ) {
				$where[] = 's.title LIKE %s';

				return $where;
			}
		);
		add_filter(
			'wb_listora_search_where_params',
			static function ( $params ) {
				$params[] = 'Keep%';

				return $params;
			}
		);

		$result = ( new Search_Engine() )->search( array( 'per_page' => 50 ) );

		$this->assertSame( 3, (int) $result['total'], 'A placeholder and its value must line up.' );
	}

	public function test_the_result_filter_can_reshape_the_output(): void {
		add_filter(
			'wb_listora_search_result',
			static function ( $result ) {
				$result['listing_ids'] = array_slice( $result['listing_ids'], 0, 2 );
				$result['total']       = 2;

				return $result;
			}
		);

		$result = ( new Search_Engine() )->search( array( 'per_page' => 50 ) );

		$this->assertSame( 2, (int) $result['total'] );
		$this->assertCount( 2, $result['listing_ids'] );
	}

	public function test_the_orderby_filter_changes_the_order(): void {
		add_filter(
			'wb_listora_search_orderby',
			static function () {
				return 's.title ASC, s.listing_id ASC';
			}
		);

		$result = ( new Search_Engine() )->search( array( 'per_page' => 50 ) );
		$titles = array_map( 'get_the_title', $result['listing_ids'] );

		$sorted = $titles;
		sort( $sorted );

		$this->assertSame( $sorted, $titles, 'The custom ORDER BY must reach the query.' );
	}

	/**
	 * An empty or non-string return must not produce `ORDER BY ` and a fatal.
	 */
	public function test_an_empty_orderby_falls_back_instead_of_breaking_the_query(): void {
		add_filter( 'wb_listora_search_orderby', '__return_empty_string' );

		$result = ( new Search_Engine() )->search( array( 'per_page' => 50 ) );

		$this->assertSame( 7, (int) $result['total'] );
	}

	/**
	 * No listener means no behaviour change - every existing site.
	 */
	public function test_without_listeners_nothing_changes(): void {
		$result = ( new Search_Engine() )->search( array( 'per_page' => 50 ) );

		$this->assertSame( 7, (int) $result['total'] );
	}
}
