<?php
/**
 * Listing Limits per Role — enforces a cap on how many listings each user role
 * can own, with an optional "pay credits to overflow" path.
 *
 * Admins configure the cap in Settings → Submissions. When a user tries to
 * submit beyond their cap, the before_create_listing filter aborts the request
 * with a 402 WP_Error telling them how many credits are needed to override it.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Listing Limits service.
 *
 * Storage:
 *   - Per-role caps: `wb_listora_settings['listing_limits_per_role']` → [ 'subscriber' => 3, ... ]
 *   - Default cap:   `wb_listora_settings['listing_limits_default']`   → int (-1 = unlimited)
 *   - Overflow cost: option `wb_listora_overflow_credit_cost`          → int (credits per extra listing)
 *
 * A value of -1 means "unlimited".
 */
class Listing_Limits {

	/**
	 * Settings option key (shared with Settings_Page).
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wb_listora_settings';

	/**
	 * Option storing the credits cost per overflow listing.
	 *
	 * @var string
	 */
	const OVERFLOW_COST_OPTION = 'wb_listora_overflow_credit_cost';

	/**
	 * Wire up hooks. Called once from Plugin::init_core().
	 */
	public static function init() {
		add_filter( 'wb_listora_before_create_listing', array( __CLASS__, 'enforce_on_create' ), 5, 3 );

		// Invalidate the per-user count cache whenever listings change.
		add_action( 'transition_post_status', array( __CLASS__, 'bust_count_cache_on_status' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'bust_count_cache_on_delete' ), 10, 2 );
	}

	/**
	 * Bust the cached listing count when a listora_listing changes status.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 */
	public static function bust_count_cache_on_status( $new_status, $old_status, $post ) {
		if ( 'listora_listing' !== $post->post_type ) {
			return;
		}

		if ( $new_status === $old_status ) {
			return;
		}

		wp_cache_delete( 'listora_limit_count_' . (int) $post->post_author, 'wb_listora' );
	}

	/**
	 * Bust the cached listing count when a listora_listing is deleted.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object (may be null on older WP).
	 */
	public static function bust_count_cache_on_delete( $post_id, $post = null ) {
		if ( $post && 'listora_listing' !== $post->post_type ) {
			return;
		}

		$author = (int) get_post_field( 'post_author', $post_id );
		if ( $author > 0 ) {
			wp_cache_delete( 'listora_limit_count_' . $author, 'wb_listora' );
		}
	}

	/**
	 * Filter callback: block the submission if the user has hit their cap and
	 * does not have enough credits to pay for an overflow listing.
	 *
	 * Runs at priority 5 so it fires BEFORE the Credits SDK's hold callback
	 * (default priority 10) — that way, when we DO allow the submission to
	 * continue past the cap, the SDK then holds credits on the same hook.
	 *
	 * @param bool|WP_Error   $check   True to proceed, WP_Error to abort.
	 * @param string          $title   Listing title (unused).
	 * @param WP_REST_Request $request REST request (unused).
	 * @return bool|WP_Error
	 */
	public static function enforce_on_create( $check, $title = '', $request = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $title/$request are part of the documented filter signature.
		// Respect any prior abort — don't overwrite an existing WP_Error.
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$user_id = get_current_user_id();

		// No user ID (guests handled via inline registration above this point
		// set current_user to the new account) → skip; other controllers enforce auth.
		if ( $user_id <= 0 ) {
			return $check;
		}

		if ( self::can_submit( $user_id ) ) {
			return $check;
		}

		$overflow_cost = self::get_overflow_cost();

		// Overflow path: user is over the cap but has enough credits.
		if ( $overflow_cost > 0 && self::user_can_afford_overflow( $user_id, $overflow_cost ) ) {
			/**
			 * Fires when a user is submitting beyond their role's listing cap
			 * and will pay credits to do so. Useful for analytics/logging.
			 *
			 * @since 1.0.0
			 *
			 * @param int $user_id       User ID.
			 * @param int $overflow_cost Credits that will be held.
			 */
			do_action( 'wb_listora_listing_limit_overflow', $user_id, $overflow_cost );

			return $check;
		}

		// Hard stop: over cap AND can't afford overflow (or overflow disabled).
		// Remove any callbacks on this filter that expect a post ID as the first
		// arg (e.g. the Credits SDK Consumer::on_hold). They would fatal when
		// handed a WP_Error. Our limit takes precedence over downstream filters.
		self::remove_unsafe_downstream_callbacks();

		$limit = self::get_user_limit( $user_id );
		$count = self::get_user_count( $user_id );

		$message = sprintf(
			/* translators: 1: current listing count, 2: listing limit, 3: credits cost. */
			__( 'You have reached your listing limit (%1$d of %2$d). Purchase %3$d credits to post an additional listing.', 'wb-listora' ),
			$count,
			$limit,
			$overflow_cost
		);

		$data = array(
			'status'        => 402,
			'limit'         => $limit,
			'count'         => $count,
			'overflow_cost' => $overflow_cost,
			'purchase_url'  => self::get_purchase_url(),
		);

		return new WP_Error( 'limit_reached', $message, $data );
	}

	/**
	 * Defensively detach downstream callbacks from
	 * `wb_listora_before_create_listing` that would fatal when handed a
	 * WP_Error as their first arg (they expect an int post ID).
	 *
	 * Specifically targets the Wbcom Credits SDK Consumer::on_hold signature
	 * `int $item_id`, which PHP rejects with a TypeError when the filter
	 * chain passes a WP_Error through.
	 */
	private static function remove_unsafe_downstream_callbacks() {
		global $wp_filter;

		$hook = 'wb_listora_before_create_listing';
		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return;
		}

		$callbacks = $wp_filter[ $hook ]->callbacks;
		if ( empty( $callbacks ) ) {
			return;
		}

		foreach ( $callbacks as $priority => $entries ) {
			foreach ( $entries as $entry ) {
				$fn = $entry['function'] ?? null;
				if ( ! is_array( $fn ) || count( $fn ) !== 2 ) {
					continue;
				}

				list( $obj, $method ) = $fn;

				if ( is_object( $obj ) && $obj instanceof \Wbcom\Credits\Consumer && 'on_hold' === $method ) {
					remove_action( $hook, $fn, $priority );
				}
			}
		}
	}

	/**
	 * Get the listing limit for a user based on their primary role.
	 *
	 * @param int $user_id User ID.
	 * @return int Limit (-1 = unlimited, 0 = blocked).
	 */
	public static function get_user_limit( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->roles ) ) {
			return self::get_default_limit();
		}

		$settings = get_option( self::OPTION_KEY, array() );
		$map      = isset( $settings['listing_limits_per_role'] ) && is_array( $settings['listing_limits_per_role'] )
			? $settings['listing_limits_per_role']
			: array();

		// Administrators always unlimited (safety net).
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			$admin_cap = isset( $map['administrator'] ) ? (int) $map['administrator'] : -1;
			return $admin_cap;
		}

		// User may have multiple roles — pick the most permissive limit,
		// treating -1 as unlimited.
		$best = null;
		foreach ( (array) $user->roles as $role ) {
			if ( ! isset( $map[ $role ] ) ) {
				continue;
			}
			$value = (int) $map[ $role ];

			if ( -1 === $value ) {
				return -1;
			}

			if ( null === $best || $value > $best ) {
				$best = $value;
			}
		}

		if ( null !== $best ) {
			/**
			 * Filter the computed listing limit for a user.
			 *
			 * @since 1.0.0
			 *
			 * @param int $best    Computed limit (-1 unlimited).
			 * @param int $user_id User ID.
			 */
			return (int) apply_filters( 'wb_listora_user_listing_limit', $best, $user_id );
		}

		// No matching role in the map → default.
		return (int) apply_filters( 'wb_listora_user_listing_limit', self::get_default_limit(), $user_id );
	}

	/**
	 * Get the fallback limit for roles that are not in the map.
	 *
	 * @return int
	 */
	public static function get_default_limit(): int {
		$settings = get_option( self::OPTION_KEY, array() );

		if ( isset( $settings['listing_limits_default'] ) ) {
			return (int) $settings['listing_limits_default'];
		}

		return -1;
	}

	/**
	 * Count the user's listings that currently count against the cap:
	 * published + pending. Drafts, trash, expired, rejected, and deactivated
	 * listings are excluded so users can resubmit after rejection/expiry.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function get_user_count( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		$cache_key = 'listora_limit_count_' . $user_id;
		$cached    = wp_cache_get( $cache_key, 'wb_listora' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$counted_statuses = apply_filters(
			'wb_listora_listing_limit_counted_statuses',
			array( 'publish', 'pending' )
		);

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $counted_statuses ), '%s' ) );

		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'listora_listing'
			   AND post_author = %d
			   AND post_status IN ($placeholders)",
			array_merge( array( $user_id ), $counted_statuses )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $sql already went through $wpdb->prepare() above.
		$count = (int) $wpdb->get_var( $sql );

		wp_cache_set( $cache_key, $count, 'wb_listora', 60 );

		return $count;
	}

	/**
	 * Whether the user is under their cap (or has unlimited).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_submit( int $user_id ): bool {
		$limit = self::get_user_limit( $user_id );

		if ( -1 === $limit ) {
			return true;
		}

		if ( $limit <= 0 ) {
			return false;
		}

		return self::get_user_count( $user_id ) < $limit;
	}

	/**
	 * Remaining listings before the cap is hit.
	 *
	 * @param int $user_id User ID.
	 * @return int -1 if unlimited, otherwise remaining count (0 or positive).
	 */
	public static function get_remaining( int $user_id ): int {
		$limit = self::get_user_limit( $user_id );

		if ( -1 === $limit ) {
			return -1;
		}

		$remaining = $limit - self::get_user_count( $user_id );

		return max( 0, $remaining );
	}

	/**
	 * Get the credits cost for submitting a listing beyond the cap.
	 *
	 * @return int 0 when the overflow path is disabled.
	 */
	public static function get_overflow_cost(): int {
		$cost = (int) get_option( self::OVERFLOW_COST_OPTION, 10 );

		return max( 0, $cost );
	}

	/**
	 * Whether the user has enough credits to pay for an overflow listing.
	 *
	 * @param int $user_id User ID.
	 * @param int $cost    Cost in credits. If 0, uses get_overflow_cost().
	 * @return bool
	 */
	public static function user_can_afford_overflow( int $user_id, int $cost = 0 ): bool {
		if ( $cost <= 0 ) {
			$cost = self::get_overflow_cost();
		}

		if ( $cost <= 0 ) {
			return false;
		}

		$balance = 0;

		if ( class_exists( '\Wbcom\Credits\Credits' ) ) {
			$balance = (int) \Wbcom\Credits\Credits::get_balance( 'wb-listora', $user_id );
		}

		/**
		 * Filter the user's available credits for overflow calculation.
		 *
		 * Plugins that replace the Credits SDK can hook here.
		 *
		 * @since 1.0.0
		 *
		 * @param int $balance Current credit balance.
		 * @param int $user_id User ID.
		 */
		$balance = (int) apply_filters( 'wb_listora_user_credit_balance', $balance, $user_id );

		return $balance >= $cost;
	}

	/**
	 * Get the credit purchase URL (for the "buy more credits" CTA).
	 *
	 * @return string
	 */
	public static function get_purchase_url(): string {
		$url = function_exists( 'wb_listora_get_credits_purchase_url' )
			? wb_listora_get_credits_purchase_url()
			: (string) get_option( 'wb_listora_credit_purchase_url', '' );

		return esc_url_raw( $url );
	}

	/**
	 * Sanitize the per-role map coming from the settings form.
	 *
	 * Accepts raw ['subscriber' => '3', 'editor' => '-1', 'bogus' => '12'],
	 * returns only entries for roles that still exist, each cast to int and
	 * clamped to >= -1.
	 *
	 * @param mixed $raw Raw value from form.
	 * @return array<string,int>
	 */
	public static function sanitize_map( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$roles = wp_roles();
		$names = $roles instanceof \WP_Roles ? $roles->get_names() : array();

		$clean = array();

		foreach ( $raw as $role => $value ) {
			$role = sanitize_key( (string) $role );

			// Only accept roles that actually exist.
			if ( ! isset( $names[ $role ] ) ) {
				continue;
			}

			$int = (int) $value;

			// Clamp: -1 (unlimited), 0 (blocked), or positive integer.
			if ( $int < -1 ) {
				$int = -1;
			}

			$clean[ $role ] = $int;
		}

		return $clean;
	}

	/**
	 * Sanitize the default (fallback) limit.
	 *
	 * @param mixed $raw Raw value.
	 * @return int
	 */
	public static function sanitize_default( $raw ): int {
		$int = (int) $raw;

		if ( $int < -1 ) {
			$int = -1;
		}

		return $int;
	}

	/**
	 * Sanitize the overflow credit cost.
	 *
	 * @param mixed $raw Raw value.
	 * @return int
	 */
	public static function sanitize_overflow_cost( $raw ): int {
		return max( 0, (int) $raw );
	}
}
