<?php
/**
 * Listing Categories block — browse-by-category grid.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

$listing_type = $attributes['listingType'] ?? '';
$columns      = $attributes['columns'] ?? 4;
$show_count   = $attributes['showCount'] ?? true;
$show_icon    = $attributes['showIcon'] ?? true;
$limit        = $attributes['limit'] ?? 12;
$hide_empty   = $attributes['hideEmpty'] ?? false;

// Get categories — scoped to type if specified.
$term_args = array(
	'taxonomy'   => 'listora_listing_cat',
	'hide_empty' => $hide_empty,
	'number'     => $limit,
	'orderby'    => 'count',
	'order'      => 'DESC',
);

if ( $listing_type ) {
	$registry = \WBListora\Core\Listing_Type_Registry::instance();
	$type     = $registry->get( $listing_type );
	if ( $type ) {
		$allowed = $type->get_allowed_categories();
		if ( ! empty( $allowed ) ) {
			$term_args['include'] = $allowed;
		}
	}
}

$categories = get_terms( $term_args );

if ( is_wp_error( $categories ) || empty( $categories ) ) {
	return;
}

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class' => 'listora-categories',
		'style' => '--listora-cat-columns: ' . (int) $columns,
	)
);
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="listora-categories__grid">
		<?php
		foreach ( $categories as $cat_index => $cat ) :
			$icon  = get_term_meta( $cat->term_id, '_listora_icon', true );
			$image = get_term_meta( $cat->term_id, '_listora_image', true );
			$color = get_term_meta( $cat->term_id, '_listora_color', true ) ?: 'var(--listora-primary)';
			$link  = get_term_link( $cat );

			$card_classes = 'listora-categories__card';
			$card_style   = '--cat-color: ' . esc_attr( $color ) . '; --cat-index: ' . (int) $cat_index;

			if ( $image ) {
				$card_classes .= ' listora-categories__card--has-image';
				$card_style   .= '; background-image: url(' . esc_url( $image ) . ')';
			}
			?>
		<a href="<?php echo esc_url( $link ); ?>" class="<?php echo esc_attr( $card_classes ); ?>" style="<?php echo esc_attr( $card_style ); ?>">
			<?php if ( $show_icon && ! $image ) : ?>
			<span class="listora-categories__icon-wrap">
				<?php if ( $icon ) : ?>
				<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
				<?php else : ?>
				<span class="listora-categories__letter"><?php echo esc_html( mb_substr( $cat->name, 0, 1 ) ); ?></span>
				<?php endif; ?>
			</span>
			<?php endif; ?>
			<span class="listora-categories__name"><?php echo esc_html( $cat->name ); ?></span>
			<?php if ( $show_count ) : ?>
			<span class="listora-categories__count">
				<?php echo esc_html( sprintf( _n( '%s listing', '%s listings', $cat->count, 'wb-listora' ), number_format_i18n( $cat->count ) ) ); ?>
			</span>
			<?php endif; ?>
		</a>
		<?php endforeach; ?>
	</div>
</div>
