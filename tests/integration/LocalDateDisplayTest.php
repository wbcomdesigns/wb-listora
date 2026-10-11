<?php
/**
 * Stored listing dates are local wall time and display unchanged in any timezone.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Field;

/**
 * @group listora
 */
class LocalDateDisplayTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
	}

	public function test_dates_and_times_show_as_entered_west_of_utc() {
		$datetime = new Field( array( 'key' => 'start_date', 'label' => 'Start', 'type' => 'datetime' ) );
		$date     = new Field( array( 'key' => 'day', 'label' => 'Day', 'type' => 'date' ) );

		$this->assertSame( '2026-11-01 09:00', wb_listora_format_card_value( $datetime, '2026-11-01T09:00', true ) );
		$this->assertSame( '2026-12-01', wb_listora_format_card_value( $date, '2026-12-01', true ), 'A date must not slip to the day before.' );
		$this->assertFalse( wb_listora_local_timestamp( 'not a date' ) );
	}
}
