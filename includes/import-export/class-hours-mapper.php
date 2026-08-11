<?php
/**
 * Business-hours shape mapping for competitor migrators.
 *
 * Every competitor stores opening hours in its own shape, and none of them is
 * one of the three `wb_listora_normalize_hours()` understands. The migrators
 * used to pass the source value straight into `_listora_business_hours`, so the
 * normaliser rejected it, `listora_hours` got zero rows, and the imported
 * listing showed no hours, never matched "Open now", and emitted no
 * openingHoursSpecification — silently (BC 10184420962).
 *
 * One mapper class rather than a private method per migrator: the five
 * migrators already duplicate enough, and hours parsing is the kind of logic
 * that drifts the moment it is copied.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Converts competitor business-hours payloads into Listora's canonical shape.
 *
 * Canonical target: a list of entries, each carrying an integer `day` where
 * 0 is Sunday, plus `open`/`close`, or a `closed` / `is_24h` state flag.
 */
final class Hours_Mapper {

	/**
	 * Day short-name to Listora day index (0 = Sunday).
	 *
	 * @var array<string,int>
	 */
	private const SHORT_DAYS = array(
		'su' => 0,
		'mo' => 1,
		'tu' => 2,
		'we' => 3,
		'th' => 4,
		'fr' => 5,
		'sa' => 6,
	);

	/**
	 * Full day names to Listora day index (0 = Sunday).
	 *
	 * @var array<string,int>
	 */
	private const FULL_DAYS = array(
		'sunday'    => 0,
		'monday'    => 1,
		'tuesday'   => 2,
		'wednesday' => 3,
		'thursday'  => 4,
		'friday'    => 5,
		'saturday'  => 6,
	);

	/**
	 * Map GeoDirectory's business-hours value.
	 *
	 * GeoDirectory stores a schema.org-ish string in its detail table, e.g.
	 * `["Mo 09:00-17:00","Tu 09:00-12:00,13:00-17:00","Su Closed"],["UTC":"+0"]`.
	 * Its own `geodir_schema_to_array()` turns that into:
	 *
	 *     [ 'hours' => [ 'Mo' => [ [ 'opens' => '09:00', 'closes' => '17:00' ] ], ... ],
	 *       'timezone_string' => 'UTC', 'utc_offset' => '+0' ]
	 *
	 * Verified by running that function against a real stored value rather than
	 * inferring the format: a closed day comes back as `opens => 'Closed'`, and
	 * a split shift is two entries under one day key.
	 *
	 * Accepts either the raw stored string or the already-parsed array, because
	 * whether GeoDirectory is active decides which one the migrator can obtain.
	 *
	 * @param mixed $value Raw or parsed GeoDirectory hours value.
	 * @return array<int, array<string, mixed>> Canonical entries, empty when unmappable.
	 */
	public static function from_geodirectory( $value ): array {
		if ( is_string( $value ) && function_exists( 'geodir_schema_to_array' ) ) {
			$value = \geodir_schema_to_array( stripslashes_deep( $value ) );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		// Tolerate both the wrapper and a bare hours map.
		$hours = isset( $value['hours'] ) && is_array( $value['hours'] ) ? $value['hours'] : $value;

		$out = array();

		foreach ( $hours as $day_key => $ranges ) {
			$day = self::day_index( (string) $day_key );
			if ( null === $day || ! is_array( $ranges ) ) {
				continue;
			}

			foreach ( $ranges as $range ) {
				if ( ! is_array( $range ) ) {
					continue;
				}

				$opens  = trim( (string) ( $range['opens'] ?? '' ) );
				$closes = trim( (string) ( $range['closes'] ?? '' ) );

				// GeoDirectory writes the literal word rather than a flag.
				if ( '' === $opens || 0 === strcasecmp( $opens, 'Closed' ) ) {
					$out[] = array(
						'day'    => $day,
						'closed' => true,
					);
					continue;
				}

				$entry = self::range_entry( $day, $opens, $closes );
				if ( $entry ) {
					$out[] = $entry;
				}
			}
		}

		return $out;
	}

	/**
	 * Map a day-keyed payload of the shape most competitors settle on.
	 *
	 * Covers the common family: keys are day names (short or full, any case),
	 * values are either a single range, a list of ranges, or a state marker.
	 * Directorist's `_bdbh` and ListingPro's `business_hours` are both members
	 * of this family, differing only in their open/close key names, which is why
	 * the accepted aliases are broad rather than per-plugin.
	 *
	 * NOT verified against a live Directorist or ListingPro install — see the
	 * card and the accompanying journey. Directorist's hours are a paid
	 * extension and ListingPro is a premium theme, so neither could be exercised
	 * on the verification site. Treat this path as best-effort until it is.
	 *
	 * @param mixed $value Day-keyed hours payload.
	 * @return array<int, array<string, mixed>> Canonical entries, empty when unmappable.
	 */
	public static function from_day_keyed( $value ): array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : maybe_unserialize( $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();

		foreach ( $value as $day_key => $entry ) {
			$day = self::day_index( (string) $day_key );
			if ( null === $day ) {
				continue;
			}

			foreach ( self::as_range_list( $entry ) as $range ) {
				if ( ! is_array( $range ) ) {
					continue;
				}

				if ( self::is_truthy( $range, array( 'closed', 'is_closed', 'off' ) ) ) {
					$out[] = array(
						'day'    => $day,
						'closed' => true,
					);
					continue;
				}

				if ( self::is_truthy( $range, array( 'is_24h', 'open_24_hours', 'enable247hour', 'always_open' ) ) ) {
					$out[] = array(
						'day'    => $day,
						'is_24h' => true,
					);
					continue;
				}

				$opens  = self::first_value( $range, array( 'open', 'opens', 'start', 'from', 'opening_time', 'start_time' ) );
				$closes = self::first_value( $range, array( 'close', 'closes', 'end', 'to', 'closing_time', 'end_time' ) );

				$mapped = self::range_entry( $day, $opens, $closes );
				if ( $mapped ) {
					$out[] = $mapped;
				}
			}
		}

		return $out;
	}

	/**
	 * Resolve a day key to Listora's 0-6 index.
	 *
	 * Accepts short names (Mo), full names (Monday), and numeric keys. Numeric
	 * keys are taken as already being Listora's convention — a source that
	 * numbers days differently must be mapped by its own method rather than
	 * guessed at here, because an off-by-one silently shifts every listing's
	 * opening hours by a day and nothing downstream can detect it.
	 *
	 * @param string $key Raw day key.
	 * @return int|null 0-6, or null when unrecognised.
	 */
	private static function day_index( string $key ): ?int {
		$key = strtolower( trim( $key ) );

		if ( '' === $key ) {
			return null;
		}

		if ( ctype_digit( $key ) ) {
			$n = (int) $key;
			return ( $n >= 0 && $n <= 6 ) ? $n : null;
		}

		if ( isset( self::FULL_DAYS[ $key ] ) ) {
			return self::FULL_DAYS[ $key ];
		}

		$short = substr( $key, 0, 2 );

		return self::SHORT_DAYS[ $short ] ?? null;
	}

	/**
	 * Build one canonical range entry, or null when the times are unusable.
	 *
	 * @param int    $day    Day index.
	 * @param string $opens  Opening time.
	 * @param string $closes Closing time.
	 * @return array<string, mixed>|null
	 */
	private static function range_entry( int $day, string $opens, string $closes ): ?array {
		$open  = self::time( $opens );
		$close = self::time( $closes );

		if ( '' === $open ) {
			return null;
		}

		// A day that opens and closes at the same moment is how several sources
		// encode "open all day"; recording it as a zero-length range would show
		// the listing as shut.
		if ( '' !== $close && $open === $close ) {
			return array(
				'day'    => $day,
				'is_24h' => true,
			);
		}

		return array(
			'day'   => $day,
			'open'  => $open,
			'close' => $close,
		);
	}

	/**
	 * Normalise a time to 24-hour HH:MM.
	 *
	 * @param string $raw Raw time.
	 * @return string HH:MM, or empty when unparseable.
	 */
	private static function time( string $raw ): string {
		$raw = trim( $raw );

		if ( '' === $raw ) {
			return '';
		}

		if ( preg_match( '/^(\d{1,2}):(\d{2})/', $raw, $m ) ) {
			$hour = (int) $m[1];
			$min  = (int) $m[2];

			// 12-hour sources carry a meridiem suffix.
			if ( preg_match( '/([ap])\.?m/i', $raw, $mer ) ) {
				$is_pm = 0 === strcasecmp( $mer[1], 'p' );
				if ( $is_pm && $hour < 12 ) {
					$hour += 12;
				} elseif ( ! $is_pm && 12 === $hour ) {
					$hour = 0;
				}
			}

			if ( $hour > 23 || $min > 59 ) {
				return '';
			}

			return sprintf( '%02d:%02d', $hour, $min );
		}

		return '';
	}

	/**
	 * Coerce a day's value into a list of range arrays.
	 *
	 * @param mixed $entry Range, list of ranges, or state marker.
	 * @return array<int, mixed>
	 */
	private static function as_range_list( $entry ): array {
		if ( ! is_array( $entry ) ) {
			return array();
		}

		// A list of ranges has sequential integer keys.
		if ( array_keys( $entry ) === range( 0, count( $entry ) - 1 ) ) {
			return $entry;
		}

		return array( $entry );
	}

	/**
	 * Whether any of the given keys is set and truthy.
	 *
	 * @param array<string,mixed> $range Range array.
	 * @param array<int,string>   $keys  Candidate keys.
	 * @return bool
	 */
	private static function is_truthy( array $range, array $keys ): bool {
		foreach ( $keys as $key ) {
			// empty() already rejects '', '0', 0 and false. The only value that
			// still reads as true but means false is the literal string
			// "false", which JSON-ish sources produce.
			if ( ! empty( $range[ $key ] ) && 'false' !== $range[ $key ] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * First non-empty value among candidate keys.
	 *
	 * @param array<string,mixed> $range Range array.
	 * @param array<int,string>   $keys  Candidate keys in priority order.
	 * @return string
	 */
	private static function first_value( array $range, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $range[ $key ] ) && is_scalar( $range[ $key ] ) && '' !== (string) $range[ $key ] ) {
				return (string) $range[ $key ];
			}
		}

		return '';
	}
}
