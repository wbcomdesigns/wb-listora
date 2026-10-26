<?php
/**
 * Every contact message is saved as a lead the owner, the admin and REST can read.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WBListora\Contact_Form;
use WBListora\Core\Leads;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group leads
 */
class LeadsTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Leads::create_table();
		do_action( 'rest_api_init' );
	}

	private function send( int $listing_id, string $email = 'visitor@example.test' ) {
		$request = new WP_REST_Request( 'POST', '/listora/v1/listings/' . $listing_id . '/contact-form' );
		$request->set_param( 'id', $listing_id );
		$request->set_param( 'name', 'Sam Visitor' );
		$request->set_param( 'email', $email );
		$request->set_param( 'phone', '+1 555 0100' );
		$request->set_param( 'message', 'Is it open on Sunday?' );
		$request->set_param( 'hp', '' );
		return Contact_Form::handle_submit( $request );
	}

	public function test_a_message_is_saved_and_only_its_owner_and_admins_read_it() {
		$owner   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$other   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $owner,
			)
		);

		$this->assertNotWPError( $this->send( $listing ) );

		$rows = Leads::query( array( 'owner_id' => $owner ) )['rows'];
		$this->assertCount( 1, $rows );
		$this->assertSame( '+1 555 0100', $rows[0]['phone'] );
		$this->assertSame( $listing, $rows[0]['listing_id'] );

		wp_set_current_user( $owner );
		$this->assertCount( 1, rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/leads' ) )->get_data() );

		wp_set_current_user( $other );
		$this->assertCount( 0, rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/leads' ) )->get_data() );
		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/leads/' . $rows[0]['id'] ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertGreaterThanOrEqual( 1, count( rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/leads' ) )->get_data() ) );
	}

	public function test_retention_and_privacy_erase() {
		global $wpdb;
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		$old     = Leads::record( $listing, array( 'name' => 'Old', 'email' => 'old@example.test', 'message' => 'x' ) );
		$wpdb->update( Leads::table(), array( 'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 years' ) ) ), array( 'id' => $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		Leads::record( $listing, array( 'name' => 'New', 'email' => 'new@example.test', 'message' => 'y' ) );

		$this->assertSame( 1, Leads::prune() );
		$this->assertNull( Leads::get( $old ) );

		$this->assertSame( 1, Leads::erase( 'new@example.test' )['items_removed'] );
		$this->assertSame( 0, Leads::query( array( 'listing_id' => $listing ) )['total'] );
	}

	public function test_leads_follow_the_listing_to_a_new_owner() {
		$old     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$new     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $old ) );
		$lead    = Leads::record( $listing, array( 'name' => 'A', 'email' => 'a@example.test', 'message' => 'x' ) );

		wp_update_post( array( 'ID' => $listing, 'post_author' => $new ) );

		$this->assertSame( 1, Leads::query( array( 'owner_id' => $new ) )['total'] );
		$this->assertSame( 0, Leads::query( array( 'owner_id' => $old ) )['total'] );
		$this->assertFalse( Leads::user_can_manage( Leads::get( $lead ), $old ) );
		$this->assertTrue( Leads::user_can_manage( Leads::get( $lead ), $new ) );
	}

	public function test_privacy_covers_messages_sent_under_another_address() {
		$member  = self::factory()->user->create( array( 'user_email' => 'member@example.test' ) );
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		wp_set_current_user( $member );
		Leads::record( $listing, array( 'name' => 'M', 'email' => 'other-address@example.test', 'message' => 'x' ) );
		wp_set_current_user( 0 );

		$this->assertCount( 1, Leads::export( 'member@example.test' )['data'] );
		$this->assertSame( 1, Leads::erase( 'member@example.test' )['items_removed'] );
	}

	public function test_leads_tab_stays_for_owners_whose_listings_are_unpublished() {
		$owner   = self::factory()->user->create();
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $owner ) );
		$this->send( $listing );
		wp_update_post( array( 'ID' => $listing, 'post_status' => 'draft' ) );
		$this->assertTrue( \WBListora\Core\Leads_Dashboard_Tab::visible_to( $owner ), 'Leads on a now-draft listing keep the tab.' );

		$paused_only = self::factory()->user->create();
		self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'pending', 'post_author' => $paused_only ) );
		$this->assertTrue( \WBListora\Core\Leads_Dashboard_Tab::visible_to( $paused_only ), 'An unpublished listing is enough.' );

		$binned = self::factory()->user->create();
		self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'trash', 'post_author' => $binned ) );
		$this->assertFalse( \WBListora\Core\Leads_Dashboard_Tab::visible_to( $binned ), 'Only binned listings and no leads: no tab.' );

		$this->assertFalse( \WBListora\Core\Leads_Dashboard_Tab::visible_to( self::factory()->user->create() ) );
	}

	public function test_deleting_an_account_erases_the_members_sent_messages() {
		global $wpdb;
		$owner   = self::factory()->user->create();
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => $owner ) );
		$member  = self::factory()->user->create( array( 'user_email' => 'sender@example.test' ) );
		wp_set_current_user( $member );
		$this->send( $listing, 'sender@example.test' );
		$this->assertSame( '1', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Leads::table() . ' WHERE email = %s', 'sender@example.test' ) ) );

		$request = new WP_REST_Request( 'DELETE', '/listora/v1/me' );
		$request->set_param( 'confirm', 'DELETE' );
		rest_do_request( $request );

		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Leads::table() . ' WHERE email = %s', 'sender@example.test' ) ), 'The erase right covers messages the member sent.' );
	}

	public function test_hard_deleting_a_listing_removes_its_messages() {
		global $wpdb;
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => self::factory()->user->create() ) );
		$this->send( $listing );

		wp_delete_post( $listing, true );

		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Leads::table() . ' WHERE listing_id = %d', $listing ) ) );
	}

	public function test_count_since_counts_the_stored_rows_a_dashboard_card_links_to() {
		global $wpdb;
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		$old     = Leads::record( $listing, array( 'name' => 'Old', 'email' => 'old@example.test', 'message' => 'x' ) );
		Leads::record( $listing, array( 'name' => 'New', 'email' => 'new@example.test', 'message' => 'y' ) );
		$wpdb->update( Leads::table(), array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ), array( 'id' => $old ) );

		$this->assertSame( 1, wb_listora_count_leads_since( gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
		$this->assertSame( 2, wb_listora_count_leads_since( gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS ) ) );
	}
}
