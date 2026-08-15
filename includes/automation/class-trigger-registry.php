<?php
/**
 * Trigger registry.
 *
 * @package WBListora\Automation
 */

namespace WBListora\Automation;

use WBListora\Contracts\Trigger_Registry_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Declares what automations can subscribe to. Delivers nothing.
 */
class Trigger_Registry implements Trigger_Registry_Interface {

	/**
	 * Keys every trigger must carry.
	 *
	 * @var string[]
	 */
	const REQUIRED_KEYS = array( 'name', 'label', 'group', 'hook', 'capability', 'version', 'schema' );

	/**
	 * Registered triggers, keyed by name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $triggers = array();

	/**
	 * Register a trigger.
	 *
	 * @param array<string, mixed> $trigger Trigger definition.
	 * @return bool
	 */
	public function register( array $trigger ) {
		foreach ( self::REQUIRED_KEYS as $key ) {
			if ( ! isset( $trigger[ $key ] ) || '' === $trigger[ $key ] ) {
				return false;
			}
		}

		// Positive integer, strictly. G15 compares versions across commits and
		// a string "1" would compare unequal to 1, failing the build for a
		// reason that has nothing to do with the payload.
		if ( ! is_int( $trigger['version'] ) || $trigger['version'] < 1 ) {
			return false;
		}

		$name = (string) $trigger['name'];

		// First declaration wins. Pro registers into this registry, and a Pro
		// typo must not silently redefine a Free event's payload.
		if ( isset( $this->triggers[ $name ] ) ) {
			return false;
		}

		$this->triggers[ $name ] = $trigger;

		return true;
	}

	/**
	 * Every registered trigger, keyed by name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all() {
		return $this->triggers;
	}

	/**
	 * One trigger by name.
	 *
	 * @param string $name Trigger name.
	 * @return array<string, mixed>|null
	 */
	public function get( $name ) {
		$name = (string) $name;

		return isset( $this->triggers[ $name ] ) ? $this->triggers[ $name ] : null;
	}

	/**
	 * Whether a trigger is registered.
	 *
	 * @param string $name Trigger name.
	 * @return bool
	 */
	public function has( $name ) {
		return isset( $this->triggers[ (string) $name ] );
	}

	/**
	 * Every registered trigger name.
	 *
	 * @return string[]
	 */
	public function names() {
		return array_keys( $this->triggers );
	}
}
