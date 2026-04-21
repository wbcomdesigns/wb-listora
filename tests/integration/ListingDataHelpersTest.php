<?php
/**
 * Integration tests for the Listing_Data helpers used by Bundle 39.
 *
 * Protects the shape contract that tabs.php depends on — if any helper
 * changes return shape, the detail Reviews tab renders wrong. These tests
 * fail loudly on any regression.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 */

namespace WBListora\Tests\Integration;

use WBListora\Core\Listing_Data;
use WP_UnitTestCase;

/**
 * @group listora
 * @group listing-data
 */
class ListingDataHelpersTest extends WP_UnitTestCase {

	/**
	 * Create a listing and insert N reviews against it directly into the
	 * custom table. Uses $wpdb rather than a factory because the factories
	 * helper class depends on the indexer firing (not under test here).
	 *
	 * @param int                          $listing_id Listing post ID.
	 * @param array<int,array<string,mixed>> $reviews  Per-review row data.
	 */
	private function insert_reviews( int $listing_id, array $reviews ): void {
		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews';
		foreach ( $reviews as $r ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array_merge(
					array(
						'listing_id'     => $listing_id,
						'user_id'        => $r['user_id'] ?? 1,
						'overall_rating' => $r['rating'] ?? 5,
						'title'          => $r['title'] ?? 'Great',
						'content'        => $r['content'] ?? 'Loved it.',
						'status'         => $r['status'] ?? 'approved',
						'helpful_count'  => $r['helpful_count'] ?? 0,
						'created_at'     => $r['created_at'] ?? current_time( 'mysql' ),
					)
				)
			);
		}
	}

	/**
	 * has_user_reviewed returns false when user id is 0 (guests).
	 */
	public function test_has_user_reviewed_returns_false_for_guest() {
		$this->assertFalse( Listing_Data::has_user_reviewed( 1, 0 ) );
	}

	/**
	 * has_user_reviewed correctly detects existing + absent reviews.
	 */
	public function test_has_user_reviewed_detects_reviewer() {
		$listing_id = self::factory()->post->create(
			array( 'post_type' => 'listora_listing', 'post_status' => 'publish' )
		);
		$user_id    = self::factory()->user->create();

		$this->assertFalse( Listing_Data::has_user_reviewed( $listing_id, $user_id ) );

		$this->insert_reviews( $listing_id, array( array( 'user_id' => $user_id ) ) );

		$this->assertTrue( Listing_Data::has_user_reviewed( $listing_id, $user_id ) );
		$this->assertFalse( Listing_Data::has_user_reviewed( $listing_id, $user_id + 999 ) );
	}

	/**
	 * get_reviews respects limit + status filter (approved only).
	 */
	public function test_get_reviews_respects_limit_and_status() {
		$listing_id = self::factory()->post->create(
			array( 'post_type' => 'listora_listing', 'post_status' => 'publish' )
		);

		$this->insert_reviews(
			$listing_id,
			array(
				array( 'rating' => 5, 'status' => 'approved' ),
				array( 'rating' => 4, 'status' => 'approved' ),
				array( 'rating' => 3, 'status' => 'pending' ),
				array( 'rating' => 2, 'status' => 'approved' ),
			)
		);

		$all = Listing_Data::get_reviews( $listing_id, 'newest', 10 );
		$this->assertCount( 3, $all, 'Only approved reviews should return.' );

		$limited = Listing_Data::get_reviews( $listing_id, 'newest', 2 );
		$this->assertCount( 2, $limited, 'Limit must be honored.' );
	}

	/**
	 * get_review_distribution returns the documented shape + sane numbers.
	 */
	public function test_get_review_distribution_returns_full_shape() {
		$listing_id = self::factory()->post->create(
			array( 'post_type' => 'listora_listing', 'post_status' => 'publish' )
		);

		$this->insert_reviews(
			$listing_id,
			array(
				array( 'rating' => 5 ),
				array( 'rating' => 5 ),
				array( 'rating' => 4 ),
				array( 'rating' => 1 ),
			)
		);

		$dist = Listing_Data::get_review_distribution( $listing_id );

		$this->assertArrayHasKey( 'avg', $dist );
		$this->assertArrayHasKey( 'total', $dist );
		$this->assertArrayHasKey( 'dist', $dist );
		$this->assertCount( 5, $dist['dist'], 'Distribution must include all 5 star buckets.' );
		$this->assertSame( 4, $dist['total'] );
		$this->assertSame( 2, $dist['dist'][5] );
		$this->assertSame( 1, $dist['dist'][4] );
		$this->assertSame( 0, $dist['dist'][3] );
		$this->assertSame( 0, $dist['dist'][2] );
		$this->assertSame( 1, $dist['dist'][1] );
		$this->assertEqualsWithDelta( 3.75, $dist['avg'], 0.01 );
	}

	/**
	 * get_review_distribution returns zeros for a listing with no approved reviews.
	 */
	public function test_get_review_distribution_zeros_for_empty() {
		$listing_id = self::factory()->post->create(
			array( 'post_type' => 'listora_listing', 'post_status' => 'publish' )
		);

		$dist = Listing_Data::get_review_distribution( $listing_id );

		$this->assertSame( 0, $dist['total'] );
		$this->assertSame( 0.0, (float) $dist['avg'] );
		foreach ( range( 1, 5 ) as $n ) {
			$this->assertSame( 0, $dist['dist'][ $n ] );
		}
	}
}
