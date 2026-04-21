<?php
/**
 * Integration tests for the Notifications email filter system.
 *
 * Protects the per-event filter contract added in Bundle 41:
 *   - wb_listora_email_subject_{event}
 *   - wb_listora_email_content_{event}
 *   - wb_listora_email_from_name / wb_listora_email_from_address
 *   - wb_listora_email_logo_url / wb_listora_email_footer_text
 *
 * Regression guard: customers rely on these hooks to customize transactional
 * emails without forking template files. Removing or renaming any of these
 * breaks those customizations.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 */

namespace WBListora\Tests\Integration;

use WBListora\Workflow\Notifications;
use WP_UnitTestCase;

/**
 * @group listora
 * @group email-filters
 */
class EmailFiltersTest extends WP_UnitTestCase {

	/**
	 * Captured outbound mail during a test (fed by the wp_mail filter).
	 *
	 * @var array<int,array{to:string,subject:string,message:string,headers:array<int,string>}>
	 */
	private $captured_mail = array();

	/**
	 * @var Notifications
	 */
	private $notifications;

	/**
	 * Capture outbound mail by short-circuiting the pre_wp_mail filter.
	 */
	public function set_up() {
		parent::set_up();

		$this->captured_mail = array();
		$this->notifications = new Notifications();

		add_filter(
			'pre_wp_mail',
			function ( $return, $atts ) {
				$this->captured_mail[] = array(
					'to'      => is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'],
					'subject' => (string) $atts['subject'],
					'message' => (string) $atts['message'],
					'headers' => (array) $atts['headers'],
				);
				return true; // Short-circuit so wp_mail does not actually send.
			},
			10,
			2
		);
	}

	/**
	 * Invoke the private Notifications::send() method via reflection. Allows us
	 * to exercise the full filter chain without staging a full CPT lifecycle.
	 *
	 * @param string $to    Recipient.
	 * @param string $event Event key.
	 * @param array  $vars  Template variables.
	 */
	private function invoke_send( string $to, string $event, array $vars ): void {
		$ref = new \ReflectionMethod( Notifications::class, 'send' );
		$ref->setAccessible( true );
		$ref->invoke( $this->notifications, $to, $event, $vars );
	}

	/**
	 * Per-event subject filter should fire AFTER the global filter and win.
	 */
	public function test_subject_per_event_filter_wins_over_global() {
		add_filter(
			'wb_listora_email_subject',
			static fn( $subject ) => $subject . ' [GLOBAL]',
			10
		);
		add_filter(
			'wb_listora_email_subject_listing_approved',
			static fn( $subject ) => 'OVERRIDE: ' . $subject,
			10
		);

		$this->invoke_send(
			'user@example.com',
			'listing_approved',
			array( 'listing_title' => 'My Cafe', 'author_name' => 'Ann', 'listing_url' => 'https://x/l', 'dashboard_url' => 'https://x/d' )
		);

		$this->assertNotEmpty( $this->captured_mail );
		$subject = $this->captured_mail[0]['subject'];
		$this->assertStringStartsWith( 'OVERRIDE: ', $subject, 'Per-event filter must run after global.' );
		$this->assertStringContainsString( '[GLOBAL]', $subject, 'Global filter should still have run first.' );
	}

	/**
	 * Per-event content filter must receive the rendered HTML and can replace it.
	 */
	public function test_content_per_event_filter_can_override_body() {
		add_filter(
			'wb_listora_email_content_listing_approved',
			static fn() => '<p>REPLACED</p>',
			10
		);

		$this->invoke_send(
			'user@example.com',
			'listing_approved',
			array( 'listing_title' => 'X', 'author_name' => 'Y', 'listing_url' => '', 'dashboard_url' => '' )
		);

		$this->assertNotEmpty( $this->captured_mail );
		$this->assertSame( '<p>REPLACED</p>', $this->captured_mail[0]['message'] );
	}

	/**
	 * From-name + from-address filters should append a single `From:` header.
	 */
	public function test_from_headers_appended_when_filters_set() {
		add_filter(
			'wb_listora_email_from_name',
			static fn() => 'Directory Bot',
			10
		);
		add_filter(
			'wb_listora_email_from_address',
			static fn() => 'noreply@example.test',
			10
		);

		$this->invoke_send(
			'user@example.com',
			'listing_approved',
			array( 'listing_title' => 'X', 'author_name' => 'Y', 'listing_url' => '', 'dashboard_url' => '' )
		);

		$headers   = $this->captured_mail[0]['headers'];
		$from_line = array_values( array_filter( $headers, static fn( $h ) => 0 === stripos( $h, 'From:' ) ) );

		$this->assertCount( 1, $from_line, 'Exactly one From: header should be present.' );
		$this->assertSame( 'From: Directory Bot <noreply@example.test>', $from_line[0] );
	}

	/**
	 * Invalid from-address should silently skip the From: header (no mis-delivery).
	 */
	public function test_invalid_from_address_is_skipped() {
		add_filter(
			'wb_listora_email_from_name',
			static fn() => 'Bot',
			10
		);
		add_filter(
			'wb_listora_email_from_address',
			static fn() => 'not-an-email',
			10
		);

		$this->invoke_send(
			'user@example.com',
			'listing_approved',
			array( 'listing_title' => 'X', 'author_name' => 'Y', 'listing_url' => '', 'dashboard_url' => '' )
		);

		$headers   = $this->captured_mail[0]['headers'];
		$from_line = array_filter( $headers, static fn( $h ) => 0 === stripos( $h, 'From:' ) );
		$this->assertEmpty( $from_line, 'Invalid email must not produce a From: header.' );
	}

	/**
	 * Logo URL filter should reach the rendered HTML body (via parts/header.php).
	 */
	public function test_logo_url_filter_reaches_template() {
		add_filter(
			'wb_listora_email_logo_url',
			static fn() => 'https://example.test/logo.png',
			10
		);

		$this->invoke_send(
			'user@example.com',
			'listing_approved',
			array( 'listing_title' => 'X', 'author_name' => 'Y', 'listing_url' => '', 'dashboard_url' => '' )
		);

		$body = $this->captured_mail[0]['message'];
		$this->assertStringContainsString( 'https://example.test/logo.png', $body, 'Logo URL must appear in rendered email body.' );
		$this->assertStringContainsString( '<img', $body, 'Logo image tag should render when URL is set.' );
	}

	/**
	 * Footer text filter should replace the default branding line in parts/footer.php.
	 */
	public function test_footer_text_filter_replaces_default_line() {
		add_filter(
			'wb_listora_email_footer_text',
			static fn() => 'Unique footer sentence for override test.',
			10
		);

		$this->invoke_send(
			'user@example.com',
			'listing_approved',
			array( 'listing_title' => 'X', 'author_name' => 'Y', 'listing_url' => '', 'dashboard_url' => '' )
		);

		$body = $this->captured_mail[0]['message'];
		$this->assertStringContainsString( 'Unique footer sentence for override test.', $body );
		$this->assertStringNotContainsString( 'This email was sent by', $body, 'Default line must be replaced when override is non-empty.' );
	}

	/**
	 * Palette filter should flow through to the rendered email (colors array
	 * lands in the template via $colors['primary'], etc.).
	 */
	public function test_palette_filter_reaches_template() {
		add_filter(
			'wb_listora_email_palette',
			static function ( $palette ) {
				$palette['primary'] = '#BADA55';
				return $palette;
			},
			10
		);

		$this->invoke_send(
			'user@example.com',
			'listing_approved',
			array( 'listing_title' => 'X', 'author_name' => 'Y', 'listing_url' => '', 'dashboard_url' => '' )
		);

		$this->assertStringContainsString( '#BADA55', $this->captured_mail[0]['message'], 'Custom palette color must land in inline styles.' );
	}

	/**
	 * wb_listora_send_notification short-circuit should prevent wp_mail entirely.
	 */
	public function test_send_notification_short_circuit_prevents_delivery() {
		add_filter( 'wb_listora_send_notification', '__return_false', 10 );

		$this->invoke_send(
			'user@example.com',
			'listing_approved',
			array( 'listing_title' => 'X', 'author_name' => 'Y', 'listing_url' => '', 'dashboard_url' => '' )
		);

		$this->assertEmpty( $this->captured_mail, 'Short-circuit must stop delivery before wp_mail fires.' );
	}
}
