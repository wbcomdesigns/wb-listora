<?php
/**
 * One job list: arming, the stale / changed checks, one-off jobs, clearing.
 *
 * @package WBListora\Tests
 */

use WBListora\Workflow\Cron_Scheduler;

/**
 * @group listora
 * @group cron
 */
class CronJobsTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// arm_jobs() runs only on cron, wp-admin and WP-CLI requests.
		set_current_screen( 'dashboard' );
		Cron_Scheduler::unschedule_all_jobs( array( 'wb_listora_test_once' ) );
	}

	public function tear_down() {
		remove_all_filters( Cron_Scheduler::JOBS_FILTER );
		Cron_Scheduler::unschedule_all_jobs( array( 'wb_listora_test_once' ) );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function add_test_job(): void {
		add_filter(
			Cron_Scheduler::JOBS_FILTER,
			static function ( $jobs ) {
				$jobs['wb_listora_test_addon_job'] = array(
					'schedule' => 'hourly',
					'group'    => 'wb-listora-test',
				);
				$jobs['']                          = array( 'schedule' => 'daily' );
				$jobs['wb_listora_test_bad']       = array( 'schedule' => 'every-second' );
				return $jobs;
			}
		);
	}

	public function test_the_list_holds_free_jobs_and_normalises_additions() {
		$this->add_test_job();
		$jobs = Cron_Scheduler::jobs();

		foreach ( array( 'wb_listora_check_expirations', 'wb_listora_draft_reminder_cron', 'wb_listora_review_reminder_cron', 'wb_listora_daily_cleanup', 'wb_listora_expire_featured', 'wb_listora_cleanup_unverified_listings', 'wb_listora_prune_email_log' ) as $hook ) {
			$this->assertArrayHasKey( $hook, $jobs );
			$this->assertSame( Cron_Scheduler::GROUP, $jobs[ $hook ]['group'] );
		}
		$this->assertSame( 'wb-listora-test', $jobs['wb_listora_test_addon_job']['group'] );
		$this->assertArrayNotHasKey( '', $jobs, 'An empty hook is dropped.' );
		$this->assertSame( 'daily', $jobs['wb_listora_test_bad']['schedule'], 'An unknown schedule falls back to daily.' );
	}

	public function test_arming_schedules_every_job_then_waits_until_something_changes() {
		Cron_Scheduler::arm_jobs();
		foreach ( Cron_Scheduler::jobs() as $hook => $job ) {
			$this->assertTrue( Cron_Scheduler::has_scheduled( $hook, $job['group'] ), "$hook is armed." );
		}

		// Fresh and unchanged: a job removed by hand is not re-armed by an admin page.
		Cron_Scheduler::unschedule( 'wb_listora_daily_cleanup' );
		Cron_Scheduler::arm_jobs();
		$this->assertFalse( Cron_Scheduler::has_scheduled( 'wb_listora_daily_cleanup' ), 'No check while the list is unchanged and fresh.' );

		// The list changed (a feature switched on): everything is checked again.
		$this->add_test_job();
		Cron_Scheduler::arm_jobs();
		$this->assertTrue( Cron_Scheduler::has_scheduled( 'wb_listora_test_addon_job', 'wb-listora-test' ) );
		$this->assertTrue( Cron_Scheduler::has_scheduled( 'wb_listora_daily_cleanup' ), 'A changed list re-arms the missing job.' );

		// Stale: a day old on wp-admin means check again.
		Cron_Scheduler::unschedule( 'wb_listora_daily_cleanup' );
		$state       = get_option( Cron_Scheduler::ARMED_OPTION );
		$state['at'] = time() - DAY_IN_SECONDS - 1;
		update_option( Cron_Scheduler::ARMED_OPTION, $state );
		Cron_Scheduler::arm_jobs();
		$this->assertTrue( Cron_Scheduler::has_scheduled( 'wb_listora_daily_cleanup' ) );
	}

	public function test_a_visitor_request_never_arms() {
		set_current_screen( 'front' );
		Cron_Scheduler::arm_jobs();
		$this->assertFalse( Cron_Scheduler::has_scheduled( 'wb_listora_check_expirations' ) );
		$this->assertFalse( get_option( Cron_Scheduler::ARMED_OPTION ) );
	}

	public function test_clearing_removes_every_job_group_and_one_off() {
		$this->add_test_job();
		Cron_Scheduler::arm_jobs();
		Cron_Scheduler::schedule_single( 'wb_listora_test_once', array( 42 ), 300 );

		Cron_Scheduler::unschedule_all_jobs( array( 'wb_listora_test_once' ) );

		foreach ( Cron_Scheduler::jobs() as $hook => $job ) {
			$this->assertFalse( Cron_Scheduler::has_scheduled( $hook, $job['group'] ), "$hook is cleared." );
		}
		$this->assertFalse( wp_next_scheduled( 'wb_listora_test_once', array( 42 ) ), 'A one-off with args is cleared too.' );
		if ( function_exists( 'as_next_scheduled_action' ) && did_action( 'action_scheduler_init' ) ) {
			$this->assertFalse( as_next_scheduled_action( 'wb_listora_test_once', array( 42 ), Cron_Scheduler::GROUP ) );
		}
		$this->assertFalse( get_option( Cron_Scheduler::ARMED_OPTION ), 'The next request re-arms.' );
	}

	public function test_a_one_off_job_is_queued_once() {
		$this->assertTrue( Cron_Scheduler::schedule_single( 'wb_listora_test_once', array( 7 ), 120 ) );
		$this->assertTrue( Cron_Scheduler::schedule_single( 'wb_listora_test_once', array( 7 ), 120 ) );

		if ( function_exists( 'as_get_scheduled_actions' ) && did_action( 'action_scheduler_init' ) ) {
			$pending = as_get_scheduled_actions(
				array(
					'hook'   => 'wb_listora_test_once',
					'args'   => array( 7 ),
					'status' => 'pending',
				),
				'ids'
			);
			$this->assertCount( 1, $pending );
		} else {
			$this->assertNotFalse( wp_next_scheduled( 'wb_listora_test_once', array( 7 ) ) );
		}
	}
}
