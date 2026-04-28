<?php
/**
 * Cache — WP-core idiomatic cache helper.
 *
 * Wraps `wp_cache_*` with the `wp_cache_set_last_changed( $group )`
 * incrementor pattern used by WP_Term_Query, WP_Comment_Query, etc.
 *
 * Use this instead of:
 * - Custom cache classes wrapping `wp_cache_*`
 * - `class_exists( 'Redis' )` plugin-level Redis detection
 * - `DELETE FROM wp_options WHERE option_name LIKE '_transient_%'`
 *   for group invalidation
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Centralised cache group + key + invalidation helper.
 *
 * Read path:
 *   $key    = Cache::key( Cache::GROUP_LISTINGS, "search:{$args_hash}" );
 *   $cached = wp_cache_get( $key, Cache::GROUP_LISTINGS );
 *   if ( false === $cached ) {
 *       $cached = run_query( $args );
 *       wp_cache_set( $key, $cached, Cache::GROUP_LISTINGS );
 *   }
 *
 * Write path:
 *   Cache::bump( Cache::GROUP_LISTINGS );
 *
 * When `bump()` runs the group's last-changed incrementor flips, any
 * cache key that embedded the old value becomes orphaned and is evicted
 * via the object-cache backend's normal eviction (LRU on Redis,
 * TTL/process death otherwise). No manual key tracking, no
 * transient LIKE-DELETE.
 */
class Cache {

	/**
	 * Cache group for listings — bust on any listing write.
	 */
	const GROUP_LISTINGS = 'wb_listora_listings';

	/**
	 * Cache group for reviews — bust on any review write.
	 */
	const GROUP_REVIEWS = 'wb_listora_reviews';

	/**
	 * Cache group for dashboard / per-user aggregates.
	 *
	 * Bust whenever a listing OR review owned by the user changes.
	 */
	const GROUP_DASHBOARD = 'wb_listora_dashboard';

	/**
	 * Cache group for plugin settings.
	 *
	 * Bust on `update_option_wb_listora_settings`.
	 */
	const GROUP_SETTINGS = 'wb_listora_settings';

	/**
	 * Wire write-time invalidations to the existing `wb_listora_after_*`
	 * hooks. Single source of truth for cache invalidation — adding a new
	 * write hook means adding one bump here, not chasing transient keys
	 * across the codebase.
	 */
	public static function init(): void {
		// Listing writes → bust listings + dashboard.
		add_action( 'wb_listora_after_create_listing', array( __CLASS__, 'bump_listings' ) );
		add_action( 'wb_listora_after_update_listing', array( __CLASS__, 'bump_listings' ) );
		add_action( 'wb_listora_after_delete_listing', array( __CLASS__, 'bump_listings' ) );

		// Review writes → bust reviews + dashboard (review counts roll up
		// into the per-user dashboard aggregates).
		add_action( 'wb_listora_after_create_review', array( __CLASS__, 'bump_reviews' ) );
		add_action( 'wb_listora_after_update_review', array( __CLASS__, 'bump_reviews' ) );
		add_action( 'wb_listora_after_delete_review', array( __CLASS__, 'bump_reviews' ) );

		// Favorite writes → bust dashboard (favorite counts).
		add_action( 'wb_listora_after_add_favorite', array( __CLASS__, 'bump_dashboard' ) );
		add_action( 'wb_listora_after_remove_favorite', array( __CLASS__, 'bump_dashboard' ) );

		// Settings update → bust the static settings cache so the next
		// wb_listora_get_setting() call re-reads from the option.
		add_action( 'update_option_wb_listora_settings', array( __CLASS__, 'on_settings_updated' ) );
		add_action( 'add_option_wb_listora_settings', array( __CLASS__, 'on_settings_updated' ) );
	}

	/**
	 * Bump the last-changed incrementor on a group.
	 *
	 * @param string $group Cache group constant.
	 */
	public static function bump( string $group ): void {
		wp_cache_set_last_changed( $group );
	}

	/**
	 * Build a versioned cache key by appending the group's last-changed
	 * incrementor. When the group is bumped this returns a new key, so the
	 * next read sees the cache as "missing" and re-computes.
	 *
	 * @param string $group Cache group.
	 * @param string $base  Stable base key (e.g. "search:{$hash}" or "stats:user:{$id}").
	 * @return string Versioned key safe to pass to wp_cache_get/set.
	 */
	public static function key( string $group, string $base ): string {
		$last_changed = wp_cache_get_last_changed( $group );
		return $base . ':' . $last_changed;
	}

	// ──────────────────────────────────────────────────────────────────
	// Hook callbacks. Kept as named static methods so they're unhookable
	// and reflect cleanly under \WP_Hook for debugging.
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Listing-write callback — bust listings AND dashboard groups.
	 *
	 * Dashboard aggregates (counts by status, etc.) are derived from
	 * listings, so they invalidate together.
	 */
	public static function bump_listings(): void {
		self::bump( self::GROUP_LISTINGS );
		self::bump( self::GROUP_DASHBOARD );
	}

	/**
	 * Review-write callback — bust reviews AND dashboard groups.
	 *
	 * Listing rating summaries also live in `search_index` and are
	 * cached against the listings group, so we bust that too.
	 */
	public static function bump_reviews(): void {
		self::bump( self::GROUP_REVIEWS );
		self::bump( self::GROUP_LISTINGS );
		self::bump( self::GROUP_DASHBOARD );
	}

	/**
	 * Favorite-write callback — bust dashboard group.
	 */
	public static function bump_dashboard(): void {
		self::bump( self::GROUP_DASHBOARD );
	}

	/**
	 * Settings-update callback — invalidate the in-process static settings
	 * cache so subsequent calls in this request see the new value, AND
	 * bump the group so cross-request callers re-read.
	 */
	public static function on_settings_updated(): void {
		// Re-prime the static cache inside wb_listora_get_setting() — the
		// helper accepts a force-reload flag so the next read fetches the
		// fresh option value.
		if ( function_exists( 'wb_listora_get_setting' ) ) {
			wb_listora_get_setting( null, null, true );
		}
		self::bump( self::GROUP_SETTINGS );
	}
}
