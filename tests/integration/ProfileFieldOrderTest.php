<?php
/**
 * The profile form groups related fields: first and last name together, email next to the
 * password it needs, and the optional website last.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ProfileFieldOrderTest extends WP_UnitTestCase {

	public function test_profile_fields_are_grouped_in_reading_order() {
		$tpl = (string) file_get_contents( WB_LISTORA_PLUGIN_DIR . 'templates/blocks/user-dashboard/tab-profile.php' );
		preg_match_all( '/<input[^>]+name="(first_name|last_name|display_name|email|phone|current_password|website)"/', $tpl, $m );

		$this->assertSame(
			array( 'first_name', 'last_name', 'display_name', 'email', 'phone', 'current_password', 'website' ),
			$m[1]
		);
	}
}
