<?php
/**
 * Listing Detail block — full listing page.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

// Define helper function before it's used in the template below.
if ( ! function_exists( 'wb_listora_render_hours' ) ) :
	function wb_listora_render_hours( $hours ) {
		if ( empty( $hours ) || ! is_array( $hours ) ) {
			return '';
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
			$day_data = null;
			foreach ( $hours as $h ) {
				if ( isset( $h['day'] ) && (int) $h['day'] === $d ) {
					$day_data = $h;
					break; }
			}
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

$unique_id     = $attributes['uniqueId'] ?? '';
$post          = get_post( $post_id );
$layout        = $attributes['layout'] ?? 'tabbed';
$show_gallery  = $attributes['showGallery'] ?? true;
$show_map      = $attributes['showMap'] ?? true;
$show_reviews  = $attributes['showReviews'] ?? true;
$show_related  = $attributes['showRelated'] ?? true;
$show_share    = $attributes['showShare'] ?? true;
$show_claim    = $attributes['showClaim'] ?? true;
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
		'class'               => 'listora-detail listora-detail--' . esc_attr( $layout ) . ' ' . $block_classes,
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
		'style'               => '--listora-type-color: ' . esc_attr( $type_color ),
	)
);
?>

<?php echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Breadcrumbs ─── ?>
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

				<?php if ( $avg_rating > 0 ) : ?>
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
			<button type="button" class="listora-btn listora-btn--secondary" data-wp-on--click="actions.toggleFavorite" data-wp-class--is-favorited="state.isFavorited" data-wp-bind--aria-pressed="state.isFavorited" aria-label="<?php esc_attr_e( 'Save to favorites', 'wb-listora' ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
				<?php esc_html_e( 'Save', 'wb-listora' ); ?>
				<?php if ( $favorite_count > 0 ) : ?>
				<span class="listora-detail__favorite-count"><?php echo esc_html( $favorite_count ); ?></span>
				<?php endif; ?>
			</button>

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
					array( 'listora-shared' ),
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

				$rel_type        = $rel_listing['type'] ?? null;
				$rel_view_data   = array(
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
					'rating'          => $rel_listing['rating'] ?? array( 'average' => 0, 'count' => 0 ),
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
	<div class="listora-detail__modal" id="listora-claim-modal" hidden data-wp-class--is-open="state.activeModal === 'claim'" data-wp-bind--hidden="state.activeModal !== 'claim'">
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
		<button type="button" class="listora-btn listora-btn--secondary" data-wp-on--click="actions.toggleFavorite" data-wp-class--is-favorited="state.isFavorited">
			<?php esc_html_e( 'Save', 'wb-listora' ); ?>
		</button>
	</div>

</div>

<?php // Vanilla JS fallback for tab/gallery switching when Interactivity API isn't available in custom templates. ?>
<script>
(function(){
	var d = document.querySelector('.listora-detail');
	if (!d) return;
	d.addEventListener('click', function(e) {
		var tab = e.target.closest('.listora-detail__tab');
		if (tab) {
			var tabId = (tab.id || '').replace('tab-', '');
			if (!tabId) return;
			d.querySelectorAll('.listora-detail__tab').forEach(function(t) { t.classList.remove('is-active'); t.setAttribute('aria-selected','false'); });
			d.querySelectorAll('.listora-detail__panel').forEach(function(p) { p.hidden = true; });
			tab.classList.add('is-active'); tab.setAttribute('aria-selected','true');
			var panel = d.querySelector('#panel-' + tabId);
			if (panel) panel.hidden = false;
			if (typeof history !== 'undefined') history.replaceState(null,'','#' + tabId);
			return;
		}
		var thumb = e.target.closest('.listora-detail__gallery-thumb');
		if (thumb) {
			var img = d.querySelector('.listora-detail__gallery-image');
			var src = thumb.querySelector('img');
			if (img && src) img.src = src.src.replace(/thumbnail|150x150/, 'large').replace(/\d+x\d+/, '');
			d.querySelectorAll('.listora-detail__gallery-thumb').forEach(function(t) { t.classList.remove('is-active'); });
			thumb.classList.add('is-active');
			return;
		}
		// Share button.
		var shareBtn = e.target.closest('[data-wp-on--click="actions.shareDialog"]');
		if (shareBtn) {
			var title = d.querySelector('.listora-detail__title');
			var shareData = { title: title ? title.textContent : document.title, url: location.href };
			if (navigator.share) { navigator.share(shareData); }
			else if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(location.href).then(function(){ if(window.listoraToast) listoraToast(listoraI18n.linkCopied||'Link copied!',{type:'success'}); }); }
			else { var ta = document.createElement('textarea'); ta.value = location.href; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); if(window.listoraToast) listoraToast(listoraI18n.linkCopied||'Link copied!',{type:'success'}); }
			return;
		}
		// Favorite button.
		var favBtn = e.target.closest('[data-wp-on--click="actions.toggleFavorite"]');
		if (favBtn) { favBtn.classList.toggle('is-favorited'); }
		// Modal close button or backdrop fallback (when Interactivity API isn't bound).
		var closeBtn = e.target.closest('.listora-detail__modal-close, .listora-detail__modal-backdrop');
		if (closeBtn) {
			var modal = closeBtn.closest('.listora-detail__modal');
			if (modal) {
				modal.hidden = true;
				modal.classList.remove('is-open');
				document.body.classList.remove('listora-modal-open');
			}
			return;
		}
		// Cancel button inside modal (text variant).
		var cancelBtn = e.target.closest('.listora-detail__modal .listora-btn--text[data-wp-on--click="actions.closeModal"]');
		if (cancelBtn) {
			var modal2 = cancelBtn.closest('.listora-detail__modal');
			if (modal2) { modal2.hidden = true; modal2.classList.remove('is-open'); document.body.classList.remove('listora-modal-open'); }
			return;
		}
	});
	// ESC key closes any open modal.
	document.addEventListener('keydown', function(e) {
		if (e.key !== 'Escape' && e.keyCode !== 27) return;
		d.querySelectorAll('.listora-detail__modal:not([hidden]), .listora-detail__modal.is-open').forEach(function(m) {
			m.hidden = true;
			m.classList.remove('is-open');
		});
		document.body.classList.remove('listora-modal-open');
	});
	var hash = location.hash.replace('#','');
	if (hash) { var t = d.querySelector('#tab-' + hash); if (t) t.click(); }
})();
</script>
