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

// One prefix per rendered block, so two search blocks on a page never share an id.
$id_prefix = isset( $id_prefix ) ? (string) $id_prefix : 'listora-';
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its output. ?>>

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

	<?php // ─── Type chips: the type picker. Scrolls with a fade + arrows when it overflows. ─── ?>
	<?php if ( $show_type && ! $listing_type && count( $types ) > 1 ) : ?>
	<div class="listora-search__type-tabs-wrap">
	<button type="button" class="listora-search__type-tabs-nav listora-search__type-tabs-nav--prev" data-listora-scroll="-1" aria-label="<?php esc_attr_e( 'Scroll listing types back', 'wb-listora' ); ?>" hidden>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
	</button>
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
			class="listora-search__type-tab listora-type--<?php echo esc_attr( $type->get_slug() ); ?>"
			data-wp-class--is-active="state.isActiveTypeTab"
			data-wp-bind--aria-pressed="state.isActiveTypeTab"
			data-wp-on--click="actions.selectType"
			<?php echo wp_interactivity_data_wp_context( array( 'typeSlug' => $type->get_slug() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core builds an escaped attribute. ?>
		>
			<?php echo \WBListora\Core\Lucide_Icons::render( $type->get_icon(), 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render returns a fixed SVG from a whitelist. ?>
			<?php echo esc_html( $type->get_name() ); ?>
		</button>
		<?php endforeach; ?>
	</div>
	<button type="button" class="listora-search__type-tabs-nav listora-search__type-tabs-nav--next" data-listora-scroll="1" aria-label="<?php esc_attr_e( 'Scroll listing types forward', 'wb-listora' ); ?>" hidden>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
	</button>
	</div>
	<?php endif; ?>

	<?php // ─── Filters Toggle + Save Search Row ─── ?>
	<?php if ( $show_more ) : ?>
	<div class="listora-search__actions-row">
		<?php wb_listora_get_template( 'blocks/listing-search/filters.php', $view_data ); ?>
		<?php
		/**
		 * Fires inside the actions row, after the filters toggle.
		 *
		 * Pro hooks in here to render the "Save This Search" button for logged-in users.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wb_listora_after_search_results' );
		?>
	</div>
	<?php else : ?>
		<?php
		/**
		 * Fires after the search results section (no filters variant).
		 *
		 * @since 1.0.0
		 */
		do_action( 'wb_listora_after_search_results' );
		?>
	<?php endif; ?>

	<?php if ( ! empty( $show_near_me ) ) : ?>
	<div class="listora-search__radius is-hidden" data-wp-class--is-hidden="!state.hasUserLocation">
		<label for="<?php echo esc_attr( $id_prefix . 'radius' ); ?>"><?php esc_html_e( 'Near me, within', 'wb-listora' ); ?></label>
		<select id="<?php echo esc_attr( $id_prefix . 'radius' ); ?>" class="listora-input listora-select listora-search__radius-select" data-wp-on--change="actions.setSearchRadius">
			<?php
			$listora_radius   = wb_listora_search_geo_from_url()['radius'] ?? (int) wb_listora_get_setting( 'near_me_radius' );
			$listora_unit_lbl = 'mi' === wb_listora_get_setting( 'distance_unit', 'km' ) ? __( 'mi', 'wb-listora' ) : __( 'km', 'wb-listora' );
			foreach ( wb_listora_near_me_radius_options() as $listora_r ) :
				?>
				<option value="<?php echo esc_attr( (string) $listora_r ); ?>" <?php selected( $listora_r, $listora_radius ); ?>><?php echo esc_html( number_format_i18n( $listora_r ) . ' ' . $listora_unit_lbl ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php endif; ?>

	<?php // ─── Error Message ─── ?>
	<div
		class="listora-search__error is-hidden"
		role="alert"
		data-wp-class--is-hidden="!state.searchError"
		data-wp-text="state.searchError"
	></div>

</div>
