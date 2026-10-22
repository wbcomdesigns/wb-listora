<?php
/**
 * Saving a listing from wp-admin without a listing type does not leave it published.
 *
 * @package WBListora\Tests
 */

use WBListora\Admin\Listing_Type_Metabox;

/**
 * @group listora
 */
class TypelessListingTest extends WP_UnitTestCase {

	public function test_admin_save_drafts_a_typeless_publish() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$post_id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );

		$_POST[ Listing_Type_Metabox::NONCE_NAME ] = wp_create_nonce( Listing_Type_Metabox::NONCE_ACTION );
		$_POST['wb_listora_listing_type']          = '';

		Listing_Type_Metabox::save_post( $post_id );

		$this->assertSame( 'draft', get_post_status( $post_id ) );
		$this->assertNotFalse( get_transient( 'wb_listora_type_required_' . $admin ) );
	}

	public function test_admin_save_keeps_a_typed_listing_published() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		$types   = \WBListora\Core\Listing_Type_Registry::instance()->get_all();

		$_POST[ Listing_Type_Metabox::NONCE_NAME ] = wp_create_nonce( Listing_Type_Metabox::NONCE_ACTION );
		$_POST['wb_listora_listing_type']          = reset( $types )->get_slug();

		Listing_Type_Metabox::save_post( $post_id );

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	public function test_new_listing_opens_with_the_type_in_the_link() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post  = get_post( self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'auto-draft' ) ) );
		$types = \WBListora\Core\Listing_Type_Registry::instance()->get_all();
		$slug  = reset( $types )->get_slug();

		$_GET['listing_type'] = $slug;
		Listing_Type_Metabox::register_metabox( $post );
		unset( $_GET['listing_type'] );

		$this->assertSame( $slug, \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $post->ID )->get_slug() );
	}

	public function test_an_unknown_type_in_the_link_assigns_nothing() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post = get_post( self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'auto-draft' ) ) );

		$_GET['listing_type'] = 'not-a-type';
		Listing_Type_Metabox::register_metabox( $post );
		unset( $_GET['listing_type'] );

		$this->assertNull( \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $post->ID ) );
	}
}
