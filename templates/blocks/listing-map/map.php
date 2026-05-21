<?php
/**
 * Listing Map block — main map wrapper template.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-map/map.php
 *
 * @package WBListora
 *
 * @var string $wrapper_attrs   Block wrapper attributes string.
 * @var string $height          Map container height CSS value.
 * @var int    $markers_count   Number of initial markers.
 * @var string $map_element_id  Unique DOM ID for the map container.
 * @var bool   $show_near_me    Whether to show the "Near Me" button.
 * @var bool   $search_on_drag  Whether to show the "Search this area" button.
 * @var array  $view_data       Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<a href="#listora-after-map" class="listora-sr-only listora-sr-only--focusable">
		<?php esc_html_e( 'Skip map, go to listing results', 'wb-listora' ); ?>
	</a>

	<div
		class="listora-map"
		id="<?php echo esc_attr( $map_element_id ); ?>"
		role="application"
		aria-label="
		<?php
		echo esc_attr(
			sprintf(
			/* translators: %d: number of markers */
				__( 'Map showing %d listing locations', 'wb-listora' ),
				$markers_count
			)
		);
		?>
		"
		style="height: <?php echo esc_attr( $height ); ?>;"
		data-wp-init="callbacks.onMapInit"
	></div>

	<?php // --- Map Controls --- ?>
	<div class="listora-map__controls">
		<?php if ( $show_near_me ) : ?>
		<button
			type="button"
			class="listora-btn listora-btn--secondary listora-map__near-me-btn"
			data-wp-on--click="actions.nearMe"
			aria-label="<?php esc_attr_e( 'Find listings near your location', 'wb-listora' ); ?>"
		>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
			</svg>
			<?php esc_html_e( 'Near Me', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>

		<?php if ( $search_on_drag ) : ?>
		<button
			type="button"
			class="listora-btn listora-btn--secondary listora-map__search-area-btn"
			data-wp-on--click="actions.searchMapArea"
			style="display: none;"
			aria-label="<?php esc_attr_e( 'Search listings in this map area', 'wb-listora' ); ?>"
		>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>
			</svg>
			<?php esc_html_e( 'Search this area', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<span id="listora-after-map"></span>
</div>
