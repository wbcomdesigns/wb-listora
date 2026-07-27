<?php
/**
 * Shared plumbing for per-request batch caches.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * The bookkeeping every batch-prime cache repeats.
 *
 * A batch cache exists to answer "one query per page, never one per row" — the
 * big-site rule. Each one differs in the ONE part that matters (its table and
 * its SELECT), but they all repeat the same three chores around it: normalise
 * the incoming ids, drop the ones already resolved, and build the placeholder
 * string. That bookkeeping was copy-pasted between Favorites_Cache and
 * Business_Hours, which the duplicate detector flagged at ~103 tokens.
 *
 * This class owns ONLY the bookkeeping. It deliberately does not own the query:
 * the callers' SELECTs are genuinely different (different tables, different key
 * shapes, one is per-user), and forcing them through a shared query builder
 * would trade a small honest duplication for a leaky abstraction. Extract the
 * repetition, not the meaning.
 *
 * @since 1.2.3
 */
class Batch_Prime {

	/**
	 * Normalise a caller-supplied id list into unique positive ints.
	 *
	 * Callers get ids from REST params, post loops and cursor pages, so the list
	 * arrives with strings, nulls, zeroes and duplicates. Every cache repeated
	 * this same map/filter/unique dance before it could trust the input.
	 *
	 * @param array $ids Raw ids.
	 * @return int[] Unique positive ints, re-indexed.
	 */
	public static function ids( array $ids ): array {
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Reduce a normalised id list to the ones not already cached.
	 *
	 * Priming is called per page and pages overlap (a cursor re-read, a related
	 * strip, a bulk rehydrate), so without this a warm cache still pays for a
	 * query.
	 *
	 * @param int[]    $ids       Normalised ids (run them through ids() first).
	 * @param callable $is_cached fn( int $id ): bool — true when already resolved.
	 * @return int[] The ids still needing a lookup. Empty means: do not query.
	 */
	public static function pending( array $ids, callable $is_cached ): array {
		return array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $is_cached ) {
					return ! $is_cached( (int) $id );
				}
			)
		);
	}

	/**
	 * Build a `%d,%d,%d` placeholder run for an IN () clause.
	 *
	 * Always feed the SAME array to $wpdb->prepare() that was measured here — a
	 * count/args mismatch is how a prepare() silently returns an empty string
	 * and the query quietly does nothing.
	 *
	 * @param int $count How many ids.
	 * @return string Placeholder run, or '' when there is nothing to query.
	 */
	public static function placeholders( int $count ): string {
		return $count > 0 ? implode( ',', array_fill( 0, $count, '%d' ) ) : '';
	}
}
