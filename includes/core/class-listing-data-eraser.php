<?php
/**
 * Listing data eraser — cascades a permanent listing delete across every
 * Free-owned listing-scoped custom table.
 *
 * Before 1.4.1 the only delete cascade was Search_Indexer::remove_from_index()
 * (search_index / field_index / geo / hours — correct for what that method is:
 * index maintenance, which must also run on trash). Nothing ever deleted the
 * DATA tables by listing_id, so hard-deleting a listing orphaned rows in
 * reviews, review_votes, favorites, claims, services, and analytics forever
 * (BC 10156782139).
 *
 * Scope rules:
 * - Runs ONLY on permanent delete (`before_delete_post`). Trashing a listing
 *   keeps all its data so restore works — the indexer alone handles trash.
 * - Deletes ONLY Free-owned tables (INV-6: no cross-plugin table writes).
 *   Pro cleans its own listing-scoped tables (need_responses, coupon_usage)
 *   by listening to the `wb_listora_listing_data_deleted` action fired here.
 * - `payments` is intentionally RETAINED: financial records are kept for
 *   accounting/tax obligations (GDPR Art. 17(3)(b)) — the same policy
 *   Privacy_Eraser applies to personal-data erasure requests. Post IDs are
 *   never reused, so the stale listing_id pointer is inert.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes all Free-owned listing-scoped rows when a listing is hard-deleted.
 */
class Listing_Data_Eraser {

	/**
	 * Free-owned tables (without the listora_ prefix) that carry a
	 * listing_id column and hold listing DATA (not index rows).
	 *
	 * The four index tables (search_index, field_index, geo, hours) are
	 * owned by Search_Indexer, which already cascades them on both trash
	 * and delete.
	 *
	 * @var string[]
	 */
	private const DATA_TABLES = array(
		'reviews',
		'favorites',
		'claims',
		'services',
		'analytics',
	);

	/**
	 * Hook up the cascade.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'before_delete_post', array( $this, 'erase' ), 10, 2 );

		// The cascade above only covers deletes that happen from 1.4.1 onward.
		// Every listing hard-deleted BEFORE it shipped left its rows behind, and
		// a stale search_index row still carries status 'publish' — Search_Engine
		// selects candidates from that table with no join to wp_posts, so those
		// rows keep inflating result totals and pagination. purge_orphans() was
		// reachable only from `wp listora cleanup`, a command site owners have no
		// reason to run, so the backfill never happened on real sites.
		add_action( 'wb_listora_daily_cleanup', array( $this, 'purge_orphans_cron' ) );
	}

	/**
	 * Delete every Free-owned listing-scoped row for a hard-deleted listing.
	 *
	 * @param int           $post_id Post ID being deleted.
	 * @param \WP_Post|null $post    Post object (WP >= 5.5 passes it).
	 * @return void
	 */
	public function erase( $post_id, $post = null ) {
		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// review_votes has no listing_id of its own — it is keyed on
		// review_id, so it must go BEFORE its parent reviews rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- listing-scoped custom tables, prefix is constant-built.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are constant-built.
				"DELETE rv FROM {$prefix}review_votes rv INNER JOIN {$prefix}reviews r ON rv.review_id = r.id WHERE r.listing_id = %d",
				$post_id
			)
		);

		foreach ( self::DATA_TABLES as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- listing-scoped custom tables, prefix is constant-built.
			$wpdb->delete( "{$prefix}{$table}", array( 'listing_id' => $post_id ), array( '%d' ) );
		}

		/**
		 * Fires after Free has deleted every Free-owned listing-scoped row
		 * for a permanently deleted listing.
		 *
		 * Pro (and any extension owning a listing-scoped table) listens here
		 * to cascade its own tables — INV-6 forbids Free touching them.
		 *
		 * @since 1.4.1
		 *
		 * @param int      $post_id Listing post ID that was hard-deleted.
		 * @param \WP_Post $post    The listing post object (pre-delete).
		 */
		do_action( 'wb_listora_listing_data_deleted', $post_id, $post );
	}

	/**
	 * Void adapter for the daily-cleanup action.
	 *
	 * `purge_orphans()` returns per-table counts because `wp listora cleanup`
	 * reports them. An action callback must return nothing, so the cron path
	 * goes through here instead of hooking the counting method directly.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function purge_orphans_cron(): void {
		$this->purge_orphans();
	}

	/**
	 * Purge rows whose listing no longer exists (backfill for sites that
	 * hard-deleted listings before 1.4.1 shipped this cascade).
	 *
	 * Post IDs are never reused, so any listing_id without a wp_posts row is
	 * a permanent orphan. Called from `wp listora cleanup`.
	 *
	 * @return array<string, int> Table (unprefixed) => rows deleted.
	 */
	public function purge_orphans() {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$deleted = array();

		// Orphaned vote rows first (parent review's listing is gone), then
		// votes whose review row itself is gone.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- backfill over constant-built custom tables.
		$deleted['review_votes'] = (int) $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE rv FROM {$prefix}review_votes rv
			 LEFT JOIN {$prefix}reviews r ON rv.review_id = r.id
			 LEFT JOIN {$wpdb->posts} p ON r.listing_id = p.ID
			 WHERE r.id IS NULL OR p.ID IS NULL"
		);

		foreach ( self::DATA_TABLES as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted[ $table ] = (int) $wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE t FROM {$prefix}{$table} t LEFT JOIN {$wpdb->posts} p ON t.listing_id = p.ID WHERE p.ID IS NULL"
			);
		}

		/**
		 * Fires after Free has purged orphaned listing-scoped rows.
		 *
		 * Pro listens here to purge orphans from its own listing-scoped
		 * tables during the same `wp listora cleanup` run.
		 *
		 * @since 1.4.1
		 */
		do_action( 'wb_listora_purge_orphaned_listing_data' );

		return $deleted;
	}
}
