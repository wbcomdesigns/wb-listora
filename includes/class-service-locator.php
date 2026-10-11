<?php
/**
 * Service Locator, the public extension surface for Pro and 3rd-party code.
 *
 * Resolve services by name and use them through the \WBListora\Contracts\*
 * interfaces only; concrete classes may change. Names (registered in
 * {@see Plugin::register_services()}): listing_types, featured, meta,
 * services, search_indexer, search_engine, geo_query, block_css, e.g.
 * `wb_listora_service( 'listing_types' )` returns a
 * Contracts\Listing_Type_Registry_Interface.
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Static service registry. Boot-time registration only — Plugin::register_services()
 * registers everything before do_action( 'wb_listora_loaded' ) fires, which is
 * when Pro hooks in.
 */
class Service_Locator {

	/**
	 * Registered services keyed by short name.
	 *
	 * @var array<string,object>
	 */
	private static $services = array();

	/**
	 * Register a service instance.
	 *
	 * @param string $name     Short, stable name (e.g. 'listing_types').
	 * @param object $instance Service instance implementing one of the
	 *                         \WBListora\Contracts\* interfaces.
	 * @return void
	 */
	public static function register( $name, $instance ) {
		self::$services[ $name ] = $instance;
	}

	/**
	 * Resolve a service by short name.
	 *
	 * @param string $name Service name.
	 * @return object|null Registered instance, or null when not registered
	 *                     (e.g. plugin booted in an unusual order, or feature
	 *                     not available in this build).
	 */
	public static function get( $name ) {
		return isset( self::$services[ $name ] ) ? self::$services[ $name ] : null;
	}

	/**
	 * Whether a service is registered.
	 *
	 * @param string $name Service name.
	 * @return bool
	 */
	public static function has( $name ) {
		return isset( self::$services[ $name ] );
	}

	/**
	 * Get all registered service names.
	 *
	 * @return string[]
	 */
	public static function names() {
		return array_keys( self::$services );
	}
}
