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

// Rating.
global $wpdb;
$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
$idx_row      = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT avg_rating, review_count FROM {$prefix}search_index WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$post_id
	),
	ARRAY_A
);
$avg_rating   = $idx_row ? (float) $idx_row['avg_rating'] : 0;
$review_count = $idx_row ? (int) $idx_row['review_count'] : 0;

// Favorite count.
$favorite_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$prefix}favorites WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$post_id
	)
);

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
$is_featured = (bool) get_post_meta( $post_id, '_listora_is_featured', true );
$is_verified = (bool) get_post_meta( $post_id, '_listora_is_verified', true );
$is_claimed  = (bool) get_post_meta( $post_id, '_listora_is_claimed', true );

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
$breadcrumbs = array(
	array(
		'name' => __( 'Home', 'wb-listora' ),
		'url'  => home_url( '/' ),
	),
);
if ( $type_name ) {
	$breadcrumbs[] = array(
		'name' => $type_name,
		'url'  => '',
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

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-detail listora-detail--' . esc_attr( $layout ),
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
		'style'               => '--listora-type-color: ' . esc_attr( $type_color ),
	)
);
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Breadcrumbs ─── ?>
	<nav class="listora-detail__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'wb-listora' ); ?>">
		<ol>
			<?php foreach ( $breadcrumbs as $i => $crumb ) : ?>
			<li <?php echo $i === count( $breadcrumbs ) - 1 ? 'aria-current="page"' : ''; ?>>
				<?php if ( $crumb['url'] && $i < count( $breadcrumbs ) - 1 ) : ?>
				<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['name'] ); ?></a>
				<?php else : ?>
				<span><?php echo esc_html( $crumb['name'] ); ?></span>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ol>
	</nav>

	<?php // ─── Gallery ─── ?>
	<?php if ( $show_gallery && ( $featured_id || ! empty( $gallery_ids ) ) ) : ?>
	<div class="listora-detail__gallery">
		<?php
		$all_images = array();
		if ( $featured_id ) {
			$all_images[] = $featured_id;
		}
		foreach ( $gallery_ids as $gid ) {
			if ( (int) $gid !== (int) $featured_id ) {
				$all_images[] = (int) $gid;
			}
		}
		?>
		<div class="listora-detail__gallery-main">
			<?php if ( ! empty( $all_images[0] ) ) : ?>
			<img
				src="<?php echo esc_url( wp_get_attachment_image_url( $all_images[0], 'large' ) ); ?>"
				alt="<?php echo esc_attr( $post->post_title ); ?>"
				class="listora-detail__gallery-image"
				loading="eager"
			/>
			<?php endif; ?>
		</div>
		<?php if ( count( $all_images ) > 1 ) : ?>
		<div class="listora-detail__gallery-thumbs">
			<?php foreach ( array_slice( $all_images, 0, 5 ) as $idx => $img_id ) : ?>
			<button class="listora-detail__gallery-thumb <?php echo 0 === $idx ? 'is-active' : ''; ?>" type="button"
				data-wp-on--click="actions.switchGalleryImage"
				data-wp-context='{"imageId":<?php echo (int) $img_id; ?>,"imageSrc":"<?php echo esc_url( wp_get_attachment_image_url( $img_id, 'large' ) ); ?>"}'
			>
				<img src="<?php echo esc_url( wp_get_attachment_image_url( $img_id, 'thumbnail' ) ); ?>" alt="<?php echo esc_attr( get_post_meta( $img_id, '_wp_attachment_image_alt', true ) ?: sprintf( /* translators: 1: listing title, 2: photo number */ __( '%1$s photo %2$d', 'wb-listora' ), $post->post_title, $idx + 1 ) ); ?>" loading="lazy" />
			</button>
			<?php endforeach; ?>
			<?php if ( count( $all_images ) > 5 ) : ?>
			<span class="listora-detail__gallery-more">+<?php echo esc_html( count( $all_images ) - 5 ); ?></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

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

			<?php if ( $show_claim && ! $is_claimed ) : ?>
			<button type="button" class="listora-btn listora-btn--secondary" data-wp-on--click="actions.showClaimModal">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
				<?php esc_html_e( 'Claim', 'wb-listora' ); ?>
			</button>
			<?php endif; ?>
		</div>
	</header>

	<?php // ─── Main Content Area ─── ?>
	<div class="listora-detail__content">

		<?php // ─── Tabs / Sections ─── ?>
		<div class="listora-detail__main">

			<?php // Tab Navigation ?>
			<div class="listora-detail__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Listing details', 'wb-listora' ); ?>">
				<button role="tab" class="listora-detail__tab is-active" id="tab-overview" aria-selected="true" aria-controls="panel-overview"
					data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"overview"}'>
					<?php esc_html_e( 'Overview', 'wb-listora' ); ?>
				</button>
				<?php foreach ( $field_groups as $group ) : ?>
				<button role="tab" class="listora-detail__tab" id="tab-<?php echo esc_attr( $group->get_key() ); ?>" aria-selected="false"
					aria-controls="panel-<?php echo esc_attr( $group->get_key() ); ?>"
					data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"<?php echo esc_attr( $group->get_key() ); ?>"}'>
					<?php echo esc_html( $group->get_label() ); ?>
				</button>
				<?php endforeach; ?>
				<?php if ( $detail_service_count > 0 ) : ?>
				<button role="tab" class="listora-detail__tab" id="tab-services" aria-selected="false" aria-controls="panel-services"
					data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"services"}'>
					<?php esc_html_e( 'Services', 'wb-listora' ); ?>
					<span class="listora-detail__tab-count"><?php echo esc_html( $detail_service_count ); ?></span>
				</button>
				<?php endif; ?>
				<?php if ( $show_reviews ) : ?>
				<button role="tab" class="listora-detail__tab" id="tab-reviews" aria-selected="false" aria-controls="panel-reviews"
					data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"reviews"}'>
					<?php esc_html_e( 'Reviews', 'wb-listora' ); ?>
					<?php if ( $review_count > 0 ) : ?>
					<span class="listora-detail__tab-count"><?php echo esc_html( $review_count ); ?></span>
					<?php endif; ?>
				</button>
				<?php endif; ?>
				<?php if ( $show_map && $lat ) : ?>
				<button role="tab" class="listora-detail__tab" id="tab-map" aria-selected="false" aria-controls="panel-map"
					data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"map"}'>
					<?php esc_html_e( 'Map', 'wb-listora' ); ?>
				</button>
				<?php endif; ?>
			</div>

			<?php // Overview Tab ?>
			<div role="tabpanel" id="panel-overview" aria-labelledby="tab-overview" class="listora-detail__panel">
				<?php if ( $post->post_content ) : ?>
				<div class="listora-detail__description" itemprop="description">
					<?php echo wp_kses_post( apply_filters( 'the_content', $post->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter. ?>
				</div>
				<?php endif; ?>

				<?php // Quick info fields ?>
				<?php if ( $type ) : ?>
				<div class="listora-detail__quick-info">
					<?php
					foreach ( $type->get_card_fields() as $field ) :
						$key     = $field->get_key();
						$value   = $meta[ $key ] ?? '';
						$display = wb_listora_format_card_value( $field, $value );
						if ( '' === $display || 'map_location' === $field->get_type() || 'gallery' === $field->get_type() || 'social_links' === $field->get_type() || 'business_hours' === $field->get_type() ) {
							continue;
						}
						?>
					<div class="listora-detail__info-item">
						<dt><?php echo esc_html( $field->get_label() ); ?></dt>
						<dd><?php echo esc_html( $display ); ?></dd>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php // Features ?>
				<?php if ( ! empty( $features ) ) : ?>
				<div class="listora-detail__features">
					<h3><?php esc_html_e( 'Features & Amenities', 'wb-listora' ); ?></h3>
					<div class="listora-detail__features-list">
						<?php foreach ( $features as $feature ) : ?>
						<span class="listora-feature-badge">
							<?php $icon = get_term_meta( $feature->term_id, '_listora_icon', true ); ?>
							<?php if ( $icon ) : ?>
							<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
							<?php echo esc_html( $feature->name ); ?>
						</span>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<?php // Field Group Tabs ?>
			<?php foreach ( $field_groups as $group ) : ?>
			<div role="tabpanel" id="panel-<?php echo esc_attr( $group->get_key() ); ?>" aria-labelledby="tab-<?php echo esc_attr( $group->get_key() ); ?>" class="listora-detail__panel" hidden>
				<dl class="listora-detail__field-list">
					<?php
					foreach ( $group->get_fields() as $field ) :
						// Skip fields whose conditional logic is not met.
						if ( ! $field->check_conditional( $meta ) ) {
							continue;
						}

						$key     = $field->get_key();
						$value   = $meta[ $key ] ?? '';
						$display = wb_listora_format_card_value( $field, $value );
						if ( '' === $display ) {
							continue;
						}

						// Skip complex types that render separately.
						if ( in_array( $field->get_type(), array( 'gallery', 'social_links' ), true ) ) {
							continue;
						}

						// Business hours: render as schedule.
						if ( 'business_hours' === $field->get_type() && ! empty( $business_hours ) ) :
							?>
						<div class="listora-detail__field-item listora-detail__field-item--hours">
							<dt><?php echo esc_html( $field->get_label() ); ?></dt>
							<dd>
								<?php echo wp_kses_post( wb_listora_render_hours( $business_hours ) ); ?>
							</dd>
						</div>
							<?php
							continue;
endif;
						?>
					<div class="listora-detail__field-item">
						<dt><?php echo esc_html( $field->get_label() ); ?></dt>
						<dd>
							<?php if ( 'url' === $field->get_type() ) : ?>
							<a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $value, PHP_URL_HOST ) ?: $value ); ?></a>
							<?php elseif ( 'email' === $field->get_type() ) : ?>
							<a href="mailto:<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></a>
							<?php elseif ( 'phone' === $field->get_type() ) : ?>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $value ) ); ?>"><?php echo esc_html( $value ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $display ); ?>
							<?php endif; ?>
						</dd>
					</div>
					<?php endforeach; ?>
				</dl>
			</div>
			<?php endforeach; ?>

			<?php // Services Tab ?>
			<?php if ( $detail_service_count > 0 ) : ?>
			<div role="tabpanel" id="panel-services" aria-labelledby="tab-services" class="listora-detail__panel" hidden>
				<div class="listora-detail__services-grid">
					<?php foreach ( $detail_services as $svc ) : ?>
					<div class="listora-detail__service-card">
						<?php
						$svc_image_url = '';
						if ( ! empty( $svc['image_id'] ) ) {
							$svc_image_url = wp_get_attachment_image_url( (int) $svc['image_id'], 'medium' );
						}
						?>
						<?php if ( $svc_image_url ) : ?>
						<div class="listora-detail__service-image">
							<img src="<?php echo esc_url( $svc_image_url ); ?>" alt="<?php echo esc_attr( $svc['title'] ); ?>" loading="lazy" />
						</div>
						<?php endif; ?>
						<div class="listora-detail__service-body">
							<h4 class="listora-detail__service-title"><?php echo esc_html( $svc['title'] ); ?></h4>
							<?php
							// Price display.
							$svc_price_display = '';
							if ( 'free' === $svc['price_type'] ) {
								$svc_price_display = __( 'Free', 'wb-listora' );
							} elseif ( 'contact' === $svc['price_type'] ) {
								$svc_price_display = __( 'Contact for price', 'wb-listora' );
							} elseif ( null !== $svc['price'] ) {
								$svc_formatted_price = number_format( (float) $svc['price'], 2 );
								if ( 'starting_from' === $svc['price_type'] ) {
									/* translators: %s: price amount */
									$svc_price_display = sprintf( __( 'From $%s', 'wb-listora' ), $svc_formatted_price );
								} elseif ( 'hourly' === $svc['price_type'] ) {
									/* translators: %s: price amount */
									$svc_price_display = sprintf( __( '$%s/hr', 'wb-listora' ), $svc_formatted_price );
								} else {
									$svc_price_display = '$' . $svc_formatted_price;
								}
							}
							?>
							<div class="listora-detail__service-meta">
								<?php if ( $svc_price_display ) : ?>
								<span class="listora-detail__service-price"><?php echo esc_html( $svc_price_display ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $svc['duration_minutes'] ) ) : ?>
								<span class="listora-detail__service-duration">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
									<?php
									$svc_hours = floor( (int) $svc['duration_minutes'] / 60 );
									$svc_mins  = (int) $svc['duration_minutes'] % 60;
									if ( $svc_hours > 0 && $svc_mins > 0 ) {
										/* translators: 1: hours, 2: minutes */
										printf( esc_html__( '%1$dh %2$dmin', 'wb-listora' ), (int) $svc_hours, (int) $svc_mins );
									} elseif ( $svc_hours > 0 ) {
										/* translators: %d: hours */
										printf( esc_html( _n( '%d hour', '%d hours', (int) $svc_hours, 'wb-listora' ) ), (int) $svc_hours );
									} else {
										/* translators: %d: minutes */
										printf( esc_html__( '%d min', 'wb-listora' ), (int) $svc_mins );
									}
									?>
								</span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $svc['description'] ) ) : ?>
							<div class="listora-detail__service-desc-wrap">
								<p class="listora-detail__service-desc listora-detail__service-desc--collapsed"><?php echo esc_html( $svc['description'] ); ?></p>
								<button type="button" class="listora-btn listora-btn--text listora-detail__service-toggle"
									data-wp-on--click="actions.toggleServiceDesc">
									<?php esc_html_e( 'Details', 'wb-listora' ); ?>
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
								</button>
							</div>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<?php // Reviews Tab — render actual reviews from DB. ?>
			<?php if ( $show_reviews ) : ?>
			<div role="tabpanel" id="panel-reviews" aria-labelledby="tab-reviews" class="listora-detail__panel" hidden>
				<?php
				// Fetch reviews for this listing.
				$detail_reviews = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved' ORDER BY created_at DESC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$post_id
					),
					ARRAY_A
				);

				// Check if current user already reviewed this listing.
				$detail_user_reviewed = false;
				if ( is_user_logged_in() ) {
					$detail_user_reviewed = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT id FROM {$prefix}reviews WHERE listing_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							$post_id,
							get_current_user_id()
						)
					);
				}

				// Check if current user is the listing owner.
				$detail_is_owner = is_user_logged_in() && (int) get_post_field( 'post_author', $post_id ) === get_current_user_id();

				if ( ! empty( $detail_reviews ) ) :
					// Rating summary.
					$summary = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT AVG(overall_rating) as avg_r, COUNT(*) as cnt, SUM(CASE WHEN overall_rating = 5 THEN 1 ELSE 0 END) as s5, SUM(CASE WHEN overall_rating = 4 THEN 1 ELSE 0 END) as s4, SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) as s3, SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) as s2, SUM(CASE WHEN overall_rating = 1 THEN 1 ELSE 0 END) as s1 FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							$post_id
						),
						ARRAY_A
					);
					$avg     = $summary ? round( (float) $summary['avg_r'], 1 ) : 0;
					$cnt     = $summary ? (int) $summary['cnt'] : 0;
					?>
					<div class="listora-detail__reviews-summary">
						<div class="listora-detail__reviews-score">
							<span class="listora-detail__reviews-avg"><?php echo esc_html( number_format( $avg, 1 ) ); ?></span>
							<div class="listora-detail__reviews-stars">
								<?php for ( $star = 1; $star <= 5; $star++ ) : ?>
								<svg class="listora-rating__star<?php echo $star > round( $avg ) ? ' listora-rating__star--empty' : ''; ?>" viewBox="0 0 24 24" aria-hidden="true" width="20" height="20"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
								<?php endfor; ?>
							</div>
							<span class="listora-detail__reviews-total">
								<?php
								/* translators: %d: number of reviews */
								printf( esc_html( _n( '%d review', '%d reviews', $cnt, 'wb-listora' ) ), $cnt ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- integer used with %d format specifier; format string is esc_html-wrapped.
								?>
							</span>
						</div>
						<div class="listora-detail__reviews-bars">
							<?php
							for ( $bar = 5; $bar >= 1; $bar-- ) :
								$bar_count = (int) ( $summary[ 's' . $bar ] ?? 0 );
								$bar_pct   = $cnt > 0 ? round( $bar_count / $cnt * 100 ) : 0;
								?>
							<div class="listora-detail__reviews-bar-row">
								<span><?php echo esc_html( $bar ); ?></span>
								<div class="listora-detail__reviews-bar"><div style="width:<?php echo esc_attr( $bar_pct ); ?>%"></div></div>
								<span><?php echo esc_html( $bar_count ); ?></span>
							</div>
							<?php endfor; ?>
						</div>
					</div>

					<div class="listora-detail__reviews-list">
						<?php
						foreach ( $detail_reviews as $rev ) :
							$reviewer   = get_user_by( 'id', $rev['user_id'] );
							$rev_name   = $reviewer ? $reviewer->display_name : __( 'Anonymous', 'wb-listora' );
							$rev_avatar = $reviewer ? get_avatar_url( $rev['user_id'], array( 'size' => 48 ) ) : '';
							?>
						<div class="listora-detail__review">
							<div class="listora-detail__review-header">
								<?php if ( $rev_avatar ) : ?>
								<img src="<?php echo esc_url( $rev_avatar ); ?>" alt="<?php echo esc_attr( $rev_name ); ?>" class="listora-detail__review-avatar" width="40" height="40" loading="lazy" />
								<?php endif; ?>
								<div>
									<strong class="listora-detail__review-author"><?php echo esc_html( $rev_name ); ?></strong>
									<div class="listora-detail__review-meta">
										<span class="listora-rating">
											<?php for ( $rs = 1; $rs <= 5; $rs++ ) : ?>
											<svg class="listora-rating__star<?php echo $rs > (int) $rev['overall_rating'] ? ' listora-rating__star--empty' : ''; ?>" viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
											<?php endfor; ?>
										</span>
										<time datetime="<?php echo esc_attr( $rev['created_at'] ); ?>"><?php echo esc_html( human_time_diff( strtotime( $rev['created_at'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wb-listora' ) ); ?></time>
									</div>
								</div>
							</div>
							<?php if ( ! empty( $rev['title'] ) ) : ?>
							<h4 class="listora-detail__review-title"><?php echo esc_html( $rev['title'] ); ?></h4>
							<?php endif; ?>
							<p class="listora-detail__review-content"><?php echo esc_html( $rev['content'] ); ?></p>
							<?php if ( ! empty( $rev['owner_reply'] ) ) : ?>
							<div class="listora-detail__review-reply">
								<strong><?php esc_html_e( 'Owner Response:', 'wb-listora' ); ?></strong>
								<p><?php echo esc_html( $rev['owner_reply'] ); ?></p>
							</div>
							<?php endif; ?>
							<?php if ( (int) $rev['helpful_count'] > 0 ) : ?>
							<span class="listora-detail__review-helpful">
								<?php
								/* translators: %d: number of people */
								printf( esc_html( _n( '%d person found this helpful', '%d people found this helpful', (int) $rev['helpful_count'], 'wb-listora' ) ), (int) $rev['helpful_count'] );
								?>
							</span>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="listora-detail__reviews-empty">
						<p><?php esc_html_e( 'No reviews yet. Be the first to share your experience!', 'wb-listora' ); ?></p>
					</div>
				<?php endif; ?>

				<?php // ─── Review Form ─── ?>
				<?php if ( ! $detail_user_reviewed && ! $detail_is_owner && is_user_logged_in() ) : ?>
				<button type="button" class="listora-btn listora-btn--primary listora-reviews__write-btn" data-wp-on--click="actions.toggleDetailReviewForm">
					<?php esc_html_e( 'Write a Review', 'wb-listora' ); ?>
				</button>
				<?php endif; ?>

				<?php if ( ! $detail_user_reviewed && ! $detail_is_owner ) : ?>
				<div class="listora-reviews__form-wrapper" id="listora-detail-review-form" hidden>
					<?php if ( ! is_user_logged_in() ) : ?>
					<p class="listora-reviews__login-notice">
						<a href="<?php echo esc_url( wp_login_url( get_permalink() . '#reviews' ) ); ?>"><?php esc_html_e( 'Log in', 'wb-listora' ); ?></a>
						<?php esc_html_e( 'to write a review.', 'wb-listora' ); ?>
					</p>
					<?php else : ?>
					<form class="listora-reviews__form" data-wp-on--submit="actions.submitDetailReviewForm">
						<h3><?php esc_html_e( 'Write a Review', 'wb-listora' ); ?></h3>

						<div class="listora-submission__field">
							<label class="listora-submission__label"><?php esc_html_e( 'Your Rating', 'wb-listora' ); ?> <span class="required">*</span></label>
							<fieldset class="listora-reviews__star-input" role="radiogroup" aria-label="<?php esc_attr_e( 'Rating', 'wb-listora' ); ?>">
								<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
								<label class="listora-reviews__star-label">
									<input type="radio" name="overall_rating" value="<?php echo esc_attr( $s ); ?>" required />
									<svg viewBox="0 0 24 24" width="28" height="28" class="listora-reviews__star-svg">
										<path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
									</svg>
									<span class="listora-sr-only"><?php echo esc_html( $s ); ?> <?php echo esc_html( _n( 'star', 'stars', $s, 'wb-listora' ) ); ?></span>
								</label>
								<?php endfor; ?>
							</fieldset>
						</div>

						<div class="listora-submission__field">
							<label for="listora-detail-review-title" class="listora-submission__label"><?php esc_html_e( 'Review Title', 'wb-listora' ); ?> <span class="required">*</span></label>
							<input type="text" id="listora-detail-review-title" name="title" class="listora-input" required
								placeholder="<?php esc_attr_e( 'Summarize your experience', 'wb-listora' ); ?>" />
						</div>

						<div class="listora-submission__field">
							<label for="listora-detail-review-content" class="listora-submission__label"><?php esc_html_e( 'Your Review', 'wb-listora' ); ?> <span class="required">*</span></label>
							<textarea id="listora-detail-review-content" name="content" class="listora-input listora-submission__textarea" rows="5" required minlength="20"
								placeholder="<?php esc_attr_e( 'Share your experience (minimum 20 characters)', 'wb-listora' ); ?>"></textarea>
						</div>

						<?php
						/** This action is documented in blocks/listing-reviews/render.php */
						do_action( 'wb_listora_review_form_after_content', $post_id );
						?>

						<?php
						/** This filter is documented in blocks/listing-reviews/render.php */
						$detail_listing_type_slug = '';
						$detail_listing_type_obj  = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $post_id );
						if ( $detail_listing_type_obj ) {
							$detail_listing_type_slug = $detail_listing_type_obj->get_slug();
						}
						$detail_review_criteria = apply_filters( 'wb_listora_review_criteria', array(), $detail_listing_type_slug );

						if ( ! empty( $detail_review_criteria ) ) :
							?>
						<div class="listora-reviews__criteria">
							<label class="listora-submission__label"><?php esc_html_e( 'Rate each aspect', 'wb-listora' ); ?></label>
							<?php foreach ( $detail_review_criteria as $criterion ) : ?>
							<div class="listora-reviews__criterion">
								<span class="listora-reviews__criterion-label"><?php echo esc_html( $criterion['label'] ); ?></span>
								<fieldset class="listora-reviews__star-input listora-reviews__star-input--small" role="radiogroup"
									aria-label="<?php echo esc_attr( $criterion['label'] ); ?>">
									<?php for ( $cs = 1; $cs <= 5; $cs++ ) : ?>
									<label class="listora-reviews__star-label">
										<input type="radio" name="criteria_ratings[<?php echo esc_attr( $criterion['key'] ); ?>]" value="<?php echo esc_attr( $cs ); ?>" />
										<svg viewBox="0 0 24 24" width="20" height="20" class="listora-reviews__star-svg">
											<path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
										</svg>
										<span class="listora-sr-only"><?php echo esc_html( $cs ); ?> <?php echo esc_html( _n( 'star', 'stars', $cs, 'wb-listora' ) ); ?></span>
									</label>
									<?php endfor; ?>
								</fieldset>
							</div>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>

						<div class="listora-reviews__form-actions">
							<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Review', 'wb-listora' ); ?></button>
							<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="actions.toggleDetailReviewForm"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
						</div>

						<div class="listora-reviews__form-message" hidden></div>
					</form>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php // Map Tab ?>
			<?php if ( $show_map && $lat ) : ?>
			<div role="tabpanel" id="panel-map" aria-labelledby="tab-map" class="listora-detail__panel" hidden>
				<div class="listora-detail__map-embed" style="height: 300px;" id="listora-detail-map"
					data-lat="<?php echo esc_attr( $lat ); ?>" data-lng="<?php echo esc_attr( $lng ); ?>">
				</div>
				<a class="listora-btn listora-btn--secondary" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr( $lat . ',' . $lng ); ?>" target="_blank" rel="noopener" style="margin-block-start: 0.75rem;">
					<?php esc_html_e( 'Get Directions', 'wb-listora' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>

		<?php // ─── Sidebar (contact + hours) ─── ?>
		<aside class="listora-detail__sidebar">

			<?php // Contact Card ?>
			<?php if ( $phone || $email || $website ) : ?>
			<div class="listora-detail__contact-card">
				<h3><?php esc_html_e( 'Contact', 'wb-listora' ); ?></h3>
				<?php if ( $phone ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" class="listora-detail__contact-item" itemprop="telephone">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
					<?php echo esc_html( $phone ); ?>
				</a>
				<?php endif; ?>
				<?php if ( $website ) : ?>
				<a href="<?php echo esc_url( $website ); ?>" class="listora-detail__contact-item" target="_blank" rel="noopener" itemprop="url">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
					<?php echo esc_html( wp_parse_url( $website, PHP_URL_HOST ) ?: $website ); ?>
				</a>
				<?php endif; ?>
				<?php if ( $email ) : ?>
				<a href="mailto:<?php echo esc_attr( $email ); ?>" class="listora-detail__contact-item" itemprop="email">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
					<?php echo esc_html( $email ); ?>
				</a>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php // Business Hours ?>
			<?php if ( ! empty( $business_hours ) ) : ?>
			<div class="listora-detail__hours-card">
				<h3><?php esc_html_e( 'Business Hours', 'wb-listora' ); ?></h3>
				<?php echo wp_kses_post( wb_listora_render_hours( $business_hours ) ); ?>
			</div>
			<?php endif; ?>

			<?php // Claimed badge ?>
			<?php if ( $is_claimed ) : ?>
			<div class="listora-detail__claimed-badge">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
				<?php esc_html_e( 'Claimed & Verified Business', 'wb-listora' ); ?>
			</div>
			<?php endif; ?>
		<?php
		/**
		 * Fires after listing detail fields in the sidebar.
		 *
		 * Pro uses this to render the lead/contact form.
		 *
		 * @param int    $post_id   Listing post ID.
		 * @param string $type_slug Listing type slug.
		 */
		$detail_type_slug = $type ? $type->get_slug() : '';
		do_action( 'wb_listora_after_listing_fields', $post_id, $detail_type_slug );

		/**
		 * Hook point for booking/appointment button.
		 * Third-party or Pro implements the actual booking UI.
		 *
		 * @param int    $post_id         Listing ID.
		 * @param string $detail_type_slug Listing type slug.
		 */
		do_action( 'wb_listora_appointment_button', $post_id, $detail_type_slug );
		?>
		</aside>
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
			?>
	<section class="listora-detail__related">
		<h2 class="listora-detail__related-title"><?php esc_html_e( 'Related Listings', 'wb-listora' ); ?></h2>
		<div class="listora-detail__related-grid">
			<?php
			while ( $related_query->have_posts() ) :
				$related_query->the_post();
				$rel_id     = get_the_ID();
				$rel_link   = get_permalink( $rel_id );
				$rel_title  = get_the_title( $rel_id );
				$rel_thumb  = get_the_post_thumbnail_url( $rel_id, 'medium_large' );
				$rel_rating = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT avg_rating FROM {$prefix}search_index WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$rel_id
					)
				);
				$rel_rating = $rel_rating ? (float) $rel_rating : 0;
				?>
			<a href="<?php echo esc_url( $rel_link ); ?>" class="listora-card listora-card--standard listora-detail__related-card">
				<div class="listora-card__media">
					<?php if ( $rel_thumb ) : ?>
					<img class="listora-card__image" src="<?php echo esc_url( $rel_thumb ); ?>" alt="<?php echo esc_attr( $rel_title ); ?>" loading="lazy" decoding="async" />
					<?php else : ?>
					<div class="listora-card__image-placeholder" aria-hidden="true">
						<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.25;">
							<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
						</svg>
					</div>
					<?php endif; ?>
					<?php if ( $rel_rating > 0 ) : ?>
					<span class="listora-rating listora-card__rating">
						<svg class="listora-rating__star" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						<span><?php echo esc_html( number_format( $rel_rating, 1 ) ); ?></span>
					</span>
					<?php endif; ?>
				</div>
				<div class="listora-card__body">
					<h3 class="listora-card__title"><?php echo esc_html( $rel_title ); ?></h3>
				</div>
			</a>
			<?php endwhile; ?>
		</div>
	</section>
			<?php
		endif;
		wp_reset_postdata();
	endif;
	?>

	<?php // ─── Claim Modal ─── ?>
	<?php if ( $show_claim && ! $is_claimed && is_user_logged_in() && (int) $post->post_author !== get_current_user_id() ) : ?>
	<div class="listora-detail__modal" id="listora-claim-modal" data-wp-class--is-open="state.activeModal === 'claim'" data-wp-bind--hidden="state.activeModal !== 'claim'">
		<div class="listora-detail__modal-backdrop" data-wp-on--click="actions.closeModal"></div>
		<div class="listora-detail__modal-content" role="dialog" aria-labelledby="claim-modal-title" aria-modal="true">
			<h3 id="claim-modal-title"><?php esc_html_e( 'Claim This Business', 'wb-listora' ); ?></h3>
			<p class="listora-detail__modal-desc"><?php esc_html_e( 'Prove you own or manage this business to get verified status and control your listing.', 'wb-listora' ); ?></p>
			<form class="listora-detail__claim-form" data-wp-on--submit="actions.submitClaim" enctype="multipart/form-data">
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
			else { navigator.clipboard.writeText(location.href).then(function(){ alert('Link copied!'); }); }
			return;
		}
		// Favorite button.
		var favBtn = e.target.closest('[data-wp-on--click="actions.toggleFavorite"]');
		if (favBtn) { favBtn.classList.toggle('is-favorited'); }
	});
	var hash = location.hash.replace('#','');
	if (hash) { var t = d.querySelector('#tab-' + hash); if (t) t.click(); }
})();
</script>

