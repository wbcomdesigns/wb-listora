<?php
/**
 * Unit tests for the role-stripped write block.
 *
 * Removing a user's role is WordPress's own moderation lever, and a
 * zero-capability account kept writing (card 10100523205). The first fix put a
 * `read` check in wb_listora_require_logged_in(), which missed every owner
 * route and blocked reads and account erasure. The check now lives in
 * Member_Suspension, so the one REST gate applies it to every listora/v1 write
 * with the existing exemptions. These tests pin that contract.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;
use WBListora\Core\Member_Suspension;

/**
 * @group listora
 * @group moderation
 */
class RoleStrippedWriteGateTest extends WP_UnitTestCase {

	/**
	 * Account with its role removed.
	 *
	 * @var int
	 */
	private $stripped;

	/**
	 * Ordinary subscriber, the control.
	 *
	 * @var int
	 */
	private $member;

	/**
	 * Create both accounts.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->member   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->stripped = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( $this->stripped )->remove_role( 'subscriber' );
	}

	/**
	 * Run the REST write gate for a request as the given user.
	 *
	 * @param int    $user_id User to act as.
	 * @param string $method  HTTP method.
	 * @param string $route   Route, e.g. /listora/v1/favorites.
	 * @return mixed Gate result: the untouched response or a WP_Error.
	 */
	private function gate( int $user_id, string $method, string $route ) {
		wp_set_current_user( $user_id );

		return ( new Member_Suspension() )->block_rest_writes( null, array(), new WP_REST_Request( $method, $route ) );
	}

	public function test_role_stripped_account_is_write_blocked(): void {
		$this->assertTrue( Member_Suspension::is_role_stripped( $this->stripped ) );
		$this->assertTrue( Member_Suspension::is_write_blocked( $this->stripped ) );
	}

	public function test_member_with_a_role_is_not_blocked(): void {
		$this->assertFalse( Member_Suspension::is_role_stripped( $this->member ) );
		$this->assertFalse( Member_Suspension::is_write_blocked( $this->member ) );
		$this->assertNull( $this->gate( $this->member, 'POST', '/listora/v1/favorites' ) );
	}

	/**
	 * Reviewer AND owner routes: the first fix covered the former only.
	 *
	 * @dataProvider provide_write_routes
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route.
	 */
	public function test_writes_are_refused_with_restricted_code( string $method, string $route ): void {
		$result = $this->gate( $this->stripped, $method, $route );

		$this->assertWPError( $result );
		$this->assertSame( 'listora_account_restricted', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Write routes a stripped account must not reach.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public function provide_write_routes(): array {
		return array(
			'favourite'         => array( 'POST', '/listora/v1/favorites' ),
			'review report'     => array( 'POST', '/listora/v1/reviews/1/report' ),
			'listing deactivate' => array( 'POST', '/listora/v1/listings/1/deactivate' ),
			'edit own listing'  => array( 'PUT', '/listora/v1/submit/1' ),
			'services'          => array( 'POST', '/listora/v1/listings/1/services' ),
		);
	}

	public function test_reads_are_never_gated(): void {
		$this->assertNull( $this->gate( $this->stripped, 'GET', '/listora/v1/favorites' ) );
	}

	public function test_account_erasure_is_always_allowed(): void {
		$this->assertNull( $this->gate( $this->stripped, 'DELETE', '/listora/v1/me' ) );
	}

	public function test_login_helper_is_login_only(): void {
		wp_set_current_user( $this->stripped );
		$this->assertTrue( wb_listora_require_logged_in() );
	}

	public function test_filter_restores_access_for_roles_without_read(): void {
		add_filter( 'wb_listora_user_can_act', '__return_true' );

		$this->assertFalse( Member_Suspension::is_write_blocked( $this->stripped ) );

		remove_filter( 'wb_listora_user_can_act', '__return_true' );
	}
}
