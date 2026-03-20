<?php
/**
 * Listing Card block — server-rendered with Interactivity API.
 *
 * This render file can be called directly with $listing_data or via block rendering.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

// Support both block rendering and direct function call.
$listing_id    = $attributes['listingId'] ?? 0;
$layout        = $attributes['layout'] ?? 'standard';
$show_rating   = $attributes['showRating'] ?? true;
$show_favorite = $attributes['showFavorite'] ?? true;
$show_type     = $attributes['showType'] ?? true;
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

// Interactivity context for this card.
$context = wp_json_encode(
	array(
		'listingId'    => $id,
		'listingTitle' => $title,
		'listingUrl'   => $link,
	)
);
?>

<article
	class="listora-card listora-card--<?php echo esc_attr( $layout ); ?>"
	data-wp-interactive="listora/directory"
	data-wp-context="<?php echo esc_attr( $context ); ?>"
	data-wp-on--mouseenter="actions.highlightMarker"
	data-wp-on--mouseleave="actions.unhighlightMarker"
	data-wp-class--is-highlighted="state.isHighlightedCard"
	itemscope
	itemtype="https://schema.org/<?php echo esc_attr( $type ? $type['schema'] : 'LocalBusiness' ); ?>"
	style="--listora-type-color: <?php echo esc_attr( $type_color ); ?><?php echo null !== $card_index ? '; --card-index: ' . (int) $card_index : ''; ?>"
>
	<?php // ─── Image Section ─── ?>
	<div class="listora-card__media">
		<a href="<?php echo esc_url( $link ); ?>" class="listora-card__image-link" tabindex="-1" aria-hidden="true">
			<?php if ( $image ) : ?>
			<img
				class="listora-card__image"
				src="<?php echo esc_url( $image['medium'] ?? $image['full'] ); ?>"
				alt="<?php echo esc_attr( $title ); ?>"
				loading="lazy"
				decoding="async"
				itemprop="image"
			/>
			<?php else : ?>
			<div class="listora-card__image-placeholder" aria-hidden="true">
				<?php if ( $type_icon ) : ?>
				<span class="dashicons <?php echo esc_attr( $type_icon ); ?>"></span>
				<?php else : ?>
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.25;color:var(--listora-type-color,var(--listora-primary))">
					<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
				</svg>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</a>

		<?php // Badges on image. ?>
		<?php if ( ! empty( $badges['featured'] ) ) : ?>
		<span class="listora-badge listora-badge--featured listora-card__badge-featured">
			<?php esc_html_e( 'Featured', 'wb-listora' ); ?>
		</span>
		<?php endif; ?>

		<?php if ( $show_favorite ) : ?>
		<button
			type="button"
			class="listora-favorite-btn listora-card__favorite"
			data-wp-on--click="actions.toggleFavorite"
			data-wp-class--is-favorited="state.isFavorited"
			data-wp-bind--aria-label="state.isFavorited ? '<?php echo esc_js( __( 'Remove from favorites', 'wb-listora' ) ); ?>' : '<?php echo esc_js( __( 'Save to favorites', 'wb-listora' ) ); ?>'"
			data-wp-bind--aria-pressed="state.isFavorited"
		>
			<svg class="listora-favorite-btn__icon" viewBox="0 0 24 24" aria-hidden="true">
				<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
			</svg>
		</button>
		<?php endif; ?>

		<?php if ( $show_rating && $rating['average'] > 0 ) : ?>
		<span class="listora-rating listora-card__rating" aria-label="<?php echo esc_attr( sprintf( __( 'Rating: %s out of 5', 'wb-listora' ), $rating['average'] ) ); ?>">
			<svg class="listora-rating__star" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
			<span><?php echo esc_html( number_format( $rating['average'], 1 ) ); ?></span>
		</span>
		<?php endif; ?>
	</div>

	<?php // ─── Card Body ─── ?>
	<div class="listora-card__body">

		<?php if ( $show_type && $type_name ) : ?>
		<span class="listora-badge listora-badge--type listora-card__type">
			<?php echo esc_html( $type_name ); ?>
		</span>
		<?php endif; ?>

		<h3 class="listora-card__title" itemprop="name">
			<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
		</h3>

		<?php if ( $location ) : ?>
		<address class="listora-card__location" itemprop="address">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle>
			</svg>
			<?php echo esc_html( $location ); ?>
		</address>
		<?php endif; ?>

		<?php // ─── Type-specific meta fields ─── ?>
		<?php if ( ! empty( $card_fields ) ) : ?>
		<div class="listora-card__meta">
			<?php
			$shown = 0;
			foreach ( $card_fields as $field_data ) :
				if ( $shown >= $max_meta ) {
					break;
				}
				$value = $field_data['display_value'] ?? '';
				if ( '' === $value ) {
					continue;
				}
				?>
				<span class="listora-card__meta-item <?php echo ! empty( $field_data['badge_class'] ) ? esc_attr( $field_data['badge_class'] ) : ''; ?>">
					<?php echo esc_html( $value ); ?>
				</span>
				<?php
				++$shown;
			endforeach;
			?>
		</div>
		<?php endif; ?>

		<?php // ─── Features ─── ?>
		<?php if ( $show_features && ! empty( $features ) ) : ?>
		<div class="listora-card__features">
			<?php foreach ( array_slice( $features, 0, 3 ) as $feature ) : ?>
			<span class="listora-feature-badge" title="<?php echo esc_attr( $feature['name'] ); ?>">
				<?php if ( ! empty( $feature['icon'] ) ) : ?>
				<span class="dashicons <?php echo esc_attr( $feature['icon'] ); ?>" aria-hidden="true"></span>
				<?php endif; ?>
				<span><?php echo esc_html( $feature['name'] ); ?></span>
			</span>
			<?php endforeach; ?>
			<?php if ( count( $features ) > 3 ) : ?>
			<span class="listora-feature-badge listora-feature-badge--more">
				+<?php echo esc_html( count( $features ) - 3 ); ?>
			</span>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php // ─── Excerpt (horizontal layout only) ─── ?>
		<?php if ( 'horizontal' === $layout && $excerpt ) : ?>
		<p class="listora-card__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 20 ) ); ?></p>
		<?php endif; ?>

		<?php // ─── Distance (shown when geo search active) ─── ?>
		<?php if ( ! empty( $listing['distance'] ) ) : ?>
		<span class="listora-card__distance">
			<?php echo esc_html( $listing['distance'] . ' ' . wb_listora_get_setting( 'distance_unit', 'km' ) ); ?>
		</span>
		<?php endif; ?>

	</div>
</article>
