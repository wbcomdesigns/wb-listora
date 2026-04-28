<?php
/**
 * Block CSS service — instance proxy over \WBListora\Block_CSS.
 *
 * Resolved via wb_listora_service( 'block_css' ). Implements
 * {@see \WBListora\Contracts\Block_CSS_Interface}.
 *
 * @package WBListora\Services
 */

namespace WBListora\Services;

use WBListora\Block_CSS;
use WBListora\Contracts\Block_CSS_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Instance proxy over the static Block_CSS helpers.
 */
class Block_CSS_Service implements Block_CSS_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function render( $unique_id, $attributes ) {
		return Block_CSS::render( $unique_id, $attributes );
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate( $unique_id, $attributes ) {
		return Block_CSS::generate( $unique_id, $attributes );
	}

	/**
	 * {@inheritdoc}
	 */
	public function visibility_classes( $attributes ) {
		return Block_CSS::visibility_classes( $attributes );
	}

	/**
	 * {@inheritdoc}
	 */
	public function wrapper_classes( $block_name, $unique_id, $attributes, $extra = '' ) {
		return Block_CSS::wrapper_classes( $block_name, $unique_id, $attributes, $extra );
	}
}
