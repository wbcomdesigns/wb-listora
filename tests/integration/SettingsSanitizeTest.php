<?php
/**
 * Regression tests for the settings sanitize callback (G9 + G9a).
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   settings
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Admin\Settings_Page;

/**
 * @group listora
 * @group settings
 */
class SettingsSanitizeTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// Start each test from a known state with every tracked boolean TRUE so
		// we can prove the sanitize callback doesn't reset them on partial POST.
		update_option(
			'wb_listora_settings',
			array_merge(
				wb_listora_get_default_settings(),
				array(
					'map_clustering'     => true,
					'map_search_on_drag' => true,
					'enable_expiration'  => true,
				)
			)
		);
	}

	/**
	 * G9 regression — saving ONE tab must not zero booleans on OTHER tabs.
	 *
	 * Simulates the General tab submission: $_POST contains only General-tab
	 * fields (per_page, listing_slug). Booleans on Maps tabs must keep
	 * their pre-save value of true.
	 */
	public function test_partial_tab_save_preserves_other_tab_booleans() {
		$input = array(
			'per_page'     => 25,
			'listing_slug' => 'my-listing',
		);

		$sanitized = Settings_Page::sanitize( $input );

		// General tab values were passed through.
		$this->assertSame( 25, $sanitized['per_page'] );
		$this->assertSame( 'my-listing', $sanitized['listing_slug'] );

		// Other-tab booleans survived.
		$this->assertTrue( $sanitized['map_clustering'],     'Maps tab boolean must survive saving General.' );
		$this->assertTrue( $sanitized['map_search_on_drag'], 'Maps tab boolean must survive saving General.' );
		$this->assertTrue( $sanitized['enable_expiration'],  'Submissions tab boolean must survive saving General.' );
	}

	/**
	 * G9a regression — unchecking a checkbox on the SUBMITTED tab must flip
	 * the boolean to false. Relies on the hidden value=0 sibling each
	 * checkbox template now renders.
	 */
	public function test_unchecking_checkbox_with_hidden_fallback_stores_false() {
		// Simulate Submissions tab submit: hidden=0 sent, checkbox unchecked.
		$input = array(
			'enable_guest_submission' => '0',
			'moderation'              => 'manual',
		);

		$sanitized = Settings_Page::sanitize( $input );

		$this->assertFalse( $sanitized['enable_guest_submission'] );

		// Other-tab booleans still preserved.
		$this->assertTrue( $sanitized['map_clustering'] );
	}

	/**
	 * Checking a previously-false checkbox must flip to true.
	 */
	public function test_checking_checkbox_stores_true() {
		// Start from false, simulate check.
		$existing                            = get_option( 'wb_listora_settings' );
		$existing['enable_guest_submission'] = false;
		update_option( 'wb_listora_settings', $existing );

		// Browser sends both hidden and checkbox — checkbox "1" wins by POST order.
		$input     = array( 'enable_guest_submission' => '1' );
		$sanitized = Settings_Page::sanitize( $input );

		$this->assertTrue( $sanitized['enable_guest_submission'] );
	}
}
