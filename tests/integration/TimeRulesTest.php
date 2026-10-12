<?php
/**
 * One time rule per kind of value: site-calendar days, UTC datetimes, wall times with an offset.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;

/**
 * @group listora
 */
class TimeRulesTest extends WP_UnitTestCase {

	public function tear_down() {
		update_option( 'timezone_string', '' );
		parent::tear_down();
	}

	public function test_site_date_follows_the_site_calendar_not_utc() {
		update_option( 'timezone_string', 'Pacific/Kiritimati' ); // UTC+14: almost always a day ahead of UTC.
		$this->assertSame( wp_date( 'Y-m-d' ), wb_listora_site_date() );
		$this->assertSame( wp_date( 'Y-m-d', time() - 7 * DAY_IN_SECONDS ), wb_listora_site_date( 7 ) );
	}

	public function test_wall_time_gets_the_site_offset() {
		update_option( 'timezone_string', 'America/New_York' );
		$date = date_create_immutable( '2026-01-15T09:00', wp_timezone() );
		$this->assertSame( '2026-01-15T09:00:00-05:00', $date->format( DATE_ATOM ) );
		$this->assertSame( '09:00', wp_date( 'H:i', wb_listora_local_timestamp( '2026-01-15T09:00' ) ) );
	}
}
