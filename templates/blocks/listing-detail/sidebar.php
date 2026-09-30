<?php
/**
 * Listing Detail — Sidebar (contact info, hours, map).
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-detail/sidebar.php
 *
 * @package WBListora
 *
 * @var int    $post_id        Listing post ID.
 * @var string $owner_name     Public owner name, '' when nothing to show.
 * @var string $owner_url      URL the owner name links to, '' for plain text.
 * @var string $contact_name   Contact person, '' when none.
 * @var string $phone          Phone number.
 * @var string $email          Email address.
 * @var string $website        Website URL.
 * @var bool   $show_map       Whether the Location card renders (block attribute and the type's Map toggle).
 * @var float  $lat            Latitude, 0 when unknown.
 * @var float  $lng            Longitude, 0 when unknown.
 * @var string $location       Formatted address line.
 * @var string $map_provider   Map provider slug ('osm' unless Pro swaps it).
 * @var int    $map_default_zoom Default map zoom.
 * @var array  $social_links   Social-link map (slug => url).
 * @var array  $business_hours Business hours data.
 * @var bool   $is_claimed     Whether the listing is claimed.
 * @var object $type           Listing type object or null.
 * @var array  $view_data      Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

do_action( 'wb_listora_before_detail_sidebar', $view_data );
?>
<aside class="listora-detail__sidebar">

	<?php
	/*
	 * Who listed this. Its own card rather than a line inside Contact,
	 * because a listing with no phone, email or website still has an owner
	 * and the contact card does not render at all without one of those.
	 *
	 * The helper returns '' when the Owner Name feature is off, so this is
	 * the only check the template needs.
	 */
	$sidebar_owner_name = isset( $owner_name ) ? (string) $owner_name : '';
	$sidebar_owner_url  = isset( $owner_url ) ? (string) $owner_url : '';
	?>
	<?php if ( '' !== $sidebar_owner_name ) : ?>
	<div class="listora-detail__owner-card">
		<h3><?php esc_html_e( 'Listed by', 'wb-listora' ); ?></h3>
		<p class="listora-detail__owner-name">
			<?php if ( '' !== $sidebar_owner_url ) : ?>
				<a href="<?php echo esc_url( $sidebar_owner_url ); ?>" rel="author"><?php echo esc_html( $sidebar_owner_name ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $sidebar_owner_name ); ?>
			<?php endif; ?>
		</p>
	</div>
	<?php endif; ?>

	<?php // Contact Card: the one place the phone, email and website show. ?>
	<?php $sidebar_contact_name = isset( $contact_name ) ? (string) $contact_name : ''; ?>
	<?php if ( $phone || $email || $website || '' !== $sidebar_contact_name ) : ?>
	<div class="listora-detail__contact-card">
		<h3><?php esc_html_e( 'Contact', 'wb-listora' ); ?></h3>
		<?php if ( '' !== $sidebar_contact_name ) : ?>
		<p class="listora-detail__contact-name"><?php echo esc_html( $sidebar_contact_name ); ?></p>
		<?php endif; ?>
		<?php if ( $phone ) : ?>
		<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" class="listora-detail__contact-item" itemprop="telephone">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
			<?php echo esc_html( $phone ); ?>
		</a>
		<?php endif; ?>
		<?php if ( $website ) : ?>
		<a href="<?php echo esc_url( $website ); ?>" class="listora-detail__contact-item" target="_blank" rel="noopener" itemprop="url">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
			<?php echo esc_html( wp_parse_url( $website, PHP_URL_HOST ) ?: $website ); ?>
		</a>
		<?php endif; ?>
		<?php if ( $email ) : ?>
		<a href="mailto:<?php echo esc_attr( $email ); ?>" class="listora-detail__contact-item" itemprop="email">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
			<?php echo esc_html( $email ); ?>
		</a>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
	/*
	 * Location card: one marker, the address and directions (card
	 * 10337187661). Renders only when the block shows maps AND the type's
	 * "Map enabled" toggle is on (render.php folds the toggle into
	 * $show_map). The element carries the same data attributes as the old
	 * tab-embedded map, so the store's initDetailMap engine (Leaflet, or a
	 * provider Pro registers) draws it unchanged; the callback initialises
	 * it on load since nothing has to be clicked to see it.
	 */
	$sidebar_show_map = ! empty( $show_map ) && ! empty( $lat ) && ! empty( $lng );
	if ( $sidebar_show_map ) :
		$sidebar_map_provider = isset( $map_provider ) ? (string) $map_provider : 'osm';
		$sidebar_map_zoom     = isset( $map_default_zoom ) ? (int) $map_default_zoom : 15;
		$sidebar_map_tiles    = wb_listora_get_map_tiles( $sidebar_map_provider );
		$sidebar_location     = isset( $location ) ? (string) $location : '';
		?>
	<div class="listora-detail__map-card">
		<h3><?php esc_html_e( 'Location', 'wb-listora' ); ?></h3>
		<div class="listora-detail__map-embed listora-detail__map-embed--sidebar" id="listora-detail-map"
			data-wp-init="callbacks.initSidebarMap"
			data-lat="<?php echo esc_attr( (string) $lat ); ?>" data-lng="<?php echo esc_attr( (string) $lng ); ?>"
			data-provider="<?php echo esc_attr( $sidebar_map_provider ); ?>"
			data-tile-url="<?php echo esc_attr( $sidebar_map_tiles['url'] ); ?>"
			data-tile-attribution="<?php echo esc_attr( $sidebar_map_tiles['attribution'] ); ?>"
			data-zoom="<?php echo esc_attr( (string) $sidebar_map_zoom ); ?>">
		</div>
		<?php if ( '' !== $sidebar_location ) : ?>
		<p class="listora-detail__map-address"><?php echo esc_html( $sidebar_location ); ?></p>
		<?php endif; ?>
		<a class="listora-btn listora-btn--secondary listora-detail__map-directions" href="<?php echo esc_url( wb_listora_directions_url( $lat, $lng ) ); ?>" target="_blank" rel="noopener">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
			<?php esc_html_e( 'Get directions', 'wb-listora' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<?php // Social Links. ?>
	<?php
	$social_links = isset( $social_links ) && is_array( $social_links ) ? $social_links : array();
	if ( ! empty( $social_links ) ) :
		$platforms = \WBListora\Core\Field::social_link_platforms();
		?>
	<div class="listora-detail__social-card">
		<h3><?php esc_html_e( 'Follow', 'wb-listora' ); ?></h3>
		<ul class="listora-detail__social-list">
			<?php
			foreach ( $platforms as $slug => $label ) :
				// is_scalar: a nested legacy/importer array value would fatal
				// esc_url() (ltrim on non-string throws on PHP 8).
				if ( empty( $social_links[ $slug ] ) || ! is_scalar( $social_links[ $slug ] ) ) {
					continue;
				}
				$url = esc_url( (string) $social_links[ $slug ] );
				if ( '' === $url ) {
					continue;
				}
				?>
				<li>
					<a class="listora-detail__social-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer me" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>">
						<?php echo \WBListora\Core\Lucide_Icons::render( 'external-link', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
						<span class="listora-detail__social-label"><?php echo esc_html( $label ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>

	<?php // Business Hours. ?>
	<?php if ( ! empty( $business_hours ) ) : ?>
	<div class="listora-detail__hours-card">
		<h3><?php esc_html_e( 'Business Hours', 'wb-listora' ); ?></h3>
		<?php echo wp_kses_post( wb_listora_render_hours( $business_hours ) ); ?>
	</div>
	<?php endif; ?>

	<?php // Claimed badge. ?>
	<?php if ( $is_claimed ) : ?>
	<div class="listora-detail__claimed-badge">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
		<?php esc_html_e( 'Claimed & Verified Business', 'wb-listora' ); ?>
	</div>
	<?php endif; ?>
<?php
$detail_type_slug = $type ? $type->get_slug() : '';
do_action( 'wb_listora_after_listing_fields', $post_id, $detail_type_slug );

/**
 * Hook point for booking/appointment button.
 * Third-party or Pro implements the actual booking UI.
 *
 * @param int    $post_id         Listing ID.
 * @param string $detail_type_slug Listing type slug.
 */
do_action( 'wb_listora_appointment_button', $post_id, $detail_type_slug );
?>
</aside>
<?php
do_action( 'wb_listora_after_detail_sidebar', $view_data );
