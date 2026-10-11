<?php
/**
 * Cache contract.
 *
 * Lets Pro / extensions build cache keys inside Free's incrementor scheme, so
 * their cached payloads are invalidated by the same writes as Free's (e.g. a
 * new review clears Pro's criteria aggregate). Resolve via
 * `wb_listora_service( 'cache' )`; the instance proxies the static
 * \WBListora\Core\Cache class, which extensions must not import.
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
