<?php
/**
 * Listing Search — Search bar template (keyword, location, type selector, button).
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-search/search-bar.php
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
			value="<?php echo esc_attr( $url_keyword ?? '' ); ?>"
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
			value="<?php echo esc_attr( $url_location ?? '' ); ?>"
			data-wp-bind--value="state.selectedLocation"
			data-wp-on--input="actions.setLocation"
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
			<option value=""<?php selected( $url_type ?? '', '' ); ?>><?php esc_html_e( 'All Types', 'wb-listora' ); ?></option>
			<?php foreach ( $types as $type ) : ?>
			<option value="<?php echo esc_attr( $type->get_slug() ); ?>"<?php selected( $url_type ?? '', $type->get_slug() ); ?>>
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
