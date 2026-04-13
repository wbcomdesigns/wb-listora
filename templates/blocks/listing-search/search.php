<?php
/**
 * Listing Search — Main search wrapper template.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-search/search.php
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
	<?php wb_listora_get_template( 'blocks/listing-search/search-bar.php', $view_data ); ?>

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
			<?php echo \WBListora\Core\Lucide_Icons::render( $type->get_icon(), 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo esc_html( $type->get_name() ); ?>
		</button>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php // ─── Filters Panel ─── ?>
	<?php if ( $show_more ) : ?>
		<?php wb_listora_get_template( 'blocks/listing-search/filters.php', $view_data ); ?>
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
