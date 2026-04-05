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
	 * Constructor — register cron hook and schedule.
	 */
	public function __construct() {
		add_action( 'wb_listora_check_expirations', array( $this, 'check_expirations' ) );

		if ( ! wp_next_scheduled( 'wb_listora_check_expirations' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'wb_listora_check_expirations' );
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
}
