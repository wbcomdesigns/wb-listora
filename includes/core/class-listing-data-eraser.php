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

		self::delete_listing_media( $post_id );

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

	/**
	 * Delete the images a permanently-deleted listing owned.
	 *
	 * WHY THE PLUGIN HAS TO DO THIS. WordPress does not delete a post's
	 * attachments when the post goes — `wp_delete_post()` resets their
	 * `post_parent` to 0 and leaves both the rows and the files on disk.
	 * Verified on this codebase: hard-deleting a listing with a featured image
	 * and two gallery images left all three attachments alive, re-orphaned, and
	 * every file still on disk. On a directory where members upload photos with
	 * every submission, that is an upload folder that only ever grows, full of
	 * images belonging to listings that no longer exist.
	 *
	 * SCOPE. Permanent delete only — this runs from `before_delete_post`, so
	 * trashing a listing still keeps everything and restore works unchanged,
	 * matching the rule the rest of this class follows.
	 *
	 * WHAT COUNTS AS THE LISTING'S OWN. The union of its featured image, its
	 * gallery meta, and anything parented to it. Read here, before the delete,
	 * because the meta is gone afterwards.
	 *
	 * THE ONE THING IT WILL NOT DELETE is an image another listing still uses.
	 * A member can put the same photo on two listings; deleting the first must
	 * not blank the second. That is not a hedge against the delete policy — it
	 * is the difference between removing this listing's images and corrupting a
	 * different listing.
	 *
	 * @since 1.7.0
	 *
	 * @param int $post_id Listing being permanently deleted.
	 * @return void
	 */
	private static function delete_listing_media( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}

		/**
		 * Filter whether a listing's images are deleted along with it.
		 *
		 * Return false to keep them — they become unattached Media Library
		 * items, which is exactly the behaviour before 1.7.0.
		 *
		 * @since 1.7.0
		 *
		 * @param bool $delete  Whether to delete the listing's images.
		 * @param int  $post_id Listing being permanently deleted.
		 */
		if ( ! apply_filters( 'wb_listora_delete_listing_media', true, $post_id ) ) {
			return;
		}

		$ids = array();

		$featured = (int) get_post_thumbnail_id( $post_id );
		if ( $featured > 0 ) {
			$ids[] = $featured;
		}

		$gallery = Meta_Handler::get_value( $post_id, 'gallery' );
		if ( is_array( $gallery ) ) {
			foreach ( $gallery as $gallery_id ) {
				$ids[] = absint( $gallery_id );
			}
		}

		// Anything parented to the listing — custom image fields included,
		// which are in neither the thumbnail nor the gallery.
		$children = get_children(
			array(
				'post_parent' => $post_id,
				'post_type'   => 'attachment',
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);
		if ( is_array( $children ) ) {
			foreach ( $children as $child ) {
				// 'fields' => 'ids' yields ints, but get_children()'s return shape
				// follows that argument. Reading ->ID when handed objects keeps a
				// later change to 'fields' from turning absint() loose on a
				// WP_Post -- in code whose next step is deleting what it collects.
				$ids[] = absint( is_object( $child ) ? $child->ID : $child );
			}
		}

		// Custom image/file fields the listing TYPE defines — a Company Logo, a
		// brochure, a second gallery. These live in neither _thumbnail_id nor
		// the built-in gallery meta.
		//
		// Read from the field definitions rather than leaning on post_parent,
		// and that is the whole point: parenting only starts at a listing's
		// next save, so on every install upgrading to 1.7.0 the existing
		// listings have post_parent 0. get_children() finds nothing for them.
		// Without this the feature would work on new listings and silently do
		// nothing on the entire back catalogue of every live site — and it
		// would have needed a data migration to fix, which this avoids.
		$type_terms = wp_get_object_terms( $post_id, 'listora_listing_type', array( 'fields' => 'slugs' ) );
		$type_slug  = ( ! is_wp_error( $type_terms ) && ! empty( $type_terms ) ) ? $type_terms[0] : '';

		if ( '' !== $type_slug ) {
			$listing_type = Listing_Type_Registry::instance()->get( $type_slug );

			if ( $listing_type ) {
				foreach ( $listing_type->get_all_fields() as $field ) {
					if ( ! in_array( $field->get_type(), array( 'file', 'image', 'gallery' ), true ) ) {
						continue;
					}

					// file is a scalar id, gallery an array — (array) covers both.
					foreach ( (array) Meta_Handler::get_value( $post_id, $field->get_key() ) as $field_value ) {
						$ids[] = absint( $field_value );
					}
				}
			}
		}

		$ids = array_filter( array_unique( $ids ) );
		if ( empty( $ids ) ) {
			return;
		}

		$deleted = array();

		foreach ( $ids as $attachment_id ) {
			if ( self::attachment_used_elsewhere( $attachment_id, $post_id ) ) {
				continue;
			}

			if ( wp_delete_attachment( $attachment_id, true ) ) {
				$deleted[] = $attachment_id;
			}
		}

		/**
		 * Fires after a deleted listing's images have been removed.
		 *
		 * @since 1.7.0
		 *
		 * @param int   $post_id    Listing that was deleted.
		 * @param array $deleted    Attachment IDs actually deleted.
		 * @param array $considered Every attachment ID considered.
		 */
		do_action( 'wb_listora_listing_media_deleted', $post_id, $deleted, $ids );
	}

	/**
	 * Is this attachment still used by a listing other than the one going away?
	 *
	 * Checks both places a listing can reference an image: `_thumbnail_id`, and
	 * the gallery meta, which stores a serialized array of IDs. The gallery
	 * lookup is a LIKE against the serialized form — crude, but it errs toward
	 * KEEPING an image, and the cost of a false positive (one file survives) is
	 * far smaller than a false negative (a live listing loses its photo).
	 *
	 * @since 1.7.0
	 *
	 * @param int $attachment_id Attachment being considered for deletion.
	 * @param int $exclude_id    The listing being deleted.
	 * @return bool
	 */
	private static function attachment_used_elsewhere( $attachment_id, $exclude_id ) {
		global $wpdb;

		$as_thumbnail = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_thumbnail_id'
				   AND pm.meta_value = %s
				   AND pm.post_id != %d
				   AND p.post_type = 'listora_listing'",
				(string) $attachment_id,
				$exclude_id
			)
		);

		if ( $as_thumbnail > 0 ) {
			return true;
		}

		// Same key Meta_Handler::get_value() builds: prefix + field name.
		$gallery_key = WB_LISTORA_META_PREFIX . 'gallery';

		$in_gallery = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND pm.post_id != %d
				   AND p.post_type = 'listora_listing'
				   AND pm.meta_value LIKE %s",
				$gallery_key,
				$exclude_id,
				'%i:' . $wpdb->esc_like( (string) $attachment_id ) . ';%'
			)
		);

		return $in_gallery > 0;
	}

}
