<?php
/**
 * Canonical reader + normaliser for a listing's opening hours.
 *
 * ## Why this exists
 *
 * `business_hours` was being emitted in two incompatible shapes by two
 * endpoints reading two different producers:
 *
 * | Endpoint | Source | Shape |
 * |---|---|---|
 * | `/search` → `meta.business_hours` | post meta | `[{day:1, open:"06:00", close:"01:00"}]` |
 * | `/listings/{id}/detail` → `business_hours` | the `hours` table | `[{day:0, day_name, open_time:"06:00:00", close_time:"01:00:00", is_closed, is_24h, timezone}]` |
 *
 * Different keys, different precision, different day-base — and only the detail
 * shape carries `timezone` / `is_closed` / `is_24h`. That makes **"Open now"
 * impossible to compute honestly from a search card**: seeded data contains
 * overnight spans (06:00→01:00) which need midnight-wrap logic evaluated in the
 * *listing's* timezone, not the device's.
 *
 * This class is the single source of the normalised (detail-shaped) block, so
 * `/search` and `/listings/{id}/detail` cannot drift apart again — there is one
 * producer and one shape.
 *
 * ## What it does NOT do
 *
 * It does not touch `meta.business_hours`. That key is public, shipped, and
 * consumed; per the plugin's production rules it keeps working byte-identically
 * and is neither renamed nor restructured here. This class only ADDS the
 * normalised block alongside it. See the convergence note in
 * {@see \WBListora\REST\Search_Controller::hydrate_listings()}.
 *
 * ## Shape
 *
 * `get()` returns a list of rows, ordered by `day` ascending, day-base 0 =
 * Sunday (matching PHP's `w` and the `hours` table's own `day_of_week`):
 *
 *     [
 *       [
 *         'day'        => 1,             // int, 0=Sunday … 6=Saturday
 *         'day_name'   => 'Monday',      // translated
 *         'open_time'  => '06:00:00',    // HH:MM:SS, listing-local, null when closed
 *         'close_time' => '01:00:00',    // HH:MM:SS; < open_time means an overnight span
 *         'is_closed'  => false,
 *         'is_24h'     => false,
 *         'timezone'   => 'America/New_York',
 *       ],
 *     ]
 *
 * Reads hit the table's PRIMARY KEY `(listing_id, day_of_week)`; batching is a
 * `WHERE listing_id IN (…)` against that same key. No schema change.
 *
 * @package WBListora\Core
 * @since   1.2.3
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Batched, normalised business-hours reader.
 */
class Business_Hours {

	/**
	 * Per-request cache of normalised rows: `[ listing_id => array<int, array> ]`.
	 *
	 * @var array<int, array<int, array<string, mixed>>>
	 */
	private static $cache = array();

	/**
	 * Translated day names, indexed by the table's `day_of_week` (0 = Sunday).
	 *
	 * @return array<int, string>
	 */
	private static function day_names() {
		return array(
			0 => __( 'Sunday', 'wb-listora' ),
			1 => __( 'Monday', 'wb-listora' ),
			2 => __( 'Tuesday', 'wb-listora' ),
			3 => __( 'Wednesday', 'wb-listora' ),
			4 => __( 'Thursday', 'wb-listora' ),
			5 => __( 'Friday', 'wb-listora' ),
			6 => __( 'Saturday', 'wb-listora' ),
		);
	}

	/**
	 * Prime the normalised hours for a batch of listings in one query.
	 *
	 * Call once per page, before the render/prepare loop.
	 *
	 * @param array<int> $listing_ids Listing post IDs on the current page.
	 * @return void
	 */
	public static function prime( array $listing_ids ) {
		$listing_ids = Batch_Prime::ids( $listing_ids );

		$pending = Batch_Prime::pending(
			$listing_ids,
			static function ( $id ) {
				return isset( self::$cache[ $id ] );
			}
		);

		if ( empty( $pending ) ) {
			return;
		}

		global $wpdb;
		$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$placeholders = Batch_Prime::placeholders( count( $pending ) );

		// One query for the page. Hits PRIMARY KEY (listing_id, day_of_week).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, day_of_week, open_time, close_time, is_closed, is_24h, timezone
				FROM {$prefix}hours
				WHERE listing_id IN ({$placeholders})
				ORDER BY listing_id ASC, day_of_week ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$pending
			),
			ARRAY_A
		);

		$day_names = self::day_names();
		$grouped   = array();

		foreach ( (array) $rows as $row ) {
			$listing_id = (int) $row['listing_id'];

			$grouped[ $listing_id ][] = array(
				'day'        => (int) $row['day_of_week'],
				'day_name'   => $day_names[ (int) $row['day_of_week'] ] ?? '',
				'open_time'  => $row['open_time'],
				'close_time' => $row['close_time'],
				'is_closed'  => (bool) $row['is_closed'],
				'is_24h'     => (bool) $row['is_24h'],
				'timezone'   => $row['timezone'],
			);
		}

		// A listing with no hours rows caches as an empty list, so a later read
		// is a cache hit rather than a repeat query.
		foreach ( $pending as $id ) {
			self::$cache[ $id ] = $grouped[ $id ] ?? array();
		}
	}

	/**
	 * Get a listing's normalised opening hours.
	 *
	 * An unprimed ID falls back to a bounded single-ID prime through the same
	 * one-query path, so callers are never required to prime.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array<int, array<string, mixed>> Empty list when the listing has no hours.
	 */
	public static function get( $listing_id ) {
		$listing_id = (int) $listing_id;

		if ( $listing_id <= 0 ) {
			return array();
		}

		if ( ! isset( self::$cache[ $listing_id ] ) ) {
			self::prime( array( $listing_id ) );
		}

		return self::$cache[ $listing_id ] ?? array();
	}

	/**
	 * Get the timezone the listing's hours are expressed in.
	 *
	 * Returns an empty string when the listing has no hours rows — we do not
	 * invent one. A client with no hours cannot compute "Open now" anyway, and
	 * substituting the site or device timezone would silently produce wrong
	 * answers for listings in another region. Callers that want a fallback can
	 * apply their own.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return string IANA timezone identifier, or '' when unknown.
	 */
	public static function get_timezone( $listing_id ) {
		foreach ( self::get( $listing_id ) as $row ) {
			if ( ! empty( $row['timezone'] ) ) {
				return (string) $row['timezone'];
			}
		}

		return '';
	}
}
