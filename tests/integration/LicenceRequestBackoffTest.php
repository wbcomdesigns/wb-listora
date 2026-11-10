<?php
/**
 * Pressing Activate gets a real attempt even while the SDK is backing off a host it saw fail.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group licence
 */
class LicenceRequestBackoffTest extends WP_UnitTestCase {

	/** @var int */
	private $attempts = 0;

	public function set_up() {
		parent::set_up();
		if ( ! class_exists( '\EasyDigitalDownloads\Updater\Requests\API' ) ) {
			$this->markTestSkipped( 'Needs the bundled EDD SDK.' );
		}
		$this->attempts = 0;
		foreach ( array( WB_LISTORA_LICENCE_HOST, 'https://wbcomdesigns.com' ) as $host ) {
			update_option( 'eddsdk_failed_request_' . md5( $host ), time() + HOUR_IN_SECONDS, false );
		}
		add_filter(
			'pre_http_request',
			function () {
				++$this->attempts;
				return new WP_Error( 'down', 'down' );
			}
		);
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	public function test_a_background_call_respects_the_backoff() {
		wb_listora_licence_request( array( 'edd_action' => 'activate_license' ) );

		$this->assertSame( 0, $this->attempts );
	}

	public function test_a_user_initiated_call_is_sent() {
		wb_listora_licence_request( array( 'edd_action' => 'activate_license' ), true );

		$this->assertGreaterThan( 0, $this->attempts );
	}
}
