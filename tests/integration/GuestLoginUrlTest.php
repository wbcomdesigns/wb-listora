<?php
/**
 * A guest's Save, Claim, Report and Review go to the site's login page.
 *
 * @package WBListora\Tests
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;

/**
 * @group listora
 * @group login
 */
class GuestLoginUrlTest extends WP_UnitTestCase {

	public function test_the_address_is_core_login_url() {
		$this->assertSame( wp_login_url(), wb_listora_login_url() );
	}

	public function test_a_login_page_set_through_core_is_followed() {
		$set = static fn () => 'https://example.test/members/login/';
		add_filter( 'login_url', $set );
		$this->assertSame( 'https://example.test/members/login/', wb_listora_login_url() );
		remove_filter( 'login_url', $set );
	}

	public function test_the_listora_filter_overrides_it() {
		$set = static fn () => 'https://example.test/sign-in/';
		add_filter( 'wb_listora_login_url', $set );
		$this->assertSame( 'https://example.test/sign-in/', wb_listora_login_url() );
		remove_filter( 'wb_listora_login_url', $set );
	}
}
