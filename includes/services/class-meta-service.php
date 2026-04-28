<?php
/**
 * Listing meta service — instance proxy over \WBListora\Core\Meta_Handler.
 *
 * Wraps the static get/set helpers so Pro / extensions can resolve it through
 * {@see \WBListora\Contracts\Meta_Handler_Interface} via wb_listora_service( 'meta' ).
 *
 * @package WBListora\Services
 */

namespace WBListora\Services;

use WBListora\Contracts\Meta_Handler_Interface;
use WBListora\Core\Meta_Handler;

defined( 'ABSPATH' ) || exit;

/**
 * Instance proxy over the static Meta_Handler helpers.
 */
class Meta_Service implements Meta_Handler_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function get_value( $post_id, $key, $default = '' ) {
		return Meta_Handler::get_value( $post_id, $key, $default );
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_value( $post_id, $key, $value ) {
		return Meta_Handler::set_value( $post_id, $key, $value );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_all_values( $post_id ) {
		return Meta_Handler::get_all_values( $post_id );
	}
}
