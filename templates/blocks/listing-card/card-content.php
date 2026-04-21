<?php
/**
 * Listing Card — Content section (title, meta, rating).
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-card/card-content.php
 *
 * @package WBListora
 *
 * @var int    $id              Listing post ID.
 * @var string $title           Listing title.
 * @var string $link            Listing permalink.
 * @var string $excerpt         Listing excerpt.
 * @var string $layout          Card layout ('standard' or 'horizontal').
 * @var bool   $show_type       Whether to show the listing type badge.
 * @var string $type_name       Listing type name.
 * @var bool   $show_features   Whether to show features.
 * @var string $location        Location string.
 * @var array  $meta            All listing meta values.
 * @var array  $card_fields     Card field data array.
 * @var int    $max_meta        Maximum number of meta fields to display.
 * @var array  $features        Features array with 'name' and 'icon'.
 * @var array  $listing         Full listing data array.
 * @var array  $view_data       Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

do_action( 'wb_listora_before_card_content', $view_data );
?>
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

	<?php
	// Next occurrence for recurring events.
	$recurrence_type_val = $meta['recurrence_type'] ?? 'none';
	if ( ! empty( $recurrence_type_val ) && 'none' !== $recurrence_type_val ) :
		$next_date = \WBListora\Core\Recurrence::get_next_occurrence( $id );
		if ( $next_date ) :
			$formatted_next = wp_date( get_option( 'date_format' ), strtotime( $next_date ) );
			?>
	<span class="listora-card__next-occurrence">
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<path d="M17 2.1l4 4-4 4"/><path d="M3 12.2v-2a4 4 0 0 1 4-4h12.8M7 21.9l-4-4 4-4"/><path d="M21 11.8v2a4 4 0 0 1-4 4H4.2"/>
		</svg>
			<?php
			printf(
			/* translators: %s: formatted date of the next event occurrence */
				esc_html__( 'Next: %s', 'wb-listora' ),
				esc_html( $formatted_next )
			);
			?>
	</span>
			<?php
		endif;
	endif;
	?>

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

	<?php if ( $show_features && ! empty( $features ) ) : ?>
	<div class="listora-card__features">
		<?php foreach ( array_slice( $features, 0, 3 ) as $feature ) : ?>
		<span class="listora-feature-badge" title="<?php echo esc_attr( $feature['name'] ); ?>">
			<?php if ( ! empty( $feature['icon'] ) ) : ?>
				<?php echo \WBListora\Core\Lucide_Icons::render( $feature['icon'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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

	<?php if ( 'horizontal' === $layout && $excerpt ) : ?>
	<p class="listora-card__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 20 ) ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $listing['distance'] ) ) : ?>
	<span class="listora-card__distance">
		<?php echo esc_html( $listing['distance'] . ' ' . wb_listora_get_setting( 'distance_unit', 'km' ) ); ?>
	</span>
	<?php endif; ?>
<?php
do_action( 'wb_listora_after_card_content', $view_data );
