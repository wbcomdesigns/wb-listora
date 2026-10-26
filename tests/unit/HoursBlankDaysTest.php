<?php
/**
 * Days with no time and no closed / 24h flag are not hours.
 *
 * @package WBListora\Tests\Unit
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;

/**
 * @group listora
 */
class HoursBlankDaysTest extends WP_UnitTestCase {

	public function test_seven_blank_days_are_no_hours() {
		$hours = array_fill( 0, 7, array( 'open' => '', 'close' => '' ) );

		$this->assertSame( array(), wb_listora_normalize_hours( $hours ) );
	}

	public function test_set_days_are_kept_and_blank_ones_dropped() {
		$hours = array(
			0 => array( 'closed' => '1' ),
			1 => array( 'open' => '09:00', 'close' => '17:00' ),
			2 => array( 'open' => '', 'close' => '' ),
			3 => array( 'is_24h' => '1' ),
		);

		$this->assertSame( array( 0, 1, 3 ), array_column( wb_listora_normalize_hours( $hours ), 'day' ) );
	}
}
