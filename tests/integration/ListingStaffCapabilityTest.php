<?php
/**
 * Who may act on another member's listing is decided by the plugin's capability.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ListingStaffCapabilityTest extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass(): void {
		// A Listora Moderator: the listing capability, nothing from core's editing set.
		add_role( 'qa_listing_staff', 'QA listing staff', array( 'read' => true, 'edit_others_listora_listings' => true, 'edit_listora_listings' => true ) );
		// A shop manager: core's edit_others_posts, no listing capability.
		add_role( 'qa_shop_staff', 'QA shop staff', array( 'read' => true, 'edit_others_posts' => true, 'delete_others_posts' => true ) );
	}

	public static function wpTearDownAfterClass(): void {
		remove_role( 'qa_listing_staff' );
		remove_role( 'qa_shop_staff' );
	}

	private function act( string $action, int $user, int $listing ): int {
		wp_set_current_user( $user );
		$request = new WP_REST_Request( 'POST', "/listora/v1/listings/{$listing}/{$action}" );
		$request->set_param( 'id', $listing );

		return rest_do_request( $request )->get_status();
	}

	private function members_listing(): int {
		return self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) );
	}

	public function test_a_listing_moderator_can_deactivate_a_members_listing() {
		$moderator = self::factory()->user->create( array( 'role' => 'qa_listing_staff' ) );

		$this->assertTrue( wb_listora_user_can_moderate_listings( $moderator ) );
		$this->assertTrue( wb_listora_user_can_edit_listing( $this->members_listing(), $moderator ) );
		$this->assertSame( 200, $this->act( 'deactivate', $moderator, $this->members_listing() ) );
	}

	public function test_a_shop_manager_cannot() {
		$shop = self::factory()->user->create( array( 'role' => 'qa_shop_staff' ) );

		$this->assertFalse( wb_listora_user_can_moderate_listings( $shop ) );
		$this->assertFalse( wb_listora_user_can_edit_listing( $this->members_listing(), $shop ) );
		$this->assertSame( 403, $this->act( 'deactivate', $shop, $this->members_listing() ) );
	}

	public function test_the_owner_and_an_administrator_still_can() {
		$listing = $this->members_listing();
		$owner   = (int) get_post_field( 'post_author', $listing );
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertTrue( wb_listora_user_can_edit_listing( $listing, $owner ) );
		$this->assertTrue( wb_listora_user_can_moderate_listings( $admin ) );
		$this->assertSame( 200, $this->act( 'deactivate', $admin, $this->members_listing() ) );
	}
}
