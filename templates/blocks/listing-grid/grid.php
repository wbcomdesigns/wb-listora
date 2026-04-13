<?php
/**
 * Listing Grid — Main grid template (toolbar + results + pagination).
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-grid/grid.php
 *
 * @package WBListora
 *
 * @var string $wrapper_attrs          Block wrapper attributes string.
 * @var bool   $show_result_count      Whether to show the result count.
 * @var bool   $show_view_toggle       Whether to show the grid/list view toggle.
 * @var bool   $show_sort              Whether to show the sort dropdown.
 * @var bool   $show_pagination        Whether to show pagination controls.
 * @var int    $total                  Total number of results.
 * @var int    $pages                  Total number of pages.
 * @var int    $current_page           Current page number.
 * @var int    $per_page               Results per page.
 * @var int    $columns                Number of grid columns.
 * @var string $default_view           Default view mode ('grid' or 'list').
 * @var string $card_layout            Card layout style.
 * @var array  $sort_options           Sort options as value => label pairs.
 * @var array  $listings_data          Array of listing data arrays.
 * @var array  $grid_fav_counts        Favorite counts keyed by listing ID.
 * @var array  $grid_block_attributes  Original block attributes for hooks/cards.
 * @var string $base_url               Base URL for building page links.
 * @var array  $view_data              Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Toolbar ─── ?>
	<?php wb_listora_get_template( 'blocks/listing-grid/toolbar.php', $view_data ); ?>

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
	<?php wb_listora_get_template( 'blocks/listing-grid/pagination.php', $view_data ); ?>

</div>
