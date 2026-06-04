<?php
/**
 * Claims read model.
 *
 * Canonical home for the claims list query + COUNT(*). Both the admin Claims
 * page and the REST claims controller previously carried verbatim copies of
 * the same `SELECT ... LEFT JOIN posts/users ... ORDER BY created_at DESC
 * LIMIT/OFFSET` query (plus a matching COUNT). They now route through
 * {@see Claims_Model::get_list()} + {@see Claims_Model::get_list_count()} so the
 * list and its total stay in lockstep and there is a single place to tune the
 * query for big-site readiness.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Read model for the listora_claims table.
 */
class Claims_Model {

	/**
	 * Get the claims table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'claims';
	}

	/**
	 * Get the geo table name.
	 *
	 * @return string
	 */
	private static function geo_table() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'geo';
	}

	/**
	 * Build the shared WHERE clause + bound parameters for both queries.
	 *
	 * Keeping the predicate in one place is what guarantees the list and the
	 * COUNT agree — a row that the list shows is a row the COUNT counts.
	 *
	 * @param array<string, mixed> $args Query args. See {@see Claims_Model::get_list()}.
	 * @return array{where: string, params: array<int, scalar>, geo: bool} The WHERE fragment, its ordered bound params, and whether the geo JOIN is forced.
	 */
	private static function build_where( array $args ) {
		global $wpdb;

		$where  = '1=1';
		$params = array();
		$geo    = false;

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND c.status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['listing_id'] ) ) {
			$where   .= ' AND c.listing_id = %d';
			$params[] = (int) $args['listing_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (p.post_title LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		// Bounding-box constraint on the listing's geo row. Applied only when
		// every edge is present so the query never degrades into an unbounded
		// scan of the geo table (big-site readiness — idx_lat_lng covers it).
		$has_box = isset( $args['min_lat'], $args['max_lat'], $args['min_lng'], $args['max_lng'] );
		if ( $has_box ) {
			$geo      = true;
			$where   .= ' AND g.lat BETWEEN %f AND %f AND g.lng BETWEEN %f AND %f';
			$params[] = (float) $args['min_lat'];
			$params[] = (float) $args['max_lat'];
			$params[] = (float) $args['min_lng'];
			$params[] = (float) $args['max_lng'];
		}

		return array(
			'where'  => $where,
			'params' => $params,
			'geo'    => $geo,
		);
	}

	/**
	 * Build the JOIN clause shared by both queries.
	 *
	 * @param bool $geo Whether the geo bounding-box JOIN is required.
	 * @return string
	 */
	private static function joins( $geo ) {
		global $wpdb;

		$joins  = " LEFT JOIN {$wpdb->posts} p ON c.listing_id = p.ID";
		$joins .= " LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID";

		if ( $geo ) {
			$geo_table = self::geo_table();
			$joins    .= " INNER JOIN {$geo_table} g ON c.listing_id = g.listing_id";
		}

		return $joins;
	}

	/**
	 * Get a page of claims with the joined listing title + claimant identity.
	 *
	 * @param array<string, mixed> $args {
	 *     Query args. All optional.
	 *
	 *     @type int    $limit      Max rows to return. Default 50.
	 *     @type int    $offset     Row offset. Default 0.
	 *     @type string $status     Filter by claim status (pending|approved|rejected).
	 *     @type int    $listing_id Filter by listing ID.
	 *     @type string $search     LIKE search across listing title + claimant name/email.
	 *     @type float  $min_lat    Bounding-box south edge (requires all four edges).
	 *     @type float  $max_lat    Bounding-box north edge.
	 *     @type float  $min_lng    Bounding-box west edge.
	 *     @type float  $max_lng    Bounding-box east edge.
	 * }
	 * @return array<int, array<string, mixed>> Array of associative claim rows (ARRAY_A).
	 */
	public static function get_list( array $args ) {
		global $wpdb;

		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 50;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$clause = self::build_where( $args );
		$table  = self::table();
		$joins  = self::joins( $clause['geo'] );

		$params   = $clause['params'];
		$params[] = $limit;
		$params[] = $offset;

		$sql = "SELECT c.*, p.post_title as listing_title, u.display_name as user_name, u.user_email
			FROM {$table} c{$joins}
			WHERE {$clause['where']}
			ORDER BY c.created_at DESC
			LIMIT %d OFFSET %d";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- placeholdered SQL assembled from a fixed template; only bound params are dynamic.
			$wpdb->prepare( $sql, ...$params ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count the claims matching the same filter as {@see Claims_Model::get_list()}.
	 *
	 * Accepts the same filter args (limit/offset are ignored — a COUNT spans the
	 * whole filtered set).
	 *
	 * @param array<string, mixed> $args Query args. See {@see Claims_Model::get_list()}.
	 * @return int
	 */
	public static function get_list_count( array $args ) {
		global $wpdb;

		$clause = self::build_where( $args );
		$table  = self::table();
		$joins  = self::joins( $clause['geo'] );

		$sql = "SELECT COUNT(*) FROM {$table} c{$joins} WHERE {$clause['where']}";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $clause['params'] ) ) {
			$total = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- placeholdered SQL assembled from a fixed template; only bound params are dynamic.
				$wpdb->prepare( $sql, ...$clause['params'] )
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $total;
	}
}
