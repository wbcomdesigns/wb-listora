<?php
/**
 * Unit tests for WBListora\Core\Recurrence.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;
use WBListora\Core\Recurrence;
use WBListora\Core\Meta_Handler;

/**
 * @group listora
 * @group recurrence
 */
class RecurrenceTest extends WP_UnitTestCase {

	/**
	 * Helper: create a listing post and set its recurrence meta.
	 *
	 * @param array $meta Associative array of meta keys (without prefix) to values.
	 * @return int Post ID.
	 */
	private function create_listing_with_meta( array $meta ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
			)
		);

		foreach ( $meta as $key => $value ) {
			Meta_Handler::set_value( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * Non-recurring listing should return null.
	 */
	public function test_get_next_occurrence_returns_null_for_non_recurring() {
		$post_id = $this->create_listing_with_meta(
			array(
				'recurrence_type' => 'none',
				'start_date'      => gmdate( 'Y-m-d' ),
			)
		);

		$this->assertNull( Recurrence::get_next_occurrence( $post_id ) );
	}

	/**
	 * Daily recurrence with a past start date should return today or tomorrow.
	 */
	public function test_daily_recurrence_returns_today_or_tomorrow() {
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		$post_id = $this->create_listing_with_meta(
			array(
				'recurrence_type' => 'daily',
				'start_date'      => $yesterday,
			)
		);

		$next = Recurrence::get_next_occurrence( $post_id );

		$this->assertNotNull( $next, 'Daily recurrence should return a date.' );

		// The next occurrence must be today or in the future.
		$this->assertGreaterThanOrEqual(
			gmdate( 'Y-m-d' ),
			$next,
			'Next daily occurrence should be on or after today.'
		);
	}

	/**
	 * Weekly recurrence should return the same weekday as the base date.
	 */
	public function test_weekly_recurrence_returns_correct_weekday() {
		// Start date two weeks ago.
		$base      = strtotime( '-14 days' );
		$base_date = gmdate( 'Y-m-d', $base );
		$base_dow  = (int) gmdate( 'w', $base ); // Day of week (0=Sun, 6=Sat).

		$post_id = $this->create_listing_with_meta(
			array(
				'recurrence_type' => 'weekly',
				'start_date'      => $base_date,
			)
		);

		$next = Recurrence::get_next_occurrence( $post_id );
		$this->assertNotNull( $next );

		$next_dow = (int) gmdate( 'w', strtotime( $next ) );
		$this->assertSame(
			$base_dow,
			$next_dow,
			'Weekly recurrence must land on the same day of the week.'
		);
	}

	/**
	 * Monthly recurrence on the 31st should clamp to shorter months.
	 */
	public function test_monthly_recurrence_handles_month_end_clamping() {
		// Use Jan 31 of the current year as a base date in the past.
		$year      = (int) gmdate( 'Y' ) - 1;
		$base_date = "{$year}-01-31";

		$post_id = $this->create_listing_with_meta(
			array(
				'recurrence_type' => 'monthly',
				'start_date'      => $base_date,
			)
		);

		$next = Recurrence::get_next_occurrence( $post_id );
		$this->assertNotNull( $next );

		$next_day       = (int) gmdate( 'j', strtotime( $next ) );
		$next_month     = (int) gmdate( 'n', strtotime( $next ) );
		$next_year      = (int) gmdate( 'Y', strtotime( $next ) );
		$max_day_in_month = (int) gmdate( 't', gmmktime( 0, 0, 0, $next_month, 1, $next_year ) );

		// The day should be <= 31 and also <= max days in that month.
		$this->assertLessThanOrEqual(
			$max_day_in_month,
			$next_day,
			'Monthly recurrence should clamp to the last day of shorter months.'
		);
	}

	/**
	 * Recurrence with an end date in the past should return null.
	 */
	public function test_expired_recurrence_returns_null() {
		$post_id = $this->create_listing_with_meta(
			array(
				'recurrence_type' => 'daily',
				'start_date'      => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
				'recurrence_end'  => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
			)
		);

		$this->assertNull(
			Recurrence::get_next_occurrence( $post_id ),
			'Expired recurrence should return null.'
		);
	}

	/**
	 * get_occurrences should return the correct dates within a date range.
	 */
	public function test_get_occurrences_returns_correct_dates_for_month_range() {
		// Daily recurrence starting on the 1st of next month.
		$start = gmdate( 'Y-m-01', strtotime( '+1 month' ) );

		$post_id = $this->create_listing_with_meta(
			array(
				'recurrence_type' => 'daily',
				'start_date'      => $start,
			)
		);

		$range_start = $start;
		$range_end   = gmdate( 'Y-m-t', strtotime( $start ) ); // Last day of that month.

		$occurrences = Recurrence::get_occurrences( $post_id, $range_start, $range_end );

		// Number of days in the month.
		$days_in_month = (int) gmdate( 't', strtotime( $start ) );

		$this->assertIsArray( $occurrences );
		$this->assertCount(
			$days_in_month,
			$occurrences,
			"Daily recurrence should produce {$days_in_month} occurrences for the month."
		);

		// First and last dates should match the range boundaries.
		$this->assertSame( $range_start, $occurrences[0] );
		$this->assertSame( $range_end, end( $occurrences ) );
	}
}
