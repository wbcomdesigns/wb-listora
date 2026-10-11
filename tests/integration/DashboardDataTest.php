<?php
/**
 * The dashboard block and the REST dashboard read one data source.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;
use WBListora\Core\Dashboard_Data;

/**
 * @group listora
 */
class DashboardDataTest extends WP_UnitTestCase {

	public function test_rest_stats_match_dashboard_data_and_refresh_on_writes() {
		global $wpdb;
		$member = self::factory()->user->create( array( 'role' => 'author' ) );
		$other  = self::factory()->user->create();
		$mine   = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $member, 'post_status' => 'publish' ) );
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$wpdb->insert( "{$prefix}reviews", array( 'listing_id' => $mine, 'user_id' => $other, 'overall_rating' => 5, 'title' => 'Great', 'content' => 'Lovely place, would come back.', 'status' => 'approved' ) );
		$wpdb->insert( "{$prefix}claims", array( 'listing_id' => $mine, 'user_id' => $member, 'status' => 'pending', 'proof_text' => 'Owner' ) );

		$stats = Dashboard_Data::stats( $member );
		$this->assertSame( 1, $stats['published'] );
		$this->assertSame( 1, $stats['reviews_received'] );
		$this->assertSame( 1, $stats['claims_pending'] );

		wp_set_current_user( $member );
		$rest = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/listora/v1/dashboard/stats' ) )->get_data();
		$this->assertSame( $stats['reviews_total'], $rest['reviews'] );
		$this->assertSame( $stats['published'], $rest['listings']['published'] );
		$this->assertSame( $stats['claims_pending'], $rest['claims']['pending'] );

		// A claim write must refresh the cached numbers.
		$wpdb->update( "{$prefix}claims", array( 'status' => 'approved' ), array( 'user_id' => $member ) );
		do_action( 'wb_listora_after_update_claim', (int) $wpdb->insert_id, 'approved', null );
		$this->assertSame( 1, Dashboard_Data::stats( $member )['claims_approved'] );
	}
}
