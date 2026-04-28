<?php
/**
 * Listing Services contract.
 *
 * Public surface for Pro / extensions to read services attached to listings.
 * Resolve via:
 *   $services = wb_listora_service( 'services' );
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Listing Services contract.
 */
interface Services_Interface {

	/**
	 * Get all services for a listing.
	 *
	 * @param int    $listing_id Listing post ID.
	 * @param string $status     Status filter ('active' by default).
	 * @return array Array of service rows.
	 */
	public function get_services( $listing_id, $status = 'active' );

	/**
	 * Get a single service by ID.
	 *
	 * @param int $service_id Service ID.
	 * @return array|null
	 */
	public function get_service( $service_id );

	/**
	 * Get the count of active services for a listing.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return int
	 */
	public function get_service_count( $listing_id );

	/**
	 * Get the service-category term IDs assigned to a service.
	 *
	 * @param int $service_id Service ID.
	 * @return array Array of term IDs.
	 */
	public function get_service_categories( $service_id );
}
