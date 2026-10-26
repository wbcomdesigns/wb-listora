<?php
/**
 * The dashboard's profile-photo hint and its Change photo link always name the
 * same place: the community profile when one can be linked, Gravatar otherwise.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ProfilePhotoLinkTest extends WP_UnitTestCase {

	public function test_hint_and_link_agree() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$html = wb_listora_get_template_html(
			'blocks/user-dashboard/tab-profile.php',
			array(
				'user'      => get_userdata( $user_id ),
				'user_id'   => $user_id,
				'view_data' => array(),
			)
		);

		$this->assertSame( 1, preg_match( '/avatar-hint">([^<]+)</', $html, $hint ) );
		$this->assertSame( 1, preg_match( '/href="([^"]+)"[^>]*>\s*Change photo/', $html, $link ) );

		$profile = wb_listora_community_profile_url( $user_id );
		if ( '' === $profile ) {
			$this->assertStringContainsString( 'Gravatar', $hint[1] );
			$this->assertSame( 'https://gravatar.com/profile', $link[1] );
		} else {
			$this->assertStringContainsString( 'community profile', $hint[1] );
			$this->assertStringStartsWith( trailingslashit( $profile ), $link[1] );
		}
	}

	public function test_no_community_url_for_a_missing_member() {
		$this->assertSame( '', wb_listora_community_profile_url( 0 ) );
	}
}
