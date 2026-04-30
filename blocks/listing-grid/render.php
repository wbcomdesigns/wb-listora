<?php
/**
 * Listing Grid block — displays search results.
 *
 * Server-renders initial results. Interactivity API handles
 * live search updates, view mode switching, pagination.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

// Grid renders card sub-blocks programmatically — enqueue card styles explicitly.
$card_style_path = WB_LISTORA_PLUGIN_DIR . 'blocks/listing-card/style.css';
if ( file_exists( $card_style_path ) ) {
	wp_enqueue_style(
		'listora-listing-card',
		WB_LISTORA_PLUGIN_URL . 'blocks/listing-card/style.css',
		array( 'listora-shared' ),
		filemtime( $card_style_path )
	);

	// RTL: WordPress auto-swaps to listing-card/style-rtl.css on RTL sites.
	wp_style_add_data( 'listora-listing-card', 'rtl', 'replace' );
}

$unique_id         = $attributes['uniqueId'] ?? '';
$listing_type      = $attributes['listingType'] ?? '';
$columns           = $attributes['columns'] ?? 3;
$per_page          = $attributes['perPage'] ?? 20;
$default_view      = $attributes['defaultView'] ?? 'grid';
$show_view_toggle  = $attributes['showViewToggle'] ?? true;
$show_result_count = $attributes['showResultCount'] ?? true;
$show_sort         = $attributes['showSort'] ?? true;
$show_pagination   = $attributes['showPagination'] ?? true;
$card_layout       = $attributes['cardLayout'] ?? 'standard';

// Read current page from URL param for server-side rendering and SEO.
$current_page = isset( $_GET['listora_page'] ) ? max( 1, (int) $_GET['listora_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ─── Read search query params from URL ───
//
// The Search block's submit handler navigates to the current URL with
// ?keyword=…&type=…&category=…&sort=… so the grid below can render the
// filtered results server-side (which keeps share/refresh/back-button
// working and gives search engines crawlable result pages).
//
// Without this block the grid would render the same unfiltered list
// regardless of what's in the URL — clicking "Search" would change the
// address bar but not the cards. phpcs nonce-verification is silenced
// because read-only filtering doesn't need a nonce.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

$grid_keyword     = isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['keyword'] ) ) : '';
$grid_url_type    = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '';
$grid_url_sort    = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( (string) $_GET['sort'] ) ) : '';
$grid_date_filter = isset( $_GET['date_filter'] ) ? sanitize_key( wp_unslash( (string) $_GET['date_filter'] ) ) : '';
$grid_date_from   = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : '';
$grid_date_to     = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : '';

// Category / location accept either a slug or numeric term ID. We resolve
// to a term_id here because Search_Engine's tax-query path keys off IDs.
$grid_category_id = wb_listora_resolve_term_id( isset( $_GET['category'] ) ? wp_unslash( (string) $_GET['category'] ) : '', 'listora_listing_cat' );
$grid_location_id = wb_listora_resolve_term_id( isset( $_GET['location'] ) ? wp_unslash( (string) $_GET['location'] ) : '', 'listora_listing_location' );

// phpcs:enable WordPress.Security.NonceVerification.Recommended

// A type pinned via the block attribute always wins over the URL —
// otherwise a "Restaurants" grid would silently switch to "Hotels"
// just because someone shared a URL with ?type=hotel.
$effective_type = $listing_type ? $listing_type : $grid_url_type;

// Sort allowlist (mirrors the dropdown options below). Falls back to
// the block default if a stranger pushes ?sort=evil — defence in depth.
$allowed_sorts = array( 'featured', 'newest', 'rating', 'price_asc', 'price_desc', 'most_reviewed', 'alphabetical', 'distance', 'relevance' );
$effective_sort = in_array( $grid_url_sort, $allowed_sorts, true ) ? $grid_url_sort : 'featured';

// Fetch initial results (server-rendered for SEO).
$search_args = array(
	'type'        => $effective_type,
	'keyword'     => $grid_keyword,
	'category'    => $grid_category_id,
	'location'    => $grid_location_id,
	'date_filter' => $grid_date_filter,
	'date_from'   => $grid_date_from,
	'date_to'     => $grid_date_to,
	'page'        => $current_page,
	'per_page'    => $per_page,
	'sort'        => $effective_sort,
);

/** Hook: Filter the listing grid query args before search. @since 1.1.0 */
$search_args = apply_filters( 'wb_listora_grid_query_args', $search_args, $attributes );

$engine = new \WBListora\Search\Search_Engine();
$result = $engine->search( $search_args );
$total  = $result['total'];
$pages  = $result['pages'];
$ids    = $result['listing_ids'];

// Prepare card data for each listing.
$listings_data = array();
foreach ( $ids as $lid ) {
	$data = wb_listora_prepare_card_data( $lid );
	if ( $data ) {
		$listings_data[] = $data;
	}
}

// Save original block attributes before the card loop overwrites $attributes.
$grid_block_attributes = $attributes;

// Provide initial query results to the Interactivity API store so data-wp-text bindings
// don't override server-rendered counts with client-side defaults (totalResults: 0).
// pageFrom/pageTo must be seeded too — otherwise the toolbar live region reads
// "Showing 1–0 of 0 listings" to screen readers even while 20 cards render below.
$initial_page_from = $total > 0 ? ( $current_page - 1 ) * $per_page + 1 : 0;
$initial_page_to   = $total > 0 ? min( $current_page * $per_page, $total ) : 0;

wp_interactivity_state(
	'listora/directory',
	array(
		'totalResults' => $total,
		'totalPages'   => $pages,
		'pageFrom'     => $initial_page_from,
		'pageTo'       => $initial_page_to,
		'currentPage'  => $current_page,
	)
);

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                     => 'listora-grid-wrapper ' . $block_classes,
		'data-wp-interactive'       => 'listora/directory',
		'data-wp-class--is-loading' => 'state.isLoading',
		'style'                     => '--listora-grid-columns: ' . (int) $columns,
	)
);

// Sort options.
$sort_options = array(
	'featured'      => __( 'Featured', 'wb-listora' ),
	'newest'        => __( 'Newest', 'wb-listora' ),
	'rating'        => __( 'Highest Rated', 'wb-listora' ),
	'price_asc'     => __( 'Price: Low to High', 'wb-listora' ),
	'price_desc'    => __( 'Price: High to Low', 'wb-listora' ),
	'most_reviewed' => __( 'Most Reviewed', 'wb-listora' ),
	'alphabetical'  => __( 'A to Z', 'wb-listora' ),
);

if ( ! empty( $result['distances'] ) ) {
	$sort_options = array( 'distance' => __( 'Nearest', 'wb-listora' ) ) + $sort_options;
}

// Batch-load favorite counts to avoid N+1 queries.
$grid_fav_counts = array();
if ( ! empty( $listings_data ) ) {
	global $wpdb;
	$grid_fav_prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
	$grid_fav_ids    = wp_list_pluck( $listings_data, 'id' );
	$grid_fav_ph     = implode( ',', array_fill( 0, count( $grid_fav_ids ), '%d' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$grid_fav_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT listing_id, COUNT(*) AS cnt FROM {$grid_fav_prefix}favorites WHERE listing_id IN ({$grid_fav_ph}) GROUP BY listing_id",
			...$grid_fav_ids
		),
		ARRAY_A
	);
	foreach ( $grid_fav_rows as $row ) {
		$grid_fav_counts[ (int) $row['listing_id'] ] = (int) $row['cnt'];
	}
}

// Build base URL for server-side page links (preserves all existing query args).
$base_url = remove_query_arg( 'listora_page' );

// ─── Assemble $view_data for templates ───
$view_data = array(
	'wrapper_attrs'         => $wrapper_attrs,
	'show_result_count'     => $show_result_count,
	'show_view_toggle'      => $show_view_toggle,
	'show_sort'             => $show_sort,
	'show_pagination'       => $show_pagination,
	'total'                 => $total,
	'pages'                 => $pages,
	'current_page'          => $current_page,
	'per_page'              => $per_page,
	'columns'               => $columns,
	'default_view'          => $default_view,
	'card_layout'           => $card_layout,
	'sort_options'          => $sort_options,
	'effective_sort'        => $effective_sort,
	'listings_data'         => $listings_data,
	'grid_fav_counts'       => $grid_fav_counts,
	'grid_block_attributes' => $grid_block_attributes,
	'base_url'              => $base_url,
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

/** Hook: Fires before the listing grid wrapper is rendered. @since 1.1.0 */
do_action( 'wb_listora_before_listing_grid', $grid_block_attributes );

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

wb_listora_get_template( 'blocks/listing-grid/grid.php', $view_data );

/** Hook: Fires after the listing grid wrapper is closed. @since 1.1.0 */
do_action( 'wb_listora_after_listing_grid', $grid_block_attributes );
