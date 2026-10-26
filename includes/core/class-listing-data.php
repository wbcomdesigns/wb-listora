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
	 * A listing's public rating: average and count of approved reviews.
	 *
	 * One number for every viewer, the API, cards and structured data, rounded
	 * to one decimal here and nowhere else. Blocking a reviewer hides their
	 * review text from that viewer (get_reviews()); it does not change this.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array{avg_rating: float, review_count: int}
	 */
	public static function get_rating_summary( $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table with no WP API, read must be live.
			$wpdb->prepare(
				"SELECT avg_rating, review_count FROM {$prefix}search_index WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				$listing_id
			),
			ARRAY_A
		);

		return array(
			'avg_rating'   => $row ? round( (float) $row['avg_rating'], 1 ) : 0.0,
			'review_count' => $row ? (int) $row['review_count'] : 0,
		);
	}

	/**
	 * Recompute and persist a listing's rating aggregate in search_index.
	 *
	 * Idempotent: derives AVG(overall_rating) / COUNT(*) over approved reviews
	 * from the live table, so any code that changes or anonymizes reviews
	 * (including the privacy eraser) can call it.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return void
	 */
	public static function recompute_rating_aggregate( $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, no WP API, table name is the site table prefix
		$wpdb->update( "{$prefix}search_index", self::rating_aggregate( $listing_id ), array( 'listing_id' => $listing_id ) );
	}

	/**
	 * The stored rating aggregate over approved reviews, as search_index keeps it.
	 *
	 * The indexer and recompute_rating_aggregate() both write this, so the
	 * stored figure has one rule: one decimal, the figure every surface shows,
	 * so the index, search API, cards, map and rating filter cannot disagree.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array{avg_rating: float, review_count: int}
	 */
	public static function rating_aggregate( $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(overall_rating) as avg_r, COUNT(*) as cnt
				FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				$listing_id
			),
			ARRAY_A
		);

		return array(
			'avg_rating'   => $stats ? round( (float) $stats['avg_r'], 1 ) : 0.0,
			'review_count' => $stats ? (int) $stats['cnt'] : 0,
		);
	}

	/**
	 * Published listings favourited most since a date, for the owner's analytics.
	 *
	 * One grouped COUNT over the favorites table (idx_created narrows the
	 * window). Totals only: who favourited what is not exposed.
	 *
	 * @since 2.0.0
	 *
	 * @param string $since_gmt UTC datetime, 'Y-m-d H:i:s' or 'Y-m-d'.
	 * @param int    $limit     Rows to return.
	 * @return array<int, array{listing_id: int, title: string, count: int}>
	 */
	public static function most_favorited( string $since_gmt, int $limit = 10 ): array {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, aggregate read.
			$wpdb->prepare(
				"SELECT f.listing_id, COUNT(*) AS cnt FROM {$prefix}favorites f INNER JOIN {$wpdb->posts} p ON p.ID = f.listing_id AND p.post_status = 'publish' WHERE f.created_at >= %s GROUP BY f.listing_id ORDER BY cnt DESC, f.listing_id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				$since_gmt,
				max( 1, min( 100, $limit ) )
			),
			ARRAY_A
		);

		$ids = array_map( 'intval', wp_list_pluck( (array) $rows, 'listing_id' ) );
		_prime_post_caches( $ids, false, false );

		return array_map(
			static function ( $row ) {
				return array(
					'listing_id' => (int) $row['listing_id'],
					'title'      => wp_specialchars_decode( get_the_title( (int) $row['listing_id'] ), ENT_QUOTES ),
					'count'      => (int) $row['cnt'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * How many favourites were added since a date: the figure the Analytics card
	 * shows, read from the same table as most_favorited() so the two agree.
	 *
	 * @since 2.1.0
	 *
	 * @param string $since_gmt UTC datetime, 'Y-m-d H:i:s' or 'Y-m-d'.
	 * @return int
	 */
	public static function count_favorites_since( string $since_gmt ): int {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, aggregate read.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				$since_gmt
			)
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

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table with no WP API, read must be live.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				$listing_id
			)
		);
	}

	/**
	 * Favourite counts for many listings in one query (no per-card query in grids).
	 *
	 * @since 1.9.1
	 *
	 * @param int[] $listing_ids Listing IDs.
	 * @return array<int, int> Listing ID => count; listings nobody saved are absent.
	 */
	public static function get_favorite_counts( array $listing_ids ) {
		$listing_ids = array_values( array_filter( array_map( 'intval', $listing_ids ) ) );
		if ( ! $listing_ids ) {
			return array();
		}

		global $wpdb;
		$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$placeholders = implode( ',', array_fill( 0, count( $listing_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin table, read must be live.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, COUNT(*) AS cnt FROM {$prefix}favorites WHERE listing_id IN ({$placeholders}) GROUP BY listing_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the site table prefix plus generated IN placeholders.
				...$listing_ids
			),
			ARRAY_A
		);

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['listing_id'] ] = (int) $row['cnt'];
		}
		return $counts;
	}

	/**
	 * Star breakdown with the public average and count, by the same rule as
	 * get_rating_summary(): every approved review, one decimal.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array{avg: float, total: int, dist: array<int, int>}
	 */
	public static function get_review_distribution( $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$stars = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table with no WP API, read must be live.
			$wpdb->prepare(
				"SELECT
				AVG(overall_rating) as avg_rating,
				COUNT(*) as total,
				SUM(CASE WHEN overall_rating = 5 THEN 1 ELSE 0 END) as s5,
				SUM(CASE WHEN overall_rating = 4 THEN 1 ELSE 0 END) as s4,
				SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) as s3,
				SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) as s2,
				SUM(CASE WHEN overall_rating = 1 THEN 1 ELSE 0 END) as s1
			FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				$listing_id
			),
			ARRAY_A
		);

		return array(
			'avg'   => $stars ? round( (float) $stars['avg_rating'], 1 ) : 0.0,
			'total' => $stars ? (int) $stars['total'] : 0,
			'dist'  => array(
				5 => (int) ( $stars['s5'] ?? 0 ),
				4 => (int) ( $stars['s4'] ?? 0 ),
				3 => (int) ( $stars['s3'] ?? 0 ),
				2 => (int) ( $stars['s2'] ?? 0 ),
				1 => (int) ( $stars['s1'] ?? 0 ),
			),
		);
	}

	/**
	 * Get paginated reviews for a listing.
	 *
	 * @param int    $listing_id Listing post ID.
	 * @param string $sort       Sort key: newest|highest|lowest|helpful.
	 * @param int    $limit      Number of reviews to return.
	 * @return array Rows as ARRAY_A, each with `user_voted` (the current user marked it helpful).
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

		/*
		 * Hide reviews either party has blocked (App Store Guideline 1.2).
		 * Filtered in SQL so LIMIT still returns a full page. The REST list uses
		 * the same wb_listora_hidden_review_authors() so the two paths agree.
		 * Admin counts and moderation stay unfiltered: a moderator must see a
		 * reported review to act on it.
		 */
		$hidden      = function_exists( 'wb_listora_hidden_review_authors' )
			? wb_listora_hidden_review_authors()
			: array();
		$block_sql   = '';
		$block_args  = array();

		if ( $hidden ) {
			$block_sql  = ' AND user_id NOT IN ( ' . implode( ', ', array_fill( 0, count( $hidden ), '%d' ) ) . ' )';
			$block_args = $hidden;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, safe orderby allowlist.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}reviews
			WHERE listing_id = %d AND status = 'approved'{$block_sql}
			ORDER BY {$order_by} LIMIT %d",
				array_merge( array( $listing_id ), $block_args, array( $limit ) )
			),
			ARRAY_A
		);

		// One lookup for the page, so a reload still shows the member's own votes.
		$voted   = array();
		$user_id = get_current_user_id();
		if ( $rows && $user_id ) {
			$ids   = array_map( 'intval', array_column( $rows, 'id' ) );
			$voted = array_flip(
				array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT review_id FROM {$prefix}review_votes WHERE user_id = %d AND review_id IN ( " . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ' )',
							array_merge( array( $user_id ), $ids )
						)
					)
				)
			);
		}
		// phpcs:enable

		foreach ( $rows as &$row ) {
			$row['user_voted'] = isset( $voted[ (int) $row['id'] ] );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Whether this user has used up their reviews on the listing.
	 *
	 * True only while "One review per listing" is on and they have reviewed.
	 *
	 * @since 1.9.0
	 *
	 * @param int $listing_id Listing ID.
	 * @param int $user_id    User ID.
	 * @return bool
	 */
	public static function review_limit_reached( $listing_id, $user_id ) {
		return self::has_user_reviewed( $listing_id, $user_id );
	}

	/**
	 * Whether this user has a review on the listing awaiting moderation.
	 *
	 * A pending review is invisible in the list, so the form shows a notice
	 * instead of inviting a duplicate.
	 *
	 * @since 1.9.0
	 *
	 * @param int $listing_id Listing ID.
	 * @param int $user_id    User ID.
	 * @return bool
	 */
	public static function has_pending_review( $listing_id, $user_id ) {
		if ( ! $user_id ) {
			return false;
		}
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table with no WP API, read must be live.
			$wpdb->prepare(
				"SELECT id FROM {$prefix}reviews WHERE listing_id = %d AND user_id = %d AND status = 'pending' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				$listing_id,
				$user_id
			)
		);
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

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table with no WP API, read must be live.
			$wpdb->prepare(
				"SELECT id FROM {$prefix}reviews WHERE listing_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				$listing_id,
				$user_id
			)
		);
	}
}
