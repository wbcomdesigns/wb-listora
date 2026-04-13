<?php
/**
 * Listing Categories block — browse-by-category grid.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

$unique_id    = $attributes['uniqueId'] ?? '';
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
	$empty_attrs = get_block_wrapper_attributes( array( 'class' => 'listora-categories listora-categories--empty' ) );
	?>
	<div <?php echo $empty_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="listora-categories__empty">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
			</svg>
			<h3><?php esc_html_e( 'No categories yet', 'wb-listora' ); ?></h3>
			<p><?php esc_html_e( 'Categories will appear here once listings are organized.', 'wb-listora' ); ?></p>
		</div>
	</div>
	<?php
	return;
}

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class' => 'listora-categories ' . $block_classes,
		// Trailing semicolon ensures valid CSS when the block system appends additional inline styles.
		'style' => '--listora-cat-columns: ' . (int) $columns . ';',
	)
);
?>

<?php
/** Hook: Fires before the categories grid is rendered. @since 1.1.0 */
do_action( 'wb_listora_before_categories_grid', $attributes );
?>

<?php echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
			?>
		<a
			href="<?php echo esc_url( $link ); ?>"
			class="<?php echo esc_attr( $card_classes ); ?>"
			style="<?php echo esc_attr( $card_style ); ?>"
			role="listitem"
			aria-label="<?php echo esc_attr( $cat->name ); ?>"
		>
			<?php if ( $show_icon && ! $image ) : ?>
			<span class="listora-categories__icon-wrap" aria-hidden="true">
				<?php if ( $icon ) : ?>
				<?php echo \WBListora\Core\Lucide_Icons::render( $icon, 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
				<span class="listora-categories__letter"><?php echo esc_html( mb_substr( $cat->name, 0, 1 ) ); ?></span>
				<?php endif; ?>
			</span>
			<?php endif; ?>
			<span class="listora-categories__name"><?php echo esc_html( $cat->name ); ?></span>
			<?php if ( $show_count ) : ?>
			<span class="listora-categories__count">
				<?php /* translators: %s: number of listings */ ?>
				<?php echo esc_html( sprintf( _n( '%s listing', '%s listings', $cat->count, 'wb-listora' ), number_format_i18n( $cat->count ) ) ); ?>
			</span>
			<?php endif; ?>
		</a>
		<?php endforeach; ?>
	</div>
</div>
<?php
/** Hook: Fires after the categories grid wrapper is closed. @since 1.1.0 */
do_action( 'wb_listora_after_categories_grid', $attributes );
