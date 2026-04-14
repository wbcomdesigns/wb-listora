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

do_action( 'wb_listora_before_card_image', $view_data );
?>
<div class="listora-card__media">
	<a href="<?php echo esc_url( $link ); ?>" class="listora-card__image-link" tabindex="-1" aria-hidden="true">
		<img
			class="listora-card__image"
			src="<?php echo esc_url( $image ? ( $image['medium'] ?? $image['full'] ) : $placeholder_url ); ?>"
			alt="<?php echo esc_attr( $title ); ?>"
			loading="lazy"
			decoding="async"
			itemprop="image"
			onerror="this.onerror=null;this.src='<?php echo esc_url( $placeholder_url ); ?>';"
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
		data-wp-bind--aria-label="state.isFavorited ? '<?php echo esc_js( __( 'Remove from favorites', 'wb-listora' ) ); ?>' : '<?php echo esc_js( __( 'Save to favorites', 'wb-listora' ) ); ?>'"
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
