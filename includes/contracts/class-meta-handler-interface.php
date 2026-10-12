<?php
/**
 * Listing Meta contract.
 *
 * Public surface for Pro / extensions to read and write listing meta values
 * without poking at WordPress meta keys directly. The underlying class handles
 * meta-key prefixing, serialization, and cache-busting.
 *
 * Resolve via:
 *   $meta = wb_listora_service( 'meta' );
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Listing Meta contract.
 */
interface Meta_Handler_Interface {

	/**
	 * Get a single meta value by field key (without the _listora_ prefix).
	 *
	 * @param int    $post_id Listing post ID.
	 * @param string $key     Field key (e.g. 'address', 'phone').
	 * @param mixed  $default_value Default value when missing.
	 * @return mixed
	 */
	public function get_value( $post_id, $key, $default_value = '' );

	/**
	 * Set a single meta value by field key.
	 *
	 * @param int    $post_id Listing post ID.
	 * @param string $key     Field key (without prefix).
	 * @param mixed  $value   Value to store.
	 * @return bool
	 */
	public function set_value( $post_id, $key, $value );

	/**
	 * Get every Listora meta value for the listing as an associative
	 * array keyed by un-prefixed key.
	 *
	 * @param int $post_id Listing post ID.
	 * @return array
	 */
	public function get_all_values( $post_id );

	/**
	 * The listing's meta values the current user may see: fields the owner hid
	 * ("Show on listing page", and for 'rest' also "Show in API") are left out
	 * unless the user can edit the listing.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $post_id Listing post ID.
	 * @param string $context 'detail' (the listing page) or 'rest' (the API).
	 * @return array<string, mixed>
	 */
	public function get_visible_values( $post_id, $context = 'detail' );
}
