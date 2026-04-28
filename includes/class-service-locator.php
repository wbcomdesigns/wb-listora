<?php
/**
 * Service Locator — the public extension surface for Pro and 3rd-party code.
 *
 * Pro / extensions resolve services by short name and consume them via the
 * \WBListora\Contracts\* interfaces. Concrete classes are NOT part of the
 * public surface and may change without notice.
 *
 * Usage:
 *   $registry = wb_listora_service( 'listing_types' );
 *   if ( $registry instanceof \WBListora\Contracts\Listing_Type_Registry_Interface ) {
 *       $type = $registry->get_for_post( $post_id );
 *   }
 *
 * Available service names (registered in {@see Plugin::register_services()}):
 *
 *   - 'listing_types'  → Contracts\Listing_Type_Registry_Interface
 *   - 'featured'       → Contracts\Featured_Interface
 *   - 'meta'           → Contracts\Meta_Handler_Interface
 *   - 'services'       → Contracts\Services_Interface
 *   - 'search_indexer' → Contracts\Search_Indexer_Interface
 *   - 'search_engine'  → Contracts\Search_Engine_Interface
 *   - 'geo_query'      → Contracts\Geo_Query_Interface
 *   - 'block_css'      → Contracts\Block_CSS_Interface
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

