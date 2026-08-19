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

wp_enqueue_style( 'listora-base' );

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
// Clustering is intentionally a PER-BLOCK presentation choice (the
// `showClustering` block attribute, default true), NOT the site-wide
// `map_clustering` setting. Unlike search-on-drag / max-markers below (which
// are performance/behaviour tuning that should apply uniformly), how markers
// visually group is a layout decision an editor makes per inserted map — a
// tight neighbourhood map may want clustering off while a country-wide map
// wants it on. So this block does not read `map_clustering`; each map block
// carries its own toggle in the Inspector. By design. See Basecamp 9909608577.
$show_clustering = $attributes['showClustering'] ?? true;
$show_near_me    = $attributes['showNearMe'] ?? true;
$show_fullscreen = $attributes['showFullscreen'] ?? true;

// Site-wide map behaviour (Settings → Maps) is the source of truth here:
// these two values affect how the map performs and feels site-wide
// (refetching results on pan, marker cap), and the site owner's choice
// should win across every map block regardless of when each block was
// inserted. Per-block overrides for these specific keys would let a
// stale block insertion ignore the admin's tuning, which is exactly the
// confusion the audit flagged.
$search_on_drag = (bool) wb_listora_get_setting( 'map_search_on_drag', true );
$max_markers    = max( 1, (int) wb_listora_get_setting( 'map_max_markers', 500 ) );

// Use default map center from settings if not set.
if ( 0 === $center_lat && 0 === $center_lng ) {
	$center_lat   = (float) wb_listora_get_setting( 'map_default_lat', 40.7128 );
	$center_lng   = (float) wb_listora_get_setting( 'map_default_lng', -74.0060 );
	$default_zoom = (int) wb_listora_get_setting( 'map_default_zoom', 12 );
}

// Get initial markers from published listings.
global $wpdb;
$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

// ─── Which listings to plot ───
//
// Resolve through Search_Engine, exactly as the grid does, rather than
// querying search_index directly.
//
// This block used to read ONE URL filter (bounds) and build its own SQL, while
// the grid read eleven and asked the engine. On the Directory page the two
// render together, so `?keyword=cafe` gave a grid reading "No results" beside a
// map still showing pins for every listing that did not match. Both halves
// looked correct in isolation; only side by side was it obviously broken.
//
// A type pinned on the block wins over the URL, matching the grid: a
// "Restaurants" map must not become a "Hotels" map because of a shared link.
$map_search_args = wb_listora_search_args_from_url(
	array(
		'page'     => 1,
		// Page over MAPPABLE listings, so max_markers caps markers rather than
		// candidates. Without has_geo the engine returns the first N results
		// overall and the mappable rows ranked past N disappear from the map.
		'has_geo'  => true,
		'per_page' => $max_markers,
		'sort'     => wb_listora_search_sort_from_url(),
	)
);

if ( $listing_type ) {
	$map_search_args['type'] = $listing_type;
}

/** Hook: Filter the map marker query args before search. @since 1.5.0 */
$map_search_args = apply_filters( 'wb_listora_map_query_args', $map_search_args, $attributes );

$map_engine = new \WBListora\Search\Search_Engine();
$map_result = $map_engine->search( $map_search_args );

// The engine returns `listing_ids`. Guard the shape rather than assuming it,
// per the 1.4.1 shape-hardening sweep.
$map_ids = isset( $map_result['listing_ids'] ) && is_array( $map_result['listing_ids'] )
	? array_values( array_filter( array_map( 'intval', $map_result['listing_ids'] ) ) )
	: array();

// Coordinates are not in the engine's payload, so join geo for the resolved
// IDs only. A listing with no geo row simply has no pin - it is still in the
// grid, which is correct: the map can show a subset, never a superset.
$marker_rows = array();
if ( $map_ids ) {
	$placeholders = implode( ', ', array_fill( 0, count( $map_ids ), '%d' ) );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$markers_sql = $wpdb->prepare(
		"SELECT g.listing_id, g.lat, g.lng, si.title, si.listing_type, si.avg_rating, si.is_featured
		FROM {$prefix}geo g
		INNER JOIN {$prefix}search_index si ON g.listing_id = si.listing_id
		WHERE g.listing_id IN ( {$placeholders} ) AND g.lat != 0
		ORDER BY si.is_featured DESC, si.avg_rating DESC",
		...$map_ids
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- built via $wpdb->prepare() above.
	$marker_rows = $wpdb->get_results( $markers_sql, ARRAY_A );
}

// Build markers array for JS.
$markers_json = array();
$registry     = \WBListora\Core\Listing_Type_Registry::instance();

// Prefetch listing post-meta in one query so the per-row
// `get_the_post_thumbnail_url()` calls below resolve `_thumbnail_id`
// from cache instead of issuing N separate SELECTs. Same N+1 mitigation
// pattern as REST listings (commit 98c8d47, P1-2).
$listing_ids = array();
foreach ( $marker_rows as $row ) {
	$listing_ids[] = (int) $row['listing_id'];
}
if ( $listing_ids ) {
	// `update_meta_cache( 'post', $ids )` is the canonical WP function;
	// `update_post_meta_cache()` doesn't exist in core (the typo introduced
	// the fatal in card 9871222447).
	update_meta_cache( 'post', $listing_ids );
}

foreach ( $marker_rows as $row ) {
	$type_obj   = $registry->get( $row['listing_type'] );
	$listing_id = (int) $row['listing_id'];
	// The popup image fills a ~241px-wide × 80px-tall cover area, so the
	// 150x150 `thumbnail` size renders visibly soft (it upscales on width).
	// `medium` (300px) covers the width crisply at 1x and stays light for a
	// lazy-loaded map popup. Card 9867372176.
	$thumbnail_url = get_the_post_thumbnail_url( $listing_id, 'medium' );

	// ─── Popup featured-image a11y enforcement (WCAG 2.1 AA) ───
	// The popup builder (build/blocks/listing-map/view.js) uses the marker
	// title as the <img> alt. An untitled listing would emit an empty alt, so
	// resolve the title to a deterministic "Listing #ID" label here — the one
	// place the popup data is assembled. `imageAlt` is surfaced explicitly so
	// the alt is enforced from PHP rather than implied by `title`. Clears the
	// visual_required_no_enforcement detector for the map-popup surface.
	$marker_title = trim( (string) $row['title'] );
	if ( '' === $marker_title ) {
		/* translators: %d: listing ID, used as an alt-text and title fallback for an untitled listing */
		$marker_title = sprintf( __( 'Listing #%d', 'wb-listora' ), $listing_id );
	}

	$markers_json[] = array(
		'id'       => $listing_id,
		'lat'      => (float) $row['lat'],
		'lng'      => (float) $row['lng'],
		'title'    => $marker_title,
		'type'     => $row['listing_type'],
		'color'    => $type_obj ? $type_obj->get_color() : '#0073aa',
		'icon'     => $type_obj ? $type_obj->get_icon() : '',
		'rating'   => (float) $row['avg_rating'],
		'featured' => (bool) $row['is_featured'],
		'url'      => get_permalink( $listing_id ),
		'image'    => $thumbnail_url ? $thumbnail_url : '',
		'imageAlt' => $thumbnail_url ? $marker_title : '',
	);
}

// Map config for JS.
$listora_map_tiles = function_exists( 'wb_listora_get_map_tiles' )
	? wb_listora_get_map_tiles()
	: array(
		'url'         => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
		'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
	);

$map_config = array(
	'centerLat'       => $center_lat,
	'centerLng'       => $center_lng,
	'zoom'            => $default_zoom,
	'clustering'      => $show_clustering,
	'searchOnDrag'    => $search_on_drag,
	'maxMarkers'      => $max_markers,
	// Shared with the /settings/maps REST payload via wb_listora_get_map_tiles()
	// so the web map and native clients can never drift to different tile sources.
	'tileUrl'         => $listora_map_tiles['url'],
	'tileAttribution' => $listora_map_tiles['attribution'],
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

$context = (string) wp_json_encode(
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

/*
 * No tile source = no map. Say so, rather than painting a grey rectangle.
 *
 * 1.6.0 removed the hardcoded OpenStreetMap fallback, because shipping a
 * product that silently leans on someone else's tile servers at volumes their
 * usage policy forbids is not a defensible default. Upgrading sites keep
 * working — the migration records what they were already using — but a FRESH
 * install now starts with no tile URL, and Leaflet renders pins and clusters
 * onto a solid grey canvas. That reads as a broken map, not as an
 * unconfigured one (BC 10213013326).
 *
 * Removing the default was right; leaving the failure silent was not. An owner
 * gets the reason and a link to fix it; a visitor gets a plain, non-alarming
 * line instead of grey nothing.
 */
$listora_map_provider = (string) ( $map_config['provider'] ?? '' );

/*
 * Google is not a raster-tile provider. It draws its own basemap from its own
 * API and never asks us for a tile URL, so Pro's Google_Maps deliberately sets
 * tileUrl to '' — and reading a blank tileUrl as "unconfigured" hid the map
 * behind this notice on every Pro site running Google Maps, then told the
 * owner to go and set a tile source that Google would never use.
 *
 * `provider` is the right signal because Pro only registers the
 * `wb_listora_map_config` filter that sets it once Google is genuinely live
 * (provider selected AND an API key present). A Free site that picks Google
 * without Pro never gets it, and correctly still lands on the notice.
 *
 * Read from the filtered config rather than $listora_map_tiles so a site that
 * supplies its tiles through `wb_listora_map_config` is honoured too.
 */
$listora_map_configured = 'google' === $listora_map_provider
	|| '' !== trim( (string) ( $map_config['tileUrl'] ?? '' ) );

if ( ! $listora_map_configured ) {
	$listora_map_can_fix = current_user_can( 'manage_listora_settings' );

	printf(
		'<div %1$s><div class="listora-map__unconfigured listora-empty"><p class="listora-empty__title">%2$s</p>%3$s</div></div>',
		$wrapper_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by get_block_wrapper_attributes().
		esc_html__( 'The map is not available.', 'wb-listora' ),
		$listora_map_can_fix
			? sprintf(
				'<p class="listora-empty__desc">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'No map tile source is set, so there is nothing to draw. Choose one to switch the map on.', 'wb-listora' ),
				esc_url( admin_url( 'admin.php?page=listora-settings&tab=maps' ) ),
				esc_html__( 'Open Map settings', 'wb-listora' )
			)
			: ''
	);

	/** Hook: Fires after the map wrapper is closed. @since 1.1.0 */
	do_action( 'wb_listora_after_map', $attributes );

	return;
}

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

wb_listora_get_template( 'blocks/listing-map/map.php', $view_data );

/** Hook: Fires after the map wrapper is closed. @since 1.1.0 */
do_action( 'wb_listora_after_map', $attributes );
