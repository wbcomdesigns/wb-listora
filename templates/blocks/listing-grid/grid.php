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
	>
		<?php
		// Stash the grid's $view_data BEFORE the card loop. The card's
		// render.php is `include`d into this scope (not via
		// `wb_listora_get_template`) and freely overwrites $view_data with
		// per-card data. Without preserving and restoring, downstream
		// templates (toolbar, pagination, empty-state) would receive the
		// LAST card's data instead of the grid's. That's why pagination
		// was silently disappearing on Directory pages — pages=0 read off
		// a card array (Basecamp 9842853418).
		$grid_view_data = $view_data;
		?>
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
		<?php
		// Restore the grid's view_data so the rest of this template (and any
		// `wb_listora_get_template` calls below) see the right scope.
		$view_data = $grid_view_data;
		extract( $grid_view_data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- restore individual variables overwritten by card render.
		?>
	</div>

	<?php // ─── Loading Skeletons (shown via Interactivity during search) ─── ?>
	<div class="listora-grid__skeletons listora-grid" data-wp-class--is-hidden="!state.isLoading" aria-hidden="true">
		<?php for ( $i = 0; $i < min( $per_page, 6 ); $i++ ) : ?>
		<div class="listora-card listora-card--skeleton">
			<div class="listora-card__media">
				<div class="listora-skeleton listora-skeleton--image"></div>
			</div>
			<div class="listora-card__body">
				<div class="listora-skeleton listora-skeleton--label"></div>
				<div class="listora-skeleton listora-skeleton--title"></div>
				<div class="listora-skeleton listora-skeleton--meta"></div>
				<div class="listora-skeleton__pills">
					<div class="listora-skeleton listora-skeleton--pill"></div>
					<div class="listora-skeleton listora-skeleton--pill"></div>
					<div class="listora-skeleton listora-skeleton--pill"></div>
				</div>
			</div>
		</div>
		<?php endfor; ?>
	</div>

	<?php // ─── Empty State (canonical .listora-card--empty .listora-empty / Part 7.6.1). ?>
	<div
		class="listora-grid__empty listora-card listora-card--empty<?php echo esc_attr( $total > 0 ? ' is-hidden' : '' ); ?>"
		data-wp-class--is-hidden="!state.showEmptyState"
		role="status"
	>
		<div class="listora-grid__empty-inner listora-empty">
			<span class="listora-empty__icon" aria-hidden="true">
				<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
					<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>
				</svg>
			</span>
			<h3 class="listora-empty__title"><?php esc_html_e( 'No listings found', 'wb-listora' ); ?></h3>
			<p class="listora-empty__desc"><?php esc_html_e( 'Try adjusting your filters, or be the first to add a listing.', 'wb-listora' ); ?></p>
			<div class="listora-empty__actions">
				<?php
				/*
				 * "be the first to add a listing" is an INVITATION, so it
				 * consults the submission toggle — not merely whether a URL
				 * can be built.
				 *
				 * It used to gate on wb_listora_get_submit_url() alone, and
				 * that helper never checks the feature; it even falls back to
				 * home_url( '/add-listing/' ) when no page is mapped, so it is
				 * effectively never empty. With submissions closed the empty
				 * state therefore still offered "Add a listing", and the page
				 * it led to answered "New listings are closed" — the exact
				 * ask-then-refuse the toggle exists to prevent.
				 */
				$grid_can_submit = ! function_exists( 'wb_listora_feature_enabled' ) || wb_listora_feature_enabled( 'submission' );
				$grid_submit_url = ( $grid_can_submit && function_exists( 'wb_listora_get_submit_url' ) ) ? wb_listora_get_submit_url() : '';
				if ( $grid_submit_url ) :
					?>
					<a class="listora-btn listora-btn--primary" href="<?php echo esc_url( $grid_submit_url ); ?>">
						<?php esc_html_e( 'Add a listing', 'wb-listora' ); ?>
					</a>
				<?php endif; ?>
				<button
					type="button"
					class="listora-btn listora-btn--secondary"
					data-wp-on--click="actions.clearAllFilters"
				>
					<?php esc_html_e( 'Clear All Filters', 'wb-listora' ); ?>
				</button>
			</div>
		</div>
	</div>

	<?php // ─── Pagination ─── ?>
	<?php wb_listora_get_template( 'blocks/listing-grid/pagination.php', $view_data ); ?>

</div>
