<?php
/**
 * Cache contract.
 *
 * Public surface for Pro / extensions to build cache keys that participate in
 * Free's incrementor scheme, so an extension's cached payload is invalidated by
 * the same write that invalidates Free's own.
 *
 * Resolve via:
 *   $cache = wb_listora_service( 'cache' );
 *
 * Without this, an extension caching data derived from listings or reviews has
 * two bad options: a raw `wp_cache_*` key that never gets invalidated when Free
 * bumps its group, or importing the concrete \WBListora\Core\Cache class, which
 * INV-3 forbids. Pro's multi-criteria review aggregate hit exactly that — it
 * needs the REVIEWS incrementor so a new review clears it.
 *
 * The underlying \WBListora\Core\Cache class uses static methods. The
 * service-locator instance is a thin proxy so consumers don't import the
 * concrete class directly.
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Cache contract.
 */
interface Cache_Interface {

	/**
	 * Build an incrementor-aware cache key for a group.
	 *
	 * The returned key embeds the group's current incrementor, so bumping the
	 * group invalidates every key previously built for it.
	 *
	 * @param string $group Group name — use one of the group_* accessors.
	 * @param string $base  Caller-specific key suffix.
	 * @return string
	 */
	public function key( string $group, string $base ): string;

	/**
	 * Invalidate a whole group by advancing its incrementor.
	 *
	 * @param string $group Group name.
	 * @return void
	 */
	public function bump( string $group ): void;

	/**
	 * The listings group name.
	 *
	 * @return string
	 */
	public function group_listings(): string;

	/**
	 * The reviews group name.
	 *
	 * @return string
	 */
	public function group_reviews(): string;

	/**
	 * The dashboard group name.
	 *
	 * @return string
	 */
	public function group_dashboard(): string;

	/**
	 * The settings group name.
	 *
	 * @return string
	 */
	public function group_settings(): string;
}
