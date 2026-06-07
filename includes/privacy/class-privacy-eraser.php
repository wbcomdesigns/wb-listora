<?php
/**
 * WordPress personal-data ERASER for WB Listora PII.
 *
 * Implements the core `wp_privacy_personal_data_erasers` callback contract for
 * the three Listora domains that store user-attributable data: reviews,
 * favorites, and claims. The callback is registered elsewhere (the single
 * registration site in {@see \WBListora\Plugin}); this class owns only the
 * erasure behavior so it can be unit-tested and called in isolation.
 *
 * Erasure policy per domain:
 *
 * - Reviews are ANONYMIZED, not deleted. The reviewer's identity and free-text
 *   PII (user_id, title, content, photos, ip_address, per-criterion ratings)
 *   are stripped while the row — and crucially its `overall_rating` — is
 *   retained, so a listing's rating aggregates (avg_rating / review_count in
 *   the search_index) stay correct. After anonymizing, every affected listing's
 *   aggregates are recomputed through the canonical recompute entry point
 *   (f7a — `wb_listora_recompute_listing_rating()`). This eraser MUST NOT write
 *   the aggregates itself; it always goes through that single recompute path.
 *
 * - Favorites rows for the user are DELETED outright (a favorite is pure
 *   personal data with no shared aggregate to preserve).
 *
 * - Claims rows for the user are DELETED outright (the proof text / proof files
 *   are the claimant's PII; the listing itself is unaffected).
 *
 * Work is batched per `$page` with a bounded LIMIT so a user with thousands of
 * rows never loads an unbounded set into memory. `done` is reported true only
 * when no more rows remain across ALL three domains.
 *
 * Reuses the same `listora_` table layout the read paths use (reviews/favorites/
 * claims are all keyed on `user_id`; the supplied email resolves to its WP user
 * via {@see get_user_by()}).
 *
 * @package WBListora\Privacy
 */

namespace WBListora\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Personal-data eraser for Listora reviews, favorites, and claims.
 */
class Privacy_Eraser {

	/**
	 * Rows processed per domain per page.
	 *
	 * Bounds memory + query cost for users with very large histories. The WP
	 * privacy tool re-invokes the eraser with the next page until `done`.
	 */
	const PER_PAGE = 100;

	/**
	 * Eraser callback conforming to the WP privacy personal-data eraser contract.
	 *
	 * @param string $email_address Email address of the data subject.
	 * @param int    $page          1-based page number supplied by the privacy tool.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public static function erase( $email_address, $page = 1 ) {
		$page = max( 1, (int) $page );

		$response = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof \WP_User ) {
			// No matching account — nothing keyed on user_id to erase.
			return $response;
		}

		$user_id = (int) $user->ID;
		$limit   = (int) apply_filters( 'wb_listora_privacy_erase_per_page', self::PER_PAGE, $email_address, $page );
		$limit   = max( 1, $limit );
		$offset  = ( $page - 1 ) * $limit;

		// Each domain reports whether more rows remain so `done` reflects all
		// three. Reviews additionally report which listings need a recompute.
		$reviews = self::anonymize_reviews( $user_id, $limit, $offset );
		$favs    = self::delete_favorites( $user_id, $limit );
		$claims  = self::delete_claims( $user_id, $limit );

		if ( $reviews['count'] > 0 ) {
			$response['items_removed'] = true;
			/* translators: %d: number of reviews anonymized. */
			$response['messages'][] = sprintf(
				/* translators: %d: number of reviews. */
				_n( 'Anonymized %d WB Listora review (rating retained).', 'Anonymized %d WB Listora reviews (ratings retained).', $reviews['count'], 'wb-listora' ),
				$reviews['count']
			);

			// Keep listing rating aggregates correct via the canonical recompute
			// entry point (f7a). Never write the aggregates here.
			self::recompute_listings( $reviews['listing_ids'] );
		}

		if ( $favs > 0 ) {
			$response['items_removed'] = true;
		}

		if ( $claims > 0 ) {
			$response['items_removed'] = true;
		}

		// More work remains in any domain → not done yet. Reviews paginate with
		// an offset (rows are kept, so they don't drain); favorites + claims are
		// deleted, so they drain from the front and never need an offset.
		$response['done'] = ! ( $reviews['has_more'] || self::favorites_remain( $user_id ) || self::claims_remain( $user_id ) );

		return $response;
	}

	/**
	 * Anonymize a bounded page of the user's reviews in place.
	 *
	 * Strips the reviewer's identity + free-text PII but retains the row and its
	 * `overall_rating` so listing aggregates survive. The listing owner's reply
	 * (`owner_reply`) is the owner's content, not the data subject's PII, so it
	 * is preserved.
	 *
	 * @param int $user_id Resolved user ID.
	 * @param int $limit   Max rows this page.
	 * @param int $offset  Row offset for this page.
	 * @return array{count: int, listing_ids: array<int, int>, has_more: bool}
	 */
	private static function anonymize_reviews( $user_id, $limit, $offset ) {
		global $wpdb;

		$table = self::reviews_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix + a fixed constant.
				"SELECT id, listing_id FROM {$table} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$rows = is_array( $rows ) ? $rows : array();
		if ( empty( $rows ) ) {
			return array(
				'count'       => 0,
				'listing_ids' => array(),
				'has_more'    => false,
			);
		}

		$listing_ids = array();
		foreach ( $rows as $row ) {
			$review_id = (int) $row['id'];

			// Strip identity + free-text PII; retain overall_rating + status so
			// the row still counts toward the listing aggregate.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'user_id'          => 0,
					'title'            => '',
					'content'          => '',
					'photos'           => null,
					'criteria_ratings' => null,
					'ip_address'       => '',
				),
				array( 'id' => $review_id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			$listing_ids[] = (int) $row['listing_id'];
		}

		// Is there another page of this user's reviews after the one we just did?
		// Rows are kept (anonymized), so the next page is offset + limit.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix + fixed constant.
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'count'       => count( $rows ),
			'listing_ids' => array_values( array_unique( array_filter( $listing_ids ) ) ),
			'has_more'    => ( $offset + count( $rows ) ) < $remaining,
		);
	}

	/**
	 * Delete a bounded page of the user's favorites.
	 *
	 * @param int $user_id Resolved user ID.
	 * @param int $limit   Max rows to delete this page.
	 * @return int Rows deleted.
	 */
	private static function delete_favorites( $user_id, $limit ) {
		global $wpdb;

		$table = self::favorites_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix + fixed constant.
				"DELETE FROM {$table} WHERE user_id = %d LIMIT %d",
				$user_id,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}

	/**
	 * Delete a bounded page of the user's claims.
	 *
	 * @param int $user_id Resolved user ID.
	 * @param int $limit   Max rows to delete this page.
	 * @return int Rows deleted.
	 */
	private static function delete_claims( $user_id, $limit ) {
		global $wpdb;

		$table = self::claims_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix + fixed constant.
				"DELETE FROM {$table} WHERE user_id = %d LIMIT %d",
				$user_id,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}

	/**
	 * Whether any favorites rows remain for the user.
	 *
	 * @param int $user_id Resolved user ID.
	 * @return bool
	 */
	private static function favorites_remain( $user_id ) {
		return self::rows_remain( self::favorites_table(), $user_id );
	}

	/**
	 * Whether any claims rows remain for the user.
	 *
	 * @param int $user_id Resolved user ID.
	 * @return bool
	 */
	private static function claims_remain( $user_id ) {
		return self::rows_remain( self::claims_table(), $user_id );
	}

	/**
	 * Whether any rows keyed on the user remain in the given table.
	 *
	 * @param string $table   Fully-qualified table name.
	 * @param int    $user_id Resolved user ID.
	 * @return bool
	 */
	private static function rows_remain( $table, $user_id ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix + fixed constant.
				"SELECT 1 FROM {$table} WHERE user_id = %d LIMIT 1",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return ! empty( $exists );
	}

	/**
	 * Recompute rating aggregates for every affected listing.
	 *
	 * Routes through the canonical recompute entry point (f7a). This eraser must
	 * never write avg_rating / review_count itself — recompute is the single
	 * owner of that derivation, so anonymized reviews keep their rating in the
	 * aggregate exactly as the recompute defines it.
	 *
	 * @param array<int, int> $listing_ids Affected listing IDs.
	 * @return void
	 */
	private static function recompute_listings( array $listing_ids ) {
		if ( empty( $listing_ids ) ) {
			return;
		}

		foreach ( $listing_ids as $listing_id ) {
			$listing_id = (int) $listing_id;
			if ( $listing_id <= 0 ) {
				continue;
			}

			/**
			 * Canonical rating-aggregate recompute entry point (f7a).
			 *
			 * Guarded so a merge-order slip can never fatal the eraser. When
			 * present (the dependency contract guarantees it at runtime), the
			 * recompute is the single path that keeps avg_rating / review_count
			 * correct after a review is anonymized.
			 */
			if ( function_exists( 'wb_listora_recompute_listing_rating' ) ) {
				wb_listora_recompute_listing_rating( $listing_id );
			}
		}
	}

	/**
	 * Reviews table name.
	 *
	 * @return string
	 */
	private static function reviews_table() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews';
	}

	/**
	 * Favorites table name.
	 *
	 * @return string
	 */
	private static function favorites_table() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'favorites';
	}

	/**
	 * Claims table name.
	 *
	 * @return string
	 */
	private static function claims_table() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'claims';
	}
}
