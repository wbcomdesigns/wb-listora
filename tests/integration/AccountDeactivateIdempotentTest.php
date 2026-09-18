<?php
/**
 * Asking to leave twice is a no-op, not an error.
 *
 * Card 10317662503: `Account_Manager::deactivate()` has a documented idempotent
 * branch returning `already_deactivated: true`, written so a double-tapped
 * button cannot corrupt the saved prior statuses. It was unreachable - the
 * member write gate refused the second request before the controller ran, so a
 * member who tapped twice got a 403 telling them they cannot post, which is not
 * what they were trying to do.
 *
 * The branch had no test precisely because it could not run: nothing failed
 * when it broke.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   account-deactivate
 */

namespace WBListora\Tests\Integration;

use WBListora\Core\Member_Suspension;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group account-deactivate
 */
class AccountDeactivateIdempotentTest extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $member = 0;

	public function set_up() {
		parent::set_up();

		$this->member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $this->member );

		rest_get_server();
	}

	/**
	 * @param string $route Route under /listora/v1.
	 * @return \WP_REST_Response
	 */
	private function post( string $route ) {
		return rest_do_request( new WP_REST_Request( 'POST', '/listora/v1/' . ltrim( $route, '/' ) ) );
	}

	/**
	 * The defect: the second call answered 403.
	 */
	public function test_deactivating_twice_is_a_no_op(): void {
		$first = $this->post( 'me/deactivate' );
		$this->assertSame( 200, $first->get_status() );
		$this->assertFalse( $first->get_data()['already_deactivated'] );

		$second = $this->post( 'me/deactivate' );

		$this->assertSame( 200, $second->get_status(), 'A second deactivate must not be an error.' );
		$this->assertTrue( $second->get_data()['already_deactivated'] );
	}

	/**
	 * The guarantee the idempotent branch exists for: the saved prior statuses
	 * must survive, or reactivating restores the wrong thing.
	 */
	public function test_a_second_call_does_not_disturb_the_saved_state(): void {
		$listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $this->member,
			)
		);

		$this->post( 'me/deactivate' );
		$this->post( 'me/deactivate' );

		$reactivated = $this->post( 'me/reactivate' );

		$this->assertSame( 200, $reactivated->get_status() );
		$this->assertSame( 'publish', get_post_status( $listing ), 'The listing must come back published.' );
	}

	/**
	 * Reactivate was always idempotent; it stays that way.
	 */
	public function test_reactivating_twice_is_still_a_no_op(): void {
		$this->post( 'me/deactivate' );
		$this->post( 'me/reactivate' );

		$second = $this->post( 'me/reactivate' );

		$this->assertSame( 200, $second->get_status() );
		$this->assertTrue( $second->get_data()['already_active'] );
	}

	/**
	 * A SUSPENDED member is a different case and must stay blocked: letting
	 * them self-deactivate would swap a moderator's decision for one of their
	 * own, which they can then undo.
	 */
	public function test_a_suspended_member_cannot_deactivate(): void {
		update_user_meta( $this->member, Member_Suspension::META_SUSPENDED, 1 );

		$response = $this->post( 'me/deactivate' );

		$this->assertSame( 403, $response->get_status(), 'Suspension must still win.' );
	}
}
