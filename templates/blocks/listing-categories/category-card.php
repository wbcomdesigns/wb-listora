<?php
/**
 * Listing Categories — Individual category card.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-categories/category-card.php
 *
 * @package WBListora
 *
 * @var WP_Term $cat          Term object for this category.
 * @var int     $cat_index    Index of this category in the loop.
 * @var string  $icon         Lucide icon slug or empty.
 * @var string  $image        Background image URL or empty.
 * @var string  $color        Category color (hex or CSS variable).
 * @var string  $link         Category archive permalink.
 * @var string  $card_classes CSS classes for the card link.
 * @var string  $card_style   Inline style string for the card link.
 * @var string  $name         Category display name.
 * @var int     $count        Number of listings in this category.
 * @var bool    $show_count   Whether to show the listing count.
 * @var bool    $show_icon    Whether to show the category icon.
 * @var array   $card_data    Full card data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<a
	href="<?php echo esc_url( $link ); ?>"
	class="<?php echo esc_attr( $card_classes ); ?>"
	style="<?php echo esc_attr( $card_style ); ?>"
	role="listitem"
	aria-label="<?php echo esc_attr( $name ); ?>"
>
	<?php if ( $show_icon && ! $image ) : ?>
	<span class="listora-categories__icon-wrap" aria-hidden="true">
		<?php if ( $icon ) : ?>
			<?php echo \WBListora\Core\Lucide_Icons::render( $icon, 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
		<span class="listora-categories__letter"><?php echo esc_html( mb_substr( $name, 0, 1 ) ); ?></span>
		<?php endif; ?>
	</span>
	<?php endif; ?>
	<span class="listora-categories__name"><?php echo esc_html( $name ); ?></span>
	<?php if ( $show_count ) : ?>
	<span class="listora-categories__count">
		<?php /* translators: %s: number of listings */ ?>
		<?php echo esc_html( sprintf( _n( '%s listing', '%s listings', $count, 'wb-listora' ), number_format_i18n( $count ) ) ); ?>
	</span>
	<?php endif; ?>
</a>
