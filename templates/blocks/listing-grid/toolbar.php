<?php
/**
 * Listing Grid — Toolbar template (result count, view toggle, sort).
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-grid/toolbar.php
 *
 * @package WBListora
 *
 * @var bool   $show_result_count Whether to show the result count.
 * @var bool   $show_view_toggle  Whether to show the grid/list view toggle.
 * @var bool   $show_sort         Whether to show the sort dropdown.
 * @var int    $total             Total number of results.
 * @var int    $current_page      Current page number.
 * @var int    $per_page          Results per page.
 * @var string $default_view      Default view mode ('grid' or 'list').
 * @var array  $sort_options      Sort options as value => label pairs.
 * @var array  $view_data         Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
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
