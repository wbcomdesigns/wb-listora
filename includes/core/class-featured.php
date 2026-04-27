<?php
/**
 * Featured Listings service.
 *
 * Manages the lifecycle of featured listings:
 * - Featured duration (days, configurable in settings).
 * - `_listora_featured_until` timestamp meta.
 * - Daily cron that unfeatured expired listings.
 * - A single source of truth for "is this listing currently featured?"
 *   via {@see Featured::is_featured()}.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Featured service — feature/expire lifecycle for listings.
 */
class Featured {

	/**
	 * Cron hook for unfeatured-expiration sweeps.
	 */
	const CRON_HOOK = 'wb_listora_expire_featured';

	/**
	 * Meta key: boolean flag — is the listing currently in "featured" mode?
	 */
	const META_IS_FEATURED = '_listora_is_featured';

	/**
	 * Meta key: expiration timestamp (0 / missing = permanent).
	 */
	const META_FEATURED_UNTIL = '_listora_featured_until';

	/**
	 * Bootstrap hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'expire_featured_listings' ) );
	}

	/**
	 * Ensure the daily expiration cron is scheduled.
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cron (used during plugin deactivation).
	 */
	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Sweep: unfeatured any listing whose `_listora_featured_until` is
	 * in the past. Runs daily via {@see self::CRON_HOOK}.
	 */
	public static function expire_featured_listings() {
		$now = (int) current_time( 'timestamp' );

		$post_ids = get_posts(
			array(
				'post_type'        => 'listora_listing',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- cron sweep, 1x/day.
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'     => self::META_FEATURED_UNTIL,
						'value'   => $now,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => self::META_FEATURED_UNTIL,
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		if ( empty( $post_ids ) ) {
			return;
		}

		foreach ( $post_ids as $post_id ) {
			self::unfeature_listing( (int) $post_id, 'expired' );
		}
	}

	/**
	 * Feature a listing for a number of days.
	 *
	 * Fires the SDK credit-hold filter `wb_listora_before_feature_listing`
	 * (cancellable — return WP_Error to abort) before writing meta, and the
	 * settle action `wb_listora_after_feature_listing` after.
	 *
	 * @param int $post_id Listing post ID.
	 * @param int $days    Duration in days. 0 = use admin-configured default.
	 *                     Pass a negative number to make permanent.
	 * @return bool|\WP_Error True on success, false on invalid input, WP_Error
	 *                        when a listener short-circuits the operation.
	 */
	public static function feature_listing( $post_id, $days = 0 ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || 'listora_listing' !== get_post_type( $post_id ) ) {
			return false;
		}

		if ( 0 === (int) $days ) {
			$days = (int) wb_listora_get_setting( 'featured_duration_days', 30 );
		}

		/**
		 * Filter the featured duration (in days) for this listing.
		 *
		 * Return 0 or a negative number to make the feature permanent.
		 *
		 * @param int $days    Duration in days.
		 * @param int $post_id Listing ID.
		 */
		$days = (int) apply_filters( 'wb_listora_feature_duration_days', $days, $post_id );

		$context = array(
			'days' => $days,
		);

		/**
		 * Cancellable pre-feature filter.
		 *
		 * Return a WP_Error to abort. Used by the SDK to place credit holds —
		 * an insufficient-credit response aborts the feature operation.
		 *
		 * @param true|\WP_Error $check    True to proceed, WP_Error to abort.
		 * @param int            $post_id  Listing ID.
		 * @param array          $context  Context: { days: int }.
		 */
		$check = apply_filters( 'wb_listora_before_feature_listing', true, $post_id, $context );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		update_post_meta( $post_id, self::META_IS_FEATURED, true );

		if ( $days > 0 ) {
			$until = strtotime( '+' . $days . ' days', (int) current_time( 'timestamp' ) );
			update_post_meta( $post_id, self::META_FEATURED_UNTIL, (int) $until );
		} else {
			// Permanent — remove any prior expiration.
			delete_post_meta( $post_id, self::META_FEATURED_UNTIL );
		}

		/**
		 * Fires after a listing has been featured.
		 *
		 * SDK settle hook — deducts credits.
		 *
		 * @param int   $post_id Listing ID.
		 * @param array $context Context: { days: int }.
		 */
		do_action( 'wb_listora_after_feature_listing', $post_id, $context );

		return true;
	}

	/**
	 * Unfeature a listing (clear meta + fire lifecycle action).
	 *
	 * Fires `wb_listora_before_unfeature_listing` (cancellable filter) before
	 * the write and `wb_listora_after_unfeature_listing` after.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $reason  'manual' | 'expired'.
	 * @return bool|\WP_Error True on success, false on invalid input, WP_Error
	 *                        when a listener short-circuits the operation.
	 */
	public static function unfeature_listing( $post_id, $reason = 'manual' ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}

		$context = array(
			'reason' => $reason,
		);

		/**
		 * Cancellable pre-unfeature filter.
		 *
		 * Return a WP_Error to abort.
		 *
		 * @param true|\WP_Error $check   True to proceed, WP_Error to abort.
		 * @param int            $post_id Listing ID.
		 * @param array          $context Context: { reason: string }.
		 */
		$check = apply_filters( 'wb_listora_before_unfeature_listing', true, $post_id, $context );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		update_post_meta( $post_id, self::META_IS_FEATURED, false );
		delete_post_meta( $post_id, self::META_FEATURED_UNTIL );

		/**
		 * Fires after a listing is unfeatured.
		 *
		 * @param int    $post_id Listing ID.
		 * @param string $reason  'manual' | 'expired'.
		 */
		do_action( 'wb_listora_after_unfeature_listing', $post_id, $reason );

		return true;
	}

	/**
	 * Is this listing currently featured (respecting expiration)?
	 *
	 * Single source of truth — use this instead of reading
	 * `_listora_is_featured` directly so expiration is respected even
	 * before the cron has run.
	 *
	 * @param int $post_id Listing ID.
	 * @return bool
	 */
	public static function is_featured( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}

		if ( ! (bool) get_post_meta( $post_id, self::META_IS_FEATURED, true ) ) {
			return false;
		}

		$until = (int) get_post_meta( $post_id, self::META_FEATURED_UNTIL, true );
		if ( 0 === $until ) {
			// Permanent — no expiration set.
			return true;
		}

		return $until > (int) current_time( 'timestamp' );
	}

	/**
	 * Get the expiration timestamp (0 means permanent).
	 *
	 * @param int $post_id Listing ID.
	 * @return int
	 */
	public static function get_featured_until( $post_id ) {
		return (int) get_post_meta( (int) $post_id, self::META_FEATURED_UNTIL, true );
	}

	/**
	 * Number of days a feature upgrade lasts by default (admin setting).
	 *
	 * @return int Days (0 = permanent).
	 */
	public static function get_default_duration_days() {
		return (int) wb_listora_get_setting( 'featured_duration_days', 30 );
	}
}
