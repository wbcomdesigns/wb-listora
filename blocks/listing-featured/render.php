<?php
/**
 * Featured Listings block — carousel of top listings.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-base' );
wp_enqueue_script( 'listora-interactivity-store' );

$unique_id    = sanitize_html_class( (string) ( $attributes['uniqueId'] ?? '' ) );
$listing_type = $attributes['listingType'] ?? '';
$count        = max( 1, (int) ( $attributes['count'] ?? 8 ) ); // Floor-guard: 0 reaches Search_Engine as per_page.
// Floor-guard: the editor enforces min 1, but the block-renderer REST API
// and saved content do not; columns 0 would fatal on the division in the
// dot-count calculation below.
$columns = max( 1, (int) ( $attributes['columns'] ?? 4 ) );
$sort    = $attributes['sort'] ?? 'featured';
$title   = $attributes['title'] ?? '';

// Query featured/top listings.
$engine          = new \WBListora\Search\Search_Engine();
$featured_q_args = array(
	'type'          => $listing_type,
	'sort'          => $sort,
	'per_page'      => $count,
	'page'          => 1,
	'featured_only' => 'featured' === $sort,
);
/** Hook: Filter the featured listings query args before search. @since 1.1.0 */
$featured_q_args = apply_filters( 'wb_listora_featured_query_args', $featured_q_args, $attributes );
$result          = $engine->search( $featured_q_args );

// Guard against unexpected search result shape.
$ids = isset( $result['listing_ids'] ) && is_array( $result['listing_ids'] ) ? $result['listing_ids'] : array();

/**
 * Filter whether Sort by Featured fills empty slots with top-rated listings.
 *
 * Off by default: a block that says "Featured" shows only featured listings,
 * fewer than Count if that is all there are, and its empty state when there
 * are none. Filling the rest with ordinary listings made every card look
 * featured. Return true for the old fill.
 *
 * @since 1.9.0
 *
 * @param bool  $backfill   Default false.
 * @param array $attributes Block attributes.
 */
$featured_backfill = (bool) apply_filters( 'wb_listora_featured_backfill', false, $attributes );

if ( $featured_backfill && count( $ids ) < $count && 'featured' === $sort ) {
	$more     = $engine->search(
		array(
			'type'     => $listing_type,
			'sort'     => 'rating',
			'per_page' => $count - count( $ids ),
			'page'     => 1,
		)
	);
	$more_ids = isset( $more['listing_ids'] ) && is_array( $more['listing_ids'] ) ? $more['listing_ids'] : array();
	$ids      = array_unique( array_merge( $ids, $more_ids ) );
}

if ( empty( $ids ) ) {
	// Canonical empty state: the block must not silently vanish; editors and
	// visitors get the same affordance the Categories and Reviews blocks provide.
	wb_listora_render_empty_state(
		array(
			'icon'               => 'star',
			'title'              => __( 'No featured listings yet', 'wb-listora' ),
			'description'        => __( 'Listings marked as featured will appear here.', 'wb-listora' ),
			'wrapper_attributes' => get_block_wrapper_attributes(
				array( 'class' => 'listora-block listora-featured listora-featured--empty listora-card listora-card--empty' )
			),
			'attrs'              => 'role="status"',
		)
	);
	return;
}

// "See all" opens the Directory page with this block's sort and type. The CPT
// archive would render through the theme's blog loop.
$archive_link = add_query_arg(
	array_filter(
		array(
			'listora_sort'     => $sort,
			'listora_type'     => $listing_type,
			'listora_featured' => 1,
		)
	),
	wb_listora_get_directory_url()
);

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-featured ' . $block_classes,
		'data-wp-interactive' => 'listora/directory',
		// Trailing semicolon ensures valid CSS when the block system appends additional inline styles.
		'style'               => '--listora-featured-columns: ' . (int) $columns . ';',
	)
);

$dot_count = max( 1, (int) ceil( count( $ids ) / $columns ) );

// Save original block attributes before the card loop overwrites $attributes.
$featured_block_attributes = $attributes;

/** Hook: Fires before the featured listings wrapper is rendered. @since 1.1.0 */
do_action( 'wb_listora_before_featured_listings', $featured_block_attributes );

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block_CSS::render builds the style tag and strips '<' from it.

// ─── Assemble $view_data for templates ───
$view_data = array(
	'wrapper_attrs'             => $wrapper_attrs,
	'title'                     => $title,
	'archive_link'              => $archive_link,
	'ids'                       => $ids,
	'columns'                   => $columns,
	'dot_count'                 => $dot_count,
	'featured_block_attributes' => $featured_block_attributes,
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

wb_listora_get_template( 'blocks/listing-featured/featured.php', $view_data );

/** Hook: Fires after the featured listings wrapper is closed. @since 1.1.0 */
do_action( 'wb_listora_after_featured_listings', $featured_block_attributes );
