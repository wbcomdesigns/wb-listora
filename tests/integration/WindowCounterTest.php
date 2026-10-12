<?php
/**
 * A limit answers with the seconds left in its window, not the full window.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group rate-limit
 */
class WindowCounterTest extends WP_UnitTestCase {

	public function test_window_does_not_restart_on_each_hit() {
		wb_listora_window_counter( 'qa_window', HOUR_IN_SECONDS, true );
		$stored      = get_transient( 'qa_window' );
		$stored['t'] = time() - 600; // First hit ten minutes ago.
		set_transient( 'qa_window', $stored, HOUR_IN_SECONDS );

		$after = wb_listora_window_counter( 'qa_window', HOUR_IN_SECONDS, true );

		$this->assertSame( 2, $after['count'] );
		$this->assertEqualsWithDelta( 3000, $after['wait'], 5, 'Fifty minutes left, not a fresh hour.' );
		$this->assertSame( time() - 600, get_transient( 'qa_window' )['t'], 'Another hit keeps the window start.' );
	}

	public function test_a_counter_stored_before_2_0_0_still_counts() {
		set_transient( 'qa_window_old', 3, HOUR_IN_SECONDS );
		$this->assertSame( 3, wb_listora_window_counter( 'qa_window_old', HOUR_IN_SECONDS )['count'] );
	}
}
