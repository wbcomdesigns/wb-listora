<?php
/**
 * WordPress personal-data ERASER for WB Listora PII.
 *
 * Implements the `wp_privacy_personal_data_erasers` callback for reviews,
 * favorites and claims (registered in {@see \WBListora\Plugin}). Reviews are
 * anonymized, keeping `overall_rating`, and their listings' aggregates are
 * recomputed only via `wb_listora_recompute_listing_rating()`. Favorites and
 * claims are deleted. Work is batched per `$page`; `done` is true only when
 * all three domains are empty.
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
	 * Listora user-meta keys the member owns directly — their directory profile
	 * fields. Deleted outright on erasure: pure personal data with no shared
	 * aggregate to preserve.
	 *
	 * The per-event notification opt-outs (`_listora_notify_{event}`) are stored
	 * one key per event under a dynamic suffix, so they are matched by prefix
	 * (see {@see self::NOTIFY_META_PREFIX}) rather than enumerated here.
	 *
	 * @var array<int, string>
	 */
	const USER_META_KEYS = array(
		'_listora_phone',
		'_listora_social_links',
		'_listora_notifications_read_at',
	);

	/**
	 * Prefix of the per-event notification-preference user meta
	 * (`_listora_notify_review_reminder`, `_listora_notify_...`), written by the
	 * dashboard profile tab and the unsubscribe controller.
	 */
	const NOTIFY_META_PREFIX = '_listora_notify_';

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
			$response['messages'][] = sprintf(
				/* translators: 1: number of reviews, 2: product name. */
				_n( 'Anonymized %1$d %2$s review (rating retained).', 'Anonymized %1$d %2$s reviews (ratings retained).', $reviews['count'], 'wb-listora' ),
				$reviews['count'],
				wb_listora_brand_name()
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

		// The plugin-level erasure map, for everything the three domains above
		// don't own. This path is core's Erase Personal Data tool, where the
		// user row survives, so the `on_privacy_erasure` plan applies. Page 1
		// only (the executor loops each table itself), and not while
		// Account_Manager is deleting the account: it runs the map with the
		// `on_account_deletion` plan, which retains pointer-only rows.
		if ( 1 === $page && ! \WBListora\Privacy\Account_Manager::is_deleting_account() ) {
			$erased = \WBListora\Privacy\Account_Manager::run_erasure_map( $user_id, 'on_privacy_erasure' );

			foreach ( $erased as $rows ) {
				if ( (int) $rows > 0 ) {
					$response['items_removed'] = true;
				}
			}

			// The member's Listora profile fields + notification preferences live
			// in user meta, which the three table-domains above and the erasure map
			// (tables only) never touch. WordPress core's Erase Personal Data tool
			// does NOT delete the account, so this user meta would otherwise survive
			// the request — the gap this closes. On account deletion the flag above
			// is set and we skip it: `wp_delete_user()` clears all user meta itself.
			if ( self::delete_user_meta( $user_id ) > 0 ) {
				$response['items_removed'] = true;
			}

			// Art. 17(3) retention must be DISCLOSED, not silent. Financial
			// records (payments rows, the SDK credit ledger + gateway log) are
			// kept under Art. 17(3)(b); tell the data subject exactly that.
			$retained = \WBListora\Privacy\Account_Manager::describe_retained( $user_id, 'on_privacy_erasure' );

			if ( ! empty( $retained ) ) {
				$response['items_retained'] = true;
				$response['messages'][]     = sprintf( /* translators: %s: product name */ __( 'Some %s records were retained: financial records (payments, credit ledger entries, and payment-gateway logs) are kept to meet accounting and tax obligations, which is a lawful basis for retention under GDPR Art. 17(3)(b). Personal identifiers on those records — billing name and billing email — have been removed.', 'wb-listora' ), wb_listora_brand_name() );
			}
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix + a fixed constant.
				"SELECT id, listing_id, photos FROM {$table} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
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

			// The photos are the member's own uploads: erase them with the text.
			\WBListora\Core\Listing_Data_Eraser::delete_review_photos( $row['photos'] ?? null, (int) $user_id );

			// Strip identity + free-text PII; retain overall_rating + status so
			// the row still counts toward the listing aggregate.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API
			$updated = $wpdb->update(
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

			/*
			 * The anonymise UPDATE can fail: UNIQUE(user_id, listing_id) makes a
			 * second erased review on the same listing collide on user_id = 0.
			 * The PII must go either way, so delete the row on collision; the
			 * listing is still recomputed below.
			 */
			if ( false === $updated ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API
				$wpdb->delete( $table, array( 'id' => $review_id ), array( '%d' ) );
			}

			$listing_ids[] = (int) $row['listing_id'];
		}

		// Is there another page of this user's reviews after the one we just did?
		// Rows are kept (anonymized), so the next page is offset + limit.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, no WP API; table name built from $wpdb->prefix + fixed constant.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, proof_files FROM {$table} WHERE user_id = %d LIMIT %d", $user_id, $limit ) );
		$ids  = array();
		foreach ( (array) $rows as $row ) {
			\WBListora\Core\Claim_Proofs::delete_files( $row->proof_files ); // The claimant's ID scans and bills go with the claim.
			$ids[] = (int) $row->id;
		}
		$deleted = empty( $ids ) ? 0 : $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', $ids ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API
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
	 * Delete the member's Listora profile + notification-preference user meta.
	 *
	 * Runs on the WordPress privacy-tool path only (the caller guards against the
	 * account-deletion path, where `wp_delete_user()` clears all user meta). One
	 * bounded DELETE covers the fixed profile keys plus every dynamic
	 * `_listora_notify_{event}` opt-out via a prefix match.
	 *
	 * @param int $user_id Resolved user ID.
	 * @return int Rows deleted.
	 */
	private static function delete_user_meta( $user_id ) {
		global $wpdb;

		$keys         = self::USER_META_KEYS;
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$like         = $wpdb->esc_like( self::NOTIFY_META_PREFIX ) . '%';

		// user_id, then each exact key, then the notify-prefix LIKE — bound in order.
		$params = array_merge( array( (int) $user_id ), $keys, array( $like ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- erasing a user's plugin meta by key pattern, no WP API
		$deleted = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholder list is built from a fixed hard-coded key array; every value is bound.
				"DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND ( meta_key IN ({$placeholders}) OR meta_key LIKE %s )",
				$params
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// The raw DELETE bypasses WP's meta cache, so bust it — otherwise any
		// get_user_meta() later in this same request would read the stale values.
		if ( is_numeric( $deleted ) && (int) $deleted > 0 ) {
			wp_cache_delete( (int) $user_id, 'user_meta' );
		}

		return is_numeric( $deleted ) ? (int) $deleted : 0;
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
