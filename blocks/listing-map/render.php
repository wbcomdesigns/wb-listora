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

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-map-wrapper',
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
	)
);
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<a href="#listora-after-map" class="listora-sr-only listora-sr-only--focusable">
		<?php esc_html_e( 'Skip map, go to listing results', 'wb-listora' ); ?>
	</a>

	<div
		class="listora-map"
		id="listora-map-<?php echo esc_attr( wp_unique_id() ); ?>"
		role="application"
		aria-label="
		<?php
		echo esc_attr(
			sprintf(
			/* translators: %d: number of markers */
				__( 'Map showing %d listing locations', 'wb-listora' ),
				count( $markers_json )
			)
		);
		?>
		"
		style="height: <?php echo esc_attr( $height ); ?>;"
		data-wp-init="callbacks.onMapInit"
	></div>

	<?php // ─── Map Controls ─── ?>
	<div class="listora-map__controls">
		<?php if ( $show_near_me ) : ?>
		<button
			type="button"
			class="listora-btn listora-btn--secondary listora-map__near-me-btn"
			data-wp-on--click="actions.nearMe"
			aria-label="<?php esc_attr_e( 'Find listings near your location', 'wb-listora' ); ?>"
		>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
			</svg>
			<?php esc_html_e( 'Near Me', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>

		<?php if ( $search_on_drag ) : ?>
		<button
			type="button"
			class="listora-btn listora-btn--secondary listora-map__search-area-btn"
			data-wp-on--click="actions.searchMapArea"
			style="display: none;"
			aria-label="<?php esc_attr_e( 'Search listings in this map area', 'wb-listora' ); ?>"
		>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>
			</svg>
			<?php esc_html_e( 'Search this area', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<span id="listora-after-map"></span>
</div>
