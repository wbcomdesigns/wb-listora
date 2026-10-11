<?php
/**
 * Trigger registry contract.
 *
 * The one list of what an automation can subscribe to and what each event
 * sends; Pro's webhook delivery reads it instead of keeping its own list.
 * Resolve via `wb_listora_service( 'triggers' )`. It declares only; delivery
 * is Pro's.
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
	 * Required keys: name (stable snake_case event key, never renamed), label
	 * (translated), group (UI grouping), hook (the WordPress hook it hangs off),
	 * capability (needed to SUBSCRIBE, not to fire), version (positive int
	 * schema version), schema (filename relative to the schema directory).
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
