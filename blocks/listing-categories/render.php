<?php
/**
 * Listing Categories block — browse-by-category grid.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

$unique_id    = $attributes['uniqueId'] ?? '';
$listing_type = $attributes['listingType'] ?? '';
$columns      = $attributes['columns'] ?? 4;
$show_count   = $attributes['showCount'] ?? true;
$show_icon    = $attributes['showIcon'] ?? true;
$limit        = $attributes['limit'] ?? 12;
$hide_empty   = $attributes['hideEmpty'] ?? false;

// Get categories — scoped to type if specified.
$term_args = array(
	'taxonomy'   => 'listora_listing_cat',
	'hide_empty' => $hide_empty,
	'number'     => $limit,
	'orderby'    => 'count',
	'order'      => 'DESC',
);

if ( $listing_type ) {
	$registry = \WBListora\Core\Listing_Type_Registry::instance();
	$type     = $registry->get( $listing_type );
	if ( $type ) {
		$allowed = $type->get_allowed_categories();
		if ( ! empty( $allowed ) ) {
			$term_args['include'] = $allowed;
		}
	}
}

$categories = get_terms( $term_args );

if ( is_wp_error( $categories ) || empty( $categories ) ) {
	$empty_attrs = get_block_wrapper_attributes( array( 'class' => 'listora-categories listora-categories--empty' ) );
	?>
	<div <?php echo $empty_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="listora-categories__empty">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
			</svg>
			<h3><?php esc_html_e( 'No categories yet', 'wb-listora' ); ?></h3>
			<p><?php esc_html_e( 'Categories will appear here once listings are organized.', 'wb-listora' ); ?></p>
		</div>
	</div>
	<?php
	return;
}

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class' => 'listora-categories ' . $block_classes,
		// Trailing semicolon ensures valid CSS when the block system appends additional inline styles.
		'style' => '--listora-cat-columns: ' . (int) $columns . ';',
	)
);

// ─── Assemble $view_data for templates ───
$view_data = array(
	'wrapper_attrs' => $wrapper_attrs,
	'categories'    => $categories,
	'show_count'    => $show_count,
	'show_icon'     => $show_icon,
	'attributes'    => $attributes,
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

/** Hook: Fires before the categories grid is rendered. @since 1.1.0 */
do_action( 'wb_listora_before_categories_grid', $attributes );

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

wb_listora_get_template( 'blocks/listing-categories/categories.php', $view_data );

/** Hook: Fires after the categories grid wrapper is closed. @since 1.1.0 */
do_action( 'wb_listora_after_categories_grid', $attributes );
