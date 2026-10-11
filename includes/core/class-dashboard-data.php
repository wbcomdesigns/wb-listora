<?php
/**
 * Member dashboard data: one source for the dashboard block and the REST dashboard.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Counts and lists a member sees on their dashboard.
 *
 * The block and the app's REST routes both read from here, so the website and
 * the app always show the same numbers. Each list has a matching count; callers
 * keep their own paging rules and response shapes.
 */
class Dashboard_Data {

	/**
	 * Plugin table name.
	 *
	 * @param string $table Table without prefix.
	 * @return string
	 */
	private static function table( $table ) {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . $table;
	}

	/**
	 * Stat tiles and badges for a member, cached until a listing, review,
	 * favourite or claim changes.
	 *
	 * @param int    $user_id   Member ID.
	 * @param string $type_slug Limit listing counts to one listing type ('' = all). Reviews,
	 *                          favourites and claims are never limited by type.
	 * @return array<string, int> Keys: published, pending, expired, draft, paused, verification,
	 *                            total, reviews, reviews_received, reviews_total, favorites,
	 *                            claims, claims_pending, claims_approved, claims_rejected.
	 */
	public static function stats( $user_id, $type_slug = '' ) {
		$user_id   = (int) $user_id;
		$type_slug = sanitize_title( (string) $type_slug );
		$cache_key = Cache::key( Cache::GROUP_DASHBOARD, "stats:user:{$user_id}:type:{$type_slug}" );
		$cached    = wp_cache_get( $cache_key, Cache::GROUP_DASHBOARD );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		// 0 = every type; -1 = a slug this site does not have, which counts nothing.
		$tt_id = 0;
		if ( '' !== $type_slug ) {
			$term  = get_term_by( 'slug', $type_slug, 'listora_listing_type' );
			$tt_id = $term instanceof \WP_Term ? (int) $term->term_taxonomy_id : -1;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- counts over core and plugin tables with no WP API; cached above.
		if ( $tt_id < 0 ) {
			$listing_counts = array();
		} elseif ( $tt_id > 0 ) {
			$listing_counts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.post_status, COUNT(*) AS cnt FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
					WHERE p.post_type = 'listora_listing' AND p.post_author = %d AND tr.term_taxonomy_id = %d
					GROUP BY p.post_status",
					$user_id,
					$tt_id
				),
				OBJECT_K
			);
		} else {
			$listing_counts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_status, COUNT(*) AS cnt FROM {$wpdb->posts}
					WHERE post_type = 'listora_listing' AND post_author = %d
					GROUP BY post_status",
					$user_id
				),
				OBJECT_K
			);
		}

		$claim_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS cnt FROM ' . self::table( 'claims' ) . ' WHERE user_id = %d GROUP BY status', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is the site table prefix plus a plugin constant.
				$user_id
			),
			OBJECT_K
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$total = 0;
		foreach ( wb_listora_member_listing_statuses() as $status ) {
			$total += (int) ( $listing_counts[ $status ]->cnt ?? 0 );
		}

		$claims = 0;
		foreach ( (array) $claim_rows as $row ) {
			$claims += (int) ( $row->cnt ?? 0 );
		}

		$written  = self::count_reviews_written( $user_id );
		$received = self::count_reviews_received( $user_id );

		$stats = array(
			'published'        => (int) ( $listing_counts['publish']->cnt ?? 0 ),
			'pending'          => (int) ( $listing_counts['pending']->cnt ?? 0 ),
			'expired'          => (int) ( $listing_counts['listora_expired']->cnt ?? 0 ),
			'draft'            => (int) ( $listing_counts['draft']->cnt ?? 0 ),
			'paused'           => (int) ( $listing_counts['listora_payment']->cnt ?? 0 ),
			'verification'     => (int) ( $listing_counts['pending_verification']->cnt ?? 0 ),
			'total'            => $total,
			'reviews'          => $written,
			'reviews_received' => $received,
			// The Reviews tab lists both, so its tile and badge count both.
			'reviews_total'    => $written + $received,
			'favorites'        => self::count_favorites( $user_id ),
			'claims'           => $claims,
			'claims_pending'   => (int) ( $claim_rows['pending']->cnt ?? 0 ),
			'claims_approved'  => (int) ( $claim_rows['approved']->cnt ?? 0 ),
			'claims_rejected'  => (int) ( $claim_rows['rejected']->cnt ?? 0 ),
		);

		wp_cache_set( $cache_key, $stats, Cache::GROUP_DASHBOARD );

		return $stats;
	}

	/**
	 * Reviews the member wrote that their list shows: approved, pending and rejected, never spam.
	 *
	 * @param int $user_id Member ID.
	 * @return int
	 */
	public static function count_reviews_written( $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- plugin table; the status list is a fixed literal from a helper.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'reviews' ) . ' WHERE user_id = %d AND status IN (' . wb_listora_member_review_statuses_sql() . ')', (int) $user_id ) );
	}

	/**
	 * A page of the member's own reviews, newest first, with the listing title.
	 *
	 * @param int      $user_id  Member ID.
	 * @param int      $per_page Rows.
	 * @param int      $offset   Offset (ignored when $cursor is set).
	 * @param int|null $cursor   Return reviews with an ID below this (0 = from the newest).
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_reviews_written( $user_id, $per_page, $offset = 0, $cursor = null ) {
		global $wpdb;
		$reviews = self::table( 'reviews' );
		$index   = self::table( 'search_index' );
		$where   = 'r.user_id = %d AND r.status IN (' . wb_listora_member_review_statuses_sql() . ')';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- plugin tables; fixed WHERE fragment with placeholders.
		if ( null !== $cursor ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.*, si.title AS listing_title FROM {$reviews} r
					LEFT JOIN {$index} si ON r.listing_id = si.listing_id
					WHERE {$where} AND r.id < %d ORDER BY r.id DESC LIMIT %d",
					(int) $user_id,
					$cursor > 0 ? (int) $cursor : PHP_INT_MAX,
					(int) $per_page
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.*, si.title AS listing_title FROM {$reviews} r
					LEFT JOIN {$index} si ON r.listing_id = si.listing_id
					WHERE {$where} ORDER BY r.created_at DESC, r.id DESC LIMIT %d OFFSET %d",
					(int) $user_id,
					(int) $per_page,
					(int) $offset
				),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		return (array) $rows;
	}

	/**
	 * Approved reviews other people left on the member's listings.
	 *
	 * @param int $user_id Member ID.
	 * @return int
	 */
	public static function count_reviews_received( $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- plugin table joined to posts.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'reviews' ) . " r INNER JOIN {$wpdb->posts} p ON r.listing_id = p.ID WHERE p.post_author = %d AND r.user_id != %d AND r.status = 'approved'", (int) $user_id, (int) $user_id ) );
	}

	/**
	 * A page of approved reviews on the member's listings, newest first.
	 *
	 * @param int $user_id  Member ID.
	 * @param int $per_page Rows.
	 * @param int $offset   Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_reviews_received( $user_id, $per_page, $offset = 0 ) {
		global $wpdb;
		$reviews = self::table( 'reviews' );
		$index   = self::table( 'search_index' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin tables joined to posts.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, si.title AS listing_title FROM {$reviews} r
				INNER JOIN {$wpdb->posts} p ON r.listing_id = p.ID
				LEFT JOIN {$index} si ON r.listing_id = si.listing_id
				WHERE p.post_author = %d AND r.user_id != %d AND r.status = 'approved'
				ORDER BY r.created_at DESC, r.id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are the site table prefix plus plugin constants.
				(int) $user_id,
				(int) $user_id,
				(int) $per_page,
				(int) $offset
			),
			ARRAY_A
		);

		return (array) $rows;
	}

	/**
	 * Listings the member saved.
	 *
	 * @param int $user_id Member ID.
	 * @return int
	 */
	public static function count_favorites( $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- plugin table.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'favorites' ) . ' WHERE user_id = %d', (int) $user_id ) );
	}

	/**
	 * A page of saved listing IDs, newest first. The table has no id column; ties break on listing_id.
	 *
	 * @param int $user_id  Member ID.
	 * @param int $per_page Rows.
	 * @param int $offset   Offset.
	 * @return int[]
	 */
	public static function list_favorite_ids( $user_id, $per_page, $offset = 0 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- plugin table.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT listing_id FROM ' . self::table( 'favorites' ) . ' WHERE user_id = %d ORDER BY created_at DESC, listing_id DESC LIMIT %d OFFSET %d', (int) $user_id, (int) $per_page, (int) $offset ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Claims the member made, optionally only one status.
	 *
	 * @param int    $user_id Member ID.
	 * @param string $status  Status to count ('' = all).
	 * @return int
	 */
	public static function count_claims( $user_id, $status = '' ) {
		global $wpdb;
		$sql  = 'SELECT COUNT(*) FROM ' . self::table( 'claims' ) . ' WHERE user_id = %d';
		$args = array( (int) $user_id );
		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = (string) $status;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- plugin table; SQL built from fixed fragments above.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * A page of the member's claims, newest first, with the listing title.
	 *
	 * @param int $user_id  Member ID.
	 * @param int $per_page Rows.
	 * @param int $offset   Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_claims( $user_id, $per_page, $offset = 0 ) {
		global $wpdb;
		$claims = self::table( 'claims' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin table joined to posts.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, p.post_title AS listing_title FROM {$claims} c
				LEFT JOIN {$wpdb->posts} p ON c.listing_id = p.ID
				WHERE c.user_id = %d
				ORDER BY c.created_at DESC, c.id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				(int) $user_id,
				(int) $per_page,
				(int) $offset
			),
			ARRAY_A
		);

		return (array) $rows;
	}
}
