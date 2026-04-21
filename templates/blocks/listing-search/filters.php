<?php
/**
 * Listing Search — Expandable filters panel template.
 *
 * Renders the "Filters" toggle button and the expandable panel containing
 * universal filters, type-specific filters, date filters, and the "Clear All" action.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-search/filters.php
 *
 * @package WBListora
 *
 * @var string $unique_id         Block unique ID.
 * @var string $listing_type      Pre-selected listing type slug (empty for all).
 * @var bool   $show_keyword      Whether to show the keyword field.
 * @var bool   $show_location     Whether to show the location field.
 * @var bool   $show_type         Whether to show the type filter.
 * @var bool   $show_more         Whether to show the "More Filters" toggle.
 * @var bool   $show_near_me      Whether to show the "Near Me" button.
 * @var string $layout            Search layout ('horizontal' or 'vertical').
 * @var string $placeholder       Keyword input placeholder text.
 * @var string $default_sort      Default sort option.
 * @var array  $types             Array of Listing_Type objects.
 * @var string $active_type_slug  Active listing type slug.
 * @var array  $type_filters      Filter config for the active type.
 * @var string $wrapper_attrs     Rendered block wrapper attributes string.
 * @var array  $view_data         Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<?php // ─── More Filters Toggle ─── ?>
<div class="listora-search__filters-toggle">
	<button
		type="button"
		class="listora-btn listora-btn--text listora-search__toggle-btn"
		data-wp-on--click="actions.toggleFiltersPanel"
		aria-expanded="false"
		aria-controls="listora-filters-panel"
	>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<line x1="4" x2="4" y1="21" y2="14"></line><line x1="4" x2="4" y1="10" y2="3"></line>
			<line x1="12" x2="12" y1="21" y2="12"></line><line x1="12" x2="12" y1="8" y2="3"></line>
			<line x1="20" x2="20" y1="21" y2="16"></line><line x1="20" x2="20" y1="12" y2="3"></line>
			<line x1="1" x2="7" y1="14" y2="14"></line><line x1="9" x2="15" y1="8" y2="8"></line>
			<line x1="17" x2="23" y1="16" y2="16"></line>
		</svg>
		<span><?php esc_html_e( 'Filters', 'wb-listora' ); ?></span>
		<span class="listora-search__filter-count is-hidden" data-wp-text="state.activeFilterCount" data-wp-class--is-hidden="!state.hasActiveFilters"></span>
	</button>
</div>

<?php // ─── Expandable Filters Panel ─── ?>
<div
	id="listora-filters-panel"
	class="listora-search__filters-panel"
	role="group"
	aria-label="<?php esc_attr_e( 'Search filters', 'wb-listora' ); ?>"
	hidden
	data-wp-bind--hidden="!state.showFiltersPanel"
	data-wp-class--is-hidden="!state.showFiltersPanel"
>
	<?php // ── Universal Filters (always visible) ── ?>
	<div class="listora-search__filter-group">
		<span class="listora-search__filter-label" id="listora-filter-category-label">
			<?php esc_html_e( 'Category', 'wb-listora' ); ?>
		</span>
		<select
			class="listora-input listora-select listora-search__filter-select"
			aria-labelledby="listora-filter-category-label"
			data-wp-on--change="actions.setFilter"
			data-wp-context='<?php echo wp_json_encode( array( 'filterKey' => 'category' ) ); ?>'
		>
			<option value=""><?php esc_html_e( 'All Categories', 'wb-listora' ); ?></option>
			<?php
			$filter_cats = get_terms(
				array(
					'taxonomy'   => 'listora_listing_cat',
					'hide_empty' => true,
				)
			);
			if ( ! is_wp_error( $filter_cats ) ) :
				foreach ( $filter_cats as $fcat ) :
					?>
					<option value="<?php echo esc_attr( $fcat->slug ); ?>"><?php echo esc_html( $fcat->name ); ?></option>
					<?php
				endforeach;
			endif;
			?>
		</select>
	</div>

	<div class="listora-search__filter-group">
		<span class="listora-search__filter-label" id="listora-filter-rating-label">
			<?php esc_html_e( 'Minimum Rating', 'wb-listora' ); ?>
		</span>
		<select
			class="listora-input listora-select listora-search__filter-select"
			aria-labelledby="listora-filter-rating-label"
			data-wp-on--change="actions.setFilter"
			data-wp-context='<?php echo wp_json_encode( array( 'filterKey' => 'min_rating' ) ); ?>'
		>
			<option value=""><?php esc_html_e( 'Any', 'wb-listora' ); ?></option>
			<option value="4">&#9733;&#9733;&#9733;&#9733; &amp; up</option>
			<option value="3">&#9733;&#9733;&#9733; &amp; up</option>
			<option value="2">&#9733;&#9733; &amp; up</option>
		</select>
	</div>

	<div class="listora-search__filter-group">
		<span class="listora-search__filter-label"><?php esc_html_e( 'Features', 'wb-listora' ); ?></span>
		<div class="listora-search__filter-checkboxes">
			<?php
			$filter_features = get_terms(
				array(
					'taxonomy'   => 'listora_listing_feature',
					'hide_empty' => true,
					'number'     => 8,
				)
			);
			if ( ! is_wp_error( $filter_features ) ) :
				foreach ( $filter_features as $feat ) :
					?>
					<label class="listora-search__filter-checkbox">
						<input type="checkbox"
							data-wp-on--change="actions.toggleFeatureFilter"
							data-wp-context='<?php echo wp_json_encode( array( 'featureSlug' => $feat->slug ) ); ?>'
						/>
						<?php echo esc_html( $feat->name ); ?>
					</label>
					<?php
				endforeach;
			endif;
			?>
		</div>
	</div>

	<?php
	// Render type-specific filters from PHP (server-rendered initial state).
	if ( ! empty( $type_filters ) ) :
		foreach ( $type_filters as $filter ) :
			$filter_key     = $filter['key'];
			$filter_label   = $filter['label'];
			$filter_type    = $filter['type'];
			$filter_options = $filter['options'];
			?>
			<div class="listora-search__filter-group">
				<span class="listora-search__filter-label" id="listora-filter-<?php echo esc_attr( $filter_key ); ?>-label">
					<?php echo esc_html( $filter_label ); ?>
				</span>

				<?php if ( 'select' === $filter_type || 'radio' === $filter_type ) : ?>
				<select
					class="listora-input listora-select listora-search__filter-select"
					aria-labelledby="listora-filter-<?php echo esc_attr( $filter_key ); ?>-label"
					data-wp-on--change="actions.setFilterSelect"
					data-wp-context='<?php echo wp_json_encode( array( 'filterKey' => $filter_key ) ); ?>'
				>
					<option value=""><?php esc_html_e( 'All', 'wb-listora' ); ?></option>
					<?php foreach ( $filter_options as $opt ) : ?>
					<option value="<?php echo esc_attr( $opt['value'] ); ?>">
						<?php echo esc_html( $opt['label'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>

				<?php elseif ( 'multiselect' === $filter_type ) : ?>
				<div class="listora-search__filter-checkboxes" role="group" aria-labelledby="listora-filter-<?php echo esc_attr( $filter_key ); ?>-label">
					<?php foreach ( $filter_options as $opt ) : ?>
					<label class="listora-search__filter-checkbox">
						<input
							type="checkbox"
							value="<?php echo esc_attr( $opt['value'] ); ?>"
							data-wp-on--change="actions.setFilterCheckbox"
							data-wp-context='
							<?php
							echo wp_json_encode(
								array(
									'filterKey'   => $filter_key,
									'filterValue' => $opt['value'],
								)
							);
							?>
												'
						/>
						<span><?php echo esc_html( $opt['label'] ); ?></span>
					</label>
					<?php endforeach; ?>
				</div>

				<?php elseif ( 'checkbox' === $filter_type ) : ?>
				<label class="listora-search__filter-toggle">
					<input
						type="checkbox"
						data-wp-on--change="actions.setFilterToggle"
						data-wp-context='<?php echo wp_json_encode( array( 'filterKey' => $filter_key ) ); ?>'
					/>
					<span><?php echo esc_html( $filter_label ); ?></span>
				</label>

				<?php elseif ( 'business_hours' === $filter_type ) : ?>
				<label class="listora-search__filter-toggle">
					<input
						type="checkbox"
						data-wp-on--change="actions.setFilterToggle"
						data-wp-context='<?php echo wp_json_encode( array( 'filterKey' => 'open_now' ) ); ?>'
					/>
					<span><?php esc_html_e( 'Open Now', 'wb-listora' ); ?></span>
				</label>

				<?php elseif ( in_array( $filter_type, array( 'number', 'price' ), true ) ) : ?>
				<div class="listora-search__filter-range">
					<input
						type="number"
						class="listora-input listora-search__range-input"
						placeholder="<?php esc_attr_e( 'Min', 'wb-listora' ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: filter label */ __( 'Minimum %s', 'wb-listora' ), $filter_label ) ); ?>"
						data-wp-on--change="actions.setFilterSelect"
						data-wp-context='<?php echo wp_json_encode( array( 'filterKey' => $filter_key . '_min' ) ); ?>'
					/>
					<span class="listora-search__range-separator">–</span>
					<input
						type="number"
						class="listora-input listora-search__range-input"
						placeholder="<?php esc_attr_e( 'Max', 'wb-listora' ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: filter label */ __( 'Maximum %s', 'wb-listora' ), $filter_label ) ); ?>"
						data-wp-on--change="actions.setFilterSelect"
						data-wp-context='<?php echo wp_json_encode( array( 'filterKey' => $filter_key . '_max' ) ); ?>'
					/>
				</div>

				<?php endif; ?>
			</div>
			<?php
		endforeach;
	endif;
	?>

	<?php // ─── Date Filters (shown when type is "event") ─── ?>
	<div
		class="listora-search__filter-group listora-search__filter-group--date"
		data-wp-class--is-hidden="!state.isEventType"
	>
		<span class="listora-search__filter-label" id="listora-filter-date-label">
			<?php esc_html_e( 'Date', 'wb-listora' ); ?>
		</span>
		<div class="listora-search__date-filters" role="group" aria-labelledby="listora-filter-date-label">
			<div class="listora-search__date-presets">
				<button
					type="button"
					class="listora-btn listora-btn--sm listora-search__date-btn"
					data-wp-class--is-active="state.isDateFilterToday"
					data-wp-on--click="actions.setDateFilter"
					data-wp-context='<?php echo wp_json_encode( array( 'dateFilterValue' => 'today' ) ); ?>'
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line>
					</svg>
					<?php esc_html_e( 'Today', 'wb-listora' ); ?>
				</button>
				<button
					type="button"
					class="listora-btn listora-btn--sm listora-search__date-btn"
					data-wp-class--is-active="state.isDateFilterWeekend"
					data-wp-on--click="actions.setDateFilter"
					data-wp-context='<?php echo wp_json_encode( array( 'dateFilterValue' => 'weekend' ) ); ?>'
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
					</svg>
					<?php esc_html_e( 'This Weekend', 'wb-listora' ); ?>
				</button>
				<button
					type="button"
					class="listora-btn listora-btn--sm listora-search__date-btn"
					data-wp-class--is-active="state.isDateFilterHappeningNow"
					data-wp-on--click="actions.setDateFilter"
					data-wp-context='<?php echo wp_json_encode( array( 'dateFilterValue' => 'happening_now' ) ); ?>'
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
					</svg>
					<?php esc_html_e( 'Happening Now', 'wb-listora' ); ?>
				</button>
			</div>
			<div class="listora-search__date-range">
				<input
					type="date"
					class="listora-input listora-search__date-input"
					aria-label="<?php esc_attr_e( 'From date', 'wb-listora' ); ?>"
					data-wp-bind--value="state.dateFrom"
					data-wp-on--change="actions.setDateFrom"
				/>
				<span class="listora-search__range-separator">&ndash;</span>
				<input
					type="date"
					class="listora-input listora-search__date-input"
					aria-label="<?php esc_attr_e( 'To date', 'wb-listora' ); ?>"
					data-wp-bind--value="state.dateTo"
					data-wp-on--change="actions.setDateTo"
				/>
			</div>
		</div>
	</div>

	<div class="listora-search__filter-actions">
		<button
			type="button"
			class="listora-btn listora-btn--text listora-search__clear-all is-hidden"
			data-wp-on--click="actions.clearAllFilters"
			data-wp-class--is-hidden="!state.hasActiveFilters"
		>
			<?php esc_html_e( 'Clear All Filters', 'wb-listora' ); ?>
		</button>
	</div>
</div>
