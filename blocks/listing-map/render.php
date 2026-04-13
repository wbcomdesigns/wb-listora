<?php
/**
 * Listing Map block — Leaflet map with markers.
 *
 * Server-renders the container. Leaflet initializes client-side.
 * Markers loaded from initial search results or via REST API.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

// Enqueue Leaflet assets.
wp_enqueue_style( 'leaflet', WB_LISTORA_PLUGIN_URL . 'assets/vendor/leaflet.css', array(), '1.9.4' );
wp_enqueue_style( 'leaflet-markercluster', WB_LISTORA_PLUGIN_URL . 'assets/vendor/MarkerCluster.css', array( 'leaflet' ), '1.5.3' );
wp_enqueue_style( 'leaflet-markercluster-default', WB_LISTORA_PLUGIN_URL . 'assets/vendor/MarkerCluster.Default.css', array( 'leaflet-markercluster' ), '1.5.3' );
wp_enqueue_script( 'leaflet', WB_LISTORA_PLUGIN_URL . 'assets/vendor/leaflet.js', array(), '1.9.4', true );
wp_enqueue_script( 'leaflet-markercluster', WB_LISTORA_PLUGIN_URL . 'assets/vendor/leaflet.markercluster.js', array( 'leaflet' ), '1.5.3', true );

$unique_id       = $attributes['uniqueId'] ?? '';
$listing_type    = $attributes['listingType'] ?? '';
$height          = $attributes['height'] ?? '450px';
$default_zoom    = $attributes['defaultZoom'] ?? 12;
$center_lat      = $attributes['centerLat'] ?? 0;
$center_lng      = $attributes['centerLng'] ?? 0;
$show_clustering = $attributes['showClustering'] ?? true;
$show_near_me    = $attributes['showNearMe'] ?? true;
$show_fullscreen = $attributes['showFullscreen'] ?? true;
$search_on_drag  = $attributes['searchOnDrag'] ?? true;
$max_markers     = $attributes['maxMarkers'] ?? 500;

// Use default map center from settings if not set.
if ( 0 === $center_lat && 0 === $center_lng ) {
	$center_lat   = (float) wb_listora_get_setting( 'map_default_lat', 40.7128 );
	$center_lng   = (float) wb_listora_get_setting( 'map_default_lng', -74.0060 );
	$default_zoom = (int) wb_listora_get_setting( 'map_default_zoom', 12 );
}

// Get initial markers from published listings.
global $wpdb;
$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

$where  = "si.status = 'publish' AND g.lat != 0";
$params = array();

if ( $listing_type ) {
	$where   .= ' AND si.listing_type = %s';
	$params[] = $listing_type;
}

$params[] = $max_markers;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$markers_sql = $wpdb->prepare(
	"SELECT g.listing_id, g.lat, g.lng, si.title, si.listing_type, si.avg_rating, si.is_featured
	FROM {$prefix}geo g
	INNER JOIN {$prefix}search_index si ON g.listing_id = si.listing_id
	WHERE {$where}
	ORDER BY si.is_featured DESC, si.avg_rating DESC
	LIMIT %d",
	...$params
);
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $markers_sql is built via $wpdb->prepare() above.
$marker_rows = $wpdb->get_results( $markers_sql, ARRAY_A );

// Build markers array for JS.
$markers_json = array();
$registry     = \WBListora\Core\Listing_Type_Registry::instance();

foreach ( $marker_rows as $row ) {
	$type_obj = $registry->get( $row['listing_type'] );

	$markers_json[] = array(
		'id'       => (int) $row['listing_id'],
		'lat'      => (float) $row['lat'],
		'lng'      => (float) $row['lng'],
		'title'    => $row['title'],
		'type'     => $row['listing_type'],
		'color'    => $type_obj ? $type_obj->get_color() : '#0073aa',
		'icon'     => $type_obj ? $type_obj->get_icon() : '',
		'rating'   => (float) $row['avg_rating'],
		'featured' => (bool) $row['is_featured'],
		'url'      => get_permalink( (int) $row['listing_id'] ),
	);
}

// Map config for JS.
$map_config = array(
	'centerLat'       => $center_lat,
	'centerLng'       => $center_lng,
	'zoom'            => $default_zoom,
	'clustering'      => $show_clustering,
	'searchOnDrag'    => $search_on_drag,
	'maxMarkers'      => $max_markers,
	'tileUrl'         => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
	'tileAttribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
	'markers'         => $markers_json,
	'restUrl'         => rest_url( WB_LISTORA_REST_NAMESPACE . '/search' ),
	'nonce'           => wp_create_nonce( 'wp_rest' ),
);

/**
 * Filter the map configuration before passing to JS.
 *
 * Pro uses this to override tile provider (e.g., Google Maps instead of OSM).
 *
 * @param array $map_config Map configuration array.
 */
$map_config = apply_filters( 'wb_listora_map_config', $map_config );

$context = wp_json_encode(
	array(
		'mapConfig' => $map_config,
	)
);

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-map-wrapper ' . $block_classes,
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
	)
);

$map_element_id = 'listora-map-' . wp_unique_id();

/** Hook: Fires before the map wrapper is rendered. @since 1.1.0 */
do_action( 'wb_listora_before_map', $attributes );

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

// ─── Assemble $view_data for templates ───
$view_data = array(
	'wrapper_attrs'  => $wrapper_attrs,
	'height'         => $height,
	'markers_count'  => count( $markers_json ),
	'map_element_id' => $map_element_id,
	'show_near_me'   => $show_near_me,
	'search_on_drag' => $search_on_drag,
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

wb_listora_get_template( 'blocks/listing-map/map.php', $view_data );

/** Hook: Fires after the map wrapper is closed. @since 1.1.0 */
do_action( 'wb_listora_after_map', $attributes );
