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

wp_enqueue_style( 'listora-base' );

// Grid renders card sub-blocks programmatically — enqueue card styles explicitly.
$card_style_path = WB_LISTORA_PLUGIN_DIR . 'blocks/listing-card/style.css';
if ( file_exists( $card_style_path ) ) {
	wp_enqueue_style(
		'listora-listing-card',
		WB_LISTORA_PLUGIN_URL . 'blocks/listing-card/style.css',
		array( 'listora-base' ),
		(string) filemtime( $card_style_path )
	);

	// RTL: WordPress auto-swaps to listing-card/style-rtl.css on RTL sites.
	wp_style_add_data( 'listora-listing-card', 'rtl', 'replace' );
}

$unique_id         = $attributes['uniqueId'] ?? '';
$listing_type      = $attributes['listingType'] ?? '';
$columns           = max( 1, (int) ( $attributes['columns'] ?? 3 ) ); // Floor-guard: REST/saved content can carry 0, which breaks the grid track count (BC #9989784605 family).
$per_page          = max( 1, (int) ( $attributes['perPage'] ?? wb_listora_get_setting( 'per_page', 20 ) ) ); // Floor-guard: 0 fatals in Search_Engine pagination (BC #9989784605 family).
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
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filtering.
$grid_url_type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '';

// A type pinned via the block attribute always wins over the URL -
// otherwise a "Restaurants" grid would silently switch to "Hotels"
// just because someone shared a URL with ?type=hotel.
$effective_type = $listing_type ? $listing_type : $grid_url_type;

// Sort allowlist lives in the shared helper so the grid dropdown, the map, and
// any future filtered surface accept exactly the same set.
$effective_sort = wb_listora_search_sort_from_url();

// Fetch initial results (server-rendered for SEO).
//
// Built through the shared URL parser so the grid and the map cannot drift
// apart on what "the current search" means - they render side by side on the
// Directory page, and when the map parsed only `bounds` the two contradicted
// each other in public. See includes/search/search-url-helpers.php.
$search_args = wb_listora_search_args_from_url(
	array(
		'type'     => $effective_type,
		'page'     => $current_page,
		'per_page' => $per_page,
		'sort'     => $effective_sort,
	)
);

/** Hook: Filter the listing grid query args before search. @since 1.1.0 */
$search_args = apply_filters( 'wb_listora_grid_query_args', $search_args, $attributes );

$engine = new \WBListora\Search\Search_Engine();
$result = $engine->search( $search_args );
$total  = $result['total'];
$pages  = $result['pages'];
$ids    = $result['listing_ids'];

// Prime the review-stats cache for every listing on this page in ONE query
// before the render loop, so wb_listora_prepare_card_data()'s per-card rating
// lookup becomes a cache hit instead of a per-card search_index query (the
// N+1 the grid render previously incurred — 20 extra queries at 20 cards/page).
wb_listora_prime_card_index_rows( $ids );

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

// The type this grid actually rendered. Load More / infinite scroll build
// their next-page request from state.selectedType, which only the Search
// block seeds - so a grid pinned to Restaurants with no search block on
// the page appended every type from page 2 on. Only set when there is a
// type, so an unpinned grid never clears a Search block's selection.
if ( '' !== $effective_type ) {
	wp_interactivity_state( 'listora/directory', array( 'selectedType' => $effective_type ) );
}

wp_interactivity_state(
	'listora/directory',
	array(
		'totalResults'    => $total,
		'totalPages'      => $pages,
		'pageFrom'        => $initial_page_from,
		'pageTo'          => $initial_page_to,
		'currentPage'     => $current_page,
		// Override the global `perPage` (seeded in class-assets.php from
		// the `per_page` setting) with this grid block's own `perPage`
		// attribute. The grid SSR uses the block attribute, so any
		// follow-up REST call (search, sort, infinite-scroll load-more)
		// must use the same page size or the next page's listings will
		// overlap or skip rows already rendered.
		'perPage'         => (int) $per_page,
		// When the server already rendered a 0-result state (e.g. visiting
		// `/business/` with no Business listings yet), seed `hasSearched`
		// to true so the IAPI `showEmptyState` getter resolves true on
		// hydration and the empty card stays visible. Without this seed,
		// the binding `data-wp-class--is-hidden="!state.showEmptyState"`
		// hides the server-rendered empty state the moment hydration
		// runs, leaving the page looking blank.
		'hasSearched'     => 0 === $total,
		// The block's Default View setting, read by the isGridView/isListView
		// getters whenever the visitor has not chosen a view of their own.
		// It used to reach the client only through an init callback that no
		// directive ever called, so a grid set to List painted list on the
		// server and flipped to grid on hydration (card 10294600329).
		'defaultViewMode' => 'list' === $default_view ? 'list' : 'grid',
	)
);

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

/*
 * Per-grid state, carried on the block itself.
 *
 * Everything above is seeded into the ONE `listora/directory` state, which is
 * shared by every block on the page - so two grids on one page overwrote each
 * other and the last one rendered won. Clicking the first grid's Load More
 * fetched the second grid's type and page size, and the restaurants section
 * filled up with hotels (card 10314572173).
 *
 * The global seeding stays: the Search block reads and writes those same keys,
 * and on the single-grid pages that are the common case the two agree. This
 * context is what any per-grid action must read instead.
 */
$grid_context = array(
	'gridType'        => $effective_type,
	'gridPerPage'     => (int) $per_page,
	'gridTotalPages'  => (int) $pages,
	'gridTotalItems'  => (int) $total,
	'gridCurrentPage' => (int) $current_page,
	'gridLoadedPages' => (int) $current_page,
	'gridPageFrom'    => (int) $initial_page_from,
	'gridPageTo'      => (int) $initial_page_to,
	'gridViewMode'    => 'list' === $default_view ? 'list' : 'grid',
	'gridLoadingMore' => false,
);

/*
 * The toolbar's "Showing X-Y of Z" reads these derived values, not the shared
 * pageFrom/pageTo/totalResults, so each grid prints its own range. With one
 * grid on the page the client getters fall back to the shared keys, which the
 * Search block maintains (card 10323784115). Server-side they read this
 * grid's context, formatted as before.
 */
$listora_grid_count = static function ( $key ) {
	return static function () use ( $key ) {
		$ctx = wp_interactivity_get_context();
		return number_format_i18n( (int) ( $ctx[ $key ] ?? 0 ) );
	};
};
wp_interactivity_state(
	'listora/directory',
	array(
		'gridCountFrom'  => $listora_grid_count( 'gridPageFrom' ),
		'gridCountTo'    => $listora_grid_count( 'gridPageTo' ),
		'gridCountTotal' => $listora_grid_count( 'gridTotalItems' ),
	)
);

// wp_json_encode() returns false on failure; the wrapper helper takes strings
// only, and an empty context is a grid the client can still read defaults from.
$grid_context_json = (string) wp_json_encode( $grid_context );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                     => 'listora-grid-wrapper ' . $block_classes,
		'data-wp-interactive'       => 'listora/directory',
		'data-wp-context'           => '' !== $grid_context_json ? $grid_context_json : '{}',
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

// Did the VISITOR narrow this grid, or is it empty as the owner built it?
// `type` is excluded deliberately: a pinned grid sets it from the block
// attribute, not from anything the visitor chose.
// The owner picked a type by NAME in the editor, so the empty state says the
// name back. An unknown slug has no type object and falls back to the slug
// itself, which is what makes a typo visible instead of silent.
$grid_pinned_type_object = $listing_type ? \WBListora\Core\Listing_Type_Registry::instance()->get( $listing_type ) : null;
$grid_pinned_type_label  = $grid_pinned_type_object ? $grid_pinned_type_object->get_name() : '';

$grid_visitor_filtered = false;
foreach ( array( 'keyword', 'category', 'location', 'features', 'tags', 'min_rating', 'date_filter', 'date_from', 'date_to', 'bounds' ) as $grid_filter_key ) {
	if ( ! empty( $search_args[ $grid_filter_key ] ) ) {
		$grid_visitor_filtered = true;
		break;
	}
}

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
	// The empty state needs to know whether this grid is pinned to a type, so
	// it can stop telling a visitor to adjust filters they did not set
	// (card 10217484053). Only when the visitor has set no filters of their
	// own - with a keyword in the box, "Clear All Filters" is the right offer
	// and the emptiness is not the pinned type's fault.
	'pinned_type'           => $grid_visitor_filtered ? '' : $listing_type,
	'pinned_type_label'     => $grid_pinned_type_label,
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
