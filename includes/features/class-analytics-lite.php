<?php
/**
 * Analytics Lite — Free-tier view tracking on the existing `listora_analytics` table.
 *
 * Reads are batched one query per page of listings via {@see prepare_views()}.
 * Writes are bot-filtered, deduped per visitor, and use one
 * `INSERT ... ON DUPLICATE KEY UPDATE` row per listing per day. When Pro
 * returns true from `wb_listora_pro_owns_analytics` it owns recording and
 * this service records nothing (reads still work).
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
	 * Event type stored for a message sent to a listing owner.
	 */
	const EVENT_LEAD = 'lead';

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
		/*
		 * Leads are counted whatever else is switched off: a lead belongs to
		 * the contact form, not to the reporting feature. Registered above the
		 * pro_owns_recording() stand-down, which only covers page views.
		 */
		add_action( 'wb_listora_after_contact_form_submit', array( __CLASS__, 'handle_lead_submission' ), 10, 1 );

		if ( self::pro_owns_recording() ) {
			return;
		}

		// `wp` fires once per front-end request after the main query is
		// resolved, so `is_singular()` / the queried object are reliable here.
		// Priority 20 keeps us after canonical-redirect resolution.
		add_action( 'wp', array( __CLASS__, 'maybe_record_view' ), 20 );
	}

	/**
	 * Action callback for both form hooks.
	 *
	 * A thin void wrapper: `record_lead()` returns whether a row was written,
	 * which callers and tests want, and an action callback must return nothing.
	 *
	 * @param int $listing_id Listing the message was sent about.
	 * @return void
	 */
	public static function handle_lead_submission( $listing_id ): void {
		self::record_lead( $listing_id );
	}

	/**
	 * Count a message sent to a listing owner.
	 *
	 * Aggregate only - one row per (listing, 'lead', day), the same shape as a
	 * view, and no PII. Deliberately NOT deduped by IP the way views are: two
	 * enquiries from one person are two enquiries, and the owner has two emails
	 * to answer.
	 *
	 * @param int $listing_id Listing the message was sent about.
	 * @return bool Whether a row was written.
	 */
	public static function record_lead( $listing_id ) {
		$listing_id = (int) $listing_id;

		if ( $listing_id <= 0 ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'analytics';

		// Same atomic upsert as record_view(); $table is $wpdb->prefix plus a
		// plugin constant, never user input.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom plugin table, no WP API, table name is the site table prefix, replacements are spread from an array
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (listing_id, event_type, event_date, count) VALUES (%d, %s, %s, 1) ON DUPLICATE KEY UPDATE count = count + 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				$listing_id,
				self::EVENT_LEAD,
				current_time( 'Y-m-d' )
			)
		);

		/**
		 * Fires after a lead is counted for a listing.
		 *
		 * @since 1.8.0
		 *
		 * @param int $listing_id Listing the message was sent about.
		 */
		do_action( 'wb_listora_lead_recorded', $listing_id );

		return false !== $result;
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
		// Established upscale-model fallback (same pattern as Contact_Form vs
		// Pro's lead_form): when Pro is active with its analytics feature
		// enabled, Pro's Analytics::track_view() records into the same
		// listora_analytics table, so Free MUST stand down even before Pro
		// registers the explicit ownership filter. Prevents every front-end
		// view being double-counted on Pro sites.
		$default = function_exists( 'wb_listora_pro_feature_enabled' )
			&& wb_listora_pro_feature_enabled( 'analytics' );

		/**
		 * Filter whether Pro's analytics feature owns view recording.
		 *
		 * Default false on Free-only sites; true when Pro's analytics toggle
		 * is enabled. Pro also asserts ownership explicitly via this filter
		 * so the contract survives feature-loading refactors on either side.
		 *
		 * @since 1.1.0
		 * @param bool $owns Whether Pro owns recording.
		 */
		return (bool) apply_filters( 'wb_listora_pro_owns_analytics', $default );
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

	/** Cookie holding the listings this browser viewed recently, as "id:unix_time" pairs. */
	const VIEWED_COOKIE = 'listora_views';

	/** Most listings the viewed-cookie remembers. */
	const VIEWED_COOKIE_MAX = 20;

	/**
	 * Whether this visitor was already counted for the listing inside the window,
	 * and if not, remember them.
	 *
	 * With a persistent object cache the visitor is an IP + listing key added with
	 * wp_cache_add(). Without one a transient would be two wp_options rows per
	 * visitor per listing (a crawler across 2000 listings leaves thousands), so a
	 * small cookie carries the recent listing ids instead and nothing is stored.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return bool True when already counted.
	 */
	private static function viewed_recently( int $listing_id ): bool {
		if ( wp_using_ext_object_cache() ) {
			return ! wp_cache_add( 'view_' . $listing_id . '_' . md5( \WBListora\Rate_Limiter::client_ip() ), 1, 'wb_listora_views', self::DEDUPE_WINDOW );
		}

		$now    = time();
		$recent = array();
		foreach ( explode( ',', isset( $_COOKIE[ self::VIEWED_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::VIEWED_COOKIE ] ) ) : '' ) as $pair ) {
			$parts = explode( ':', $pair );
			if ( 2 === count( $parts ) && (int) $parts[0] > 0 && $now - (int) $parts[1] < self::DEDUPE_WINDOW ) {
				$recent[ (int) $parts[0] ] = (int) $parts[1];
			}
		}

		if ( isset( $recent[ $listing_id ] ) ) {
			return true;
		}

		$recent[ $listing_id ] = $now;
		$recent                = array_slice( $recent, -self::VIEWED_COOKIE_MAX, null, true );
		$value                 = implode(
			',',
			array_map( static fn ( $id, $time ) => $id . ':' . $time, array_keys( $recent ), $recent )
		);

		$_COOKIE[ self::VIEWED_COOKIE ] = $value; // Seen by any later call in this request.
		if ( ! headers_sent() ) {
			setcookie(
				self::VIEWED_COOKIE,
				$value,
				array(
					'expires'  => $now + self::DEDUPE_WINDOW,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		return false;
	}

	/**
	 * Increment today's view counter for a listing.
	 *
	 * Idempotent per visitor inside {@see self::DEDUPE_WINDOW}, without writing a
	 * database row per visitor (see viewed_recently()). The DB
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

		if ( self::viewed_recently( $listing_id ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'analytics';
		$today = current_time( 'Y-m-d' );

		// One row per (listing, event_type, day). The UNIQUE key makes this an
		// atomic increment — no SELECT-then-UPDATE race, no per-view row growth.
		// $table is a trusted $wpdb->prefix + plugin constant, not user input.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom plugin table, no WP API, table name is the site table prefix, replacements are spread from an array
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (listing_id, event_type, event_date, count) VALUES (%d, %s, %s, 1) ON DUPLICATE KEY UPDATE count = count + 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, no WP API, site table prefix plus generated IN placeholders
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, SUM(count) AS views FROM {$table} WHERE event_type = %s AND listing_id IN ({$placeholders}) GROUP BY listing_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from the site table prefix, IN list is generated %d placeholders.
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
		if ( wp_doing_ajax()
			|| wp_doing_cron()
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
	 * Whether the current request is a crawler that must not count as a view.
	 *
	 * The canonical signature scan now lives in the shared
	 * {@see wb_listora_is_bot_request()} helper (one implementation for every
	 * Free surface + the documented Free→Pro extension point). This method keeps
	 * its own `wb_listora_analytics_is_bot` filter as an analytics-specific
	 * override layer for back-compat — a site that whitelisted a crawler for
	 * view-counting only (not anti-spam) keeps working.
	 *
	 * @return bool
	 */
	private static function is_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$is_bot = wb_listora_is_bot_request( $ua );

		/**
		 * Filter the bot verdict for the current view-recording request.
		 *
		 * Analytics-specific override layer. For the plugin-wide verdict use the
		 * `wb_listora_is_bot_request` filter instead — this hook only changes
		 * whether a request counts as an analytics view.
		 *
		 * @since 1.1.0
		 * @param bool   $is_bot Whether the request is treated as a bot.
		 * @param string $ua     The raw User-Agent string.
		 */
		return (bool) apply_filters( 'wb_listora_analytics_is_bot', $is_bot, $ua );
	}
}
