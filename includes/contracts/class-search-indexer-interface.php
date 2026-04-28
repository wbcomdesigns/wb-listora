<?php
/**
 * Search Indexer contract.
 *
 * Public surface for Pro / extensions to trigger re-indexing of a listing.
 * Resolve via:
 *   $indexer = wb_listora_service( 'search_indexer' );
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Search Indexer contract.
 */
interface Search_Indexer_Interface {

	/**
	 * Index (or re-index) a single listing in the search_index, field_index,
	 * geo, and hours tables.
	 *
	 * @param int           $post_id Listing post ID.
	 * @param \WP_Post|null $post    Optional post object; loaded if null.
	 * @return void
	 */
	public function index_listing( $post_id, $post = null );

	/**
	 * Remove a listing from all search-related tables.
	 *
	 * @param int $post_id Listing post ID.
	 * @return void
	 */
	public function remove_from_index( $post_id );
}
