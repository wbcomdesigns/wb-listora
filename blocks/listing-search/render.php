<?php
/**
 * Listing Search block — server-rendered with Interactivity API directives.
 *
 * @package WBListora
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// Enqueue shared styles.
wp_enqueue_style( 'listora-shared' );

$listing_type  = $attributes['listingType'] ?? '';
$show_keyword  = $attributes['showKeyword'] ?? true;
$show_location = $attributes['showLocation'] ?? true;
$show_type     = $attributes['showTypeFilter'] ?? true;
$show_more     = $attributes['showMoreFilters'] ?? true;
$show_near_me  = $attributes['showNearMe'] ?? true;
$layout        = $attributes['layout'] ?? 'horizontal';
$placeholder   = ! empty( $attributes['placeholder'] ) ? $attributes['placeholder'] : __( 'Search listings...', 'wb-listora' );
$default_sort  = $attributes['defaultSort'] ?? 'featured';

// Get listing types for type selector.
$registry = \WBListora\Core\Listing_Type_Registry::instance();
$types    = $registry->get_all();

// Get filter config for the pre-selected type (or first type).
$active_type_slug = $listing_type;
$type_filters     = array();

if ( $active_type_slug ) {
	$type_obj = $registry->get( $active_type_slug );
	if ( $type_obj ) {
		foreach ( $type_obj->get_filterable_fields() as $field ) {
			$ftype = $field->get_type();
			// Skip complex types that don't render as simple filters.
			if ( in_array( $ftype, array( 'map_location', 'gallery', 'wysiwyg', 'social_links', 'file', 'video' ), true ) ) {
				continue;
			}
			$type_filters[] = array(
				'key'     => $field->get_key(),
				'label'   => $field->get_label(),
				'type'    => $ftype,
				'options' => $field->get( 'options' ) ?: array(),
			);
		}
	}
}

// Prepare initial Interactivity context.
$context = array(
	'typeFilters' => array( $active_type_slug => $type_filters ),
);

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                     => 'listora-search listora-search--' . esc_attr( $layout ),
		'data-wp-interactive'       => 'listora/directory',
		'data-wp-init'              => 'callbacks.onSearchBlockInit',
		'data-wp-context'           => wp_json_encode( $context ),
		'data-wp-class--is-loading' => 'state.isLoading',
		'role'                      => 'search',
		'aria-label'                => esc_attr__( 'Search listings', 'wb-listora' ),
	)
);
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Loading Progress Bar (hidden by default, shown during search) ─── ?>
	<div
		class="listora-search__loading-bar"
		role="progressbar"
		aria-label="<?php esc_attr_e( 'Loading search results', 'wb-listora' ); ?>"
		hidden
		data-wp-bind--hidden="!state.isLoading"
	>
		<div class="listora-search__loading-bar-inner"></div>
	</div>

	<?php // ─── Main Search Bar ─── ?>
	<div class="listora-search__bar">

		<?php if ( $show_keyword ) : ?>
		<div class="listora-search__field listora-search__field--keyword">
			<label for="listora-keyword" class="listora-sr-only">
				<?php esc_html_e( 'Search', 'wb-listora' ); ?>
			</label>
			<svg class="listora-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>
			</svg>
			<input
				id="listora-keyword"
				type="search"
				class="listora-input listora-search__input"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				data-wp-bind--value="state.searchQuery"
				data-wp-on--input="actions.setSearchQuery"
				data-wp-on--focus="actions.showSuggestions"
				data-wp-on--keydown="actions.handleSuggestionKeydown"
				role="combobox"
				aria-expanded="false"
				data-wp-bind--aria-expanded="state.showSuggestions"
				aria-autocomplete="list"
				aria-controls="listora-suggestions"
				autocomplete="off"
			/>
			<button
				type="button"
				class="listora-search__clear"
				data-wp-on--click="actions.clearSearchQuery"
				aria-label="<?php esc_attr_e( 'Clear search', 'wb-listora' ); ?>"
			>
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
				</svg>
			</button>

			<?php // Autocomplete suggestions dropdown. ?>
			<div
				id="listora-suggestions"
				class="listora-search__suggestions"
				role="listbox"
				hidden
				data-wp-bind--hidden="!state.showSuggestions"
			></div>
		</div>
		<?php endif; ?>

		<?php if ( $show_location ) : ?>
		<div class="listora-search__field listora-search__field--location">
			<label for="listora-location" class="listora-sr-only">
				<?php esc_html_e( 'Location', 'wb-listora' ); ?>
			</label>
			<svg class="listora-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle>
			</svg>
			<input
				id="listora-location"
				type="text"
				class="listora-input listora-search__input"
				placeholder="<?php esc_attr_e( 'Location...', 'wb-listora' ); ?>"
				data-wp-bind--value="state.selectedLocation"
				data-wp-on--input="actions.setSearchQuery"
				autocomplete="off"
			/>
			<?php if ( $show_near_me ) : ?>
			<button
				type="button"
				class="listora-search__near-me"
				data-wp-on--click="actions.nearMe"
				title="<?php esc_attr_e( 'Find listings near me', 'wb-listora' ); ?>"
				aria-label="<?php esc_attr_e( 'Find listings near your location', 'wb-listora' ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
				</svg>
			</button>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( $show_type && ! $listing_type && count( $types ) > 1 ) : ?>
		<div class="listora-search__field listora-search__field--type">
			<label for="listora-type" class="listora-sr-only">
				<?php esc_html_e( 'Listing Type', 'wb-listora' ); ?>
			</label>
			<select
				id="listora-type"
				class="listora-input listora-select listora-search__select"
				data-wp-bind--value="state.selectedType"
				data-wp-on--change="actions.selectType"
				data-wp-context='{"typeSlug": ""}'
			>
				<option value=""><?php esc_html_e( 'All Types', 'wb-listora' ); ?></option>
				<?php foreach ( $types as $type ) : ?>
				<option value="<?php echo esc_attr( $type->get_slug() ); ?>">
					<?php echo esc_html( $type->get_name() ); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

		<button
			type="button"
			class="listora-btn listora-btn--primary listora-search__submit"
			data-wp-on--click="actions.searchImmediate"
		>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>
			</svg>
			<span><?php esc_html_e( 'Search', 'wb-listora' ); ?></span>
		</button>
	</div>

	<?php // ─── Type Tabs (alternative to dropdown, shown on wider layouts) ─── ?>
	<?php if ( $show_type && ! $listing_type && count( $types ) > 1 ) : ?>
	<div class="listora-search__type-tabs" role="group" aria-label="<?php esc_attr_e( 'Filter by listing type', 'wb-listora' ); ?>">
		<button
			type="button"
			class="listora-search__type-tab"
			data-wp-class--is-active="!state.selectedType"
			data-wp-bind--aria-pressed="!state.selectedType"
			data-wp-on--click="actions.selectType"
			data-wp-context='{"typeSlug": ""}'
		>
			<?php esc_html_e( 'All', 'wb-listora' ); ?>
		</button>
		<?php foreach ( $types as $type ) : ?>
		<button
			type="button"
			class="listora-search__type-tab"
			data-wp-on--click="actions.selectType"
			data-wp-context='<?php echo wp_json_encode( array( 'typeSlug' => $type->get_slug() ) ); ?>'
			style="--listora-type-color: <?php echo esc_attr( $type->get_color() ); ?>"
		>
			<span class="dashicons <?php echo esc_attr( $type->get_icon() ); ?>" aria-hidden="true"></span>
			<?php echo esc_html( $type->get_name() ); ?>
		</button>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php // ─── More Filters Toggle ─── ?>
	<?php if ( $show_more ) : ?>
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
			<span><?php esc_html_e( 'More Filters', 'wb-listora' ); ?></span>
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
	>
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
	<?php endif; ?>

	<?php // ─── Active Filter Pills ─── ?>
	<div
		class="listora-search__active-filters"
		aria-live="polite"
		data-wp-class--is-hidden="!state.hasActiveFilters"
	>
		<?php // Pills are rendered dynamically via Interactivity API. ?>
	</div>

	<?php // ─── Error Message ─── ?>
	<div
		class="listora-search__error is-hidden"
		role="alert"
		data-wp-class--is-hidden="!state.searchError"
		data-wp-text="state.searchError"
	></div>

	<?php
	/**
	 * Fires after the search results section.
	 *
	 * Pro hooks in here to render the "Save This Search" button for logged-in users.
	 *
	 * @since 1.0.0
	 */
	do_action( 'wb_listora_after_search_results' );
	?>

</div>
