<?php
/**
 * Members' listing photos stay out of the admin's Media Library: members get no
 * core upload_files, upload through listora/v1/media, and marked photos are left
 * out of library queries unless looked up by listing or ID.
 *
 * @package WBListora\Tests
 */

use WBListora\Plugin;

/**
 * @group listora
 */
class MemberMediaTest extends WP_UnitTestCase {

	private function attachment( int $author, int $parent = 0 ): int {
		return self::factory()->attachment->create(
			array(
				'post_author'    => $author,
				'post_parent'    => $parent,
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	private function library_ids( array $args = array() ): array {
		$args = apply_filters( 'ajax_query_attachments_args', $args + array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'posts_per_page' => -1 ) );
		return array_map( 'intval', ( new WP_Query( $args ) )->posts );
	}

	public function test_members_do_not_get_upload_files() {
		foreach ( array( 'subscriber', 'contributor' ) as $role ) {
			$user = self::factory()->user->create( array( 'role' => $role ) );
			$this->assertTrue( user_can( $user, 'submit_listora_listing' ) );
			$this->assertFalse( user_can( $user, 'upload_files' ), $role );
		}
	}

	public function test_old_grant_is_an_opt_in() {
		add_filter( 'wb_listora_grant_upload_files_to_submitters', '__return_true' );
		$this->assertTrue( user_can( self::factory()->user->create( array( 'role' => 'subscriber' ) ), 'upload_files' ) );
		remove_filter( 'wb_listora_grant_upload_files_to_submitters', '__return_true' );
	}

	public function test_member_cannot_post_to_core_media() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, rest_do_request( new WP_REST_Request( 'POST', '/wp/v2/media' ) )->get_status() );
	}

	public function test_listora_media_route_gates() {
		$request = new WP_REST_Request( 'POST', '/' . WB_LISTORA_REST_NAMESPACE . '/media' );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );

		remove_role( 'listora_none' );
		add_role( 'listora_none', 'None', array( 'read' => true ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'listora_none' ) ) );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
		remove_role( 'listora_none' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_FILES = array(
			'file' => array(
				'name'     => 'menu.php.jpg',
				'type'     => 'image/jpeg',
				'tmp_name' => __FILE__,
				'error'    => 0,
				'size'     => filesize( __FILE__ ),
			),
		);
		$request->set_file_params( $_FILES );
		$this->assertSame( 400, rest_do_request( $request )->get_status(), 'A PHP file named .jpg is refused.' );
		$_FILES = array();
	}

	public function test_admin_library_leaves_member_photos_out() {
		$member  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $member ) );
		$photo   = $this->attachment( $member, $listing );
		$own     = $this->attachment( $admin );
		wb_listora_mark_member_media( $photo );
		wp_set_current_user( $admin );

		$this->assertNotContains( $photo, $this->library_ids() );
		$this->assertContains( $own, $this->library_ids() );
		$this->assertContains( $photo, $this->library_ids( array( 'post_parent' => $listing ) ), 'Uploaded to this listing still lists it.' );
		$this->assertContains( $photo, $this->library_ids( array( 'post__in' => array( $photo ) ) ) );

		$rest = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media' ) )->get_data();
		$this->assertNotContains( $photo, wp_list_pluck( $rest, 'id' ) );

		add_filter( 'wb_listora_hide_member_media', '__return_false' );
		$this->assertContains( $photo, $this->library_ids() );
		remove_filter( 'wb_listora_hide_member_media', '__return_false' );
	}

	public function test_media_library_view_lists_only_member_photos() {
		global $pagenow;
		$member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$photo  = $this->attachment( $member );
		$own    = $this->attachment( 1 );
		wb_listora_mark_member_media( $photo );
		set_current_screen( 'upload' );
		$pagenow                       = 'upload.php';
		$_GET['listora_member_media'] = '1';

		$query = new WP_Query();
		$GLOBALS['wp_the_query'] = $query;
		$ids   = $query->query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids' ) );

		unset( $_GET['listora_member_media'] );
		$pagenow = 'index.php';
		set_current_screen( 'front' );
		$this->assertContains( $photo, $ids );
		$this->assertNotContains( $own, $ids );
	}

	public function test_upgrade_flags_existing_member_photos_and_takes_back_the_cap() {
		get_role( 'subscriber' )->add_cap( 'upload_files' );
		$member   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$editor   = self::factory()->user->create( array( 'role' => 'editor' ) );
		$listing  = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $member ) );
		$photo    = $this->attachment( $member, $listing );
		$staff    = $this->attachment( $editor, $listing );
		$review   = $this->attachment( $member );
		update_post_meta( $review, '_listora_review_photo', 5 );

		\WBListora\DB\Migrator::migrate_1_9_1_22();
		wp_cache_delete( $photo, 'post_meta' );
		wp_cache_delete( $staff, 'post_meta' );
		wp_cache_delete( $review, 'post_meta' );

		$this->assertSame( '1', get_post_meta( $photo, Plugin::MEMBER_MEDIA_META, true ) );
		$this->assertSame( '1', get_post_meta( $review, Plugin::MEMBER_MEDIA_META, true ) );
		$this->assertSame( '', get_post_meta( $staff, Plugin::MEMBER_MEDIA_META, true ) );
		$this->assertFalse( get_role( 'subscriber' )->has_cap( 'upload_files' ) );
	}
}
