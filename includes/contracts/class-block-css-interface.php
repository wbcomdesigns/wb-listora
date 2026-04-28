<?php
/**
 * Block CSS contract.
 *
 * Public surface for Pro / extension blocks to render the same per-instance
 * CSS, visibility classes, and wrapper classes that Free's blocks use.
 *
 * Resolve via:
 *   $block_css = wb_listora_service( 'block_css' );
 *
 * The underlying \WBListora\Block_CSS class is static. The service-locator
 * instance is a thin proxy so block render.php files don't reach into the
 * concrete class.
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Block CSS contract.
 */
interface Block_CSS_Interface {

	/**
	 * Render a <style> tag with per-instance CSS, or empty string if no
	 * custom styles are present.
	 *
	 * @param string $unique_id  Block instance unique ID.
	 * @param array  $attributes Block attributes.
	 * @return string
	 */
	public function render( $unique_id, $attributes );

	/**
	 * Generate the CSS body (no <style> wrapper).
	 *
	 * @param string $unique_id  Block instance unique ID.
	 * @param array  $attributes Block attributes.
	 * @return string
	 */
	public function generate( $unique_id, $attributes );

	/**
	 * Build a string of device-visibility classes from block attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function visibility_classes( $attributes );

	/**
	 * Build the full wrapper class string for a block.
	 *
	 * @param string $block_name Block name (e.g. 'listing-grid').
	 * @param string $unique_id  Block instance unique ID.
	 * @param array  $attributes Block attributes.
	 * @param string $extra      Extra classes to append.
	 * @return string
	 */
	public function wrapper_classes( $block_name, $unique_id, $attributes, $extra = '' );
}
