<?php
/**
 * Listing Search block — server-rendered with Interactivity API directives.
 *
 * Extension hooks:
 * - `wb_listora_search_before_form` — fires before the search form renders.
 *   Args: ( array $context ). $context includes layout, listing_type, active
 *   filter values. Use to inject pre-form markup (banners, type tabs).
 * - `wb_listora_search_after_form` — fires after the search form renders.
 *   Same args. Use to inject markup below the form (recent searches, CTAs).
 *
 * @since 1.0.0
 *
 * @package WBListora
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// Enqueue shared styles.
wp_enqueue_style( 'listora-base' );

$unique_id     = $attributes['uniqueId'] ?? '';
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

// ─── Seed Interactivity state from URL params ───
//
// When the user submits the search form we navigate to ?keyword=…&type=…
// so the listing-grid below can render filtered results server-side.
// Without this push the inputs would re-render empty after reload, even
// though the URL still carries the user's query — confusing because
// the address bar and the search box would say different things.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$search_url_keyword    = isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['keyword'] ) ) : '';
$search_url_type       = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '';
$search_url_category   = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['category'] ) ) : '';
$search_url_location   = isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['location'] ) ) : '';
$search_url_sort       = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( (string) $_GET['sort'] ) ) : '';
$search_url_min_rating = isset( $_GET['min_rating'] ) ? (int) $_GET['min_rating'] : 0;
// Features comes in as a comma- or space-separated list of slugs from
// the search-bar checkbox UI. Sanitise each piece, then index by slug
// for O(1) `checked` lookups in the filters template.
$search_url_features_raw = isset( $_GET['features'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['features'] ) ) : '';
$search_url_features     = array();
if ( '' !== $search_url_features_raw ) {
	foreach ( preg_split( '/[\s,]+/', $search_url_features_raw, -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' !== $slug ) {
			$search_url_features[ $slug ] = true;
		}
	}
}
// Tags arrive the same way — the tag chips on a listing detail page link to
// `?tags=<slug>`, and a link that lands on the directory without seeding the
// filter is decorative. Same comma/space-separated contract as features.
$search_url_tags_raw = isset( $_GET['tags'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tags'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search_url_tags     = array();
if ( '' !== $search_url_tags_raw ) {
	foreach ( preg_split( '/[\s,]+/', $search_url_tags_raw, -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $tag_slug ) {
		$tag_slug = sanitize_title( $tag_slug );
		if ( '' !== $tag_slug ) {
			$search_url_tags[ $tag_slug ] = true;
		}
	}
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Always inject these keys — they are NOT defaulted in the JS store on
// purpose (IAPI's last-defined-wins merge would have JS '' override the
// server URL value). If a page loads without query params, all five
// resolve to '' (or the configured default for sort/type) which is the
// correct empty-search state.
// Seed `state.filters` with anything that landed in the URL so the
// active-filter badge (state.activeFilterCount, store.js:132) shows
// the correct count immediately on reload — without this seed, a
// fresh load of `?min_rating=2` reports 1 active filter instead of
// 2, because the count walks state.filters and our dynamic per-type
// filter registry is empty until the user touches a control. We
// only seed keys that came from the URL (no zero/empty fillers) so
// hasActiveFilters / activeFilterCount stay honest. Features arrive
// from the URL as a slug→bool map; flatten to an array of slugs for
// state.filters.features (multi-value filters store array shape).
$search_url_filters = array();
if ( $search_url_min_rating > 0 ) {
	$search_url_filters['min_rating'] = $search_url_min_rating;
}
if ( ! empty( $search_url_features ) ) {
	$search_url_filters['features'] = array_keys( $search_url_features );
}
if ( ! empty( $search_url_tags ) ) {
	$search_url_filters['tags'] = array_keys( $search_url_tags );
}

$listora_state_seed = array(
	'searchQuery'      => $search_url_keyword,
	'selectedType'     => $search_url_type ?: $active_type_slug,
	'selectedCategory' => $search_url_category,
	'selectedLocation' => $search_url_location,
	'sortBy'           => $search_url_sort ?: $default_sort,
);
if ( ! empty( $search_url_filters ) ) {
	$listora_state_seed['filters'] = $search_url_filters;
}

wp_interactivity_state( 'listora/directory', $listora_state_seed );

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                     => 'listora-search listora-search--' . esc_attr( $layout ) . ' ' . $block_classes,
		'data-wp-interactive'       => 'listora/directory',
		'data-wp-init'              => 'callbacks.onSearchBlockInit',
		'data-wp-context'           => (string) wp_json_encode( $context ),
		'data-wp-class--is-loading' => 'state.isLoading',
		'role'                      => 'search',
		'aria-label'                => esc_attr__( 'Search listings', 'wb-listora' ),
	)
);

// ─── Assemble $view_data for templates ───
$view_data = array(
	'unique_id'        => $unique_id,
	'listing_type'     => $listing_type,
	'show_keyword'     => $show_keyword,
	'show_location'    => $show_location,
	'show_type'        => $show_type,
	'show_more'        => $show_more,
	'show_near_me'     => $show_near_me,
	'layout'           => $layout,
	'placeholder'      => $placeholder,
	'default_sort'     => $default_sort,
	'types'            => $types,
	'active_type_slug' => $active_type_slug,
	'type_filters'     => $type_filters,
	'wrapper_attrs'    => $wrapper_attrs,
	// SSR pre-fill so inputs/selects show the active filter on initial paint
	// (data-wp-bind--value only updates AFTER hydration, which leaves the
	// address bar and the input visibly disagreeing for a beat — and never
	// agreeing if hydration silently fails).
	'url_keyword'      => $search_url_keyword,
	'url_type'         => $search_url_type,
	'url_category'     => $search_url_category,
	'url_location'     => $search_url_location,
	'url_sort'         => $search_url_sort,
	'url_min_rating'   => $search_url_min_rating,
	'url_features'     => $search_url_features,
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires before the search form renders.
 *
 * @since 1.0.0
 *
 * @param array $context Render context (layout, listing_type, active URL filters).
 */
do_action(
	'wb_listora_search_before_form',
	array(
		'layout'       => $layout,
		'listing_type' => $listing_type,
		'url_keyword'  => $search_url_keyword,
		'url_type'     => $search_url_type,
		'url_location' => $search_url_location,
	)
);

wb_listora_get_template( 'blocks/listing-search/search.php', $view_data );

/**
 * Fires after the search form renders.
 *
 * @since 1.0.0
 *
 * @param array $context Render context (same shape as the before hook).
 */
do_action(
	'wb_listora_search_after_form',
	array(
		'layout'       => $layout,
		'listing_type' => $listing_type,
		'url_keyword'  => $search_url_keyword,
		'url_type'     => $search_url_type,
		'url_location' => $search_url_location,
	)
);
