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
	 */
	public function __construct() {
		add_action( 'wb_listora_check_expirations', array( $this, 'check_expirations' ) );
		add_action( 'wb_listora_draft_reminder_cron', array( $this, 'send_draft_reminders' ) );
		add_action( 'wb_listora_daily_cleanup', array( $this, 'prune_analytics' ) );

		if ( ! wp_next_scheduled( 'wb_listora_check_expirations' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'wb_listora_check_expirations' );
		}

		if ( ! wp_next_scheduled( 'wb_listora_draft_reminder_cron' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'wb_listora_draft_reminder_cron' );
		}

		if ( ! wp_next_scheduled( 'wb_listora_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wb_listora_daily_cleanup' );
		}
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

		$listings = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => array(
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
				'fields'         => 'ids',
			)
		);

		foreach ( $listings as $post_id ) {
			/**
			 * Fires when a listing is expiring soon (7 days).
			 *
			 * @param int $post_id Listing ID.
			 * @param int $days    Days until expiration.
			 */
			do_action( 'wb_listora_listing_expiring', $post_id, 7 );
			update_post_meta( $post_id, '_listora_expiry_reminded_7d', current_time( 'mysql', true ) );
		}
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

		$listings = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => array(
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
				'fields'         => 'ids',
			)
		);

		foreach ( $listings as $post_id ) {
			do_action( 'wb_listora_listing_expiring', $post_id, 1 );
			update_post_meta( $post_id, '_listora_expiry_reminded_1d', current_time( 'mysql', true ) );
		}
	}

	/**
	 * Expire listings past their expiration date.
	 */
	private function expire_listings() {
		$now = current_time( 'mysql', true );

		$listings = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_query'     => array(
					array(
						'key'     => '_listora_expiration_date',
						'value'   => $now,
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $listings as $post_id ) {
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
		}
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

		$listings = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'draft',
				'posts_per_page' => 50,
				'date_query'     => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => $cutoff,
					),
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_listora_draft_reminded',
						'compare' => 'NOT EXISTS',
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $listings as $post_id ) {
			/**
			 * Fires when a draft reminder should be sent.
			 *
			 * @param int $post_id Listing ID.
			 */
			do_action( 'wb_listora_draft_reminder', $post_id );
			update_post_meta( $post_id, '_listora_draft_reminded', current_time( 'mysql', true ) );
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
			/* translators: 1: number of deleted rows, 2: retention days */
			$message = sprintf(
				__( 'WB Listora: Pruned %1$d analytics records older than %2$d days.', 'wb-listora' ),
				$deleted,
				$retention
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}
}
