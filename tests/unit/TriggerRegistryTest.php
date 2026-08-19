<?php
/**
 * Unit tests for the trigger registry.
 *
 * The registry is the single source of truth for what an automation can
 * subscribe to. Pro's Outgoing_Webhooks reads it instead of its own EVENTS
 * constant, which is what stops a second list drifting from the first —
 * `coupon_redeemed` and `need_posted` were dispatched for releases while
 * being absent from EVENTS, so nobody could subscribe and every dispatch
 * was discarded.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;
use WBListora\Automation\Trigger_Registry;

/**
 * @group listora
 * @group automation
 */
class TriggerRegistryTest extends WP_UnitTestCase {

	/**
	 * A fresh registry per test — these are boot-time singletons in
	 * production, so a shared instance would leak state across cases.
	 *
	 * @var Trigger_Registry
	 */
	private $registry;

	public function set_up() {
		parent::set_up();
		$this->registry = new Trigger_Registry();
	}

	private function valid_trigger( $overrides = array() ) {
		return array_merge(
			array(
				'name'       => 'listing_approved',
				'label'      => 'Listing Approved',
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_status_changed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_approved.v1.json',
			),
			$overrides
		);
	}

	public function test_registers_and_returns_a_valid_trigger() {
		$this->assertTrue( $this->registry->register( $this->valid_trigger() ) );
		$this->assertTrue( $this->registry->has( 'listing_approved' ) );
		$this->assertSame( 'Listing Approved', $this->registry->get( 'listing_approved' )['label'] );
		$this->assertSame( array( 'listing_approved' ), $this->registry->names() );
	}

	public function test_rejects_a_trigger_missing_a_required_key() {
		foreach ( array( 'name', 'label', 'group', 'hook', 'capability', 'version', 'schema' ) as $key ) {
			$trigger = $this->valid_trigger();
			unset( $trigger[ $key ] );
			$this->assertFalse(
				$this->registry->register( $trigger ),
				"Registering without '{$key}' must be refused"
			);
		}
		$this->assertSame( array(), $this->registry->names() );
	}

	/**
	 * Re-registering is how Pro would accidentally clobber a Free trigger.
	 * First declaration wins, so a Pro typo cannot silently redefine a Free
	 * event's payload.
	 */
	public function test_first_registration_wins() {
		$this->registry->register( $this->valid_trigger() );
		$this->assertFalse( $this->registry->register( $this->valid_trigger( array( 'label' => 'Hijacked' ) ) ) );
		$this->assertSame( 'Listing Approved', $this->registry->get( 'listing_approved' )['label'] );
	}

	public function test_unknown_trigger_reads_as_absent_not_fatal() {
		$this->assertFalse( $this->registry->has( 'nope' ) );
		$this->assertNull( $this->registry->get( 'nope' ) );
	}

	/**
	 * Version must be a positive integer — G15 compares it across commits,
	 * and a string "1" would compare unequal to 1 and fail the build for the
	 * wrong reason.
	 */
	public function test_rejects_non_integer_version() {
		$this->assertFalse( $this->registry->register( $this->valid_trigger( array( 'version' => '1' ) ) ) );
		$this->assertFalse( $this->registry->register( $this->valid_trigger( array( 'version' => 0 ) ) ) );
	}

	/**
	 * Presence + non-empty alone let an array, object, bool, or int through
	 * a required string key. `(string) $trigger['name']` would then cast a
	 * non-scalar to the literal string "Array" and register a malformed
	 * trigger — reachable from third-party code once Pro registers its
	 * triggers through a public filter. Assert both the return value AND
	 * that nothing was written, so a "returns false but registers anyway"
	 * implementation still fails this test.
	 */
	public function test_rejects_a_non_string_value_in_a_required_string_key() {
		foreach ( array( 'name', 'label', 'group', 'hook', 'capability', 'schema' ) as $key ) {
			$registry = new Trigger_Registry();

			$this->assertFalse(
				$registry->register( $this->valid_trigger( array( $key => array( 'not' => 'a string' ) ) ) ),
				"Registering with an array '{$key}' must be refused"
			);
			$this->assertSame( array(), $registry->names(), "An array '{$key}' must not be written" );

			$this->assertFalse(
				$registry->register( $this->valid_trigger( array( $key => true ) ) ),
				"Registering with a bool '{$key}' must be refused"
			);
			$this->assertSame( array(), $registry->names(), "A bool '{$key}' must not be written" );
		}
	}
}
