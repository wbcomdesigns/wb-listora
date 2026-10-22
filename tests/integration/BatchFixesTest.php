<?php
/**
 * Regression checks for the 2026-10-12 fix batch: Health Check tables, page
 * clamping, display name, file fields, card layouts, renewal quote and codes,
 * expiry reminder, email change, demo image owners, Services box errors.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class BatchFixesTest extends WP_UnitTestCase {

	public function test_health_check_lists_every_table_the_plugin_creates() {
		$required = ( new ReflectionClassConstant( \WBListora\Activator::class, 'REQUIRED_TABLES' ) )->getValue();
		$source   = file_get_contents( WB_LISTORA_PLUGIN_DIR . 'includes/class-activator.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading plugin source in a test.
		preg_match_all( '/CREATE TABLE \{\$prefix\}([a-z_]+)/', $source, $m );
		$this->assertGreaterThan( 10, count( $m[1] ) );
		foreach ( array_merge( $m[1], array( 'leads' ) ) as $table ) { // Leads creates its own table.
			$this->assertContains( $table, $required, "Health Check misses {$table}." );
		}
	}

	public function test_a_page_past_the_end_moves_to_the_last_page() {
		$state = array( 'paged' => 99, 'per_page' => 20, 'offset' => 1960 );
		$this->assertTrue( wb_listora_clamp_paged( $state, 45 ) );
		$this->assertSame( 3, $state['paged'] );
		$this->assertSame( 40, $state['offset'] );

		$state = array( 'paged' => 2, 'per_page' => 20, 'offset' => 20 );
		$this->assertFalse( wb_listora_clamp_paged( $state, 45 ), 'A real page is left alone.' );
		$this->assertFalse( wb_listora_clamp_paged( $state, 0 ), 'An empty list has no last page to move to.' );
	}

	public function test_profile_refuses_an_empty_display_name() {
		$user = self::factory()->user->create( array( 'display_name' => 'Ada Owner' ) );
		wp_set_current_user( $user );
		$request = new WP_REST_Request( 'PUT', '/listora/v1/dashboard/profile' );
		$request->set_body_params( array( 'display_name' => '  ' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Ada Owner', get_userdata( $user )->display_name );
	}

	public function test_a_file_field_shows_its_file_name() {
		$file  = self::factory()->attachment->create( array( 'post_mime_type' => 'image/png', 'file' => 'company-logo.png' ) );
		$field = new \WBListora\Core\Field( array( 'key' => 'company_logo', 'label' => 'Company Logo', 'type' => 'file' ) );

		$this->assertSame( 'company-logo.png', wb_listora_format_card_value( $field, $file, true ) );
		$this->assertSame( '', wb_listora_format_card_value( $field, 999999, true ), 'A missing file shows nothing, not a number.' );
	}

	/**
	 * A listing with no expiry date, logged in as its owner on a paid directory.
	 */
	private function owned_listing_on_a_paid_directory(): int {
		add_filter( 'wb_listora_expiration_enabled', '__return_true' );
		$owner = self::factory()->user->create();
		wp_set_current_user( $owner );
		return self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $owner ) );
	}

	public function test_a_listing_with_no_expiry_is_refused_with_its_own_code() {
		$id    = $this->owned_listing_on_a_paid_directory();
		$quote = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/listings/' . $id . '/renewal-quote' ) )->get_data();
		$renew = rest_do_request( new WP_REST_Request( 'POST', '/listora/v1/listings/' . $id . '/renew' ) );
		remove_filter( 'wb_listora_expiration_enabled', '__return_true' );

		$this->assertSame( 'listora_not_renewable', $quote['reason_code'] );
		$this->assertSame( '', $quote['expiry'] );
		$this->assertSame( 400, $renew->get_status() );
		$this->assertSame( 'listora_not_renewable', $renew->get_data()['code'], 'Not "too early": there is no date to wait for.' );
	}

	public function test_a_free_renewal_quote_still_carries_the_real_balance() {
		if ( ! wb_listora_credits_ready() ) {
			$this->markTestSkipped( 'Credits SDK not booted in this run.' );
		}
		$id = $this->owned_listing_on_a_paid_directory();
		update_post_meta( $id, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS ) );
		\Wbcom\Credits\Credits::topup_money( 'wb-listora', get_current_user_id(), 12 );
		$quote = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/listings/' . $id . '/renewal-quote' ) )->get_data();
		remove_filter( 'wb_listora_expiration_enabled', '__return_true' );

		$this->assertSame( 0, $quote['cost'] );
		$this->assertSame( 12.0, (float) $quote['balance'], 'The dialog prints this number; 0 told a member with credits they had none.' );
	}

	public function test_the_expiry_reminder_only_offers_renew_when_the_listing_can_be_renewed() {
		add_filter( 'wb_listora_expiration_enabled', '__return_true' );
		$defaults = \WBListora\Core\Listing_Type_Defaults::get_all();
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$registry->save_type( 'qa-classified', $defaults['classified'] );
		$registry->save_type( 'qa-event', $defaults['event'] );
		$registry->flush();
		$registry->init();

		$mails = array();
		// Keyed by subject: the test bootstrap boots the plugin twice, so each mail arrives twice.
		$grab  = static function ( $null, $atts ) use ( &$mails ) {
			$mails[ $atts['subject'] ] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $grab, 10, 2 );
		$owner = self::factory()->user->create();
		foreach ( array( 'qa-event', 'qa-classified' ) as $type ) {
			$id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $owner ) );
			wp_set_object_terms( $id, $type, 'listora_listing_type' );
			update_post_meta( $id, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
			do_action( 'wb_listora_listing_expiring', $id, 1 );
		}
		remove_filter( 'pre_wp_mail', $grab, 10 );
		remove_filter( 'wb_listora_expiration_enabled', '__return_true' );

		$mails = array_values( $mails );
		$this->assertCount( 2, $mails );
		$this->assertStringNotContainsString( 'Renew Listing', $mails[0]['message'], 'An event ends on its own date.' );
		$this->assertStringContainsString( 'Renew Listing', $mails[1]['message'] );
		$this->assertStringContainsString( 'expires tomorrow', $mails[0]['subject'], 'Not "in 1 days".' );
	}

	public function test_a_wrong_password_is_told_apart_from_a_missing_one() {
		$user = self::factory()->user->create( array( 'user_pass' => 'correct horse' ) );
		wp_set_current_user( $user );
		$codes = array();
		foreach ( array( '', 'not it' ) as $password ) {
			$request = new WP_REST_Request( 'PUT', '/listora/v1/dashboard/profile' );
			$request->set_body_params( array( 'display_name' => 'Ada', 'email' => 'new-address@example.test', 'current_password' => $password ) );
			$codes[] = rest_do_request( $request )->get_data()['code'];
		}

		$this->assertSame( array( 'listora_password_required', 'listora_password_incorrect' ), $codes );
	}

	public function test_leftover_demo_images_get_an_owner_and_other_images_are_left_alone() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$demo  = self::factory()->attachment->create( array( 'post_author' => 0 ) );
		$other = self::factory()->attachment->create( array( 'post_author' => 0 ) );
		update_post_meta( $demo, '_listora_demo_content', 1 );
		clean_post_cache( $demo );

		\WBListora\DB\Migrator::migrate_1_9_1_18();
		clean_post_cache( $demo );
		clean_post_cache( $other );

		$this->assertGreaterThan( 0, (int) get_post( $demo )->post_author );
		$this->assertLessThanOrEqual( $admin, (int) get_post( $demo )->post_author, 'The first administrator.' );
		$this->assertSame( 0, (int) get_post( $other )->post_author, 'Only demo-flagged images.' );
	}

	public function test_the_services_box_reports_a_service_that_was_refused() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id   = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		$veto = static fn () => new WP_Error( 'qa_veto', 'Service limit reached.' );
		add_filter( 'wb_listora_before_create_service', $veto );
		$_POST = array(
			'_wb_listora_services_metabox_nonce' => wp_create_nonce( 'wb_listora_services_metabox' ),
			'wb_listora_services'                => array( 'new' => array( array( 'title' => 'Catering' ) ) ),
		);
		\WBListora\Admin\Services_Metabox::save_post( $id );
		remove_filter( 'wb_listora_before_create_service', $veto );
		$_POST = array();

		ob_start();
		\WBListora\Admin\Services_Metabox::render( get_post( $id ) );
		$this->assertStringContainsString( 'Catering: Service limit reached.', ob_get_clean(), '"Listing updated" alone hid the refusal.' );
	}

	public function test_amounts_show_the_decimals_the_currency_is_charged_in() {
		$this->assertSame( 3, wb_listora_get_currency_format( 'KWD' )['decimals'], 'A currency added by filter: 1.234 was shown as 1.23.' );
		$this->assertSame( 0, wb_listora_get_currency_format( 'JPY' )['decimals'] );
		$this->assertSame( 2, wb_listora_get_currency_format( 'USD' )['decimals'] );
		$this->assertStringContainsString( '1.234', wb_listora_format_currency( 1.234, 'KWD', true ) );
	}

	public function test_listing_tags_have_a_menu_entry_in_the_categories_hub() {
		$hubs = new ReflectionMethod( \WBListora\Admin\Admin_Hubs::class, 'hubs' );

		$this->assertContains( 'edit-tags.php?taxonomy=listora_listing_tag&post_type=listora_listing', $hubs->invoke( null )['categories'][1] );
	}

	public function test_a_phone_or_email_field_under_its_own_key_is_not_treated_as_the_contact_card() {
		$source = file_get_contents( WB_LISTORA_PLUGIN_DIR . 'templates/blocks/listing-detail/tabs.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		preg_match( '/\$listora_field_is_surfaced = static function.*?\};/s', $source, $m );

		$this->assertStringNotContainsString( "array( 'phone', 'email' )", $m[0], 'Matched by type, seller_email and seller_phone never reached the listing page.' );
		$this->assertStringContainsString( '$listora_surfaced_keys', $m[0] );
	}
}
