<?php
/**
 * Reporting a listing has to reach a human.
 *
 * Card 10317616906: `wb_listora_listing_reported` fired for releases with
 * nothing listening. The report was stored and the count incremented, and no
 * email, digest line or notice went anywhere - while the Reports column was
 * hidden by default, so even a curious admin saw no sign of it.
 *
 * Reporting is a promise that someone will look.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   listing-reported
 */

namespace WBListora\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group listing-reported
 */
class ListingReportedNotificationTest extends WP_UnitTestCase {

	/**
	 * Mail captured instead of sent.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $sent = array();

	/**
	 * Listing owner - must never be a recipient.
	 *
	 * @var int
	 */
	private $owner = 0;

	/**
	 * @var int
	 */
	private $moderator = 0;

	/**
	 * @var int
	 */
	private $listing = 0;

	public function set_up() {
		parent::set_up();

		$this->owner = self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'listing-owner@example.test',
			)
		);

		$this->moderator = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'staff-moderator@example.test',
			)
		);

		$this->listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => 'Reported Listing',
				'post_author' => $this->owner,
			)
		);

		$this->sent = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );

		rest_get_server();
	}

	public function tear_down() {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		delete_option( '_listora_listing_reports_' . $this->listing );

		// `wb_listora_get_setting()` memoizes in a function static, which the
		// transaction rollback between tests cannot clear. Without this, the
		// test that switches the event off silently disables it for every test
		// that runs after it. Documented invalidation: key null, force reload.
		delete_option( 'wb_listora_settings' );
		wb_listora_get_setting( null, null, true );

		parent::tear_down();
	}

	/**
	 * @param null|bool           $short_circuit Short-circuit value.
	 * @param array<string,mixed> $atts          wp_mail() arguments.
	 * @return bool Always true, so nothing is actually sent.
	 */
	public function capture_mail( $short_circuit, $atts ) {
		$this->sent[] = $atts;

		return true;
	}

	/**
	 * Every address any captured mail went to.
	 *
	 * @return string[]
	 */
	private function recipients(): array {
		$to = array();

		foreach ( $this->sent as $mail ) {
			$to = array_merge( $to, (array) $mail['to'] );
		}

		return $to;
	}

	/**
	 * Fire the hook the way the REST route does.
	 *
	 * @param int $count Report number.
	 */
	private function report( int $count = 1 ): void {
		do_action(
			'wb_listora_listing_reported',
			$this->listing,
			array(
				'user_id' => 0,
				'reason'  => 'spam',
				'details' => 'test',
				'status'  => 'open',
			),
			$count
		);
	}

	/**
	 * The defect, stated as a test: something has to listen.
	 */
	public function test_a_report_sends_mail_to_staff(): void {
		$this->report();

		$this->assertNotEmpty( $this->sent, 'A report must reach somebody.' );
		$this->assertContains( get_option( 'admin_email' ), $this->recipients() );
		$this->assertContains( 'staff-moderator@example.test', $this->recipients() );
	}

	/**
	 * Owner decision, recorded on the card: telling the owner hands a
	 * harassment vector to anyone filing reports to needle them, and warns a
	 * genuine bad actor that staff are looking.
	 */
	public function test_the_listing_owner_is_never_told(): void {
		$this->report();

		$this->assertNotContains( 'listing-owner@example.test', $this->recipients() );
	}

	public function test_the_subject_names_the_listing(): void {
		$this->report();

		$this->assertStringContainsString( 'Reported Listing', (string) $this->sent[0]['subject'] );
	}

	/**
	 * Which report numbers actually produced a notification.
	 *
	 * Counting raw wp_mail calls would measure the number of recipients (and,
	 * in this harness, the number of bootstrapped listener instances) rather
	 * than the throttle. What the throttle promises is WHICH reports notify.
	 *
	 * @param int $upto Highest report number to walk.
	 * @return int[] Report numbers that sent something.
	 */
	private function reports_that_notified( int $upto ): array {
		$notified = array();

		foreach ( range( 1, $upto ) as $count ) {
			$before = count( $this->sent );
			$this->report( $count );

			if ( count( $this->sent ) > $before ) {
				$notified[] = $count;
			}
		}

		return $notified;
	}

	/**
	 * A brigaded listing must not send one email per report.
	 */
	public function test_repeat_reports_are_throttled(): void {
		$this->assertSame( array( 1, 5, 10 ), $this->reports_that_notified( 12 ) );
	}

	public function test_the_throttle_is_filterable(): void {
		add_filter( 'wb_listora_listing_report_notify_interval', static fn() => 3 );

		$this->assertSame( array( 1, 3, 6 ), $this->reports_that_notified( 6 ) );
	}

	public function test_recipients_are_filterable(): void {
		add_filter( 'wb_listora_listing_report_recipients', static fn() => array( 'abuse-desk@example.test' ) );

		$this->report();

		$this->assertNotEmpty( $this->sent );
		$this->assertSame( array( 'abuse-desk@example.test' ), array_values( array_unique( $this->recipients() ) ) );
	}

	/**
	 * The event has to be switchable off like every other notification.
	 */
	public function test_the_event_can_be_switched_off(): void {
		$settings                                      = (array) get_option( 'wb_listora_settings', array() );
		$settings['notifications']['listing_reported'] = 0;
		update_option( 'wb_listora_settings', $settings );
		wb_listora_get_setting( null, null, true );

		$this->report();

		$this->assertEmpty( $this->sent, 'Switching the event off must stop the mail.' );
	}

	/**
	 * A notified admin used to land on a listings screen showing no sign of the
	 * report, because the column was hidden until they opened Screen Options.
	 */
	public function test_the_reports_column_is_visible_by_default(): void {
		$screen = \WP_Screen::get( 'edit-listora_listing' );
		$hidden = ( new \WBListora\Admin\Listing_Columns() )->default_hidden_columns( array(), $screen );

		$this->assertNotContains( 'listora_reports', $hidden );
	}

	/**
	 * End to end through the real route, not just the hook.
	 */
	public function test_the_rest_route_triggers_the_mail(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$request = new WP_REST_Request( 'POST', '/listora/v1/listings/' . $this->listing . '/report' );
		$request->set_param( 'reason', 'spam' );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $this->sent );
		$this->assertNotContains( 'listing-owner@example.test', $this->recipients() );
	}
}
