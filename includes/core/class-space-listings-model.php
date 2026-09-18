<?php
/**
 * Read/write model for the listora_space_listings table.
 *
 * A member submits a listing they own to a BuddyNext space (status 'pending');
 * the space team approves it ('approved') before it shows in that space's
 * Businesses tab. The curator can also add a listing already approved. One row
 * per (space, listing) - the UNIQUE key makes a re-submit idempotent.
 *
 * `space_id` is an OPAQUE BuddyNext id - this plugin never resolves it. All
 * authorization (who may submit, who may approve) is decided by the caller
 * (BuddyNext, via the integration); this model only stores and reads links.
 *
 * @package WB_Listora
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Business listing <-> space link store.
 */
class Space_Listings_Model {

	const STATUS_PENDING  = 'pending';
	const STATUS_APPROVED = 'approved';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'space_listings';
	}

	/**
	 * Submit a listing to a space (pending the space team's approval).
	 * Idempotent: a re-submit of an existing (space, listing) row leaves its
	 * current status untouched.
	 *
	 * @param int $space_id   Opaque space id.
	 * @param int $listing_id Listing post id.
	 * @param int $user_id    Submitting member (the listing owner).
	 * @return bool True when a new pending row was created.
	 */
	public static function submit( $space_id, $listing_id, $user_id ) {
		global $wpdb;
		$space_id   = (int) $space_id;
		$listing_id = (int) $listing_id;
		if ( $space_id <= 0 || $listing_id <= 0 ) {
			return false;
		}
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$done = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} ( space_id, listing_id, status, submitted_by, created_at, updated_at )
				 VALUES ( %d, %d, %s, %d, %s, %s )",
				$space_id,
				$listing_id,
				self::STATUS_PENDING,
				max( 0, (int) $user_id ),
				current_time( 'mysql' ),
				current_time( 'mysql' )
			)
		);

		return (int) $done > 0;
	}

	/**
	 * Approve a submission (or flip a curator's direct add to approved).
	 *
	 * @param int $space_id   Space id.
	 * @param int $listing_id Listing id.
	 * @param int $approver   Space team member approving.
	 * @return bool
	 */
	public static function approve( $space_id, $listing_id, $approver ) {
		global $wpdb;
		$space_id   = (int) $space_id;
		$listing_id = (int) $listing_id;
		if ( $space_id <= 0 || $listing_id <= 0 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update(
			self::table(),
			array(
				'status'      => self::STATUS_APPROVED,
				'approved_by' => max( 0, (int) $approver ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array(
				'space_id'   => $space_id,
				'listing_id' => $listing_id,
			),
			array( '%s', '%d', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * A curator places a listing straight into the showcase (skips the queue).
	 *
	 * @param int $space_id   Space id.
	 * @param int $listing_id Listing id.
	 * @param int $curator    Space team member.
	 * @return bool
	 */
	public static function add_approved( $space_id, $listing_id, $curator ) {
		self::submit( $space_id, $listing_id, $curator );
		return self::approve( $space_id, $listing_id, $curator );
	}

	/**
	 * Remove a link entirely (reject a submission, or take a listing down).
	 *
	 * @param int $space_id   Space id.
	 * @param int $listing_id Listing id.
	 * @return bool
	 */
	public static function remove( $space_id, $listing_id ) {
		global $wpdb;
		$space_id   = (int) $space_id;
		$listing_id = (int) $listing_id;
		if ( $space_id <= 0 || $listing_id <= 0 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete(
			self::table(),
			array(
				'space_id'   => $space_id,
				'listing_id' => $listing_id,
			),
			array( '%d', '%d' )
		);

		return (int) $deleted > 0;
	}

	/**
	 * Approved listing ids for a space, newest first - the showcase set.
	 *
	 * @param int $space_id Space id.
	 * @param int $limit    Max rows (0 = all).
	 * @param int $offset   Page offset.
	 * @return int[]
	 */
	public static function approved_listing_ids( $space_id, $limit = 0, $offset = 0 ) {
		return self::ids_by_status( $space_id, self::STATUS_APPROVED, (int) $limit, (int) $offset );
	}

	/**
	 * Pending submissions for a space (the moderation queue), newest first.
	 *
	 * @param int $space_id Space id.
	 * @param int $per_page  Rows to return; 0 returns every row (internal
	 *                       counting callers only - the REST queue always pages).
	 * @param int $offset    Rows to skip.
	 * @return array<int,array{listing_id:int,submitted_by:int,created_at:string}>
	 */
	public static function pending( $space_id, int $per_page = 0, int $offset = 0 ) {
		global $wpdb;
		$space_id = (int) $space_id;
		if ( $space_id <= 0 ) {
			return array();
		}
		$table = self::table();

		/*
		 * Bounded by default. This returned every pending row a space had ever
		 * accumulated, and the moderation queue rendered the lot in one
		 * response - 103 rows on the site this was found on, with no way to ask
		 * for fewer (card 10314572968). `$per_page` of 0 keeps the old
		 * unbounded behaviour for the two internal callers that count rather
		 * than render.
		 */
		$per_page = max( 0, (int) $per_page );
		$offset   = max( 0, (int) $offset );

		/*
		 * listing_id is a tiebreaker, not decoration. `created_at` has
		 * one-second resolution, so several submissions in the same second sort
		 * arbitrarily - and with LIMIT/OFFSET an arbitrary sort means a row can
		 * appear on two pages while another appears on none. A curator then
		 * moderates the same submission twice and never sees the one it
		 * displaced. Caught by the pagination test, which seeds 25 rows inside
		 * one second; a bulk import does the same thing in production.
		 */
		$sql  = "SELECT listing_id, submitted_by, created_at
				   FROM {$table}
				  WHERE space_id = %d AND status = %s
				  ORDER BY created_at DESC, listing_id DESC";
		$args = array( $space_id, self::STATUS_PENDING );

		if ( $per_page > 0 ) {
			$sql   .= ' LIMIT %d OFFSET %d';
			$args[] = $per_page;
			$args[] = $offset;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, ...$args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array_map(
			static function ( $row ) {
				return array(
					'listing_id'   => (int) $row['listing_id'],
					'submitted_by' => (int) $row['submitted_by'],
					'created_at'   => (string) $row['created_at'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Count pending submissions for a space (moderation badge).
	 *
	 * @param int $space_id Space id.
	 * @return int
	 */
	public static function pending_count( $space_id ) {
		return self::count_by_status( $space_id, self::STATUS_PENDING );
	}

	/**
	 * Count approved listings in a space (showcase pager).
	 *
	 * @param int $space_id Space id.
	 * @return int
	 */
	public static function approved_count( $space_id ) {
		return self::count_by_status( $space_id, self::STATUS_APPROVED );
	}

	/**
	 * Count a member's pending submissions in a space (anti-flood guard on submit).
	 *
	 * @param int $space_id Space id.
	 * @param int $user_id  Submitter.
	 * @return int
	 */
	public static function pending_count_for_user( $space_id, $user_id ) {
		global $wpdb;
		$space_id = (int) $space_id;
		$user_id  = (int) $user_id;
		if ( $space_id <= 0 || $user_id <= 0 ) {
			return 0;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE space_id = %d AND submitted_by = %d AND status = %s",
				$space_id,
				$user_id,
				self::STATUS_PENDING
			)
		);
	}

	/**
	 * A listing's link status in a space, or '' when none - so the UI shows the
	 * right control (submit / pending / listed).
	 *
	 * @param int $space_id   Space id.
	 * @param int $listing_id Listing id.
	 * @return string 'pending' | 'approved' | ''.
	 */
	public static function status_for( $space_id, $listing_id ) {
		global $wpdb;
		$space_id   = (int) $space_id;
		$listing_id = (int) $listing_id;
		if ( $space_id <= 0 || $listing_id <= 0 ) {
			return '';
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$table} WHERE space_id = %d AND listing_id = %d", $space_id, $listing_id )
		);

		return null === $status ? '' : (string) $status;
	}

	/**
	 * The space ids a listing is APPROVED into (for the owner's "listed in" view
	 * and for cleanup). Newest first.
	 *
	 * @param int $listing_id Listing id.
	 * @return int[]
	 */
	public static function approved_spaces_for_listing( $listing_id ) {
		global $wpdb;
		$listing_id = (int) $listing_id;
		if ( $listing_id <= 0 ) {
			return array();
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT space_id FROM {$table} WHERE listing_id = %d AND status = %s ORDER BY updated_at DESC",
				$listing_id,
				self::STATUS_APPROVED
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Purge every link for a listing (its business was deleted).
	 *
	 * @param int $listing_id Listing id.
	 * @return int Rows removed.
	 */
	public static function remove_all_for_listing( $listing_id ) {
		global $wpdb;
		$listing_id = (int) $listing_id;
		if ( $listing_id <= 0 ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->delete( self::table(), array( 'listing_id' => $listing_id ), array( '%d' ) );
	}

	/**
	 * Purge every link for a space (the space was deleted in BuddyNext).
	 *
	 * @param int $space_id Space id.
	 * @return int Rows removed.
	 */
	public static function remove_all_for_space( $space_id ) {
		global $wpdb;
		$space_id = (int) $space_id;
		if ( $space_id <= 0 ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->delete( self::table(), array( 'space_id' => $space_id ), array( '%d' ) );
	}

	/**
	 * Shared id-by-status reader.
	 *
	 * @param int    $space_id Space id.
	 * @param string $status   Row status.
	 * @param int    $limit    Max rows (0 = all).
	 * @param int    $offset   Offset.
	 * @return int[]
	 */
	private static function ids_by_status( $space_id, $status, $limit, $offset ) {
		global $wpdb;
		$space_id = (int) $space_id;
		if ( $space_id <= 0 ) {
			return array();
		}
		$table = self::table();

		// Two literal queries rather than one assembled string, so each is
		// prepared in full and nothing reaches prepare() as a variable.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is the plugin's own prefixed table name.
		if ( $limit > 0 ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT listing_id FROM {$table} WHERE space_id = %d AND status = %s ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d",
					$space_id,
					$status,
					(int) $limit,
					max( 0, (int) $offset )
				)
			);
		} else {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT listing_id FROM {$table} WHERE space_id = %d AND status = %s ORDER BY updated_at DESC, id DESC",
					$space_id,
					$status
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Shared status-count reader.
	 *
	 * @param int    $space_id Space id.
	 * @param string $status   Row status.
	 * @return int
	 */
	private static function count_by_status( $space_id, $status ) {
		global $wpdb;
		$space_id = (int) $space_id;
		if ( $space_id <= 0 ) {
			return 0;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE space_id = %d AND status = %s", $space_id, $status )
		);
	}
}
