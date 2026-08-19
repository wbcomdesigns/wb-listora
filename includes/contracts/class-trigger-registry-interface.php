<?php
/**
 * Trigger registry contract.
 *
 * The canonical answer to "what can an automation subscribe to, and what does
 * each event send?". Resolve via:
 *
 *   $triggers = wb_listora_service( 'triggers' );
 *
 * Pro's webhook delivery reads this instead of keeping its own event list.
 * That is the whole point: two lists drift, and they did — `coupon_redeemed`
 * and `need_posted` were dispatched by real handlers while being absent from
 * Pro's EVENTS constant, so the admin UI never offered them, no subscriber
 * could exist, and every dispatch built a payload and threw it away.
 *
 * This registry declares. It never delivers — delivery is Pro's.
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Trigger registry contract.
 */
interface Trigger_Registry_Interface {

	/**
	 * Register a trigger.
	 *
	 * Required keys, all of them:
	 *
	 *   name       string  Stable event key, snake_case. Never renamed.
	 *   label      string  Human-readable, translated, shown in the UI.
	 *   group      string  UI grouping only.
	 *   hook       string  The WordPress hook this event hangs off.
	 *   capability string  Capability required to SUBSCRIBE, not to fire.
	 *   version    int     Current schema version, positive.
	 *   schema     string  Schema filename, relative to the schema directory.
	 *
	 * @param array<string, mixed> $trigger Trigger definition.
	 * @return bool True when registered; false when invalid or already taken.
	 */
	public function register( array $trigger );

	/**
	 * Every registered trigger, keyed by name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all();

	/**
	 * One trigger by name.
	 *
	 * @param string $name Trigger name.
	 * @return array<string, mixed>|null Null when not registered.
	 */
	public function get( $name );

	/**
	 * Whether a trigger is registered.
	 *
	 * @param string $name Trigger name.
	 * @return bool
	 */
	public function has( $name );

	/**
	 * Every registered trigger name.
	 *
	 * @return string[]
	 */
	public function names();
}
