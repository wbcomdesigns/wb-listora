<?php
/**
 * Analytics Lite — Free-tier view tracking on the existing `listora_analytics`
 * table.
 *
 * The `analytics` table is created by the activator (it was originally reserved
 * for Pro). This Free service gives directory owners a useful baseline straight
 * away: per-listing view counts surfaced on the owner dashboard, the admin
 * listings table, and the listing REST response — no Pro required.
 *
 * Design constraints (100k-listing scale):
 *
 *  - **Bounded, batched reads.** Aggregate look-ups are done one query per page
 *    of listings via {@see prepare_views()} — never one query per row. The
 *    per-request static cache means repeated {@see get_views()} calls for the
 *    same ID after a prefetch are free.
 *  - **Bot-filtered + rate-limited writes.** A view is only recorded for a real
 *    front-end GET that isn't a crawler and that the same visitor hasn't already
 *    been counted for inside the dedupe window. Writes use a single
 *    `INSERT ... ON DUPLICATE KEY UPDATE` against the table's
 *    `(listing_id, event_type, event_date)` UNIQUE key, so a day's worth of
 *    views is one row that increments — never an unbounded append.
 *  - **Pro supersession.** When Pro's analytics feature is recording, it owns
 *    the table. Free must not double-count. Pro returns true from the
 *    `wb_listora_pro_owns_analytics` filter; this service then records nothing
 *    (reads still work, so the dashboard / admin / REST surfaces keep showing
 *    the same `view` rows Pro writes).
 *
 * Additive only — no schema change. The table already exists.
 *
 * @package WBListora\Features
 * @since   1.1.0
 */

namespace WBListora\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Free analytics-lite view tracker.
 */
class Analytics_Lite {

	/**
	 * Event type stored in the `analytics.event_type` column for page views.
	 */
	const EVENT_VIEW = 'view';

	/**
	 * Per-visitor dedupe window (seconds). A single visitor (per IP + listing)
	 * is counted at most once inside this window so a refresh-spammer or an
	 * impatient back/forward navigation doesn't inflate the count.
	 */
	const DEDUPE_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Per-request aggregate cache: `[ listing_id => view_count ]`.
	 *
	 * Populated by {@see prepare_views()} and read by {@see get_views()} so a
	 * page that prefetched a batch never issues a second query per row.
	 *
	 * @var array<int,int>
	 */
	private static $views_cache = array();

	/**
	 * Boot the service.
	 *
	 * Reads (dashboard / admin / REST) always work regardless of who owns
	 * recording, so the read surfaces are wired by their own callers. Here we
	 * only register the *recording* hook, and only when Free owns recording —
	 * Pro supersedes via {@see self::pro_owns_recording()}.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::pro_owns_recording() ) {
			return;
		}

		// `wp` fires once per front-end request after the main query is
		// resolved, so `is_singular()` / the queried object are reliable here.
		// Priority 20 keeps us after canonical-redirect resolution.
		add_action( 'wp', array( __CLASS__, 'maybe_record_view' ), 20 );
	}

	/**
	 * Whether Pro currently owns view recording.
	 *
	 * Pro hooks the `wb_listora_pro_owns_analytics` filter and returns true when
	 * its analytics feature toggle is ON. When that happens, Free stands down
	 * from *writing* so the same view is never counted twice. Free still *reads*
	 * the `view` rows Pro writes.
	 *
	 * @return bool
	 */
	public static function pro_owns_recording() {
		/**
		 * Filter whether Pro's analytics feature owns view recording.
		 *
		 * Default false (Free records). Pro returns true when its analytics
		 * toggle is enabled so Free defers and the count is never doubled.
		 *
		 * @since 1.1.0
		 * @param bool $owns Whether Pro owns recording. Default false.
		 */
		return (bool) apply_filters( 'wb_listora_pro_owns_analytics', false );
	}

	/**
	 * Record a view for the current request when it's a real, single listing
	 * front-end pageview that passes the bot + dedupe gates.
	 *
	 * @return void
	 */
	public static function maybe_record_view() {
		if ( is_admin() || ! is_singular( 'listora_listing' ) ) {
			return;
		}

		// Only count true page reads — never POST/PUT, REST, AJAX, cron, CLI,
		// or feed requests.
		if ( ! self::is_countable_request() ) {
			return;
		}

		if ( self::is_bot() ) {
			return;
		}

		$listing_id = (int) get_queried_object_id();
		if ( $listing_id <= 0 ) {
			return;
		}

		self::record_view( $listing_id );
	}

	/**
	 * Increment today's view counter for a listing.
	 *
	 * Idempotent per visitor inside {@see self::DEDUPE_WINDOW}: a short-lived
	 * transient keyed by listing + hashed IP suppresses repeat counts. The DB
	 * write is a single upsert against the UNIQUE
	 * `(listing_id, event_type, event_date)` key, so today's views live in one
	 * row that increments — the table never grows per view.
	 *
	 * Respects Pro supersession: returns early when Pro owns recording so the
	 * count can never be doubled, even if a caller invokes this directly.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return bool True when a view was recorded, false when skipped.
	 */
	public static function record_view( $listing_id ) {
		$listing_id = (int) $listing_id;
		if ( $listing_id <= 0 || self::pro_owns_recording() ) {
			return false;
		}

		// Per-visitor dedupe: one count per IP + listing per window.
		$dedupe_key = 'listora_view_' . $listing_id . '_' . md5( self::client_ip() );
		if ( false !== get_transient( $dedupe_key ) ) {
			return false;
		}
		set_transient( $dedupe_key, 1, self::DEDUPE_WINDOW );

		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'analytics';
		$today = current_time( 'Y-m-d' );

		// One row per (listing, event_type, day). The UNIQUE key makes this an
		// atomic increment — no SELECT-then-UPDATE race, no per-view row growth.
		// $table is a trusted $wpdb->prefix + plugin constant, not user input.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (listing_id, event_type, event_date, count) VALUES (%d, %s, %s, 1) ON DUPLICATE KEY UPDATE count = count + 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$listing_id,
				self::EVENT_VIEW,
				$today
			)
		);

		// Keep any prefetched aggregate for this listing in sync for the rest
		// of the request.
		if ( false !== $result && isset( self::$views_cache[ $listing_id ] ) ) {
			++self::$views_cache[ $listing_id ];
		}

		/**
		 * Fires after a Free analytics-lite view is recorded.
		 *
		 * @since 1.1.0
		 * @param int $listing_id Listing the view was recorded for.
		 */
		do_action( 'wb_listora_view_recorded', $listing_id );

		return false !== $result;
	}

	/**
	 * Batch-prefetch total view counts for a set of listings in a single query.
	 *
	 * MUST be called once per page of listings before looping rows (the
	 * 100k-scale rule: never one query per row). After this, {@see get_views()}
	 * is a free array read for every prefetched ID.
	 *
	 * @param int[] $listing_ids Listing IDs to prefetch.
	 * @return array<int,int> Map of listing ID => total view count.
	 */
	public static function prepare_views( array $listing_ids ) {
		$listing_ids = array_values( array_unique( array_map( 'intval', $listing_ids ) ) );
		$listing_ids = array_filter(
			$listing_ids,
			static function ( $id ) {
				return $id > 0 && ! isset( self::$views_cache[ $id ] );
			}
		);

		if ( empty( $listing_ids ) ) {
			return self::$views_cache;
		}

		global $wpdb;
		$table        = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'analytics';
		$placeholders = implode( ',', array_fill( 0, count( $listing_ids ), '%d' ) );

		// One grouped SUM for the whole page — the `idx_listing` + UNIQUE key
		// cover this lookup. $table + $placeholders are trusted (prefix +
		// constant, and a generated %d list), not user input.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, SUM(count) AS views FROM {$table} WHERE event_type = %s AND listing_id IN ({$placeholders}) GROUP BY listing_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::EVENT_VIEW,
				...$listing_ids
			),
			ARRAY_A
		);

		$found = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$found[ (int) $row['listing_id'] ] = (int) $row['views'];
			}
		}

		// Seed the cache for every requested ID — including the zero-view ones
		// that returned no row — so a later get_views() never re-queries them.
		foreach ( $listing_ids as $id ) {
			self::$views_cache[ $id ] = $found[ $id ] ?? 0;
		}

		return self::$views_cache;
	}

	/**
	 * Get the total view count for a single listing.
	 *
	 * Reads from the per-request cache when the listing was prefetched via
	 * {@see prepare_views()}; otherwise issues a single bounded query for that
	 * one listing (acceptable on detail pages, never inside a row loop).
	 *
	 * @param int $listing_id Listing post ID.
	 * @return int Total recorded views (0 when none).
	 */
	public static function get_views( $listing_id ) {
		$listing_id = (int) $listing_id;
		if ( $listing_id <= 0 ) {
			return 0;
		}

		if ( isset( self::$views_cache[ $listing_id ] ) ) {
			return (int) self::$views_cache[ $listing_id ];
		}

		self::prepare_views( array( $listing_id ) );

		return (int) ( self::$views_cache[ $listing_id ] ?? 0 );
	}

	/**
	 * Whether the current request should ever count as a view.
	 *
	 * Excludes non-GET methods, REST, AJAX, cron, CLI, feeds, previews, and
	 * embeds — only a plain front-end page read counts.
	 *
	 * @return bool
	 */
	private static function is_countable_request() {
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			|| ( defined( 'DOING_CRON' ) && DOING_CRON )
			|| ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		) {
			return false;
		}

		if ( is_feed() || is_preview() || is_embed() ) {
			return false;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		return 'GET' === $method;
	}

	/**
	 * Lightweight crawler detection from the User-Agent.
	 *
	 * Deliberately conservative — it catches the common, self-identifying bots
	 * (Googlebot, bingbot, generic spiders/crawlers, scrapers, headless agents)
	 * without trying to be a full bot-management product. A missing UA is
	 * treated as a bot.
	 *
	 * @return bool
	 */
	private static function is_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		if ( '' === $ua ) {
			return true;
		}

		$signatures = array(
			'bot',
			'crawl',
			'spider',
			'slurp',
			'mediapartners',
			'facebookexternalhit',
			'embedly',
			'quora link preview',
			'pinterest',
			'wget',
			'curl',
			'python-requests',
			'headlesschrome',
			'phantomjs',
			'archive.org_bot',
			'semrush',
			'ahrefs',
			'mj12bot',
			'dotbot',
		);

		$ua_lower = strtolower( $ua );
		foreach ( $signatures as $needle ) {
			if ( false !== strpos( $ua_lower, $needle ) ) {
				return true;
			}
		}

		/**
		 * Filter the bot verdict for the current view-recording request.
		 *
		 * @since 1.1.0
		 * @param bool   $is_bot Whether the request is treated as a bot.
		 * @param string $ua     The raw User-Agent string.
		 */
		return (bool) apply_filters( 'wb_listora_analytics_is_bot', false, $ua );
	}

	/**
	 * Resolve the client IP for per-visitor dedupe.
	 *
	 * Mirrors {@see \WBListora\Rate_Limiter}'s proxy-aware resolution so the
	 * dedupe key is computed the same way the rest of the plugin treats client
	 * IPs. Defaults to REMOTE_ADDR; honours X-Forwarded-For only when the site
	 * opts in via `wb_listora_trust_proxy_headers`.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		/** This filter is documented in includes/class-rate-limiter.php */
		if ( apply_filters( 'wb_listora_trust_proxy_headers', false ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$first     = trim( explode( ',', $forwarded )[0] );
			if ( $first && filter_var( $first, FILTER_VALIDATE_IP ) ) {
				$ip = $first;
			}
		}

		return $ip;
	}
}
