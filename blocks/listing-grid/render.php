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

$listing_type      = $attributes['listingType'] ?? '';
$columns           = $attributes['columns'] ?? 3;
$per_page          = $attributes['perPage'] ?? 20;
$default_view      = $attributes['defaultView'] ?? 'grid';
$show_view_toggle  = $attributes['showViewToggle'] ?? true;
$show_result_count = $attributes['showResultCount'] ?? true;
$show_sort         = $attributes['showSort'] ?? true;
$show_pagination   = $attributes['showPagination'] ?? true;
$card_layout       = $attributes['cardLayout'] ?? 'standard';

// Fetch initial results (server-rendered for SEO).
$search_args = array(
	'type'     => $listing_type,
	'page'     => 1,
	'per_page' => $per_page,
	'sort'     => 'featured',
);

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

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                     => 'listora-grid-wrapper',
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

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Toolbar ─── ?>
	<div class="listora-grid__toolbar">

		<?php if ( $show_result_count ) : ?>
		<div class="listora-grid__count" aria-live="polite" role="status">
			<?php
			printf(
				/* translators: %s: number of results */
				esc_html( _n( '%s result', '%s results', $total, 'wb-listora' ) ),
				'<span data-wp-text="state.totalResults">' . esc_html( number_format_i18n( $total ) ) . '</span>'
			);
			?>
		</div>
		<?php endif; ?>

		<div class="listora-grid__toolbar-actions">

			<?php if ( $show_view_toggle ) : ?>
			<div class="listora-grid__view-toggle" role="radiogroup" aria-label="<?php esc_attr_e( 'View mode', 'wb-listora' ); ?>">
				<button
					type="button"
					role="radio"
					class="listora-grid__view-btn"
					data-wp-on--click="actions.setViewMode"
					data-wp-context='{"mode":"grid"}'
					data-wp-class--is-active="state.viewMode === 'grid' || !state.viewMode"
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
					class="listora-grid__view-btn"
					data-wp-on--click="actions.setViewMode"
					data-wp-context='{"mode":"list"}'
					data-wp-class--is-active="state.viewMode === 'list'"
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
		class="listora-grid__results listora-grid"
		data-wp-class--listora-grid--list="state.viewMode === 'list'"
		role="list"
		aria-busy="false"
		data-wp-bind--aria-busy="state.isLoading"
		style="container-type: inline-size; container-name: listora-grid;"
	>
		<?php if ( ! empty( $listings_data ) ) : ?>
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
				);

				// Include the card render template.
				$attributes = $card_attrs; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				include WB_LISTORA_PLUGIN_DIR . 'blocks/listing-card/render.php';
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
	<?php if ( $show_pagination && $pages > 1 ) : ?>
	<nav class="listora-grid__pagination" aria-label="<?php esc_attr_e( 'Pagination', 'wb-listora' ); ?>" data-wp-class--is-hidden="!state.showPagination">
		<button
			type="button"
			class="listora-btn listora-btn--icon listora-grid__page-btn"
			data-wp-on--click="actions.prevPage"
			data-wp-bind--disabled="state.currentPage <= 1"
			aria-label="<?php esc_attr_e( 'Previous page', 'wb-listora' ); ?>"
		>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="m15 18-6-6 6-6"></path>
			</svg>
		</button>

		<div class="listora-grid__page-numbers">
			<?php
			// Render page number buttons (max 7 visible).
			$max_visible = 7;
			$start       = max( 1, min( (int) ceil( $max_visible / 2 ), $pages - $max_visible + 1 ) );
			$end         = min( $pages, $start + $max_visible - 1 );

			if ( $start > 1 ) : ?>
				<button type="button" class="listora-grid__page-num is-active" data-wp-on--click="actions.setPage" data-wp-context='<?php echo wp_json_encode( array( 'page' => 1 ) ); ?>'>1</button>
				<?php if ( $start > 2 ) : ?>
				<span class="listora-grid__page-ellipsis">&hellip;</span>
				<?php endif; ?>
			<?php endif;

			for ( $p = $start; $p <= $end; $p++ ) :
				if ( 1 === $p && $start > 1 ) {
					continue;
				}
				?>
				<button
					type="button"
					class="listora-grid__page-num<?php echo 1 === $p ? ' is-active' : ''; ?>"
					data-wp-on--click="actions.setPage"
					data-wp-context='<?php echo wp_json_encode( array( 'page' => $p ) ); ?>'
				><?php echo esc_html( $p ); ?></button>
			<?php endfor;

			if ( $end < $pages ) : ?>
				<?php if ( $end < $pages - 1 ) : ?>
				<span class="listora-grid__page-ellipsis">&hellip;</span>
				<?php endif; ?>
				<button type="button" class="listora-grid__page-num" data-wp-on--click="actions.setPage" data-wp-context='<?php echo wp_json_encode( array( 'page' => $pages ) ); ?>'><?php echo esc_html( $pages ); ?></button>
			<?php endif; ?>
		</div>

		<button
			type="button"
			class="listora-btn listora-btn--icon listora-grid__page-btn"
			data-wp-on--click="actions.nextPage"
			data-wp-bind--disabled="state.currentPage >= state.totalPages"
			aria-label="<?php esc_attr_e( 'Next page', 'wb-listora' ); ?>"
		>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="m9 18 6-6-6-6"></path>
			</svg>
		</button>
	</nav>
	<?php endif; ?>

</div>
