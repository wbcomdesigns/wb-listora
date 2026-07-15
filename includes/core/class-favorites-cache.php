<?php
/**
 * Request-scoped batching for favorite lookups.
 *
 * Every listing surface that renders a heart needs two facts: "has *this* user
 * favorited *this* listing" (`is_favorited`) and, on the detail screen, "how
 * many people favorited it" (`favorite_count`). Both were answered with one
 * `SELECT COUNT(*)` per listing, issued from inside
 * {@see \WBListora\REST\Listings_Controller::prepare_item_for_response()} — so a
 * `per_page=20` page of listings cost 20 extra queries, and the number grew
 * linearly with the page size.
 *
 * This service applies the pattern the codebase already uses for the adjacent
 * view-count block: {@see \WBListora\Features\Analytics_Lite::prepare_views()}
 * primes a per-request cache with ONE batched query per page, and the per-row
 * read is then a memory hit. Favourites simply never got it.
 *
 * Usage — prime once per page, read per row:
 *
 *     Favorites_Cache::prime( $listing_ids );          // 1 query for the page
 *     foreach ( $listing_ids as $id ) {
 *         $fav = Favorites_Cache::is_favorited( $id );  // memory hit
 *     }
 *
 * A read for an ID that was never primed falls back to a bounded single-ID
 * batch through the same one-query path, so an unprimed caller behaves exactly
 * as before (same value, same cost) and no caller is *required* to prime.
 *
 * **Anonymous contract:** `is_favorited` is `false` for logged-out users and
 * issues no query at all — a favourite is a per-user fact and an anonymous
 * request has no user. The field is always *present* (never omitted) on every
 * surface that carries it — `/search`, `/listings`, `/listings/{id}/detail`
 * and `/listings/bulk` — so a client can read `item.is_favorited` without a
 * presence check and without branching on auth state.
 *
 * Both queries are covered by existing indexes — the `favorites` table's
 * PRIMARY KEY is `(user_id, listing_id)` and it carries a secondary
 * `idx_listing (listing_id)` — so no schema change is needed or made.
 *
 * @package WBListora\Core
 * @since   1.2.3
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Batched favorite lookups with a request-scoped cache.
 */
class Favorites_Cache {

	/**
	 * Resolved per-user favorite flags: `[ user_id => [ listing_id => bool ] ]`.
	 *
	 * @var array<int, array<int, bool>>
	 */
	private static $favorited = array();

	/**
	 * IDs already resolved for a user: `[ user_id => [ listing_id => true ] ]`.
	 *
	 * Tracked separately from {@see self::$favorited} so "primed and false" is
	 * distinguishable from "never looked up" — the batched query only returns
	 * rows that exist, so absence from the result set is a real `false`, not a
	 * cache miss.
	 *
	 * @var array<int, array<int, bool>>
	 */
	private static $primed = array();

	/**
	 * Per-listing favorite totals: `[ listing_id => int ]`.
	 *
	 * @var array<int, int>
	 */
	private static $counts = array();

	/**
	 * Prime the favorited flags for a batch of listings in one query.
	 *
	 * Call once per page, before the render/prepare loop.
	 *
	 * @param array<int> $listing_ids Listing post IDs on the current page.
	 * @param int        $user_id     Optional user. Defaults to current user.
	 * @return void
	 */
	public static function prime( array $listing_ids, $user_id = 0 ) {
		$user_id = (int) $user_id ?: get_current_user_id();

		// A favourite is a per-user fact; anonymous callers have none.
		if ( $user_id <= 0 ) {
			return;
		}

		$listing_ids = array_values( array_unique( array_filter( array_map( 'intval', $listing_ids ) ) ) );
		if ( empty( $listing_ids ) ) {
			return;
		}

		// Only look up what we haven't already resolved for this user.
		$pending = array_values(
			array_filter(
				$listing_ids,
				static function ( $id ) use ( $user_id ) {
					return ! isset( self::$primed[ $user_id ][ $id ] );
				}
			)
		);

		if ( empty( $pending ) ) {
			return;
		}

		global $wpdb;
		$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$placeholders = implode( ',', array_fill( 0, count( $pending ), '%d' ) );

		// One query for the whole page. Hits the PRIMARY KEY (user_id, listing_id).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT listing_id FROM {$prefix}favorites WHERE user_id = %d AND listing_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $user_id ), $pending )
			)
		);

		$hits = array_flip( array_map( 'intval', (array) $rows ) );

		foreach ( $pending as $id ) {
			self::$primed[ $user_id ][ $id ]    = true;
			self::$favorited[ $user_id ][ $id ] = isset( $hits[ $id ] );
		}
	}

	/**
	 * Whether a user has favorited a listing.
	 *
	 * Reads the cache primed by {@see self::prime()}. An unprimed ID falls back
	 * to a bounded single-ID prime so the value is always correct.
	 *
	 * @param int $listing_id Listing post ID.
	 * @param int $user_id    Optional user. Defaults to current user.
	 * @return bool False for anonymous callers (no query issued).
	 */
	public static function is_favorited( $listing_id, $user_id = 0 ) {
		$user_id    = (int) $user_id ?: get_current_user_id();
		$listing_id = (int) $listing_id;

		if ( $user_id <= 0 || $listing_id <= 0 ) {
			return false;
		}

		if ( ! isset( self::$primed[ $user_id ][ $listing_id ] ) ) {
			self::prime( array( $listing_id ), $user_id );
		}

		return ! empty( self::$favorited[ $user_id ][ $listing_id ] );
	}

	/**
	 * Prime the per-listing favorite totals for a batch of listings in one query.
	 *
	 * @param array<int> $listing_ids Listing post IDs.
	 * @return void
	 */
	public static function prime_counts( array $listing_ids ) {
		$listing_ids = array_values( array_unique( array_filter( array_map( 'intval', $listing_ids ) ) ) );

		$pending = array_values(
			array_filter(
				$listing_ids,
				static function ( $id ) {
					return ! isset( self::$counts[ $id ] );
				}
			)
		);

		if ( empty( $pending ) ) {
			return;
		}

		global $wpdb;
		$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$placeholders = implode( ',', array_fill( 0, count( $pending ), '%d' ) );

		// One grouped query. Hits idx_listing (listing_id).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, COUNT(*) AS total FROM {$prefix}favorites WHERE listing_id IN ({$placeholders}) GROUP BY listing_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$pending
			),
			ARRAY_A
		);

		$totals = array();
		foreach ( (array) $rows as $row ) {
			$totals[ (int) $row['listing_id'] ] = (int) $row['total'];
		}

		// A listing with zero favourites has no row — record the 0 so a later
		// read is a cache hit rather than a repeat query.
		foreach ( $pending as $id ) {
			self::$counts[ $id ] = $totals[ $id ] ?? 0;
		}
	}

	/**
	 * Total number of users who favorited a listing.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return int
	 */
	public static function get_count( $listing_id ) {
		$listing_id = (int) $listing_id;

		if ( $listing_id <= 0 ) {
			return 0;
		}

		if ( ! isset( self::$counts[ $listing_id ] ) ) {
			self::prime_counts( array( $listing_id ) );
		}

		return (int) ( self::$counts[ $listing_id ] ?? 0 );
	}

	/**
	 * Drop cached state for a listing so the next read re-queries.
	 *
	 * The cache is request-scoped, so this only matters for a request that
	 * writes a favourite and then reads it back (the add/remove endpoints).
	 *
	 * @param int $listing_id Listing post ID.
	 * @param int $user_id    Optional user. Defaults to current user.
	 * @return void
	 */
	public static function forget( $listing_id, $user_id = 0 ) {
		$user_id    = (int) $user_id ?: get_current_user_id();
		$listing_id = (int) $listing_id;

		unset( self::$counts[ $listing_id ] );

		if ( $user_id > 0 ) {
			unset( self::$primed[ $user_id ][ $listing_id ], self::$favorited[ $user_id ][ $listing_id ] );
		}
	}
}
