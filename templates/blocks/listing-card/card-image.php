<?php
/**
 * Listing Card — Image/Thumbnail section.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-card/card-image.php
 *
 * @package WBListora
 *
 * @var int    $id              Listing post ID.
 * @var string $title           Listing title.
 * @var string $link            Listing permalink.
 * @var array  $image           Image data array with 'full' and 'medium' URLs, or null.
 * @var string $placeholder_url Placeholder image URL.
 * @var array  $badges          Badges array with 'featured', 'verified', 'claimed' booleans.
 * @var bool   $show_favorite   Whether to show the favorite button.
 * @var int    $card_fav_count  Favorite count for this listing.
 * @var bool   $show_rating     Whether to show the rating badge.
 * @var array  $rating          Rating data with 'average' and 'count'.
 * @var array  $view_data       Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

// ─── Featured-image a11y enforcement (WCAG 2.1 AA) ───
// A real featured image is informative: its alt falls back to the listing
// title, and when the listing has no title (untitled draft / import) to a
// deterministic "Listing #ID" label so screen readers never hit an empty alt.
// The bundled placeholder carries no information — it is decorative, so it
// gets alt="" + aria-hidden="true" instead of a misleading title alt. Clears
// the visual_required_no_enforcement detector.
$has_featured_image = ! empty( $image );
if ( '' !== trim( (string) $title ) ) {
	$card_image_alt = $title;
} else {
	/* translators: %d: listing ID, used as an alt-text fallback for an untitled listing */
	$card_image_alt = sprintf( __( 'Listing #%d', 'wb-listora' ), (int) $id );
}

do_action( 'wb_listora_before_card_image', $view_data );
?>
<div class="listora-card__media">
	<a href="<?php echo esc_url( $link ); ?>" class="listora-card__image-link" tabindex="-1" aria-hidden="true">
		<img
			class="listora-card__image"
			<?php
			/*
			 * `medium_large` (768px) first: a card is rendered at up to ~400px
			 * CSS and needs the headroom on a 2x display. `medium` used to
			 * carry that size in the card payload; it now means WordPress's
			 * 300px `medium`, matching what `/search` has always meant by the
			 * key, so this falls through it to `full` for older payloads.
			 */
			?>
			src="<?php echo esc_url( $has_featured_image ? ( $image['medium_large'] ?? $image['medium'] ?? $image['full'] ) : $placeholder_url ); ?>"
			alt="<?php echo $has_featured_image ? esc_attr( $card_image_alt ) : ''; ?>"
			<?php echo $has_featured_image ? '' : 'aria-hidden="true"'; ?>
			loading="lazy"
			decoding="async"
			itemprop="image"
			data-listora-fallback-src="<?php echo esc_url( $placeholder_url ); ?>"
		/>
	</a>

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
		aria-label="<?php esc_attr_e( 'Save to favorites', 'wb-listora' ); ?>"
		data-wp-bind--aria-label="state.favoriteAriaLabel"
		data-wp-bind--aria-pressed="state.isFavorited"
	>
		<svg class="listora-favorite-btn__icon" viewBox="0 0 24 24" aria-hidden="true">
			<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
		</svg>
		<?php if ( $card_fav_count > 0 ) : ?>
		<span class="listora-favorite-btn__count"><?php echo esc_html( $card_fav_count ); ?></span>
		<?php endif; ?>
	</button>
	<?php endif; ?>

	<?php if ( $show_rating && $rating['average'] > 0 ) : ?>
	<span class="listora-rating listora-card__rating" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: average rating number */ __( 'Rating: %s out of 5', 'wb-listora' ), $rating['average'] ) ); ?>">
		<svg class="listora-rating__star" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
		<span><?php echo esc_html( number_format( $rating['average'], 1 ) ); ?></span>
	</span>
	<?php endif; ?>
</div>
<?php
do_action( 'wb_listora_after_card_image', $view_data );
