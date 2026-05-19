<?php
/**
 * Listing Card block — server-rendered with Interactivity API.
 *
 * This render file can be called directly with $listing_data or via block rendering.
 *
 * Extension hooks:
 * - `wb_listora_before_listing_card` — fires before the card template renders.
 *   Args: ( int $listing_id, array $context ). $context includes layout,
 *   show_rating, show_favorite, show_type, show_features, card_index. Use to
 *   inject markup above the card (badges, sponsored labels, A/B-test wrappers).
 * - `wb_listora_after_listing_card` — fires after the card template renders.
 *   Same args. Use to inject markup below the card (CTAs, additional metadata).
 *
 * @since 1.0.0
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

$unique_id = $attributes['uniqueId'] ?? '';

// Support both block rendering and direct function call.
$listing_id    = $attributes['listingId'] ?? 0;
$layout        = $attributes['layout'] ?? 'standard';
$show_rating   = $attributes['showRating'] ?? true;
$show_favorite = $attributes['showFavorite'] ?? true;
$show_type     = $attributes['showType'] ?? true;

// Site-wide favorites-feature gate. The Favorites toggle is registered
// in wb_listora_features_registry() but had ZERO consumers — meaning
// admin could disable Favorites and the heart button would still show
// + the REST POST /favorites would still accept the save. Surfaced
// during journey #29 (feature-toggle parity sweep 2026-05-18). Backend↔
// frontend uniformity per the no-UX-gaps policy.
if ( $show_favorite && function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'favorites' ) ) {
	$show_favorite = false;
}
$show_features = $attributes['showFeatures'] ?? true;
$max_meta      = $attributes['maxMetaFields'] ?? 4;

// Allow passing listing data directly (from grid block).
$listing = $attributes['_listing_data'] ?? null;

if ( ! $listing && $listing_id > 0 ) {
	$post = get_post( $listing_id );
	if ( ! $post || 'listora_listing' !== $post->post_type ) {
		return;
	}
	$listing = wb_listora_prepare_card_data( $post->ID );
}

if ( ! $listing ) {
	return;
}

$id       = $listing['id'];
$title    = $listing['title'];
$link     = $listing['link'];
$excerpt  = $listing['excerpt'] ?? '';
$type     = $listing['type'] ?? null;
$meta     = $listing['meta'] ?? array();
$rating   = $listing['rating'] ?? array(
	'average' => 0,
	'count'   => 0,
);
$image    = $listing['image'] ?? null;
$features = $listing['features'] ?? array();
$location = $listing['location'] ?? '';
$badges   = $listing['badges'] ?? array();

$type_name  = $type ? $type['name'] : '';
$type_color = $type ? $type['color'] : '#0073aa';
$type_icon  = $type ? $type['icon'] : '';

// Card fields configured for this type.
$card_fields = $listing['card_fields'] ?? array();

// Card index for staggered animation (from parent grid).
$card_index = $attributes['_card_index'] ?? null;

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

// Interactivity context for this card.
$context = wp_json_encode(
	array(
		'listingId'    => $id,
		'listingTitle' => $title,
		'listingUrl'   => $link,
	)
);

// Placeholder URL — bundled SVG, never breaks.
$placeholder_url = wb_listora_placeholder_url();

// Favorite count — use pre-loaded count from grid (avoids N+1 queries), fallback to direct query for standalone cards.
if ( $show_favorite ) {
	if ( isset( $attributes['_fav_count'] ) ) {
		$card_fav_count = (int) $attributes['_fav_count'];
	} else {
		global $wpdb;
		$card_fav_prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$card_fav_count  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$card_fav_prefix}favorites WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
	}
} else {
	$card_fav_count = 0;
}

// Schema.org type.
$schema_type = $type ? $type['schema'] : 'LocalBusiness';

// ─── Assemble $view_data for templates ───
$view_data = array(
	'id'              => $id,
	'title'           => $title,
	'link'            => $link,
	'excerpt'         => $excerpt,
	'layout'          => $layout,
	'show_rating'     => $show_rating,
	'show_favorite'   => $show_favorite,
	'show_type'       => $show_type,
	'show_features'   => $show_features,
	'max_meta'        => $max_meta,
	'type'            => $type,
	'type_name'       => $type_name,
	'type_color'      => $type_color,
	'type_icon'       => $type_icon,
	'meta'            => $meta,
	'image'           => $image,
	'placeholder_url' => $placeholder_url,
	'rating'          => $rating,
	'features'        => $features,
	'location'        => $location,
	'badges'          => $badges,
	'card_fields'     => $card_fields,
	'card_fav_count'  => $card_fav_count,
	'listing'         => $listing,
	'block_classes'   => $block_classes,
	'context'         => $context,
	'card_index'      => $card_index,
	'schema_type'     => $schema_type,
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires before the listing-card template renders.
 *
 * @since 1.0.0
 *
 * @param int   $id      Listing post ID.
 * @param array $context Render context (layout, visibility flags, card_index).
 */
do_action(
	'wb_listora_before_listing_card',
	$id,
	array(
		'layout'        => $layout,
		'show_rating'   => $show_rating,
		'show_favorite' => $show_favorite,
		'show_type'     => $show_type,
		'show_features' => $show_features,
		'card_index'    => $card_index,
	)
);

wb_listora_get_template( 'blocks/listing-card/card.php', $view_data );

/**
 * Fires after the listing-card template renders.
 *
 * @since 1.0.0
 *
 * @param int   $id      Listing post ID.
 * @param array $context Render context (same shape as the before hook).
 */
do_action(
	'wb_listora_after_listing_card',
	$id,
	array(
		'layout'        => $layout,
		'show_rating'   => $show_rating,
		'show_favorite' => $show_favorite,
		'show_type'     => $show_type,
		'show_features' => $show_features,
		'card_index'    => $card_index,
	)
);
