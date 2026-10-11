<?php
/**
 * Bespoke limiters say when to retry, so Retry-After reaches the client.
 *
 * @package WBListora\Tests
 */

use WBListora\Auth\App_Credentials;
use WBListora\Rate_Limiter;

/**
 * @group listora
 * @group rate-limit
 */
class RetryAfterTest extends WP_UnitTestCase {

	public function test_sign_in_lockout_reports_the_seconds_left() {
		$bucket = 'user:retry-after-test';
		$this->assertSame( 0, App_Credentials::lockout_remaining( $bucket ), 'No failures, no lockout.' );

		for ( $i = 0; $i < App_Credentials::MAX_FAILURES; $i++ ) {
			App_Credentials::record_failure( $bucket );
		}

		$wait = App_Credentials::lockout_remaining( $bucket );
		$this->assertGreaterThan( 0, $wait );
		$this->assertLessThanOrEqual( App_Credentials::FAILURE_WINDOW, $wait );
		$this->assertTrue( App_Credentials::is_locked_out( $bucket ) );
	}

	public function test_header_is_sent_for_both_listora_code_prefixes() {
		foreach ( array( 'listora_rate_limit', 'wb_listora_too_many_attempts' ) as $code ) {
			$response = Rate_Limiter::retry_after_header(
				rest_convert_error_to_response( new WP_Error( $code, 'x', array( 'status' => 429, 'retry_after' => 42 ) ) )
			);
			$this->assertSame( '42', $response->get_headers()['Retry-After'] ?? null, $code );
		}

		$other = Rate_Limiter::retry_after_header(
			rest_convert_error_to_response( new WP_Error( 'someone_else', 'x', array( 'status' => 429, 'retry_after' => 42 ) ) )
		);
		$this->assertArrayNotHasKey( 'Retry-After', $other->get_headers(), 'Another plugin\'s 429 is left alone.' );
	}
}
