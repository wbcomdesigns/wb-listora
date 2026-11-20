<?php
/**
 * The reCAPTCHA v2 checkbox is a selectable provider, renders its widget, and is verified
 * as pass/fail (no v3 score).
 *
 * @package WBListora\Tests
 */

use WBListora\Captcha;

/**
 * @group listora
 */
class CaptchaV2Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		update_option(
			'wb_listora_settings',
			array(
				'captcha_provider'   => 'recaptcha_v2',
				'captcha_site_key'   => 'site123',
				'captcha_secret_key' => 'secret123',
			)
		);
		wb_listora_get_setting( null, null, true );
	}

	public function test_widget_renders_checkbox_and_provider_field() {
		ob_start();
		Captcha::render_widget( 'x' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'class="g-recaptcha"', $html );
		$this->assertStringContainsString( 'data-sitekey="site123"', $html );
		$this->assertStringContainsString( 'value="recaptcha_v2"', $html );
	}

	public function test_v2_ignores_score_and_fails_on_unsuccessful_response() {
		$respond = function ( $body ) {
			return function () use ( $body ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( $body ),
				);
			};
		};

		add_filter( 'pre_http_request', $respond( array( 'success' => true, 'score' => 0.1 ) ) );
		$this->assertTrue( Captcha::verify( 'tok', 'recaptcha_v2' ) );
		remove_all_filters( 'pre_http_request' );

		add_filter( 'pre_http_request', $respond( array( 'success' => false ) ) );
		$this->assertWPError( Captcha::verify( 'tok', 'recaptcha_v2' ) );
		remove_all_filters( 'pre_http_request' );
	}
}
