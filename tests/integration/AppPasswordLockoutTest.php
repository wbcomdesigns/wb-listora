<?php
/**
 * The app-password lockout counts one account once, however it is spelled.
 *
 * @package WBListora\Tests
 */

use WBListora\Auth\App_Credentials;

/**
 * @group listora
 */
class AppPasswordLockoutTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		add_filter( 'wp_is_application_passwords_available', '__return_true' );
		do_action( 'rest_api_init' );
	}

	private function attempt( string $who ): WP_REST_Response {
		$req = new WP_REST_Request( 'POST', '/listora/v1/auth/app-password' );
		$req->set_header( 'content-type', 'application/json' );
		$req->set_body( (string) wp_json_encode( array( 'username' => $who, 'password' => 'wrong-password' ) ) );
		return rest_do_request( $req );
	}

	public function test_login_and_email_spellings_share_one_lockout() {
		add_filter( 'wb_listora_rate_limit_bypass', '__return_true' );
		self::factory()->user->create( array( 'user_login' => 'lockme', 'user_email' => 'lockme@example.com' ) );

		// Alternate spellings: with separate buckets neither would reach 5 failures in 8 tries.
		foreach ( range( 1, 8 ) as $i ) {
			$this->attempt( 0 === $i % 2 ? 'lockme' : 'lockme@example.com' );
		}

		$this->assertGreaterThan( 0, App_Credentials::lockout_remaining( 'user:lockme' ), wp_json_encode( $this->attempt( 'lockme' )->get_data() ) );
		$this->assertSame( 429, $this->attempt( 'lockme@example.com' )->get_status() );
	}
}
