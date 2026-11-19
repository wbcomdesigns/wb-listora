<?php
/**
 * Reviews read model for the moderation queue.
 *
 * One WHERE builder serves the filtered list and its COUNT, so the total
 * always matches the rows.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Reviews_Model
 *
 * @since 1.9.0
 */
class Reviews_Model {

	/**
	 * Option name prefix holding a review's visitor reports
	 * (Reviews_Controller::report_review()).
	 */
	const REPORTS_OPTION_PREFIX = '_listora_review_reports_';

	/**
	 * Reviews table.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews';
	}

	/**
	 * Reviews per status, in one query.
	 *
	 * @return array<string, int> 'all', 'pending', 'approved', 'rejected'.
	 */
	public static function status_counts() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idx_status_created covers it.
		$rows   = (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );
		$counts = array(
			'all'      => 0,
			'pending'  => 0,
			'approved' => 0,
			'rejected' => 0,
		);
		foreach ( $rows as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['n'];
			$counts['all']                    += (int) $row['n'];
		}
		return $counts;
	}

	/**
	 * IDs of reviews visitors have reported.
	 *
	 * Reports live in one option per review; option_name is indexed, so this
	 * is a prefix range scan over reported reviews only.
	 *
	 * @return int[]
	 */
	public static function reported_ids() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- LIKE scan of option names has no WP API
		$names = (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::REPORTS_OPTION_PREFIX ) . '%' ) );
		$ids   = array();
		foreach ( $names as $name ) {
			$ids[] = (int) substr( (string) $name, strlen( self::REPORTS_OPTION_PREFIX ) );
		}
		return array_values( array_filter( $ids ) );
	}

	/**
	 * Which of these reviews a member has already reported.
	 *
	 * One query for the whole page (the report options are indexed by name), so a list of
	 * reviews costs one read however long it is, not one per review.
	 *
	 * @since 2.1.2
	 *
	 * @param int[] $review_ids Review IDs on the page.
	 * @param int   $user_id    Member.
	 * @return int[] The review IDs this member reported.
	 */
	public static function reported_by_user( array $review_ids, int $user_id ): array {
		global $wpdb;
		$review_ids = array_values( array_unique( array_filter( array_map( 'intval', $review_ids ) ) ) );
		if ( ! $user_id || ! $review_ids ) {
			return array();
		}

		$names = array_map(
			static fn ( int $id ): string => self::REPORTS_OPTION_PREFIX . $id,
			$review_ids
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one indexed read for the page; the placeholder list is built from the count.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN (" . implode( ',', array_fill( 0, count( $names ), '%s' ) ) . ')', $names ) );

		$done = array();
		foreach ( $rows as $row ) {
			$reports = maybe_unserialize( $row->option_value );
			foreach ( is_array( $reports ) ? $reports : array() as $report ) {
				if ( is_array( $report ) && (int) ( $report['user_id'] ?? 0 ) === $user_id ) {
					$done[] = (int) substr( (string) $row->option_name, strlen( self::REPORTS_OPTION_PREFIX ) );
					break;
				}
			}
		}
		return $done;
	}

	/**
	 * Visitor reports on one review.
	 *
	 * @param int $review_id Review ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function reports( $review_id ) {
		$reports = get_option( self::REPORTS_OPTION_PREFIX . (int) $review_id, array() );
		return is_array( $reports ) ? $reports : array();
	}

	/**
	 * WHERE clause for the queue.
	 *
	 * @param array<mixed> $args See query().
	 * @return array{0: string, 1: array<int, mixed>} SQL and its parameters.
	 */
	private static function where( array $args ) {
		global $wpdb;
		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND r.status = %s';
			$params[] = (string) $args['status'];
		}
		if ( ! empty( $args['rating'] ) ) {
			$where   .= ' AND r.overall_rating = %d';
			$params[] = (int) $args['rating'];
		}
		if ( ! empty( $args['listing_type'] ) ) {
			$where   .= ' AND si.listing_type = %s';
			$params[] = (string) $args['listing_type'];
		}
		if ( ! empty( $args['since_days'] ) ) {
			$where   .= ' AND r.created_at >= %s';
			$params[] = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) $args['since_days'] );
		}
		if ( 'yes' === ( $args['reply'] ?? '' ) ) {
			$where .= " AND r.owner_reply IS NOT NULL AND r.owner_reply <> ''";
		} elseif ( 'no' === ( $args['reply'] ?? '' ) ) {
			$where .= " AND ( r.owner_reply IS NULL OR r.owner_reply = '' )";
		}
		if ( ! empty( $args['reported'] ) ) {
			$ids    = self::reported_ids();
			$ids    = $ids ? $ids : array( 0 );
			$where .= ' AND r.id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')';
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where   .= ' AND (si.title LIKE %s OR r.title LIKE %s OR r.content LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		return array( $where, $params );
	}

	/**
	 * One review by id, in the row shape query() returns minus `ip_address`, or null.
	 *
	 * The read door for integrations (BuddyNext shows approved reviews in its
	 * feed): they ask Listora for a review instead of reading its table. The
	 * status is kept so a caller can skip pending ones; the reviewer's IP is
	 * left out so a careless print cannot expose it.
	 *
	 * @param int $id Review id.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ) {
		if ( $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$table = self::table();
		$index = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'search_index';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are fixed.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, si.title AS listing_title, si.listing_type FROM {$table} r LEFT JOIN {$index} si ON r.listing_id = si.listing_id WHERE r.id = %d",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable
		if ( ! is_array( $row ) ) {
			return null;
		}
		unset( $row['ip_address'] );
		return $row;
	}

	/**
	 * One page of the queue and the total matching it.
	 *
	 * @param array<mixed> $args {
	 *     @type string $status       Review status.
	 *     @type int    $rating       Overall stars, 1-5.
	 *     @type string $listing_type Listing type slug.
	 *     @type int    $since_days   Only reviews from the last N days.
	 *     @type string $reply        'yes' or 'no': has an owner reply.
	 *     @type bool   $reported     Only reported reviews.
	 *     @type string $search       Listing title, review title or text.
	 *     @type string $orderby      'date' or 'rating'.
	 *     @type string $order        'ASC' or 'DESC'.
	 *     @type int    $limit        Page size.
	 *     @type int    $offset       Offset.
	 * }
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		list( $where, $params ) = self::where( $args );

		$table   = self::table();
		$index   = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'search_index';
		$orderby = 'rating' === ( $args['orderby'] ?? '' ) ? 'r.overall_rating' : 'r.created_at';
		$order   = 'ASC' === ( $args['order'] ?? '' ) ? 'ASC' : 'DESC';
		$from    = "FROM {$table} r LEFT JOIN {$index} si ON r.listing_id = si.listing_id WHERE {$where}";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholdered SQL from a fixed template.
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from}", ...$params ) ) : $wpdb->get_var( "SELECT COUNT(*) {$from}" ) );
		$rows  = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, si.title AS listing_title, si.listing_type {$from} ORDER BY {$orderby} {$order}, r.id {$order} LIMIT %d OFFSET %d",
				...array_merge( $params, array( max( 1, (int) ( $args['limit'] ?? 20 ) ), max( 0, (int) ( $args['offset'] ?? 0 ) ) ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}
}
