<?php
/**
 * Every time Listora shows goes through one formatter, in the site's timezone.
 *
 * mysql2date() and date_i18n() showed stored UTC as local time, so the
 * Transactions screen read 12:55 am for a row made at 5:55 pm the day
 * before in Los Angeles (Zoho #42411).
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class DateLabelTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/Los_Angeles' );
	}

	public function tear_down() {
		update_option( 'timezone_string', '' );
		parent::tear_down();
	}

	public function test_a_stored_utc_string_is_shown_in_site_time() {
		$this->assertSame( '2026-10-27 17:55', wb_listora_date_label( '2026-10-28 00:55:24', '', 'Y-m-d H:i' ) );
	}

	public function test_a_unix_timestamp_is_shown_in_site_time() {
		$this->assertSame( '2026-10-27 17:55', wb_listora_date_label( (int) strtotime( '2026-10-28 00:55:24 UTC' ), '', 'Y-m-d H:i' ) );
	}

	public function test_an_iso_value_keeps_its_own_offset() {
		$this->assertSame( '2026-10-27 17:55', wb_listora_date_label( '2026-10-28T00:55:24Z', '', 'Y-m-d H:i' ) );
	}

	public function test_no_time_gives_the_fallback() {
		foreach ( array( '', null, '0000-00-00 00:00:00', 'not a date', 0 ) as $value ) {
			$this->assertSame( '—', wb_listora_date_label( $value ) );
		}
	}

	public function test_a_site_can_show_each_member_their_own_timezone() {
		$filter = static fn () => new DateTimeZone( 'Asia/Kolkata' );
		add_filter( 'wb_listora_display_timezone', $filter );
		$this->assertSame( '2026-10-28 06:25', wb_listora_date_label( '2026-10-28 00:55:24', '', 'Y-m-d H:i' ) );
		remove_filter( 'wb_listora_display_timezone', $filter );
	}
}
