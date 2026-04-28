<?php
/**
 * Featured listings contract.
 *
 * Public surface for Pro / extensions to feature/unfeature listings and check
 * featured state. Resolve via:
 *   $featured = wb_listora_service( 'featured' );
 *
 * Note: the underlying \WBListora\Core\Featured class uses static methods for
 * legacy reasons. This interface wraps them as instance methods so callers
 * stay loosely coupled — the implementation is a thin instance proxy.
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Featured listings contract.
 */
interface Featured_Interface {

	/**
	 * Feature a listing for a number of days.
	 *
	 * @param int $post_id Listing ID.
	 * @param int $days    Duration in days. 0 = use admin default.
	 * @return bool|\WP_Error
	 */
	public function feature_listing( $post_id, $days = 0 );

	/**
	 * Unfeature a listing.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $reason  'manual' | 'expired'.
	 * @return bool|\WP_Error
	 */
	public function unfeature_listing( $post_id, $reason = 'manual' );

	/**
	 * Whether the listing is currently featured (respecting expiration).
	 *
	 * @param int $post_id Listing ID.
	 * @return bool
	 */
	public function is_featured( $post_id );

	/**
	 * Get the featured-until timestamp (0 = permanent / not featured).
	 *
	 * @param int $post_id Listing ID.
	 * @return int
	 */
	public function get_featured_until( $post_id );

	/**
	 * Default feature duration in days (admin setting).
	 *
	 * @return int
	 */
	public function get_default_duration_days();
}
