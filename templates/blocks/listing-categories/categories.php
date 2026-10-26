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
 * @var array  $categories     WP_Term objects for the grid (the first `limit`).
 * @var int    $total_count    Every category that matched, grid included.
 * @var array  $groups         Expanded list: { label, cats[] } per listing type. Empty when the grid shows everything.
 * @var array  $cat_types      term_id => Listing_Type that owns the category (icon + colour fallback).
 * @var bool   $show_count     Whether to show listing counts.
 * @var bool   $show_icon      Whether to show category icons.
 * @var bool   $show_type_label Whether grid tiles name their listing type (unpinned block).
 * @var array  $attributes     Block attributes array.
 * @var array  $view_data      Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;

$cat_types = isset( $cat_types ) && is_array( $cat_types ) ? $cat_types : array();
$groups    = isset( $groups ) && is_array( $groups ) ? $groups : array();
// Template overrides written before 1.9.1 do not pass it.
$show_type_label = ! empty( $show_type_label );

/**
 * Render one category tile.
 *
 * @param WP_Term $cat       Category.
 * @param int     $cat_index Position, for the staggered entrance.
 * @param bool    $with_type Name the owning type under the tile (the grouped list has a heading instead).
 */
$listora_render_tile = static function ( $cat, $cat_index, $with_type = false ) use ( $cat_types, $show_count, $show_icon ) {
	$owner = $cat_types[ (int) $cat->term_id ] ?? null;
	$icon  = get_term_meta( $cat->term_id, '_listora_icon', true );
	// The term stores an attachment ID; the tile needs the image URL.
	$image = wp_get_attachment_image_url( (int) get_term_meta( $cat->term_id, '_listora_image', true ), 'medium_large' );
	// A category without its own colour takes its type's.
	$color = wb_listora_category_color( $cat->term_id, $owner ) ?: 'var(--listora-primary)';
	$link  = get_term_link( $cat );

	// Guard against WP_Error (invalid term or taxonomy not registered yet).
	if ( is_wp_error( $link ) ) {
		return;
	}

	$card_classes = 'listora-categories__card';
	// Trailing semicolons keep each declaration well-formed for CSS concatenation.
	$card_style = '--cat-color: ' . esc_attr( $color ) . '; --cat-index: ' . (int) $cat_index . ';';

	if ( $image ) {
		$card_classes .= ' listora-categories__card--has-image';
		// Quoted URL inside url() prevents CSS parsing failures with special characters.
		$card_style .= ' background-image: url(\'' . esc_url( $image ) . '\');';
	}

	$defaults = array(
		'icon'         => $icon,
		'type_icon'    => $owner ? $owner->get_icon() : '',
		'image'        => $image,
		'color'        => $color,
		'link'         => $link,
		'card_classes' => $card_classes,
		'card_style'   => $card_style,
		'name'         => $cat->name,
		'count'        => $cat->count,
		'type_label'   => $with_type && $owner ? $owner->get_name() : '',
	);

	/**
	 * Hook: Filter each category card's data before rendering.
	 *
	 * @since 1.1.0
	 */
	$cat_data = apply_filters( 'wb_listora_category_card_data', $defaults, $cat );

	// A listener returning a scalar must not fatal the offset reads below.
	if ( ! is_array( $cat_data ) ) {
		$cat_data = $defaults;
	}

	$card_data              = array(
		'cat'          => $cat,
		'cat_index'    => $cat_index,
		'icon'         => $cat_data['icon'] ?? '',
		'type_icon'    => $cat_data['type_icon'] ?? '',
		'image'        => $cat_data['image'] ?? '',
		'color'        => $cat_data['color'] ?? '',
		'link'         => $cat_data['link'] ?? '',
		'card_classes' => $cat_data['card_classes'] ?? '',
		'card_style'   => $cat_data['card_style'] ?? '',
		'name'         => $cat_data['name'] ?? $cat->name,
		'count'        => $cat_data['count'] ?? $cat->count,
		'type_label'   => $cat_data['type_label'] ?? '',
		'show_count'   => $show_count,
		'show_icon'    => $show_icon,
	);
	$card_data['card_data'] = $card_data;

	wb_listora_get_template( 'blocks/listing-categories/category-card.php', $card_data );
};
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its output. ?>>
	<div class="listora-categories__grid listora-categories__grid--top" role="list">
		<?php
		foreach ( $categories as $cat_index => $cat ) {
			$listora_render_tile( $cat, $cat_index, $show_type_label );
		}
		?>
	</div>

	<?php
	/*
	 * Everything else, grouped by listing type, behind a native <details>.
	 * No script: the summary is the toggle, and the CSS swaps the top grid
	 * out while it is open so nothing shows twice.
	 */
	if ( $groups ) :
		?>
	<details class="listora-categories__all">
		<summary class="listora-btn listora-btn--secondary listora-categories__toggle">
			<span class="listora-categories__toggle-more">
				<?php
				printf(
					/* translators: %s: number of categories */
					esc_html__( 'View all %s categories', 'wb-listora' ),
					esc_html( number_format_i18n( (int) $total_count ) )
				);
				?>
			</span>
			<span class="listora-categories__toggle-less"><?php esc_html_e( 'Show fewer', 'wb-listora' ); ?></span>
		</summary>
		<?php foreach ( $groups as $group_index => $group ) : ?>
		<section class="listora-categories__group">
			<h3 class="listora-categories__group-title"><?php echo esc_html( $group['label'] ); ?></h3>
			<div class="listora-categories__grid" role="list">
				<?php
				foreach ( $group['cats'] as $cat_index => $cat ) {
					$listora_render_tile( $cat, $cat_index );
				}
				?>
			</div>
		</section>
		<?php endforeach; ?>
	</details>
	<?php endif; ?>
</div>
