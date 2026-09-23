<?php
/**
 * Free counts a lead, on every site, whatever is switched off.
 *
 * Card 10317861206: counting lived in Pro's Analytics, which does not load
 * while its toggle is off - so an owner who switched a contact form on and
 * left Analytics off got enquiries in their inbox and a Leads figure that
 * stayed 0. A Free-only site counted none at all, because Free had no listener
 * either, even though Free owns the contact form.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   lead-recording
 */

namespace WBListora\Tests\Integration;

use WBListora\Features\Analytics_Lite;
use WP_UnitTestCase;

/**
 * @group listora
 * @group lead-recording
 */
class LeadRecordingTest extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $listing = 0;

	public function set_up() {
		parent::set_up();

		$this->listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * @return int Leads recorded for the listing under test.
	 */
	private function leads(): int {
		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'analytics';

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(count),0) FROM {$table} WHERE listing_id = %d AND event_type = 'lead'", $this->listing ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function test_a_lead_is_recorded(): void {
		Analytics_Lite::record_lead( $this->listing );

		$this->assertSame( 1, $this->leads() );
	}

	/**
	 * Two enquiries from one person are two enquiries - the owner has two
	 * emails to answer. Views are IP-deduped; leads deliberately are not.
	 */
	public function test_leads_are_not_deduped_the_way_views_are(): void {
		Analytics_Lite::record_lead( $this->listing );
		Analytics_Lite::record_lead( $this->listing );

		$this->assertSame( 2, $this->leads() );
	}

	public function test_an_invalid_listing_records_nothing(): void {
		$this->assertFalse( Analytics_Lite::record_lead( 0 ) );
		$this->assertSame( 0, $this->leads() );
	}

	/**
	 * The stand-down that exists for page VIEWS must not swallow leads: that
	 * is exactly how they went missing.
	 */
	public function test_leads_are_recorded_even_when_pro_owns_view_recording(): void {
		add_filter( 'wb_listora_pro_owns_analytics', '__return_true' );

		$this->assertTrue( Analytics_Lite::pro_owns_recording() );

		Analytics_Lite::record_lead( $this->listing );

		$this->assertSame( 1, $this->leads(), 'Pro owning VIEW recording must not stop lead recording.' );

		remove_filter( 'wb_listora_pro_owns_analytics', '__return_true' );
	}

	/**
	 * Both forms feed the same counter, and the listeners are registered
	 * regardless of the view stand-down.
	 */
	public function test_both_form_hooks_are_listened_to(): void {
		add_filter( 'wb_listora_pro_owns_analytics', '__return_true' );
		Analytics_Lite::init();
		remove_filter( 'wb_listora_pro_owns_analytics', '__return_true' );

		$this->assertNotFalse( has_action( 'wb_listora_after_contact_form_submit', array( Analytics_Lite::class, 'handle_lead_submission' ) ) );
		$this->assertNotFalse( has_action( 'wb_listora_pro_after_submit_lead', array( Analytics_Lite::class, 'handle_lead_submission' ) ) );
	}

	public function test_recording_fires_an_action_for_integrations(): void {
		$fired = 0;
		add_action(
			'wb_listora_lead_recorded',
			static function () use ( &$fired ) {
				++$fired;
			}
		);

		Analytics_Lite::record_lead( $this->listing );

		$this->assertSame( 1, $fired );
	}
}
