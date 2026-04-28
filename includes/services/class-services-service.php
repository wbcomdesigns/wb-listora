<?php
/**
 * Listing services read-side proxy over \WBListora\Core\Services.
 *
 * Resolved via wb_listora_service( 'services' ). Implements
 * {@see \WBListora\Contracts\Services_Interface}.
 *
 * Mutation methods (create/update/delete/reorder) are intentionally not part
 * of the public surface — Pro should fire the documented hooks for those.
 *
 * @package WBListora\Services
 */

namespace WBListora\Services;

use WBListora\Contracts\Services_Interface;
use WBListora\Core\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Instance proxy over the static Services helpers.
 */
class Services_Service implements Services_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function get_services( $listing_id, $status = 'active' ) {
		return Services::get_services( $listing_id, $status );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_service( $service_id ) {
		return Services::get_service( $service_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_service_count( $listing_id ) {
		return Services::get_service_count( $listing_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_service_categories( $service_id ) {
		return Services::get_service_categories( $service_id );
	}
}
