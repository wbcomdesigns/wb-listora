<?php
/**
 * Deactivate / reactivate must say whether they changed anything.
 *
 * Card 10154925210: both routes returned the same shape whether they acted or
 * not, so the only signal was an English sentence. The app matched /already/i
 * on it, which breaks the moment anyone translates the plugin. The account
 * routes already answered with `already_deactivated` / `already_active`; these
 * now match that spelling.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 * @group   rest
 */

namespace WBListora\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group rest
 */
class ListingAlreadyFlagsTest extends WP_UnitTestCase {

	private function call( int $listing_id, string $route ): array {
		$response = rest_do_request( new WP_REST_Request( 'POST', "/listora/v1/listings/{$listing_id}/{$route}" ) );

		return array( 'status' => $response->get_status(), 'data' => (array) $response->get_data() );
	}

	public function test_flags_distinguish_a_real_change_from_a_no_op(): void {
		$owner   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $owner,
			)
		);
		wp_set_current_user( $owner );

		$first = $this->call( $listing, 'deactivate' );
		$this->assertSame( 200, $first['status'] );
		$this->assertTrue( $first['data']['deactivated'] );
		$this->assertFalse( $first['data']['already_deactivated'], 'The first call really deactivated it.' );

		$second = $this->call( $listing, 'deactivate' );
		$this->assertSame( 200, $second['status'], 'A no-op is still a success, not an error.' );
		$this->assertTrue( $second['data']['already_deactivated'], 'The second call changed nothing and must say so.' );

		$back = $this->call( $listing, 'reactivate' );
		$this->assertFalse( $back['data']['already_active'] );

		$again = $this->call( $listing, 'reactivate' );
		$this->assertTrue( $again['data']['already_active'] );
	}

	public function test_the_flag_key_matches_the_account_routes(): void {
		// The account routes are the precedent; if they ever diverge, a client
		// has to learn two vocabularies for the same idea.
		$owner = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $owner );

		$account = rest_do_request( new WP_REST_Request( 'POST', '/listora/v1/me/reactivate' ) );
		$this->assertArrayHasKey( 'already_active', (array) $account->get_data() );
	}
}
