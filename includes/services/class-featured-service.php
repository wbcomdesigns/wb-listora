<?php
/**
 * Featured listings service — instance proxy over \WBListora\Core\Featured.
 *
 * The concrete class has historically used static methods. This thin wrapper
 * lets Pro / extensions consume it via {@see \WBListora\Contracts\Featured_Interface}
 * without binding to the static class name.
 *
 * Resolved via wb_listora_service( 'featured' ).
 *
 * @package WBListora\Services
 */

namespace WBListora\Services;

use WBListora\Contracts\Featured_Interface;
use WBListora\Core\Featured;

defined( 'ABSPATH' ) || exit;

/**
 * Instance proxy over the static Featured class.
 */
class Featured_Service implements Featured_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function feature_listing( $post_id, $days = 0 ) {
		return Featured::feature_listing( $post_id, $days );
	}

	/**
	 * {@inheritdoc}
	 */
	public function unfeature_listing( $post_id, $reason = 'manual' ) {
		return Featured::unfeature_listing( $post_id, $reason );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_featured( $post_id ) {
		return Featured::is_featured( $post_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_featured_until( $post_id ) {
		return Featured::get_featured_until( $post_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_default_duration_days() {
		return Featured::get_default_duration_days();
	}
}
