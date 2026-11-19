<?php
/**
 * wb_listora_charge(): the one contract every priced action goes through.
 *
 * Each row of the data provider is one rule of the contract; a new priced action
 * (Free or Pro) is covered by pointing it at the contract, not by a new copy of these.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group credits
 */
class ChargeContractTest extends WP_UnitTestCase {

	private int $owner   = 0;
	private int $staff   = 0;
	private int $listing = 0;
	private array $start = array();

	public function set_up() {
		parent::set_up();
		if ( ! wb_listora_credits_ready() ) {
			$this->markTestSkipped( 'The credits library is not ready.' );
		}
		$this->owner   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->staff   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->reset_ledger( array( $this->owner, $this->staff ) );
		foreach ( array( $this->owner, $this->staff ) as $user ) {
			$this->start[ $user ] = 0.0;
		}
		$this->listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_author' => $this->owner,
			)
		);
	}

	/**
	 * The ledger is a custom table that PHPUnit's rollback does not clear, and test user IDs are
	 * reused, so a new user can inherit credits from an earlier run. Start (and leave) each user at zero.
	 *
	 * @param int[] $users User IDs.
	 */
	private function reset_ledger( array $users ): void {
		global $wpdb;
		foreach ( $users as $user ) {
			$wpdb->delete( \Wbcom\Credits\Ledger::table_name( 'listora' ), array( 'user_id' => (int) $user ) );
		}
	}

	public function tear_down() {
		$this->reset_ledger( array( $this->owner, $this->staff ) );
		parent::tear_down();
	}

	private function balance( int $user ): float {
		return (float) \Wbcom\Credits\Credits::balance_money( 'wb-listora', $user ) - $this->start[ $user ];
	}

	private function charge( int $actor, float $amount, callable $work ) {
		return wb_listora_charge(
			array(
				'listing_id' => $this->listing,
				'amount'     => $amount,
				'note'       => 'Charge contract test',
				'actor_id'   => $actor,
				'work'       => $work,
			)
		);
	}

	public function test_the_owner_pays_once_and_the_work_runs() {
		\Wbcom\Credits\Credits::topup_money( 'wb-listora', $this->owner, 10 );
		$ran = 0;

		$result = $this->charge(
			$this->owner,
			4,
			function () use ( &$ran ) {
				++$ran;
				return true;
			}
		);

		$this->assertSame( 4.0, $result['charged'] );
		$this->assertSame( 1, $ran );
		$this->assertSame( 6.0, $this->balance( $this->owner ) );
	}

	public function test_a_short_balance_is_refused_with_one_shape_and_the_work_never_runs() {
		\Wbcom\Credits\Credits::topup_money( 'wb-listora', $this->owner, 1 );
		$ran = false;

		$result = $this->charge(
			$this->owner,
			4,
			function () use ( &$ran ) {
				$ran = true;
				return true;
			}
		);

		$this->assertWPError( $result );
		$this->assertSame( 'insufficient_credits', $result->get_error_code() );
		$this->assertSame( 402, $result->get_error_data()['status'] );
		$this->assertSame( 4.0, $result->get_error_data()['required'] );
		$this->assertFalse( $ran );
		$this->assertSame( 1.0, $this->balance( $this->owner ) );
	}

	public function test_staff_acting_on_a_members_listing_charge_nobody() {
		\Wbcom\Credits\Credits::topup_money( 'wb-listora', $this->owner, 10 );
		$ran = false;

		$result = $this->charge(
			$this->staff,
			4,
			function () use ( &$ran ) {
				$ran = true;
				return true;
			}
		);

		$this->assertSame( 0.0, $result['charged'] );
		$this->assertTrue( $ran );
		$this->assertSame( 10.0, $this->balance( $this->owner ), 'The owner is not billed for a staff action.' );
		$this->assertSame( 0.0, $this->balance( $this->staff ), 'Staff are not billed either.' );
	}

	public function test_a_filter_can_bill_the_owner_for_staff_actions() {
		\Wbcom\Credits\Credits::topup_money( 'wb-listora', $this->owner, 10 );
		add_filter( 'wb_listora_staff_action_is_free', '__return_false' );

		$result = $this->charge( $this->staff, 4, static fn() => true );

		$this->assertSame( 4.0, $result['charged'] );
		$this->assertSame( 6.0, $this->balance( $this->owner ) );
	}

	public function data_failures(): array {
		return array(
			'work returns an error' => array( static fn() => new WP_Error( 'nope', 'No.' ) ),
			'work throws'           => array(
				static function () {
					throw new RuntimeException( 'boom' );
				},
			),
		);
	}

	/**
	 * @dataProvider data_failures
	 *
	 * @param callable $work Failing work.
	 */
	public function test_when_the_work_fails_the_balance_is_restored( callable $work ) {
		\Wbcom\Credits\Credits::topup_money( 'wb-listora', $this->owner, 10 );

		$result = $this->charge( $this->owner, 4, $work );

		$this->assertWPError( $result );
		$this->assertSame( 10.0, $this->balance( $this->owner ), 'A failed action must not keep the money.' );
	}

	public function test_a_free_action_runs_without_touching_the_ledger() {
		$result = $this->charge( $this->owner, 0, static fn() => true );

		$this->assertSame( 0.0, $result['charged'] );
		$this->assertSame( 0.0, $this->balance( $this->owner ) );
	}
}
