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
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its output. ?>>

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
		// Stash the grid's $view_data BEFORE the card loop: the card's
		// render.php is `include`d into this scope and overwrites $view_data,
		// so toolbar, pagination and empty-state would get the last card's data.
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
				$attributes = $card_attrs; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- template runs in function scope, so this is a local, not the global.
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

	<?php
	/*
	 * Empty state. A grid pinned to a listing type is empty because of how the
	 * page was built, not anything the visitor did, so it gets neither "try
	 * adjusting your filters" nor a Clear All Filters button. "Add a listing"
	 * is an invitation, so it follows the submission toggle, not only whether
	 * a submit URL exists.
	 */
	$grid_pinned_type       = isset( $pinned_type ) ? (string) $pinned_type : '';
	$grid_pinned_type_label = isset( $pinned_type_label ) && '' !== $pinned_type_label ? $pinned_type_label : $grid_pinned_type;
	$grid_submit_url        = wb_listora_can_offer_submission() ? wb_listora_get_submit_url() : '';
	wb_listora_render_empty_state(
		array(
			'icon'        => 'search',
			'title'       => '' !== $grid_pinned_type
				/* translators: %s: listing type name, e.g. Restaurant. */
				? sprintf( __( 'No %s listings yet', 'wb-listora' ), $grid_pinned_type_label )
				: __( 'No listings found', 'wb-listora' ),
			'description' => '' !== $grid_pinned_type
				? __( 'This section only shows listings of one type, and there are none yet.', 'wb-listora' )
				: __( 'Try adjusting your filters, or be the first to add a listing.', 'wb-listora' ),
			'class'       => 'listora-grid__empty' . ( $total > 0 ? ' is-hidden' : '' ),
			'attrs'       => 'data-wp-class--is-hidden="!state.showEmptyState" role="status"',
			'actions'     => static function () use ( $grid_submit_url, $grid_pinned_type ) {
				if ( $grid_submit_url ) {
					echo '<a class="listora-btn listora-btn--primary" href="' . esc_url( $grid_submit_url ) . '">' . esc_html__( 'Add a listing', 'wb-listora' ) . '</a>';
				}
				// Nothing to clear on a grid pinned to one type.
				if ( '' === $grid_pinned_type ) {
					echo '<button type="button" class="listora-btn listora-btn--secondary" data-wp-on--click="actions.clearAllFilters">' . esc_html__( 'Clear All Filters', 'wb-listora' ) . '</button>';
				}
			},
		)
	);
	?>

	<?php // ─── Pagination ─── ?>
	<?php wb_listora_get_template( 'blocks/listing-grid/pagination.php', $view_data ); ?>

</div>
