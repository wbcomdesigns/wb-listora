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
 * @var string $phone          Phone number.
 * @var string $email          Email address.
 * @var string $website        Website URL.
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

	<?php // Contact Card. ?>
	<?php if ( $phone || $email || $website ) : ?>
	<div class="listora-detail__contact-card">
		<h3><?php esc_html_e( 'Contact', 'wb-listora' ); ?></h3>
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
