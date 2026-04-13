<?php
/**
 * Listing Card — Full card template.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-card/card.php
 *
 * @package WBListora
 *
 * @var int    $id              Listing post ID.
 * @var string $title           Listing title.
 * @var string $link            Listing permalink.
 * @var string $excerpt         Listing excerpt.
 * @var string $layout          Card layout ('standard' or 'horizontal').
 * @var bool   $show_rating     Whether to show the rating badge.
 * @var bool   $show_favorite   Whether to show the favorite button.
 * @var bool   $show_type       Whether to show the listing type badge.
 * @var bool   $show_features   Whether to show features.
 * @var int    $max_meta        Maximum number of meta fields to display.
 * @var array  $type            Type data array or null.
 * @var string $type_name       Listing type name.
 * @var string $type_color      Listing type color hex.
 * @var string $type_icon       Listing type icon.
 * @var array  $meta            All listing meta values.
 * @var array  $image           Image data array with 'full' and 'medium' URLs, or null.
 * @var string $placeholder_url Placeholder image URL.
 * @var array  $rating          Rating data with 'average' and 'count'.
 * @var array  $features        Features array.
 * @var string $location        Location string.
 * @var array  $badges          Badges array.
 * @var array  $card_fields     Card field data array.
 * @var int    $card_fav_count  Favorite count for this listing.
 * @var array  $listing         Full listing data array.
 * @var string $block_classes   Block wrapper CSS classes.
 * @var string $context         JSON-encoded Interactivity API context.
 * @var int|null $card_index    Card index for staggered animation.
 * @var string $schema_type     Schema.org type.
 * @var array  $view_data       Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;

do_action( 'wb_listora_before_card', $view_data );
?>
<article
	role="listitem"
	class="listora-card listora-card--<?php echo esc_attr( $layout ); ?> <?php echo esc_attr( $block_classes ); ?>"
	data-wp-interactive="listora/directory"
	data-wp-context="<?php echo esc_attr( $context ); ?>"
	data-wp-on--mouseenter="actions.highlightMarker"
	data-wp-on--mouseleave="actions.unhighlightMarker"
	data-wp-class--is-highlighted="state.isHighlightedCard"
	itemscope
	itemtype="https://schema.org/<?php echo esc_attr( $schema_type ); ?>"
	style="--listora-type-color: <?php echo esc_attr( $type_color ); ?><?php echo null !== $card_index ? '; --card-index: ' . (int) $card_index : ''; ?>"
>
	<?php
	wb_listora_get_template( 'blocks/listing-card/card-image.php', $view_data );
	wb_listora_get_template( 'blocks/listing-card/card-content.php', $view_data );
	wb_listora_get_template( 'blocks/listing-card/card-actions.php', $view_data );
	?>

	</div>
</article>
<?php
do_action( 'wb_listora_after_card', $view_data );
