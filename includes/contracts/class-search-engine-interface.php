<?php
/**
 * Search Engine contract.
 *
 * Public surface for Pro / extensions to run search queries against the
 * Listora search index. Resolve via:
 *   $engine = wb_listora_service( 'search_engine' );
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Search Engine contract.
 */
interface Search_Engine_Interface {

	/**
	 * Execute a search query.
	 *
	 * @param array $args Search arguments — keyword, filters, geo, sort,
	 *                    page, per_page, facets.
	 * @return array {
	 *     @type int[] $listing_ids Matched listing IDs (paginated).
	 *     @type int   $total       Total match count.
	 *     @type int   $pages       Total pages.
	 *     @type array $facets      Facet counts (if requested).
	 *     @type array $distances   Distance per listing (if geo search).
	 * }
	 */
	public function search( array $args );
}
