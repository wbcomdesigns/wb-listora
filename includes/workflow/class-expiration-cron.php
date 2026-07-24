<?php
/**
 * Expiration Cron — checks for expiring/expired listings twice daily.
 *
 * @package WBListora\Workflow
 */

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Handles listing expiration via WP Cron.
 */
class Expiration_Cron {

	/**
	 * Constructor — register cron hooks and schedules.
	 *
	 * Uses {@see Cron_Scheduler} which prefers Action Scheduler (bundled
	 * by Pro / WooCommerce) and falls back to WP-Cron when AS is not
	 * loaded. AS provides retry semantics + survives low-traffic windows
	 * — important at scale (100K users, intermittent traffic).
	 */
	public function __construct() {
		add_action( 'wb_listora_check_expirations', array( $this, 'check_expirations' ) );
		add_action( 'wb_listora_draft_reminder_cron', array( $this, 'send_draft_reminders' ) );
		add_action( 'wb_listora_review_reminder_cron', array( $this, 'send_review_reminders' ) );
		add_action( 'wb_listora_daily_cleanup', array( $this, 'prune_analytics' ) );

		Cron_Scheduler::schedule_recurring( 'twicedaily', 'wb_listora_check_expirations' );
		Cron_Scheduler::schedule_recurring( 'twicedaily', 'wb_listora_draft_reminder_cron' );
		Cron_Scheduler::schedule_recurring( 'daily', 'wb_listora_review_reminder_cron' );
		Cron_Scheduler::schedule_recurring( 'daily', 'wb_listora_daily_cleanup' );
	}

	/**
	 * Check for expiring and expired listings.
	 */
	public function check_expirations() {
		if ( ! wb_listora_get_setting( 'enable_expiration', true ) ) {
			return;
		}

		$this->warn_expiring_7_days();
		$this->warn_expiring_1_day();
		$this->expire_listings();
	}

	/**
	 * Warn listings expiring in 7 days.
	 *
	 * Tracks sent reminders via _listora_expiry_reminded_7d post meta
	 * to avoid duplicate notifications.
	 */
	private function warn_expiring_7_days() {
		$now   = current_time( 'mysql', true );
		$in_7d = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + ( 7 * DAY_IN_SECONDS ) );

		$this->process_in_batches(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'meta_query'  => array(
					'relation' => 'AND',
					array(
						'key'     => '_listora_expiration_date',
						'value'   => array( $now, $in_7d ),
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => '_listora_expiry_reminded_7d',
						'compare' => 'NOT EXISTS',
					),
				),
			),
			function ( $post_id ) {
				/**
				 * Fires when a listing is expiring soon (7 days).
				 *
				 * @param int $post_id Listing ID.
				 * @param int $days    Days until expiration.
				 */
				do_action( 'wb_listora_listing_expiring', $post_id, 7 );
				update_post_meta( $post_id, '_listora_expiry_reminded_7d', current_time( 'mysql', true ) );
			},
			'wb_listora_expiry_warn_max_per_run'
		);
	}

	/**
	 * Fetch and process listings in batches until the query is exhausted.
	 *
	 * Every lifecycle query here is self-draining: a processed listing either
	 * changes status or gains a "reminded" meta flag, so it drops out of the
	 * next batch. That lets a simple loop walk the entire backlog instead of
	 * the old single hard-capped page, which silently missed entries on large
	 * sites. A per-run ceiling bounds worst-case runtime — the next scheduled
	 * run picks up any remainder.
	 *
	 * @param array<string,mixed> $query_args get_posts args WITHOUT posts_per_page/fields.
	 * @param callable            $process    Receives each int post ID.
	 * @param string              $max_filter Filter name for the per-run ceiling.
	 * @param int                 $ceiling    Default per-run ceiling.
	 * @return void
	 */
	private function process_in_batches( array $query_args, callable $process, string $max_filter, int $ceiling = 5000 ) {
		$batch     = 100;
		$processed = 0;

		/** Filters the per-run ceiling for this lifecycle sweep. */
		$max = (int) apply_filters( $max_filter, $ceiling );

		$query_args['posts_per_page'] = $batch;
		$query_args['fields']         = 'ids';
		$query_args['no_found_rows']  = true;

		do {
			$ids = get_posts( $query_args );
			foreach ( $ids as $id ) {
				$process( (int) $id );
				++$processed;
			}
		} while ( count( $ids ) === $batch && $processed < $max );
	}

	/**
	 * Warn listings expiring in 1 day.
	 *
	 * Tracks sent reminders via _listora_expiry_reminded_1d post meta
	 * to avoid duplicate notifications.
	 */
	private function warn_expiring_1_day() {
		$now   = current_time( 'mysql', true );
		$in_1d = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + DAY_IN_SECONDS );

		$this->process_in_batches(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'meta_query'  => array(
					'relation' => 'AND',
					array(
						'key'     => '_listora_expiration_date',
						'value'   => array( $now, $in_1d ),
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => '_listora_expiry_reminded_1d',
						'compare' => 'NOT EXISTS',
					),
				),
			),
			function ( $post_id ) {
				do_action( 'wb_listora_listing_expiring', $post_id, 1 );
				update_post_meta( $post_id, '_listora_expiry_reminded_1d', current_time( 'mysql', true ) );
			},
			'wb_listora_expiry_warn_max_per_run'
		);
	}

	/**
	 * Expire listings past their expiration date.
	 */
	private function expire_listings() {
		$now = current_time( 'mysql', true );

		$this->process_in_batches(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'meta_query'  => array(
					array(
						'key'     => '_listora_expiration_date',
						'value'   => $now,
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
				),
			),
			function ( $post_id ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'listora_expired',
					)
				);

				/**
				 * Fires when a listing has expired.
				 *
				 * @param int $post_id Listing ID.
				 */
				do_action( 'wb_listora_listing_expired', $post_id );
			},
			'wb_listora_expire_max_per_run'
		);
	}

	/**
	 * Send draft reminder emails for abandoned draft listings.
	 *
	 * Queries listings with status 'draft' where post_modified is older than
	 * 48 hours and _listora_draft_reminded meta is not set, then fires
	 * the draft reminder notification and sets the meta to prevent re-sending.
	 */
	public function send_draft_reminders() {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 48 * HOUR_IN_SECONDS ) );

		$this->process_in_batches(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'draft',
				'date_query'  => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => $cutoff,
					),
				),
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_listora_draft_reminded',
						'compare' => 'NOT EXISTS',
					),
				),
			),
			function ( $post_id ) {
				/**
				 * Fires when a draft reminder should be sent.
				 *
				 * @param int $post_id Listing ID.
				 */
				do_action( 'wb_listora_draft_reminder', $post_id );
				update_post_meta( $post_id, '_listora_draft_reminded', current_time( 'mysql', true ) );
			},
			'wb_listora_draft_reminder_max_per_run'
		);
	}

	/**
	 * Send review-reminder emails to listing owners who have approved reviews
	 * still awaiting a reply.
	 *
	 * Bounded batch sweep (LIMIT 50) fired daily. Idempotent per listing via
	 * the `_listora_review_reminded_upto` post meta, which stores the highest
	 * review ID a reminder has already covered. A listing is reminded again
	 * only when a NEWER un-replied review arrives beyond that watermark, so an
	 * owner is never re-nagged about the same backlog yet is reminded when fresh
	 * reviews accumulate.
	 *
	 * Only reviews older than a 48h grace window count, giving an attentive
	 * owner time to reply before the nudge fires. Tune via the
	 * `wb_listora_review_reminder_grace_hours` filter.
	 *
	 * @return void
	 */
	public function send_review_reminders(): void {
		global $wpdb;

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		/**
		 * Filters the review-reminder grace window in hours. Reviews newer than
		 * this are skipped so owners get a chance to reply before being nudged.
		 *
		 * @param int $hours Grace window in hours. Default 48.
		 */
		$grace_hours = max( 0, (int) apply_filters( 'wb_listora_review_reminder_grace_hours', 48 ) );
		$cutoff      = gmdate( 'Y-m-d H:i:s', time() - ( $grace_hours * HOUR_IN_SECONDS ) );

		// Aggregate per listing: count of approved, un-replied reviews past the
		// grace window and the highest such review ID. Bounded to 50 listings
		// per tick so the sweep stays cheap on large sites.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, COUNT(*) AS pending_count, MAX(id) AS max_review_id FROM {$prefix}reviews WHERE status = 'approved' AND ( owner_reply IS NULL OR owner_reply = '' ) AND created_at <= %s GROUP BY listing_id LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$listing_id    = (int) $row['listing_id'];
			$pending_count = (int) $row['pending_count'];
			$max_review_id = (int) $row['max_review_id'];

			// Skip listings that no longer exist (review rows can outlive a
			// deleted listing until the daily cleanup catches them).
			if ( ! get_post( $listing_id ) ) {
				continue;
			}

			// De-dupe watermark: only remind when there's an un-replied review
			// newer than the last one we reminded about.
			$reminded_upto = (int) get_post_meta( $listing_id, '_listora_review_reminded_upto', true );
			if ( $max_review_id <= $reminded_upto ) {
				continue;
			}

			/**
			 * Fires when a listing owner should be reminded about un-replied reviews.
			 *
			 * @param int $listing_id    Listing ID.
			 * @param int $pending_count Number of approved reviews awaiting a reply.
			 */
			do_action( 'wb_listora_review_reminder', $listing_id, $pending_count );

			update_post_meta( $listing_id, '_listora_review_reminded_upto', $max_review_id );
		}
	}

	/**
	 * Prune old analytics records.
	 *
	 * Deletes records older than the configured retention period (default 90 days).
	 * The retention period can be adjusted via the `wb_listora_analytics_retention_days` filter.
	 */
	public function prune_analytics() {
		global $wpdb;

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$table  = $prefix . 'analytics';

		/**
		 * Filters the analytics data retention period in days.
		 *
		 * @param int $days Number of days to retain analytics data. Default 90.
		 */
		$retention = (int) apply_filters( 'wb_listora_analytics_retention_days', 90 );

		if ( $retention < 1 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE event_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention
			)
		);

		if ( $deleted > 0 ) {
			wb_listora_log(
				sprintf(
					/* translators: 1: number of deleted rows, 2: retention days */
					__( 'Pruned %1$d analytics records older than %2$d days.', 'wb-listora' ),
					$deleted,
					$retention
				)
			);
		}
	}
}
