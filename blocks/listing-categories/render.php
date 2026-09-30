<?php
/**
 * Listing Categories block — browse-by-category grid.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-base' );

$unique_id    = $attributes['uniqueId'] ?? '';
$listing_type = $attributes['listingType'] ?? '';
$columns      = max( 1, (int) ( $attributes['columns'] ?? 4 ) ); // Floor-guard: REST/saved content can carry 0, which breaks the grid track count (BC #9989784605 family).
$show_count   = $attributes['showCount'] ?? true;
$show_icon    = $attributes['showIcon'] ?? true;
$limit        = $attributes['limit'] ?? 12;
$hide_empty   = $attributes['hideEmpty'] ?? false;

// Every category, busiest first. The first `limit` render as the grid; the
// rest sit behind "View all", grouped by listing type (card 10337188283).
$term_args = array(
	'taxonomy'   => 'listora_listing_cat',
	'hide_empty' => $hide_empty,
	'orderby'    => 'count',
	'order'      => 'DESC',
);

$registry = \WBListora\Core\Listing_Type_Registry::instance();

if ( $listing_type ) {
	$type = $registry->get( $listing_type );
	if ( $type ) {
		$allowed = $type->get_allowed_categories();
		if ( ! empty( $allowed ) ) {
			$term_args['include'] = $allowed;
		}
	}
}

$categories = get_terms( $term_args );

// Which type a category belongs to (first type that allows it). Tiles fall
// back to the type's icon and colour when the category has none, and the
// expanded list groups by it.
$listora_cat_type = array();
foreach ( $registry->get_active() as $listora_type ) {
	foreach ( $listora_type->get_allowed_categories() as $listora_cat_id ) {
		if ( ! isset( $listora_cat_type[ (int) $listora_cat_id ] ) ) {
			$listora_cat_type[ (int) $listora_cat_id ] = $listora_type;
		}
	}
}

if ( is_wp_error( $categories ) || empty( $categories ) ) {
	// Canonical empty state (Part 7.6.1 / F9). The legacy
	// `.listora-categories--empty` class still applies for any theme
	// override that targets it.
	$empty_attrs = get_block_wrapper_attributes(
		array( 'class' => 'listora-block listora-categories listora-categories--empty listora-card listora-card--empty' )
	);
	?>
	<div <?php echo $empty_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="status">
		<div class="listora-categories__empty listora-empty">
			<span class="listora-empty__icon" aria-hidden="true">
				<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
					<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
				</svg>
			</span>
			<h3 class="listora-empty__title"><?php esc_html_e( 'No categories yet', 'wb-listora' ); ?></h3>
			<p class="listora-empty__desc"><?php esc_html_e( 'Categories will appear here once listings are organized.', 'wb-listora' ); ?></p>
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

// The expanded list: one group per type, in registry order, then whatever no
// type claims. Only built when there is more than the grid shows.
$listora_groups = array();
if ( count( $categories ) > $limit ) {
	$listora_grouped = array();
	$listora_other   = array();
	foreach ( $categories as $listora_cat ) {
		$listora_owner = $listora_cat_type[ (int) $listora_cat->term_id ] ?? null;
		if ( $listora_owner ) {
			$listora_grouped[ $listora_owner->get_slug() ]['label']  = $listora_owner->get_name();
			$listora_grouped[ $listora_owner->get_slug() ]['cats'][] = $listora_cat;
		} else {
			$listora_other[] = $listora_cat;
		}
	}
	foreach ( $registry->get_active() as $listora_slug => $listora_type ) {
		if ( isset( $listora_grouped[ $listora_slug ] ) ) {
			$listora_groups[] = $listora_grouped[ $listora_slug ];
		}
	}
	if ( $listora_other ) {
		$listora_groups[] = array(
			'label' => __( 'More categories', 'wb-listora' ),
			'cats'  => $listora_other,
		);
	}
}

// ─── Assemble $view_data for templates ───
$view_data = array(
	'wrapper_attrs' => $wrapper_attrs,
	'categories'    => array_slice( $categories, 0, max( 1, (int) $limit ) ),
	'total_count'   => count( $categories ),
	'groups'        => $listora_groups,
	'cat_types'     => $listora_cat_type,
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
