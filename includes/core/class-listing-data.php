<?php
/**
 * Listing Data — shared data-loading helpers for blocks and REST.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Static helper methods that fetch listing data from custom tables.
 */
class Listing_Data {

	/**
	 * Register cache-busting hooks.
	 *
	 * NOTE: per-group invalidation for listings/reviews/favorites/dashboard
	 * is owned by \WBListora\Core\Cache (`wp_cache_set_last_changed`
	 * incrementor). This class only handles the `transition_post_status`
	 * edge case — a listing moving from publish → trash flips dashboard
	 * counts even though no `wb_listora_after_*` hook fires.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'bump_on_status_change' ), 10, 3 );
	}

	/**
	 * Bump the dashboard cache group when a listing's post status changes.
	 *
	 * The dedicated `wb_listora_after_*` write hooks (handled by Cache::init)
	 * cover REST-driven creates/updates/deletes. This callback covers the
	 * status-transition path — e.g., admin moves a listing to trash from
	 * the WP-Admin Posts list, or cron auto-expires a listing.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 */
	public static function bump_on_status_change( $new_status, $old_status, $post ) {
		if ( 'listora_listing' !== $post->post_type ) {
			return;
		}
		if ( $new_status === $old_status ) {
			return;
		}
		Cache::bump( Cache::GROUP_DASHBOARD );
		Cache::bump( Cache::GROUP_LISTINGS );
	}

	/**
	 * Get rating summary (avg + count) from search_index.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array{avg_rating: float, review_count: int}
	 */
	public static function get_rating_summary( $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT avg_rating, review_count FROM {$prefix}search_index WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$listing_id
			),
			ARRAY_A
		);

		return array(
			'avg_rating'   => $row ? (float) $row['avg_rating'] : 0,
			'review_count' => $row ? (int) $row['review_count'] : 0,
		);
	}

	/**
	 * Get favorite count for a listing.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return int
	 */
	public static function get_favorite_count( $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$listing_id
			)
		);
	}

	/**
	 * Get review distribution (star breakdown + average).
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array{avg: float, total: int, dist: array<int, int>}
	 */
	public static function get_review_distribution( $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$summary = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT
				AVG(overall_rating) as avg_rating,
				COUNT(*) as total,
				SUM(CASE WHEN overall_rating = 5 THEN 1 ELSE 0 END) as s5,
				SUM(CASE WHEN overall_rating = 4 THEN 1 ELSE 0 END) as s4,
				SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) as s3,
				SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) as s2,
				SUM(CASE WHEN overall_rating = 1 THEN 1 ELSE 0 END) as s1
			FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$listing_id
			),
			ARRAY_A
		);

		return array(
			'avg'   => $summary ? round( (float) $summary['avg_rating'], 1 ) : 0,
			'total' => $summary ? (int) $summary['total'] : 0,
			'dist'  => array(
				5 => (int) ( $summary['s5'] ?? 0 ),
				4 => (int) ( $summary['s4'] ?? 0 ),
				3 => (int) ( $summary['s3'] ?? 0 ),
				2 => (int) ( $summary['s2'] ?? 0 ),
				1 => (int) ( $summary['s1'] ?? 0 ),
			),
		);
	}

	/**
	 * Get paginated reviews for a listing.
	 *
	 * @param int    $listing_id Listing post ID.
	 * @param string $sort       Sort key: newest|highest|lowest|helpful.
	 * @param int    $limit      Number of reviews to return.
	 * @return array
	 */
	public static function get_reviews( $listing_id, $sort = 'newest', $limit = 10 ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$sort_clauses = array(
			'newest'  => 'created_at DESC',
			'highest' => 'overall_rating DESC, created_at DESC',
			'lowest'  => 'overall_rating ASC, created_at DESC',
			'helpful' => 'helpful_count DESC, created_at DESC',
		);
		$order_by     = $sort_clauses[ $sort ] ?? 'created_at DESC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, safe orderby allowlist.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}reviews
			WHERE listing_id = %d AND status = 'approved'
			ORDER BY {$order_by} LIMIT %d",
				$listing_id,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable
	}

	/**
	 * Check if a user has already reviewed a listing.
	 *
	 * @param int $listing_id Listing post ID.
	 * @param int $user_id    User ID.
	 * @return bool
	 */
	public static function has_user_reviewed( $listing_id, $user_id ) {
		if ( ! $user_id ) {
			return false;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id FROM {$prefix}reviews WHERE listing_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$listing_id,
				$user_id
			)
		);
	}
}
