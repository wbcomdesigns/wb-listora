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

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                     => 'listora-search listora-search--' . esc_attr( $layout ) . ' ' . $block_classes,
		'data-wp-interactive'       => 'listora/directory',
		'data-wp-init'              => 'callbacks.onSearchBlockInit',
		'data-wp-context'           => wp_json_encode( $context ),
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
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

wb_listora_get_template( 'blocks/listing-search/search.php', $view_data );
