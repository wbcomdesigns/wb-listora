<?php
/**
 * "Who can add listings": the owner picks the roles that hold
 * submit_listora_listing, including roles other plugins add (WooCommerce
 * Customer, Dokan Vendor), and the choice survives other saves and
 * re-activation.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Capabilities;

/**
 * @group listora
 */
class SubmissionRolesTest extends WP_UnitTestCase {

	private const CAP = Capabilities::CAP_SUBMIT_LISTING;

	public function set_up() {
		parent::set_up();
		add_role( 'customer', 'Customer', array( 'read' => true ) );
		( new Capabilities() )->add_caps();
	}

	public function tear_down() {
		remove_role( 'customer' );
		delete_option( 'wb_listora_settings' );
		( new Capabilities() )->add_caps();
		parent::tear_down();
	}

	private function save( array $input ): void {
		$old = get_option( 'wb_listora_settings', array() );
		update_option( 'wb_listora_settings', \WBListora\Admin\Settings_Page::sanitize( $input + (array) $old ) );
	}

	private function role_can( string $role ): bool {
		return ! empty( get_role( $role )->capabilities[ self::CAP ] );
	}

	public function test_ticking_a_plugin_role_lets_its_members_add_listings() {
		$this->assertFalse( $this->role_can( 'customer' ) );

		$this->save( array( 'submission_roles' => array( '', 'subscriber', 'customer' ) ) );

		$this->assertTrue( $this->role_can( 'customer' ) );
		$user = self::factory()->user->create_and_get( array( 'role' => 'customer' ) );
		$this->assertTrue( user_can( $user, self::CAP ) );
		$this->assertFalse( user_can( $user, 'upload_files' ), 'Members upload through listora/v1/media, never the Media Library.' );
	}

	public function test_unticking_a_role_revokes_it_and_administrator_always_stays() {
		$this->save( array( 'submission_roles' => array( '' ) ) );

		$this->assertFalse( $this->role_can( 'subscriber' ) );
		$this->assertFalse( $this->role_can( 'editor' ) );
		$this->assertTrue( $this->role_can( 'administrator' ) );
		$this->assertFalse( user_can( self::factory()->user->create( array( 'role' => 'subscriber' ) ), self::CAP ) );
	}

	public function test_unknown_roles_are_dropped() {
		$this->assertSame( array( 'subscriber', 'administrator' ), Capabilities::sanitize_submission_roles( array( 'subscriber', 'no-such-role', '<b>' ) ) );
	}

	public function test_saving_another_tab_keeps_a_cap_added_by_hand() {
		get_role( 'customer' )->add_cap( self::CAP );

		$this->save( array( 'moderation' => 'manual' ) );

		$this->assertTrue( $this->role_can( 'customer' ) );
	}

	public function test_the_form_shows_roles_that_hold_the_cap() {
		get_role( 'customer' )->add_cap( self::CAP );

		$this->assertContains( 'customer', Capabilities::submission_roles() );
		$this->assertContains( 'subscriber', Capabilities::submission_roles() );
	}

	public function test_reactivation_keeps_the_saved_choice() {
		$this->save( array( 'submission_roles' => array( 'customer' ) ) );

		( new Capabilities() )->add_caps();

		$this->assertFalse( $this->role_can( 'subscriber' ), 'Re-activation must not re-grant an unticked role.' );
		$this->assertTrue( $this->role_can( 'customer' ) );
	}

	public function test_reactivation_without_a_saved_choice_only_adds() {
		get_role( 'customer' )->add_cap( self::CAP );

		( new Capabilities() )->add_caps();

		$this->assertTrue( $this->role_can( 'customer' ), 'A cap added by hand survives re-activation.' );
		$this->assertTrue( $this->role_can( 'subscriber' ) );
	}

	public function test_reset_this_tab_restores_the_default_roles() {
		$this->save( array( 'submission_roles' => array( 'customer' ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'DELETE', '/' . WB_LISTORA_REST_NAMESPACE . '/settings' );
		$request->set_param( 'keys', array( 'submission_roles' ) );

		$this->assertSame( 200, rest_do_request( $request )->get_status() );

		$this->assertTrue( $this->role_can( 'subscriber' ) );
		$this->assertFalse( $this->role_can( 'customer' ) );
	}

	public function test_full_reset_restores_the_default_roles() {
		$this->save( array( 'submission_roles' => array( 'customer' ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'DELETE', '/' . WB_LISTORA_REST_NAMESPACE . '/settings' ) )->get_status() );

		$this->assertTrue( $this->role_can( 'subscriber' ) );
		$this->assertFalse( $this->role_can( 'customer' ) );
	}

	public function test_rest_settings_update_applies_the_roles() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'POST', '/' . WB_LISTORA_REST_NAMESPACE . '/settings' );
		$request->set_body_params( array( 'submission_roles' => array( 'customer' ) ) );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $this->role_can( 'customer' ) );
		$this->assertFalse( $this->role_can( 'subscriber' ) );
	}
}
