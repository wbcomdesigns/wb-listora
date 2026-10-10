<?php
/**
 * Recurring jobs are checked only in cron, wp-admin and WP-CLI, never on an
 * ordinary page, REST or AJAX request (each check is a database query).
 *
 * @package WBListora\Tests
 */

namespace WBListora\Tests\Integration;

use WBListora\Workflow\Cron_Scheduler;
use WP_UnitTestCase;

/**
 * @group listora
 * @covers \WBListora\Workflow\Cron_Scheduler::schedule_recurring
 */
class CronSchedulingGateTest extends WP_UnitTestCase {

	/**
	 * Ordinary request: no queries, nothing armed. Cron runner: armed.
	 *
	 * @return void
	 */
	public function test_schedules_are_checked_only_in_cron_context(): void {
		$hook = 'wb_listora_qa_gate_probe';
		as_unschedule_all_actions( $hook );
		global $wpdb;

		$before = $wpdb->num_queries;
		Cron_Scheduler::schedule_recurring( 'daily', $hook );
		$this->assertSame( $before, $wpdb->num_queries, 'No database work on an ordinary request.' );
		$this->assertFalse( (bool) as_next_scheduled_action( $hook ) );

		add_filter( 'wp_doing_cron', '__return_true' );
		Cron_Scheduler::schedule_recurring( 'daily', $hook );
		remove_filter( 'wp_doing_cron', '__return_true' );
		$this->assertNotFalse( as_next_scheduled_action( $hook ), 'The cron runner arms it.' );
		as_unschedule_all_actions( $hook );
	}
}
