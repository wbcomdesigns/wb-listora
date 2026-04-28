<?php
/**
 * Listing Type Registry contract.
 *
 * Public surface for Pro / extensions to consume the registry of all listing
 * types (Restaurant, Hotel, Real Estate, etc.).
 *
 * Resolve via:
 *   $registry = wb_listora_service( 'listing_types' );
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Listing Type Registry contract.
 */
interface Listing_Type_Registry_Interface {

	/**
	 * Get all registered listing types.
	 *
	 * @return Listing_Type_Interface[] Map of slug => type.
	 */
	public function get_all();

	/**
	 * Get a listing type by slug.
	 *
	 * @param string $slug Type slug.
	 * @return Listing_Type_Interface|null
	 */
	public function get( $slug );

	/**
	 * Get the listing type assigned to a post.
	 *
	 * @param int $post_id Post ID.
	 * @return Listing_Type_Interface|null
	 */
	public function get_for_post( $post_id );

	/**
	 * Get field groups for a specific type.
	 *
	 * @param string $slug Type slug.
	 * @return array
	 */
	public function get_field_groups( $slug );

	/**
	 * Get filterable fields for a specific type.
	 *
	 * @param string $slug Type slug.
	 * @return array
	 */
	public function get_search_filters( $slug );
}
