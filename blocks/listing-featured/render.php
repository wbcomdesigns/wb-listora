<?php
/**
 * Featured Listings block — carousel of top listings.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-base' );
wp_enqueue_script( 'listora-interactivity-store' );

$unique_id    = $attributes['uniqueId'] ?? '';
$listing_type = $attributes['listingType'] ?? '';
$count        = max( 1, (int) ( $attributes['count'] ?? 8 ) ); // Floor-guard: 0 reaches Search_Engine as per_page (BC #9989784605 family).
// Floor-guard: the editor enforces min 1, but the block-renderer REST API
// and saved content do not — columns 0 fatals on the division at the
// dot-count calculation below (BC #9989784605).
$columns      = max( 1, (int) ( $attributes['columns'] ?? 4 ) );
$sort         = $attributes['sort'] ?? 'featured';
$title        = $attributes['title'] ?? '';

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

// If not enough featured, fill with top-rated.
if ( count( $ids ) < $count && 'featured' === $sort ) {
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
	// Canonical empty state (Part 7.6.1 / F9, BC #9977213192) — the block
	// must not silently vanish; editors and visitors get the same affordance
	// the Categories and Reviews blocks provide.
	$empty_attrs = get_block_wrapper_attributes(
		array( 'class' => 'listora-featured listora-featured--empty listora-card listora-card--empty' )
	);
	?>
	<div <?php echo $empty_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="status">
		<div class="listora-featured__empty listora-empty">
			<span class="listora-empty__icon" aria-hidden="true">
				<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
					<path d="M11.48 3.5a.56.56 0 0 1 1.04 0l2.13 5.11 5.52.44a.56.56 0 0 1 .32.99l-4.2 3.6 1.28 5.39a.56.56 0 0 1-.84.6L12 16.73l-4.73 2.9a.56.56 0 0 1-.84-.61l1.28-5.38-4.2-3.6a.56.56 0 0 1 .32-1l5.52-.43z"></path>
				</svg>
			</span>
			<h3 class="listora-empty__title"><?php esc_html_e( 'No featured listings yet', 'wb-listora' ); ?></h3>
			<p class="listora-empty__desc"><?php esc_html_e( 'Listings marked as featured will appear here.', 'wb-listora' ); ?></p>
		</div>
	</div>
	<?php
	return;
}

// Build "See all" URL.
$archive_link = get_post_type_archive_link( 'listora_listing' );

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

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

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
