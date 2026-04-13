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

// Fetch initial results (server-rendered for SEO).
$search_args = array(
	'type'     => $listing_type,
	'page'     => $current_page,
	'per_page' => $per_page,
	'sort'     => 'featured',
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
wp_interactivity_state(
	'listora/directory',
	array(
		'totalResults' => $total,
		'totalPages'   => $pages,
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
?>

<?php
/** Hook: Fires before the listing grid wrapper is rendered. @since 1.1.0 */
do_action( 'wb_listora_before_listing_grid', $grid_block_attributes );
?>

<?php echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Toolbar ─── ?>
	<div class="listora-grid__toolbar">

		<?php if ( $show_result_count ) : ?>
		<div class="listora-grid__count" aria-live="polite" role="status">
			<?php
			if ( $total > 0 ) {
				$from = ( $current_page - 1 ) * $per_page + 1;
				$to   = min( $current_page * $per_page, $total );
				printf(
					/* translators: 1: first result number, 2: last result number, 3: total results */
					__( 'Showing %1$s&ndash;%2$s of %3$s listings', 'wb-listora' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped spans.
					'<span data-wp-text="state.pageFrom">' . esc_html( number_format_i18n( $from ) ) . '</span>',
					'<span data-wp-text="state.pageTo">' . esc_html( number_format_i18n( $to ) ) . '</span>',
					'<span data-wp-text="state.totalResults">' . esc_html( number_format_i18n( $total ) ) . '</span>'
				);
			} else {
				printf(
					/* translators: %s: number of results */
					esc_html( _n( '%s result', '%s results', $total, 'wb-listora' ) ),
					'<span data-wp-text="state.totalResults">' . esc_html( number_format_i18n( $total ) ) . '</span>'
				);
			}
			?>
		</div>
		<?php endif; ?>

		<div class="listora-grid__toolbar-actions">

			<?php if ( $show_view_toggle ) : ?>
			<div class="listora-grid__view-toggle" role="radiogroup" aria-label="<?php esc_attr_e( 'View mode', 'wb-listora' ); ?>">
				<button
					type="button"
					role="radio"
					class="listora-grid__view-btn<?php echo 'list' !== $default_view ? ' is-active' : ''; ?>"
					data-wp-on--click="actions.setViewMode"
					data-wp-context='{"mode":"grid"}'
					data-wp-class--is-active="state.isGridView"
					aria-label="<?php esc_attr_e( 'Grid view', 'wb-listora' ); ?>"
				>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
						<rect x="3" y="3" width="7" height="7" rx="1"></rect>
						<rect x="14" y="3" width="7" height="7" rx="1"></rect>
						<rect x="3" y="14" width="7" height="7" rx="1"></rect>
						<rect x="14" y="14" width="7" height="7" rx="1"></rect>
					</svg>
				</button>
				<button
					type="button"
					role="radio"
					class="listora-grid__view-btn<?php echo 'list' === $default_view ? ' is-active' : ''; ?>"
					data-wp-on--click="actions.setViewMode"
					data-wp-context='{"mode":"list"}'
					data-wp-class--is-active="state.isListView"
					aria-label="<?php esc_attr_e( 'List view', 'wb-listora' ); ?>"
				>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
						<rect x="3" y="4" width="18" height="4" rx="1"></rect>
						<rect x="3" y="10" width="18" height="4" rx="1"></rect>
						<rect x="3" y="16" width="18" height="4" rx="1"></rect>
					</svg>
				</button>
			</div>
			<?php endif; ?>

			<?php if ( $show_sort ) : ?>
			<div class="listora-grid__sort">
				<label for="listora-sort" class="listora-sr-only"><?php esc_html_e( 'Sort by', 'wb-listora' ); ?></label>
				<select
					id="listora-sort"
					class="listora-input listora-select listora-grid__sort-select"
					data-wp-on--change="actions.setSort"
					data-wp-bind--value="state.sortBy"
				>
					<?php foreach ( $sort_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

		</div>
	</div>

	<?php // ─── Results Grid ─── ?>
	<div
		class="listora-grid__results listora-grid<?php echo 'list' === $default_view ? ' listora-grid--list' : ''; ?>"
		data-wp-class--listora-grid--list="state.isListView"
		role="list"
		aria-busy="false"
		data-wp-bind--aria-busy="state.isLoading"
		style="container-type: inline-size; container-name: listora-grid;"
	>
		<?php if ( ! empty( $listings_data ) ) : ?>
			<?php
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
			?>
			<?php foreach ( $listings_data as $card_index => $listing ) : ?>
				<?php
				// Determine card layout based on view mode.
				$current_layout = ( 'list' === $default_view ) ? 'horizontal' : $card_layout;

				// Render the card directly using card render.php.
				$card_attrs = array(
					'listingId'     => $listing['id'],
					'layout'        => $current_layout,
					'showRating'    => true,
					'showFavorite'  => true,
					'showType'      => true,
					'showFeatures'  => true,
					'maxMetaFields' => 4,
					'_listing_data' => $listing,
					'_card_index'   => $card_index,
					'_fav_count'    => $grid_fav_counts[ $listing['id'] ] ?? 0,
				);

				// Include the card render template.
				$attributes = $card_attrs; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				include WB_LISTORA_PLUGIN_DIR . 'blocks/listing-card/render.php';

				/** Hook: Fires after each card is rendered inside the listing grid. @since 1.1.0 */
				do_action( 'wb_listora_grid_after_card', $listing['id'], $grid_block_attributes );
				?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<?php // ─── Loading Skeletons (shown via Interactivity during search) ─── ?>
	<div class="listora-grid__skeletons listora-grid" data-wp-class--is-hidden="!state.isLoading" aria-hidden="true">
		<?php for ( $i = 0; $i < min( $per_page, 6 ); $i++ ) : ?>
		<div class="listora-card listora-card--skeleton">
			<div class="listora-card__media">
				<div class="listora-skeleton" style="aspect-ratio: var(--listora-card-image-ratio); width: 100%;"></div>
			</div>
			<div class="listora-card__body">
				<div class="listora-skeleton" style="height: 0.7rem; width: 60px; margin-block-end: 0.5rem;"></div>
				<div class="listora-skeleton" style="height: 1.1rem; width: 80%;"></div>
				<div class="listora-skeleton" style="height: 0.85rem; width: 50%; margin-block-start: 0.4rem;"></div>
				<div style="display: flex; gap: 0.4em; margin-block-start: 0.5rem;">
					<div class="listora-skeleton" style="height: 0.8rem; width: 40px;"></div>
					<div class="listora-skeleton" style="height: 0.8rem; width: 50px;"></div>
					<div class="listora-skeleton" style="height: 0.8rem; width: 45px;"></div>
				</div>
			</div>
		</div>
		<?php endfor; ?>
	</div>

	<?php // ─── Empty State ─── ?>
	<div class="listora-grid__empty<?php echo $total > 0 ? ' is-hidden' : ''; ?>" data-wp-class--is-hidden="!state.showEmptyState">
		<div class="listora-grid__empty-inner">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>
			</svg>
			<h3><?php esc_html_e( 'No listings found', 'wb-listora' ); ?></h3>
			<p><?php esc_html_e( 'Try adjusting your filters or search in a different area.', 'wb-listora' ); ?></p>
			<button
				type="button"
				class="listora-btn listora-btn--secondary"
				data-wp-on--click="actions.clearAllFilters"
			>
				<?php esc_html_e( 'Clear All Filters', 'wb-listora' ); ?>
			</button>
		</div>
	</div>

	<?php // ─── Pagination ─── ?>
	<?php
	if ( $show_pagination && $pages > 1 ) :
		// Build base URL for server-side page links (preserves all existing query args).
		$base_url = remove_query_arg( 'listora_page' );
		?>
	<nav class="listora-grid__pagination" aria-label="<?php esc_attr_e( 'Pagination', 'wb-listora' ); ?>" data-wp-class--is-hidden="!state.showPagination">
		<?php
		$prev_url = $current_page > 1 ? add_query_arg( 'listora_page', $current_page - 1, $base_url ) : '';
		?>
		<a
			<?php
			if ( $prev_url ) :
				?>
				href="<?php echo esc_url( $prev_url ); ?>"<?php endif; ?>
			class="listora-btn listora-btn--icon listora-grid__page-btn"
			data-wp-on--click="actions.prevPage"
			<?php
			if ( $current_page <= 1 ) :
				?>
				aria-disabled="true" tabindex="-1"<?php endif; ?>
			aria-label="<?php esc_attr_e( 'Previous page', 'wb-listora' ); ?>"
		>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="m15 18-6-6 6-6"></path>
			</svg>
		</a>

		<div class="listora-grid__page-numbers">
			<?php
			// Render page number links (max 7 visible).
			$max_visible = 7;
			$start       = max( 1, min( (int) ceil( $max_visible / 2 ), $pages - $max_visible + 1 ) );
			$end         = min( $pages, $start + $max_visible - 1 );

			if ( $start > 1 ) :
				$page_url = add_query_arg( 'listora_page', 1, $base_url );
				?>
				<a href="<?php echo esc_url( $page_url ); ?>"
					class="listora-grid__page-num<?php echo 1 === $current_page ? ' is-active' : ''; ?>"
					data-wp-on--click="actions.setPage"
					data-wp-context='<?php echo wp_json_encode( array( 'page' => 1 ) ); ?>'
					<?php
					if ( 1 === $current_page ) :
						?>
						aria-current="page"<?php endif; ?>
				>1</a>
				<?php if ( $start > 2 ) : ?>
				<span class="listora-grid__page-ellipsis">&hellip;</span>
					<?php
				endif;
			endif;

			for ( $p = $start; $p <= $end; $p++ ) :
				if ( 1 === $p && $start > 1 ) {
					continue;
				}
				$page_url = add_query_arg( 'listora_page', $p, $base_url );
				?>
				<a
					href="<?php echo esc_url( $page_url ); ?>"
					class="listora-grid__page-num<?php echo $p === $current_page ? ' is-active' : ''; ?>"
					data-wp-on--click="actions.setPage"
					data-wp-context='<?php echo wp_json_encode( array( 'page' => $p ) ); ?>'
					<?php
					if ( $p === $current_page ) :
						?>
						aria-current="page"<?php endif; ?>
				><?php echo esc_html( $p ); ?></a>
				<?php
			endfor;

			if ( $end < $pages ) :
				if ( $end < $pages - 1 ) :
					?>
				<span class="listora-grid__page-ellipsis">&hellip;</span>
					<?php
				endif;
				$page_url = add_query_arg( 'listora_page', $pages, $base_url );
				?>
				<a
					href="<?php echo esc_url( $page_url ); ?>"
					class="listora-grid__page-num<?php echo $pages === $current_page ? ' is-active' : ''; ?>"
					data-wp-on--click="actions.setPage"
					data-wp-context='<?php echo wp_json_encode( array( 'page' => $pages ) ); ?>'
					<?php
					if ( $pages === $current_page ) :
						?>
						aria-current="page"<?php endif; ?>
				><?php echo esc_html( $pages ); ?></a>
			<?php endif; ?>
		</div>

		<?php $next_url = $current_page < $pages ? add_query_arg( 'listora_page', $current_page + 1, $base_url ) : ''; ?>
		<a
			<?php
			if ( $next_url ) :
				?>
				href="<?php echo esc_url( $next_url ); ?>"<?php endif; ?>
			class="listora-btn listora-btn--icon listora-grid__page-btn"
			data-wp-on--click="actions.nextPage"
			<?php
			if ( $current_page >= $pages ) :
				?>
				aria-disabled="true" tabindex="-1"<?php endif; ?>
			aria-label="<?php esc_attr_e( 'Next page', 'wb-listora' ); ?>"
		>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="m9 18 6-6-6-6"></path>
			</svg>
		</a>
	</nav>
	<?php endif; ?>

</div>
<?php
/** Hook: Fires after the listing grid wrapper is closed. @since 1.1.0 */
do_action( 'wb_listora_after_listing_grid', $grid_block_attributes );
