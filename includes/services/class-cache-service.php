<?php
/**
 * Cache service — instance proxy over \WBListora\Core\Cache.
 *
 * Resolved via wb_listora_service( 'cache' ). Implements
 * {@see \WBListora\Contracts\Cache_Interface}.
 *
 * @package WBListora\Services
 */

namespace WBListora\Services;

use WBListora\Contracts\Cache_Interface;
use WBListora\Core\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Instance proxy over the static Cache helpers.
 */
class Cache_Service implements Cache_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function key( string $group, string $base ): string {
		return Cache::key( $group, $base );
	}

	/**
	 * {@inheritdoc}
	 */
	public function bump( string $group ): void {
		Cache::bump( $group );
	}

	/**
	 * {@inheritdoc}
	 */
	public function group_listings(): string {
		return Cache::GROUP_LISTINGS;
	}

	/**
	 * {@inheritdoc}
	 */
	public function group_reviews(): string {
		return Cache::GROUP_REVIEWS;
	}

	/**
	 * {@inheritdoc}
	 */
	public function group_dashboard(): string {
		return Cache::GROUP_DASHBOARD;
	}

	/**
	 * {@inheritdoc}
	 */
	public function group_settings(): string {
		return Cache::GROUP_SETTINGS;
	}
}
