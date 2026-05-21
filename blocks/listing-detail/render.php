<?php
/**
 * Listing Detail block — full listing page.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-base' );

// Vanilla-JS fallback for tab/gallery/share/favorite/modal behaviour when the
// Interactivity API isn't available (custom templates, pre-WP-6.5 environments).
// IAPI store is the source of truth when present; fallback only runs handlers
// IAPI hasn't already bound. Extracted from inline <script> per Pass 3 F1/F2.
wp_enqueue_script(
	'listora-listing-detail-fallback',
	WB_LISTORA_PLUGIN_URL . 'assets/js/listing-detail-fallback.js',
	array(),
	WB_LISTORA_VERSION,
	true
);

// Define helper function before it's used in the template below.
if ( ! function_exists( 'wb_listora_render_hours' ) ) :
	/**
	 * Render business hours as a table.
	 *
	 * Accepts both supported storage shapes:
	 *  - day-keyed dict: [ 0 => [ open, close, closed ], 1 => [...], ... ]
	 *    (this is what the frontend submission form writes — see
	 *    submission-field-renderer.php business_hours case)
	 *  - flat list:     [ [ day, open, close, closed ], [ ... ], ... ]
	 *    (legacy / API-imported data)
	 *
	 * @param mixed $hours Hours data.
	 * @return string HTML.
	 */
	function wb_listora_render_hours( $hours ) {
		if ( empty( $hours ) || ! is_array( $hours ) ) {
			return '';
		}

		// Normalize to a day-keyed dict so the lookup below is the same regardless of input shape.
		$by_day = array();
		foreach ( $hours as $key => $h ) {
			if ( ! is_array( $h ) ) {
				continue;
			}
			if ( isset( $h['day'] ) ) {
				$by_day[ (int) $h['day'] ] = $h;
			} elseif ( is_int( $key ) || ctype_digit( (string) $key ) ) {
				$by_day[ (int) $key ] = $h;
			}
		}

		$day_names = array(
			0 => __( 'Sunday', 'wb-listora' ),
			1 => __( 'Monday', 'wb-listora' ),
			2 => __( 'Tuesday', 'wb-listora' ),
			3 => __( 'Wednesday', 'wb-listora' ),
			4 => __( 'Thursday', 'wb-listora' ),
			5 => __( 'Friday', 'wb-listora' ),
			6 => __( 'Saturday', 'wb-listora' ),
		);
		$today     = (int) current_time( 'w' );
		$html      = '<table class="listora-hours-table">';
		for ( $d = 0; $d <= 6; $d++ ) {
			$day_data = $by_day[ $d ] ?? null;
			$is_today = ( $d === $today );
			$class    = $is_today ? ' class="is-today"' : '';
			$html    .= "<tr{$class}>";
			$html    .= '<td class="listora-hours-table__day">' . esc_html( $day_names[ $d ] ) . '</td>';
			if ( $day_data && ! empty( $day_data['closed'] ) ) {
				$html .= '<td class="listora-hours-table__time listora-hours-table__time--closed">' . esc_html__( 'Closed', 'wb-listora' ) . '</td>';
			} elseif ( $day_data && ! empty( $day_data['is_24h'] ) ) {
				$html .= '<td class="listora-hours-table__time">' . esc_html__( 'Open 24 Hours', 'wb-listora' ) . '</td>';
			} elseif ( $day_data && ! empty( $day_data['open'] ) ) {
				$open  = date_i18n( get_option( 'time_format' ), strtotime( $day_data['open'] ) );
				$close = date_i18n( get_option( 'time_format' ), strtotime( $day_data['close'] ?? '23:59' ) );
				$html .= '<td class="listora-hours-table__time">' . esc_html( $open . ' – ' . $close ) . '</td>';
			} else {
				$html .= '<td class="listora-hours-table__time listora-hours-table__time--na">–</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</table>';
		return $html;
	}
endif;

$post_id = get_the_ID();
if ( ! $post_id || 'listora_listing' !== get_post_type( $post_id ) ) {
	return;
}

global $wpdb;
$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

$unique_id    = $attributes['uniqueId'] ?? '';
$post         = get_post( $post_id );
$layout       = $attributes['layout'] ?? 'tabbed';
$show_gallery = $attributes['showGallery'] ?? true;
$show_map     = $attributes['showMap'] ?? true;
$show_reviews = $attributes['showReviews'] ?? true;
$show_related = $attributes['showRelated'] ?? true;
$show_share   = $attributes['showShare'] ?? true;
$show_claim   = $attributes['showClaim'] ?? true;
// Site-wide favorites-feature gate. listing-detail has no `showFavorite`
// block attribute (the Save button always renders by design), so the gate
// has to land at the render layer. Same backend↔frontend uniformity
// fix as listing-card/render.php (journey #29 sweep 2026-05-18).
$show_favorite = function_exists( 'wb_listora_feature_enabled' )
	? (bool) wb_listora_feature_enabled( 'favorites' )
	: true;

// Site-wide reviews-feature gate. Disabling the Reviews feature must hide
// every read/display surface (tab nav + panel, header rating, summary,
// list) just like the favorites gate above hides the Save button. The block
// attribute only governs the editor toggle; the feature flag is the hard
// kill-switch (mirrors $claims_feature_enabled at :192).
if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'reviews' ) ) {
	$show_reviews = false;
}

$related_count = $attributes['relatedCount'] ?? 3;

$registry   = \WBListora\Core\Listing_Type_Registry::instance();
$type       = $registry->get_for_post( $post_id );
$meta       = \WBListora\Core\Meta_Handler::get_all_values( $post_id );
$type_name  = $type ? $type->get_name() : '';
$type_color = $type ? $type->get_color() : '#0073aa';

// Rating + favorites — loaded via shared helpers.
$rating_data    = \WBListora\Core\Listing_Data::get_rating_summary( $post_id );
$avg_rating     = $rating_data['avg_rating'];
$review_count   = $rating_data['review_count'];
$favorite_count = \WBListora\Core\Listing_Data::get_favorite_count( $post_id );

// Location.
$address  = $meta['address'] ?? array();
$location = '';
$lat      = 0;
$lng      = 0;
if ( is_array( $address ) ) {
	$parts    = array_filter( array( $address['address'] ?? '', $address['city'] ?? '', $address['state'] ?? '' ) );
	$location = implode( ', ', $parts );
	$lat      = (float) ( $address['lat'] ?? 0 );
	$lng      = (float) ( $address['lng'] ?? 0 );
}

// Resolve the admin-configured map provider so the detail map honours the
// SAME provider as the Add Listing picker and the directory/search map.
// wb_listora_get_setting() fires the documented `wb_listora_map_provider`
// filter for this key (see wb-listora.php:292) — the single resolution
// every map surface runs through. Pro's Google_Maps returns 'google' there
// once the feature is on + a key is set; otherwise it stays 'osm'
// (Leaflet/OpenStreetMap, shipped in Free). The provider + default zoom are
// surfaced on the detail-map element so the IAPI switchTab action can pick
// the engine (see src/interactivity/store.js initDetailMap delegation).
$map_provider     = (string) wb_listora_get_setting( 'map_provider', 'osm' );
$map_default_zoom = (int) wb_listora_get_setting( 'map_default_zoom', 15 );

// Enqueue Leaflet so the Map tab can actually render. The map embed
// is gated by $show_map && $lat in tabs.php; the IAPI switchTab action
// in src/interactivity/store.js initialises Leaflet on first click.
// Without this enqueue, typeof L is 'undefined', the init bails
// silently, and the user sees an empty 300px box with the Get
// Directions button below it — which read as the Get Directions
// button being misplaced (QA card 9838460853). Enqueue only when the
// map will actually render so listings without coordinates don't
// pay the asset cost.
//
// Provider-conditional: ship Leaflet only for the bundled 'osm' engine.
// For any other provider (e.g. Pro's 'google') the registered detail-map
// engine owns the element and Leaflet would be dead weight on every detail
// page. The store still falls back to Leaflet if no engine is registered
// for the provider — but in that fallback path the provider is, by
// definition, one Free can't render, so shipping Leaflet wouldn't help. Pro
// registers its Google engine on `wp_enqueue_scripts` priority 5 (before
// this render runs at the_content), and the store's initDetailMap reads the
// registry at switch-to-Map-tab time, so the late-registration race the
// submission picker guards against does not apply here.
if ( $show_map && $lat && 'osm' === $map_provider ) {
	wp_enqueue_style( 'leaflet', WB_LISTORA_PLUGIN_URL . 'assets/vendor/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', WB_LISTORA_PLUGIN_URL . 'assets/vendor/leaflet.js', array(), '1.9.4', true );
}

// Gallery.
$gallery_ids = $meta['gallery'] ?? array();
if ( is_string( $gallery_ids ) ) {
	$gallery_ids = json_decode( $gallery_ids, true ) ?: array();
}
$featured_id = get_post_thumbnail_id( $post_id );

// Contact info.
$phone   = $meta['phone'] ?? '';
$email   = $meta['email'] ?? '';
$website = $meta['website'] ?? '';

// Social links.
$social_links = $meta['social_links'] ?? array();
if ( is_string( $social_links ) ) {
	$social_links = json_decode( $social_links, true ) ?: array();
}

// Business hours.
$business_hours = $meta['business_hours'] ?? array();
if ( is_string( $business_hours ) ) {
	$business_hours = json_decode( $business_hours, true ) ?: array();
}

// Flags.
$is_featured    = \WBListora\Core\Featured::is_featured( $post_id );
$featured_until = \WBListora\Core\Featured::get_featured_until( $post_id );
$is_verified    = (bool) get_post_meta( $post_id, '_listora_is_verified', true );
$is_claimed     = (bool) get_post_meta( $post_id, '_listora_is_claimed', true );

// Same site-wide claims-feature gate as the REST controller
// (includes/rest/class-claims-controller.php:137 — returns 403
// listora_claims_disabled when off). Without this gate the frontend
// renders the Claim button, the user fills the modal, submits, and
// gets a silent 403 with no on-screen explanation. Backend↔frontend
// uniformity per the no-UX-gaps policy 2026-05-18.
$claims_feature_enabled = function_exists( 'wb_listora_feature_enabled' )
	? (bool) wb_listora_feature_enabled( 'claims' )
	: true;
if ( ! $claims_feature_enabled ) {
	$show_claim = false;
}

// Field groups for tabs.
$field_groups = $type ? $type->get_field_groups() : array();

// Services.
$detail_services      = \WBListora\Core\Services::get_services( $post_id );
$detail_service_count = count( $detail_services );

// Features.
$features = wp_get_object_terms( $post_id, 'listora_listing_feature' );
if ( is_wp_error( $features ) ) {
	$features = array();
}

// Breadcrumb parts.
$directory_page = get_page_by_path( 'listings' );
$directory_url  = $directory_page ? get_permalink( $directory_page ) : home_url( '/' );
$breadcrumbs    = array(
	array(
		'name' => __( 'Directory', 'wb-listora' ),
		'url'  => $directory_url,
	),
);
if ( $type_name && $type ) {
	$type_slug     = $type->get_slug();
	$type_page     = get_page_by_path( $type_slug );
	$type_url      = $type_page ? get_permalink( $type_page ) : '';
	$breadcrumbs[] = array(
		'name' => $type_name,
		'url'  => $type_url,
	);
}
$categories = wp_get_object_terms( $post_id, 'listora_listing_cat' );
if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
	$cat           = $categories[0];
	$breadcrumbs[] = array(
		'name' => $cat->name,
		'url'  => get_term_link( $cat ),
	);
}
$breadcrumbs[] = array(
	'name' => $post->post_title,
	'url'  => '',
);

$context = wp_json_encode(
	array(
		'listingId'    => $post_id,
		'listingTitle' => $post->post_title,
		'listingUrl'   => get_permalink( $post_id ),
		'activeTab'    => 'overview',
	)
);

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		// Canonical page shell (Part 7.6.1 / F9) + legacy detail classes for
		// inner content selectors. The legacy `__*` BEM classes retire as
		// each inner section migrates to the canonical card primitive.
		'class'               => 'listora-page listora-page--single listora-detail listora-detail--' . esc_attr( $layout ) . ' ' . $block_classes . ( $type ? ' listora-type--' . esc_attr( $type->get_slug() ) : '' ),
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
	)
);
?>

<?php echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php
	// ─── Owner toolbar ────────────────────────────────────────────────
	// Visible only to the listing's author (or admins). Gives vendors a
	// prominent set of management actions on their own listing without
	// forcing them back to the dashboard. The bar is intentionally
	// rendered BEFORE the breadcrumb so it reads as a chrome layer, not
	// part of the listing's public content.
	$listora_owner_view = is_user_logged_in() && (
		(int) $post->post_author === get_current_user_id()
		|| current_user_can( 'edit_others_posts' )
	);
	if ( $listora_owner_view ) :
		$listora_owner_status_map = array(
			'publish'              => array(
				'label' => __( 'Published', 'wb-listora' ),
				'class' => 'is-publish',
			),
			'pending'              => array(
				'label' => __( 'Pending review', 'wb-listora' ),
				'class' => 'is-pending',
			),
			'draft'                => array(
				'label' => __( 'Draft', 'wb-listora' ),
				'class' => 'is-draft',
			),
			'listora_payment'      => array(
				'label' => __( 'Awaiting credits', 'wb-listora' ),
				'class' => 'is-paused',
			),
			'listora_expired'      => array(
				'label' => __( 'Expired', 'wb-listora' ),
				'class' => 'is-expired',
			),
			'listora_rejected'     => array(
				'label' => __( 'Rejected', 'wb-listora' ),
				'class' => 'is-rejected',
			),
			'listora_deactivated'  => array(
				'label' => __( 'Deactivated', 'wb-listora' ),
				'class' => 'is-deactivated',
			),
			'pending_verification' => array(
				'label' => __( 'Awaiting email verification', 'wb-listora' ),
				'class' => 'is-pending-verification',
			),
		);
		$listora_owner_status     = $listora_owner_status_map[ $post->post_status ] ?? array(
			'label' => $post->post_status,
			'class' => 'is-draft',
		);
		$listora_owner_edit_url   = wb_listora_get_dashboard_edit_url( $post_id );
		$listora_owner_dash_url   = wb_listora_get_dashboard_url( 'listings' );
		?>
	<aside class="listora-detail__owner-bar" aria-label="<?php esc_attr_e( 'Manage this listing', 'wb-listora' ); ?>">
		<div class="listora-detail__owner-bar-left">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 22c0-4 4-7 8-7s8 3 8 7"/></svg>
			<span class="listora-detail__owner-bar-label"><?php esc_html_e( "You're viewing this as the owner.", 'wb-listora' ); ?></span>
			<span class="listora-detail__owner-bar-status <?php echo esc_attr( $listora_owner_status['class'] ); ?>"><?php echo esc_html( $listora_owner_status['label'] ); ?></span>
		</div>
		<div class="listora-detail__owner-bar-actions">
			<a href="<?php echo esc_url( $listora_owner_edit_url ); ?>" class="listora-btn listora-btn--secondary listora-btn--sm">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
				<?php esc_html_e( 'Edit listing', 'wb-listora' ); ?>
			</a>
			<a href="<?php echo esc_url( $listora_owner_dash_url ); ?>" class="listora-btn listora-btn--ghost listora-btn--sm">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
				<?php esc_html_e( 'My dashboard', 'wb-listora' ); ?>
			</a>
			<?php
			/**
			 * Fires inside the owner toolbar so Pro (analytics, leads,
			 * services management) can append context-specific actions
			 * without forking the template.
			 *
			 * @since 1.0.5
			 *
			 * @param \WP_Post $post Current listing.
			 */
			do_action( 'wb_listora_detail_owner_bar_actions', $post );
			?>
		</div>
	</aside>
	<?php endif; ?>

	<?php // ─── Breadcrumbs (gated on the breadcrumbs feature, same as the BreadcrumbList JSON-LD) ─── ?>
	<?php if ( ! function_exists( 'wb_listora_feature_enabled' ) || wb_listora_feature_enabled( 'breadcrumbs' ) ) : ?>
	<nav class="listora-detail__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'wb-listora' ); ?>">
		<ol>
			<?php foreach ( $breadcrumbs as $i => $crumb ) : ?>
			<li <?php echo $i === count( $breadcrumbs ) - 1 ? 'aria-current="page"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static literal ternary output. ?>>
				<?php if ( $crumb['url'] && $i < count( $breadcrumbs ) - 1 ) : ?>
				<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['name'] ); ?></a>
				<?php else : ?>
				<span><?php echo esc_html( $crumb['name'] ); ?></span>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php endif; ?>

	<?php
	// ─── Gallery (overridable template) ───
	$gallery_view_data              = array(
		'post_id'      => $post_id,
		'post'         => $post,
		'show_gallery' => $show_gallery,
		'featured_id'  => $featured_id,
		'gallery_ids'  => $gallery_ids,
	);
	$gallery_view_data['view_data'] = $gallery_view_data;
	wb_listora_get_template( 'blocks/listing-detail/gallery.php', $gallery_view_data );
	?>

	<?php // ─── Header ─── ?>
	<header class="listora-detail__header">
		<div class="listora-detail__header-top">
			<?php if ( $type_name ) : ?>
			<span class="listora-badge listora-badge--type"><?php echo esc_html( $type_name ); ?></span>
			<?php endif; ?>

			<?php if ( $is_verified ) : ?>
			<span class="listora-badge listora-badge--open"><?php esc_html_e( 'Verified', 'wb-listora' ); ?></span>
			<?php endif; ?>

			<?php
			/**
			 * Render promotional badges (Featured / New / Popular / etc.) inline with
			 * the type and verified badges in the listing header.
			 *
			 * Pro's badges feature attaches here so pills appear next to the title
			 * instead of floating below the sidebar (which is reserved for lead
			 * forms and similar interactive sidebar content via
			 * `wb_listora_after_listing_fields`).
			 *
			 * @since 1.0.0
			 * @param int    $post_id  Listing post ID.
			 * @param string $type_slug Listing type slug (or empty string).
			 */
			do_action( 'wb_listora_listing_title_badges', $post_id, $type ? $type->get_slug() : '' );
			?>

				<?php if ( $show_reviews && $avg_rating > 0 ) : ?>
					<?php /* translators: 1: average rating, 2: number of reviews */ ?>
			<span class="listora-rating" aria-label="<?php echo esc_attr( sprintf( __( 'Rated %1$s out of 5 based on %2$s reviews', 'wb-listora' ), number_format( $avg_rating, 1 ), $review_count ) ); ?>">
				<svg class="listora-rating__star" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<span><?php echo esc_html( number_format( $avg_rating, 1 ) ); ?></span>
				<span class="listora-rating__count">(<?php echo esc_html( $review_count ); ?>)</span>
			</span>
			<?php endif; ?>
		</div>

		<h1 class="listora-detail__title" itemprop="name"><?php echo esc_html( $post->post_title ); ?></h1>

		<?php if ( $location ) : ?>
		<address class="listora-detail__address" itemprop="address">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
			</svg>
			<?php echo esc_html( $location ); ?>
		</address>
		<?php endif; ?>

<?php // ─── Action Buttons ─── ?>
		<div class="listora-detail__actions">
			<?php if ( $show_favorite ) : ?>
			<button type="button" class="listora-btn listora-btn--secondary" data-wp-on--click="actions.toggleFavorite" data-wp-class--is-favorited="state.isFavorited" data-wp-bind--aria-pressed="state.isFavorited" data-wp-bind--aria-label="state.favoriteAriaLabel" aria-label="<?php esc_attr_e( 'Save to favorites', 'wb-listora' ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
				<?php esc_html_e( 'Save', 'wb-listora' ); ?>
				<?php if ( $favorite_count > 0 ) : ?>
				<span class="listora-detail__favorite-count"><?php echo esc_html( $favorite_count ); ?></span>
				<?php endif; ?>
			</button>
			<?php endif; ?>

			<?php if ( $show_share ) : ?>
			<button type="button" class="listora-btn listora-btn--secondary" data-wp-on--click="actions.shareDialog">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></svg>
				<?php esc_html_e( 'Share', 'wb-listora' ); ?>
			</button>
			<?php endif; ?>

			<?php if ( $lat && $lng ) : ?>
			<a class="listora-btn listora-btn--secondary" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr( $lat . ',' . $lng ); ?>" target="_blank" rel="noopener">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
				<?php esc_html_e( 'Directions', 'wb-listora' ); ?>
			</a>
			<?php endif; ?>

			<?php if ( $show_claim && ! $is_claimed && is_user_logged_in() && (int) $post->post_author !== get_current_user_id() ) : ?>
			<button type="button" class="listora-btn listora-btn--secondary" data-wp-on--click="actions.showClaimModal">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
				<?php esc_html_e( 'Claim', 'wb-listora' ); ?>
			</button>
			<?php endif; ?>

			<?php
			// Report (flag) control — visible to everyone except the owner when the
			// report_listings feature is on. Logged-out clicks open the login modal.
			$listora_can_report = function_exists( 'wb_listora_feature_enabled' )
				&& wb_listora_feature_enabled( 'report_listings' )
				&& ( ! is_user_logged_in() || (int) $post->post_author !== get_current_user_id() );
			?>
			<?php if ( $listora_can_report ) : ?>
			<button type="button" class="listora-btn listora-btn--secondary listora-detail__report-btn" data-wp-on--click="actions.openReportModal">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>
				<?php esc_html_e( 'Report', 'wb-listora' ); ?>
			</button>
			<?php endif; ?>

			<?php
			/**
			 * Fires inside the listing detail action bar.
			 *
			 * Pro features (e.g. Comparison) attach action buttons to this hook so they
			 * appear alongside Save / Share / Directions / Claim.
			 *
			 * @since 1.0.0
			 * @param int $post_id Listing post ID.
			 */
			do_action( 'wb_listora_detail_actions', $post_id );
			?>

			<?php
			// ─── Feature This Listing (owner, not yet featured) ───
			$listora_is_owner = is_user_logged_in() && (int) $post->post_author === get_current_user_id();
			if ( $listora_is_owner && ! $is_featured ) :
				$listora_feature_cost     = (int) wb_listora_get_setting( 'featured_credit_cost', 0 );
				$listora_feature_days     = \WBListora\Core\Featured::get_default_duration_days();
				$listora_feature_endpoint = rest_url( WB_LISTORA_REST_NAMESPACE . '/listings/' . $post_id . '/feature' );
				$listora_feature_label    = 0 === $listora_feature_days
					? sprintf(
						/* translators: %d: credit cost */
						_n(
							'Feature This Listing (%d credit)',
							'Feature This Listing (%d credits)',
							max( 1, $listora_feature_cost ),
							'wb-listora'
						),
						$listora_feature_cost
					)
					: sprintf(
						/* translators: 1: credit cost, 2: duration in days */
						_n(
							'Feature for %1$d credit · %2$d days',
							'Feature for %1$d credits · %2$d days',
							max( 1, $listora_feature_cost ),
							'wb-listora'
						),
						$listora_feature_cost,
						$listora_feature_days
					);
				?>
				<button
					type="button"
					class="listora-btn listora-btn--primary listora-detail__feature-btn"
					data-wp-on--click="actions.featureListing"
					data-listora-feature-url="<?php echo esc_url( $listora_feature_endpoint ); ?>"
					data-listora-feature-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
					data-listora-listing-id="<?php echo (int) $post_id; ?>"
				>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
					<?php echo esc_html( $listora_feature_label ); ?>
				</button>
			<?php elseif ( $listora_is_owner && $is_featured ) : ?>
				<span class="listora-detail__feature-status">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
					<?php
					if ( 0 === $featured_until ) {
						esc_html_e( 'Featured (permanent)', 'wb-listora' );
					} else {
						printf(
							/* translators: %s: date the listing stays featured until */
							esc_html__( 'Featured until %s', 'wb-listora' ),
							esc_html( wp_date( get_option( 'date_format' ), (int) $featured_until ) )
						);
					}
					?>
				</span>
			<?php endif; ?>
		</div>
	</header>

	<?php // ─── Main Content Area ─── ?>
	<div class="listora-detail__content">

		<?php
		// ─── Reviews tab data (only assembled when reviews tab renders) ───
		$detail_reviews        = array();
		$detail_review_summary = array(
			'avg'   => 0.0,
			'total' => 0,
			'dist'  => array(
				5 => 0,
				4 => 0,
				3 => 0,
				2 => 0,
				1 => 0,
			),
		);
		$detail_user_reviewed  = false;
		$detail_is_owner       = is_user_logged_in() && (int) get_post_field( 'post_author', $post_id ) === get_current_user_id();

		if ( $show_reviews ) {
			$reviews_limit         = (int) apply_filters( 'wb_listora_detail_reviews_limit', 20, $post_id );
			$detail_reviews        = \WBListora\Core\Listing_Data::get_reviews( $post_id, 'newest', $reviews_limit );
			$detail_review_summary = \WBListora\Core\Listing_Data::get_review_distribution( $post_id );

			if ( is_user_logged_in() ) {
				$detail_user_reviewed = \WBListora\Core\Listing_Data::has_user_reviewed( $post_id, get_current_user_id() );
			}

			// Prime reviewer user cache to avoid N+1 get_user_by() in the template loop.
			$reviewer_ids = array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $row ) {
								return isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
							},
							$detail_reviews
						)
					)
				)
			);
			if ( ! empty( $reviewer_ids ) ) {
				cache_users( $reviewer_ids );
			}
		}

		// ─── Tabs / Sections (overridable template) ───
		$tabs_view_data = array(
			'post_id'               => $post_id,
			'post'                  => $post,
			'type'                  => $type,
			'meta'                  => $meta,
			'field_groups'          => $field_groups,
			'features'              => $features,
			'business_hours'        => $business_hours,
			'detail_services'       => $detail_services,
			'detail_service_count'  => $detail_service_count,
			'show_reviews'          => $show_reviews,
			'show_map'              => $show_map,
			'avg_rating'            => $avg_rating,
			'review_count'          => $review_count,
			'lat'                   => $lat,
			'lng'                   => $lng,
			'map_provider'          => $map_provider,
			'map_default_zoom'      => $map_default_zoom,
			'detail_reviews'        => $detail_reviews,
			'detail_review_summary' => $detail_review_summary,
			'detail_user_reviewed'  => $detail_user_reviewed,
			'detail_is_owner'       => $detail_is_owner,
		);

		/**
		 * Filter the view data for the listing-detail tabs template.
		 *
		 * @param array $tabs_view_data Prepared tab data including reviews.
		 * @param int   $post_id        Current listing ID.
		 */
		$tabs_view_data              = apply_filters( 'wb_listora_detail_tabs_view_data', $tabs_view_data, $post_id );
		$tabs_view_data['view_data'] = $tabs_view_data;
		wb_listora_get_template( 'blocks/listing-detail/tabs.php', $tabs_view_data );
		?>

		<?php
		// ─── Sidebar (overridable template) ───
		$sidebar_view_data              = array(
			'post_id'        => $post_id,
			'phone'          => $phone,
			'email'          => $email,
			'website'        => $website,
			'social_links'   => $social_links,
			'business_hours' => $business_hours,
			'is_claimed'     => $is_claimed,
			'type'           => $type,
		);
		$sidebar_view_data['view_data'] = $sidebar_view_data;
		wb_listora_get_template( 'blocks/listing-detail/sidebar.php', $sidebar_view_data );
		?>
	</div>

	<?php // ─── Related Listings ─── ?>
	<?php
	if ( $show_related ) :
		// Get categories for this listing to find related ones.
		$related_cat_ids = wp_get_object_terms( $post_id, 'listora_listing_cat', array( 'fields' => 'ids' ) );
		$related_args    = array(
			'post_type'      => 'listora_listing',
			'post_status'    => 'publish',
			'posts_per_page' => $related_count,
			'post__not_in'   => array( $post_id ),
			'orderby'        => 'rand',
		);
		if ( ! is_wp_error( $related_cat_ids ) && ! empty( $related_cat_ids ) ) {
			$related_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'listora_listing_cat',
					'field'    => 'term_id',
					'terms'    => $related_cat_ids,
				),
			);
		}
		$related_query = new \WP_Query( $related_args );

		if ( $related_query->have_posts() ) :
			// Ensure the listing-card stylesheet is enqueued (the detail block
			// renders cards programmatically just like the grid block does).
			$rel_card_style_path = WB_LISTORA_PLUGIN_DIR . 'blocks/listing-card/style.css';
			if ( file_exists( $rel_card_style_path ) && ! wp_style_is( 'listora-listing-card', 'enqueued' ) ) {
				wp_enqueue_style(
					'listora-listing-card',
					WB_LISTORA_PLUGIN_URL . 'blocks/listing-card/style.css',
					array( 'listora-base' ),
					filemtime( $rel_card_style_path )
				);
				wp_style_add_data( 'listora-listing-card', 'rtl', 'replace' );
			}

			$rel_placeholder_url = wb_listora_placeholder_url();
			?>
	<section class="listora-detail__related">
		<h2 class="listora-detail__related-title"><?php esc_html_e( 'Related Listings', 'wb-listora' ); ?></h2>
		<div class="listora-detail__related-grid">
			<?php
			$rel_index = 0;
			while ( $related_query->have_posts() ) :
				$related_query->the_post();
				$rel_id      = get_the_ID();
				$rel_listing = wb_listora_prepare_card_data( $rel_id );
				if ( ! $rel_listing ) {
					continue;
				}

				$rel_type                   = $rel_listing['type'] ?? null;
				$rel_view_data              = array(
					'id'              => $rel_listing['id'],
					'title'           => $rel_listing['title'],
					'link'            => $rel_listing['link'],
					'excerpt'         => $rel_listing['excerpt'] ?? '',
					'layout'          => 'standard',
					'show_rating'     => true,
					'show_favorite'   => true,
					'show_type'       => true,
					'show_features'   => true,
					'max_meta'        => 4,
					'type'            => $rel_type,
					'type_name'       => $rel_type ? $rel_type['name'] : '',
					'type_color'      => $rel_type ? $rel_type['color'] : '#0073aa',
					'type_icon'       => $rel_type ? $rel_type['icon'] : '',
					'meta'            => $rel_listing['meta'] ?? array(),
					'image'           => $rel_listing['image'] ?? null,
					'placeholder_url' => $rel_placeholder_url,
					'rating'          => $rel_listing['rating'] ?? array(
						'average' => 0,
						'count'   => 0,
					),
					'features'        => $rel_listing['features'] ?? array(),
					'location'        => $rel_listing['location'] ?? '',
					'badges'          => $rel_listing['badges'] ?? array(),
					'card_fields'     => $rel_listing['card_fields'] ?? array(),
					'card_fav_count'  => \WBListora\Core\Listing_Data::get_favorite_count( $rel_id ),
					'listing'         => $rel_listing,
					'block_classes'   => 'listora-block listora-detail__related-card',
					'context'         => wp_json_encode(
						array(
							'listingId'    => $rel_listing['id'],
							'listingTitle' => $rel_listing['title'],
							'listingUrl'   => $rel_listing['link'],
						)
					),
					'card_index'      => $rel_index,
					'schema_type'     => $rel_type ? $rel_type['schema'] : 'LocalBusiness',
				);
				$rel_view_data['view_data'] = $rel_view_data;

				wb_listora_get_template( 'blocks/listing-card/card.php', $rel_view_data );
				++$rel_index;
			endwhile;
			?>
		</div>
	</section>
			<?php
		endif;
		wp_reset_postdata();
	endif;
	?>

	<?php // ─── Claim Modal ─── ?>
	<?php if ( $show_claim && ! $is_claimed && is_user_logged_in() && (int) $post->post_author !== get_current_user_id() ) : ?>
		<?php /* Visibility is class-driven (.listora-detail__modal { display: none } / &.is-open { display: flex }). Bound to a derived-state getter, not an inline `===` expression — IAPI doesn't reliably re-evaluate `state.x === 'literal'` when state.x mutates (Basecamp 9842877199). */ ?>
	<div class="listora-detail__modal" id="listora-claim-modal" data-wp-class--is-open="state.isClaimModalOpen">
		<div class="listora-detail__modal-backdrop" data-wp-on--click="actions.closeModal"></div>
		<div class="listora-detail__modal-content" role="dialog" aria-labelledby="claim-modal-title" aria-modal="true">
			<button type="button" class="listora-detail__modal-close" data-wp-on--click="actions.closeModal" aria-label="<?php esc_attr_e( 'Close', 'wb-listora' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
			<h3 id="claim-modal-title"><?php esc_html_e( 'Claim This Business', 'wb-listora' ); ?></h3>
			<p class="listora-detail__modal-desc"><?php esc_html_e( 'Prove you own or manage this business to get verified status and control your listing.', 'wb-listora' ); ?></p>
			<form class="listora-detail__claim-form" data-wp-on--submit="actions.submitClaim" enctype="multipart/form-data">
				<div class="listora-detail__claim-body">
					<div class="listora-submission__field">
						<label for="listora-claim-proof" class="listora-submission__label"><?php esc_html_e( 'Proof of Ownership', 'wb-listora' ); ?> *</label>
						<textarea id="listora-claim-proof" name="proof_text" class="listora-input" rows="4" required
							placeholder="<?php esc_attr_e( 'Explain how you are connected to this business (e.g., I am the owner, I manage the location at...)', 'wb-listora' ); ?>"></textarea>
					</div>
					<div class="listora-submission__field">
						<label for="listora-claim-proof-file" class="listora-submission__label"><?php esc_html_e( 'Upload proof document', 'wb-listora' ); ?></label>
						<p class="listora-submission__hint"><?php esc_html_e( 'Business license, utility bill, or official document (JPEG, PNG, PDF — max 5 MB).', 'wb-listora' ); ?></p>
						<input type="file" id="listora-claim-proof-file" name="proof_file" class="listora-input" accept="image/jpeg,image/png,image/gif,image/webp,.pdf" />
					</div>
					<div class="listora-detail__claim-actions">
						<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Claim', 'wb-listora' ); ?></button>
						<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="actions.closeModal"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
					</div>
				</div>
				<div class="listora-detail__claim-message" hidden></div>
			</form>
		</div>
	</div>
	<?php endif; ?>

	<?php // ─── Report Listing Modal ─── ?>
	<?php if ( $listora_can_report ) : ?>
	<div class="listora-detail__modal" id="listora-report-modal" data-wp-class--is-open="state.isReportModalOpen">
		<div class="listora-detail__modal-backdrop" data-wp-on--click="actions.closeModal"></div>
		<div class="listora-detail__modal-content" role="dialog" aria-labelledby="report-modal-title" aria-modal="true">
			<button type="button" class="listora-detail__modal-close" data-wp-on--click="actions.closeModal" aria-label="<?php esc_attr_e( 'Close', 'wb-listora' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
			<h3 id="report-modal-title"><?php esc_html_e( 'Report This Listing', 'wb-listora' ); ?></h3>
			<p class="listora-detail__modal-desc"><?php esc_html_e( 'Tell us what is wrong so our team can review it.', 'wb-listora' ); ?></p>
			<form class="listora-detail__report-form" data-wp-on--submit="actions.submitReport">
				<div class="listora-detail__report-body">
					<div class="listora-submission__field">
						<label for="listora-report-reason" class="listora-submission__label"><?php esc_html_e( 'Reason', 'wb-listora' ); ?> *</label>
						<select id="listora-report-reason" name="reason" class="listora-input" required>
							<option value="inaccurate"><?php esc_html_e( 'Inaccurate information', 'wb-listora' ); ?></option>
							<option value="spam"><?php esc_html_e( 'Spam or misleading', 'wb-listora' ); ?></option>
							<option value="closed"><?php esc_html_e( 'Permanently closed', 'wb-listora' ); ?></option>
							<option value="duplicate"><?php esc_html_e( 'Duplicate listing', 'wb-listora' ); ?></option>
							<option value="offensive"><?php esc_html_e( 'Offensive or inappropriate', 'wb-listora' ); ?></option>
							<option value="other"><?php esc_html_e( 'Something else', 'wb-listora' ); ?></option>
						</select>
					</div>
					<div class="listora-submission__field">
						<label for="listora-report-details" class="listora-submission__label"><?php esc_html_e( 'Details (optional)', 'wb-listora' ); ?></label>
						<textarea id="listora-report-details" name="details" class="listora-input" rows="3" placeholder="<?php esc_attr_e( 'Add anything that helps us review this listing.', 'wb-listora' ); ?>"></textarea>
					</div>
					<div class="listora-detail__report-actions">
						<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Report', 'wb-listora' ); ?></button>
						<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="actions.closeModal"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
					</div>
				</div>
				<div class="listora-detail__report-message" hidden></div>
			</form>
		</div>
	</div>
	<?php endif; ?>

	<?php // ─── Mobile Sticky Bar ─── ?>
	<div class="listora-detail__mobile-bar">
		<?php if ( $phone ) : ?>
		<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" class="listora-btn listora-btn--primary">
			<?php esc_html_e( 'Call', 'wb-listora' ); ?>
		</a>
		<?php endif; ?>
		<?php if ( $website ) : ?>
		<a href="<?php echo esc_url( $website ); ?>" class="listora-btn listora-btn--secondary" target="_blank" rel="noopener">
			<?php esc_html_e( 'Visit', 'wb-listora' ); ?>
		</a>
		<?php endif; ?>
		<?php if ( $show_favorite ) : ?>
		<button type="button" class="listora-btn listora-btn--secondary" data-wp-on--click="actions.toggleFavorite" data-wp-class--is-favorited="state.isFavorited" data-wp-bind--aria-pressed="state.isFavorited" data-wp-bind--aria-label="state.favoriteAriaLabel" aria-label="<?php esc_attr_e( 'Save to favorites', 'wb-listora' ); ?>">
			<?php esc_html_e( 'Save', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<?php // ─── Login Modal (anon Save / Favorite / Claim feedback) ─── ?>
	<?php
	$listora_current_permalink = get_permalink( $post );
	$listora_login_url         = function_exists( 'wp_login_url' ) ? wp_login_url( $listora_current_permalink ) : '/wp-login.php';
	// Render Create Account ALWAYS — even when users_can_register is off
	// at the site level. wp_registration_url() resolves to /wp-login.php
	// ?action=register, which WordPress gracefully shows as
	// "Registration is currently not allowed" when disabled. Hiding the
	// CTA entirely (the previous behavior) made the modal feel like a
	// dead-end for anonymous visitors on sites with closed registration
	// (smoke F-04). The CTA is filterable via wb_listora_login_modal_register_url
	// so sites with custom registration flows (membership plugins,
	// invite-only) can swap in their own URL or return '' to suppress.
	$listora_reg_url = function_exists( 'wp_registration_url' ) ? wp_registration_url() : '/wp-login.php?action=register';

	/**
	 * Filters the "Create Account" URL surfaced in the anon login modal.
	 *
	 * Return an empty string to suppress the Create Account CTA entirely
	 * (e.g. an invite-only directory where any "register" path is wrong).
	 *
	 * @since 1.0.4
	 *
	 * @param string $listora_reg_url     Resolved registration URL.
	 * @param int    $listing_id          Post ID of the listing being viewed.
	 * @param string $current_permalink   Permalink the user will return to after auth.
	 */
	$listora_reg_url = apply_filters( 'wb_listora_login_modal_register_url', $listora_reg_url, (int) $post->ID, $listora_current_permalink );
	?>
	<div class="listora-detail__modal" id="listora-login-modal" data-wp-class--is-open="state.isLoginModalOpen">
		<div class="listora-detail__modal-backdrop" data-wp-on--click="actions.closeModal"></div>
		<div class="listora-detail__modal-content listora-detail__modal-content--compact" role="dialog" aria-labelledby="listora-login-modal-title" aria-modal="true">
			<button type="button" class="listora-detail__modal-close" data-wp-on--click="actions.closeModal" aria-label="<?php esc_attr_e( 'Close', 'wb-listora' ); ?>">
				<?php echo \WBListora\Core\Lucide_Icons::render( 'x', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
			</button>
			<div class="listora-detail__modal-icon" aria-hidden="true">
				<?php echo \WBListora\Core\Lucide_Icons::render( 'star', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<h3 id="listora-login-modal-title"><?php esc_html_e( 'Log in to save listings', 'wb-listora' ); ?></h3>
			<p class="listora-detail__modal-desc"><?php esc_html_e( 'Sign in to save this listing to your favorites and access it from any device.', 'wb-listora' ); ?></p>
			<div class="listora-detail__modal-actions">
				<a href="<?php echo esc_url( $listora_login_url ); ?>" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Log in', 'wb-listora' ); ?></a>
				<?php if ( $listora_reg_url ) : ?>
				<a href="<?php echo esc_url( $listora_reg_url ); ?>" class="listora-btn listora-btn--secondary"><?php esc_html_e( 'Create account', 'wb-listora' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>

</div>

<?php // Vanilla-JS fallback is enqueued as 'listora-listing-detail-fallback' (see top of file). ?>
