<?php
/**
 * Recurrence helper — generates virtual event occurrences.
 *
 * Calculates future dates based on a listing's start_date and recurrence_type.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Provides static helpers for recurring event calculations.
 */
class Recurrence {

	/**
	 * Maximum number of occurrences to generate per call (safety limit).
	 *
	 * @var int
	 */
	const MAX_OCCURRENCES = 366;

	/**
	 * Get the next occurrence date/time for a recurring listing.
	 *
	 * Returns the first occurrence that is on or after the current date.
	 * Returns null if the listing does not recur, or if all occurrences are past.
	 *
	 * @param int $listing_id Post ID of the listing.
	 * @return string|null ISO date string (Y-m-d) or null.
	 */
	public static function get_next_occurrence( $listing_id ) {
		$recurrence_type = Meta_Handler::get_value( $listing_id, 'recurrence_type', 'none' );

		if ( 'none' === $recurrence_type || empty( $recurrence_type ) ) {
			return null;
		}

		$base_date = Meta_Handler::get_value( $listing_id, 'start_date', '' );

		if ( empty( $base_date ) ) {
			return null;
		}

		$recurrence_end = Meta_Handler::get_value( $listing_id, 'recurrence_end', '' );
		$today          = current_time( 'Y-m-d' );
		$base_ts        = strtotime( $base_date );

		if ( false === $base_ts ) {
			return null;
		}

		// If the recurrence has ended, return null.
		if ( ! empty( $recurrence_end ) && $recurrence_end < $today ) {
			return null;
		}

		// If the base date is today or in the future, it is the next occurrence.
		$base_ymd = gmdate( 'Y-m-d', $base_ts );
		if ( $base_ymd >= $today ) {
			return $base_ymd;
		}

		// Walk forward from the base date to find the first occurrence >= today.
		$candidate = self::advance_date( $base_ts, $recurrence_type, $today );

		if ( null === $candidate ) {
			return null;
		}

		$candidate_ymd = gmdate( 'Y-m-d', $candidate );

		// Check against recurrence end.
		if ( ! empty( $recurrence_end ) && $candidate_ymd > $recurrence_end ) {
			return null;
		}

		return $candidate_ymd;
	}

	/**
	 * Get all occurrences of a recurring listing within a date range.
	 *
	 * Returns an array of Y-m-d date strings. For non-recurring listings,
	 * returns the original start_date if it falls within the range.
	 *
	 * @param int    $listing_id Post ID of the listing.
	 * @param string $range_start Range start date (Y-m-d).
	 * @param string $range_end   Range end date (Y-m-d), inclusive.
	 * @return string[] Array of Y-m-d date strings.
	 */
	public static function get_occurrences( $listing_id, $range_start, $range_end ) {
		$base_date = Meta_Handler::get_value( $listing_id, 'start_date', '' );

		if ( empty( $base_date ) ) {
			return array();
		}

		$base_ts = strtotime( $base_date );

		if ( false === $base_ts ) {
			return array();
		}

		$recurrence_type = Meta_Handler::get_value( $listing_id, 'recurrence_type', 'none' );

		// Non-recurring: return base date if in range.
		if ( 'none' === $recurrence_type || empty( $recurrence_type ) ) {
			$base_ymd = gmdate( 'Y-m-d', $base_ts );
			if ( $base_ymd >= $range_start && $base_ymd <= $range_end ) {
				return array( $base_ymd );
			}
			return array();
		}

		$recurrence_end = Meta_Handler::get_value( $listing_id, 'recurrence_end', '' );
		$effective_end  = $range_end;

		// Clamp to recurrence end if set.
		if ( ! empty( $recurrence_end ) && $recurrence_end < $effective_end ) {
			$effective_end = $recurrence_end;
		}

		// If the effective end is before the range start, no occurrences.
		if ( $effective_end < $range_start ) {
			return array();
		}

		$dates    = array();
		$count    = 0;
		$base_day = (int) gmdate( 'j', $base_ts );

		// Walk from base_date forward, collecting dates in range.
		$current_ts = $base_ts;

		while ( $count < self::MAX_OCCURRENCES ) {
			$current_ymd = gmdate( 'Y-m-d', $current_ts );

			// Past the effective end? Stop.
			if ( $current_ymd > $effective_end ) {
				break;
			}

			// Within range? Collect.
			if ( $current_ymd >= $range_start ) {
				$dates[] = $current_ymd;
			}

			$current_ts = self::next_occurrence_ts( $current_ts, $recurrence_type, $base_day );

			if ( null === $current_ts ) {
				break;
			}

			++$count;
		}

		return $dates;
	}

	/**
	 * Advance from a base timestamp to find the first occurrence on or after a target date.
	 *
	 * Uses a calculated jump rather than iterating day-by-day for efficiency.
	 *
	 * @param int    $base_ts         Base Unix timestamp.
	 * @param string $recurrence_type One of daily, weekly, monthly.
	 * @param string $target_date     Target Y-m-d date.
	 * @return int|null Unix timestamp of the next occurrence, or null.
	 */
	private static function advance_date( $base_ts, $recurrence_type, $target_date ) {
		$target_ts = strtotime( $target_date );

		if ( false === $target_ts ) {
			return null;
		}

		$base_day   = (int) gmdate( 'j', $base_ts );
		$base_month = (int) gmdate( 'n', $base_ts );
		$base_year  = (int) gmdate( 'Y', $base_ts );

		switch ( $recurrence_type ) {
			case 'daily':
				$diff_days = (int) ceil( ( $target_ts - $base_ts ) / 86400 );
				$diff_days = max( 0, $diff_days );
				return gmmktime( 0, 0, 0, $base_month, $base_day + $diff_days, $base_year );

			case 'weekly':
				$diff_days = (int) ceil( ( $target_ts - $base_ts ) / 86400 );
				$diff_days = max( 0, $diff_days );
				// Round up to next multiple of 7.
				$remainder = $diff_days % 7;
				if ( $remainder > 0 ) {
					$diff_days += ( 7 - $remainder );
				}
				$candidate = gmmktime( 0, 0, 0, $base_month, $base_day + $diff_days, $base_year );
				// Verify candidate is on or after target.
				if ( gmdate( 'Y-m-d', $candidate ) < $target_date ) {
					$candidate = gmmktime( 0, 0, 0, $base_month, $base_day + $diff_days + 7, $base_year );
				}
				return $candidate;

			case 'monthly':
				$target_month = (int) gmdate( 'n', $target_ts );
				$target_year  = (int) gmdate( 'Y', $target_ts );
				$target_day   = (int) gmdate( 'j', $target_ts );

				// Total months offset from base.
				$month_diff = ( $target_year - $base_year ) * 12 + ( $target_month - $base_month );

				if ( $target_day > $base_day ) {
					++$month_diff;
				}

				$month_diff = max( 0, $month_diff );

				$new_month    = $base_month + $month_diff;
				$new_year     = $base_year;
				$actual_month = ( ( $new_month - 1 ) % 12 ) + 1;
				$actual_year  = $new_year + (int) floor( ( $new_month - 1 ) / 12 );

				// Clamp day to max days in the target month.
				$max_day   = (int) gmdate( 't', gmmktime( 0, 0, 0, $actual_month, 1, $actual_year ) );
				$event_day = min( $base_day, $max_day );

				$candidate = gmmktime( 0, 0, 0, $actual_month, $event_day, $actual_year );

				// Verify candidate is on or after target.
				if ( gmdate( 'Y-m-d', $candidate ) < $target_date ) {
					$actual_month += 1;
					if ( $actual_month > 12 ) {
						$actual_month = 1;
						++$actual_year;
					}
					$max_day   = (int) gmdate( 't', gmmktime( 0, 0, 0, $actual_month, 1, $actual_year ) );
					$event_day = min( $base_day, $max_day );
					$candidate = gmmktime( 0, 0, 0, $actual_month, $event_day, $actual_year );
				}

				return $candidate;

			default:
				return null;
		}
	}

	/**
	 * Get the next occurrence timestamp after the given one.
	 *
	 * @param int    $current_ts      Current timestamp.
	 * @param string $recurrence_type One of daily, weekly, monthly.
	 * @param int    $original_day    Original day-of-month from base date (used for monthly to avoid day drift).
	 * @return int|null Next occurrence timestamp or null.
	 */
	private static function next_occurrence_ts( $current_ts, $recurrence_type, $original_day = 0 ) {
		$day   = (int) gmdate( 'j', $current_ts );
		$month = (int) gmdate( 'n', $current_ts );
		$year  = (int) gmdate( 'Y', $current_ts );

		switch ( $recurrence_type ) {
			case 'daily':
				return gmmktime( 0, 0, 0, $month, $day + 1, $year );

			case 'weekly':
				return gmmktime( 0, 0, 0, $month, $day + 7, $year );

			case 'monthly':
				$next_month = $month + 1;
				$next_year  = $year;
				if ( $next_month > 12 ) {
					$next_month = 1;
					++$next_year;
				}
				// Use the original base day to avoid drift (e.g., Jan 31 -> Feb 28 -> Mar 31, not Mar 28).
				$use_day = $original_day > 0 ? $original_day : $day;
				$max_day = (int) gmdate( 't', gmmktime( 0, 0, 0, $next_month, 1, $next_year ) );
				$clamped = min( $use_day, $max_day );
				return gmmktime( 0, 0, 0, $next_month, $clamped, $next_year );

			default:
				return null;
		}
	}
}
