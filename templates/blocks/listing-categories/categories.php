<?php
/**
 * Listing Categories — Main wrapper with grid.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-categories/categories.php
 *
 * @package WBListora
 *
 * @var string $wrapper_attrs  Block wrapper attributes string (class + style).
 * @var array  $categories     Array of WP_Term objects.
 * @var bool   $show_count     Whether to show listing counts.
 * @var bool   $show_icon      Whether to show category icons.
 * @var array  $attributes     Block attributes array.
 * @var array  $view_data      Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="listora-categories__grid" role="list">
		<?php
		foreach ( $categories as $cat_index => $cat ) :
			$icon  = get_term_meta( $cat->term_id, '_listora_icon', true );
			$image = get_term_meta( $cat->term_id, '_listora_image', true );
			$color = get_term_meta( $cat->term_id, '_listora_color', true ) ?: 'var(--listora-primary)';
			$link  = get_term_link( $cat );

			// Guard against WP_Error (invalid term or taxonomy not registered yet).
			if ( is_wp_error( $link ) ) {
				continue;
			}

			$card_classes = 'listora-categories__card';
			// Trailing semicolons keep each declaration well-formed for CSS concatenation.
			$card_style = '--cat-color: ' . esc_attr( $color ) . '; --cat-index: ' . (int) $cat_index . ';';

			if ( $image ) {
				$card_classes .= ' listora-categories__card--has-image';
				// Quoted URL inside url() prevents CSS parsing failures with special characters.
				$card_style .= ' background-image: url(\'' . esc_url( $image ) . '\');';
			}

			/**
			 * Hook: Filter each category card's data before rendering.
			 *
			 * @since 1.1.0
			 */
			$cat_data = apply_filters(
				'wb_listora_category_card_data',
				array(
					'icon'         => $icon,
					'image'        => $image,
					'color'        => $color,
					'link'         => $link,
					'card_classes' => $card_classes,
					'card_style'   => $card_style,
					'name'         => $cat->name,
					'count'        => $cat->count,
				),
				$cat
			);

			// Re-apply any changes made by the filter.
			$icon         = $cat_data['icon'];
			$image        = $cat_data['image'];
			$color        = $cat_data['color'];
			$link         = $cat_data['link'];
			$card_classes = $cat_data['card_classes'];
			$card_style   = $cat_data['card_style'];

			$card_data = array(
				'cat'          => $cat,
				'cat_index'    => $cat_index,
				'icon'         => $icon,
				'image'        => $image,
				'color'        => $color,
				'link'         => $link,
				'card_classes' => $card_classes,
				'card_style'   => $card_style,
				'name'         => $cat_data['name'],
				'count'        => $cat_data['count'],
				'show_count'   => $show_count,
				'show_icon'    => $show_icon,
			);
			$card_data['card_data'] = $card_data;

			wb_listora_get_template( 'blocks/listing-categories/category-card.php', $card_data );
		endforeach;
		?>
	</div>
</div>
