<?php
/**
 * Featured Listings block — carousel of top listings.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

$listing_type = $attributes['listingType'] ?? '';
$count        = $attributes['count'] ?? 8;
$columns      = $attributes['columns'] ?? 4;
$sort         = $attributes['sort'] ?? 'featured';
$title        = $attributes['title'] ?? '';

// Query featured/top listings.
$engine = new \WBListora\Search\Search_Engine();
$result = $engine->search(
	array(
		'type'          => $listing_type,
		'sort'          => $sort,
		'per_page'      => $count,
		'page'          => 1,
		'featured_only' => 'featured' === $sort,
	)
);

$ids = $result['listing_ids'];

// If not enough featured, fill with top-rated.
if ( count( $ids ) < $count && 'featured' === $sort ) {
	$more = $engine->search(
		array(
			'type'     => $listing_type,
			'sort'     => 'rating',
			'per_page' => $count - count( $ids ),
			'page'     => 1,
		)
	);
	$ids  = array_unique( array_merge( $ids, $more['listing_ids'] ) );
}

if ( empty( $ids ) ) {
	return;
}

// Build "See all" URL.
$archive_link = get_post_type_archive_link( 'listora_listing' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-featured',
		'data-wp-interactive' => 'listora/directory',
		'style'               => '--listora-featured-columns: ' . (int) $columns,
	)
);

$dot_count = max( 1, (int) ceil( count( $ids ) / $columns ) );
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( $title ) : ?>
	<div class="listora-featured__header">
		<h2 class="listora-featured__title"><?php echo esc_html( $title ); ?></h2>

		<?php if ( $archive_link ) : ?>
		<a href="<?php echo esc_url( $archive_link ); ?>" class="listora-featured__see-all">
			<?php esc_html_e( 'See all', 'wb-listora' ); ?> &rarr;
		</a>
		<?php endif; ?>

		<div class="listora-featured__nav-arrows">
			<button
				type="button"
				class="listora-featured__arrow listora-featured__arrow--prev"
				data-wp-on--click="actions.scrollFeaturedPrev"
				aria-label="<?php esc_attr_e( 'Previous', 'wb-listora' ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path d="m15 18-6-6 6-6"></path>
				</svg>
			</button>
			<button
				type="button"
				class="listora-featured__arrow listora-featured__arrow--next"
				data-wp-on--click="actions.scrollFeaturedNext"
				aria-label="<?php esc_attr_e( 'Next', 'wb-listora' ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path d="m9 18 6-6-6-6"></path>
				</svg>
			</button>
		</div>
	</div>
	<?php endif; ?>

	<div class="listora-featured__track" data-wp-key="featured-track">
		<?php
		foreach ( $ids as $card_index => $lid ) :
			$data = wb_listora_prepare_card_data( (int) $lid );
			if ( ! $data ) {
				continue;
			}
			$attributes_card = array(
				'listingId'     => (int) $lid,
				'layout'        => 'standard',
				'showRating'    => true,
				'showFavorite'  => true,
				'showType'      => false,
				'showFeatures'  => false,
				'maxMetaFields' => 3,
				'_listing_data' => $data,
				'_card_index'   => $card_index,
			);
			$attributes      = $attributes_card; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			include WB_LISTORA_PLUGIN_DIR . 'blocks/listing-card/render.php';
		endforeach;
		?>
	</div>

	<?php if ( $dot_count > 1 ) : ?>
	<div class="listora-featured__dots" aria-label="<?php esc_attr_e( 'Carousel navigation', 'wb-listora' ); ?>">
		<?php for ( $i = 0; $i < $dot_count; $i++ ) : ?>
		<button
			type="button"
			class="listora-featured__dot<?php echo 0 === $i ? ' is-active' : ''; ?>"
			data-wp-on--click="actions.scrollFeaturedToPage"
			data-wp-context='<?php echo wp_json_encode( array( 'dotIndex' => $i ) ); ?>'
			aria-label="<?php echo esc_attr( sprintf( __( 'Go to slide group %d', 'wb-listora' ), $i + 1 ) ); ?>"
		></button>
		<?php endfor; ?>
	</div>
	<?php endif; ?>

</div>
