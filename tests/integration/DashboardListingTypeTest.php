<?php
/**
 * A dashboard pinned to a listing type manages only that type's listings.
 *
 * Card 10213596281: a site running Jobs, Classifieds and Real Estate on
 * separate pages had no way to give each page its own dashboard - the block
 * always showed every listing the member owned.
 *
 * Both halves are covered here, because the trap is that they disagree: the
 * server-rendered block and `GET /dashboard/listings` must return the same set,
 * and the overview tile that LINKS to the Listings tab must count the same
 * thing the tab shows (cross-cutting check 8).
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   dashboard-listing-type
 */

namespace WBListora\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group dashboard-listing-type
 */
class DashboardListingTypeTest extends WP_UnitTestCase {

	/**
	 * Member who owns the listings.
	 *
	 * @var int
	 */
	private $user_id = 0;

	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $this->user_id );

		foreach ( array( 'qa-alpha', 'qa-beta' ) as $slug ) {
			if ( ! get_term_by( 'slug', $slug, 'listora_listing_type' ) ) {
				wp_insert_term( strtoupper( $slug ), 'listora_listing_type', array( 'slug' => $slug ) );
			}
		}

		$this->make_listing( 'Alpha One', 'qa-alpha' );
		$this->make_listing( 'Alpha Two', 'qa-alpha' );
		$this->make_listing( 'Beta One', 'qa-beta' );

		rest_get_server();
	}

	/**
	 * @param string $title Listing title.
	 * @param string $type  Listing-type slug.
	 * @return int
	 */
	private function make_listing( string $title, string $type ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $this->user_id,
			)
		);

		wp_set_object_terms( $id, $type, 'listora_listing_type' );

		return $id;
	}

	/**
	 * @param string $type Pinned type slug, '' for none.
	 * @return string Rendered dashboard HTML.
	 */
	private function render_dashboard( string $type ): string {
		delete_transient( 'listora_dashboard_stats_' . $this->user_id . ( '' !== $type ? '_' . $type : '' ) );

		$attributes = array( 'defaultTab' => 'listings' );
		if ( '' !== $type ) {
			$attributes['listingType'] = $type;
		}

		return (string) do_blocks( '<!-- wp:listora/user-dashboard ' . wp_json_encode( $attributes ) . ' /-->' );
	}

	/**
	 * @param string $type Type slug to request, '' for none.
	 * @return array<string, mixed>
	 */
	private function rest_listings( string $type ): array {
		$request = new WP_REST_Request( 'GET', '/listora/v1/dashboard/listings' );
		$request->set_param( 'listing_type', $type );

		return (array) rest_do_request( $request )->get_data();
	}

	public function test_the_shared_counter_is_the_one_both_surfaces_use(): void {
		$statuses = array( 'publish' );

		$this->assertSame( 3, wb_listora_count_user_listings( $this->user_id, $statuses, '' ) );
		$this->assertSame( 2, wb_listora_count_user_listings( $this->user_id, $statuses, 'qa-alpha' ) );
		$this->assertSame( 1, wb_listora_count_user_listings( $this->user_id, $statuses, 'qa-beta' ) );
	}

	/**
	 * A slug that is not a type on this site must return nothing. Widening back
	 * to every listing would be the silent-filter failure the status enum on
	 * this same route was tightened for.
	 */
	public function test_an_unknown_type_matches_nothing_rather_than_everything(): void {
		$this->assertSame( 0, wb_listora_count_user_listings( $this->user_id, array( 'publish' ), 'not-a-type-here' ) );

		$html = $this->render_dashboard( 'not-a-type-here' );
		$this->assertStringNotContainsString( 'Alpha One', $html );
		$this->assertStringNotContainsString( 'Beta One', $html );

		$rest = $this->rest_listings( 'not-a-type-here' );
		$this->assertSame( 0, (int) $rest['total'] );
		$this->assertSame( array(), $rest['listings'] );
	}

	public function test_the_rendered_block_shows_only_the_pinned_type(): void {
		$html = $this->render_dashboard( 'qa-alpha' );

		$this->assertStringContainsString( 'Alpha One', $html );
		$this->assertStringContainsString( 'Alpha Two', $html );
		$this->assertStringNotContainsString( 'Beta One', $html );
	}

	public function test_an_unpinned_block_is_unchanged(): void {
		$html = $this->render_dashboard( '' );

		$this->assertStringContainsString( 'Alpha One', $html );
		$this->assertStringContainsString( 'Beta One', $html );
	}

	/**
	 * The overview tile links to the Listings tab, so it must count what that
	 * tab contains - a tile reading 3 above a list of 2 is the failure.
	 */
	public function test_the_overview_tile_counts_what_the_tab_shows(): void {
		delete_transient( 'listora_dashboard_stats_' . $this->user_id . '_qa-alpha' );

		$html = (string) do_blocks( '<!-- wp:listora/user-dashboard {"listingType":"qa-alpha","defaultTab":"overview"} /-->' );

		$this->assertMatchesRegularExpression( '/Active listings: 2\./', $html );
		$this->assertDoesNotMatchRegularExpression( '/Active listings: 3\./', $html );
	}

	public function test_rest_returns_the_same_set_as_the_block(): void {
		$rest = $this->rest_listings( 'qa-alpha' );

		$this->assertSame( 2, (int) $rest['total'] );
		$this->assertCount( 2, $rest['listings'] );

		$titles = wp_list_pluck( $rest['listings'], 'title' );
		$this->assertContains( 'Alpha One', $titles );
		$this->assertNotContains( 'Beta One', $titles );
	}

	/**
	 * Cursor mode has to apply the type inside its own SELECT. Filtering the
	 * page afterwards returns a short page, and `has_more` is computed from
	 * that count - so the client would stop paging while listings remained.
	 */
	public function test_cursor_pagination_stays_inside_the_pinned_type(): void {
		$cursor = 0;
		$titles = array();

		for ( $i = 0; $i < 5; $i++ ) {
			$request = new WP_REST_Request( 'GET', '/listora/v1/dashboard/listings' );
			$request->set_param( 'listing_type', 'qa-alpha' );
			$request->set_param( 'per_page', 1 );
			$request->set_param( 'cursor', $cursor );

			$data   = (array) rest_do_request( $request )->get_data();
			$titles = array_merge( $titles, wp_list_pluck( $data['listings'], 'title' ) );

			if ( empty( $data['has_more'] ) ) {
				break;
			}

			$cursor = (int) $data['next_cursor'];
		}

		sort( $titles );
		$this->assertSame( array( 'Alpha One', 'Alpha Two' ), $titles );
	}
}
