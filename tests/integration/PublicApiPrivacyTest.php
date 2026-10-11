<?php
/**
 * The public listing API shows what the page shows: no internal meta, no unchosen names.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Meta_Handler;

/**
 * @group listora
 * @group rest-api
 */
class PublicApiPrivacyTest extends WP_UnitTestCase {

	public function test_visitor_gets_no_internal_meta_but_the_editor_does() {
		$owner   = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $owner ) );
		update_post_meta( $post_id, '_listora_expiration_date', '2027-01-01' );
		update_post_meta( $post_id, '_listora_demo_content', '1' );
		update_post_meta( $post_id, '_listora_timezone', 'America/New_York' );

		wp_set_current_user( 0 );
		$public = Meta_Handler::get_visible_values( $post_id, 'rest' );
		$this->assertArrayNotHasKey( 'expiration_date', $public );
		$this->assertArrayNotHasKey( 'demo_content', $public );
		$this->assertSame( 'America/New_York', $public['timezone'] ?? null, 'The app reads meta.timezone.' );

		wp_set_current_user( $owner );
		$this->assertArrayHasKey( 'expiration_date', Meta_Handler::get_visible_values( $post_id, 'rest' ) );
	}

	public function test_public_name_hides_a_login_but_keeps_a_chosen_name() {
		$login_only = self::factory()->user->create_and_get( array( 'user_login' => 'jdoe42', 'display_name' => 'jdoe42' ) );
		$chosen     = self::factory()->user->create_and_get( array( 'user_login' => 'msmith', 'display_name' => 'Maria Smith' ) );

		$this->assertSame( '', wb_listora_user_public_name( $login_only ) );
		$this->assertSame( 'Maria Smith', wb_listora_user_public_name( $chosen ) );
		$this->assertSame( 'Member', wb_listora_review_author_name( $login_only->ID ) );
	}
}
