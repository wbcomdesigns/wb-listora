<?php
/**
 * Email template: Listing Renewed.
 *
 * Variables available:
 *   $site_name        (string) Site name.
 *   $site_url         (string) Site home URL.
 *   $listing_title    (string) Listing title.
 *   $listing_url      (string) Listing permalink.
 *   $new_expiry_date  (string) Formatted new expiration date.
 *   $author_name      (string) Listing owner's display name.
 *   $colors (array), $variant ('success'), $is_marketing, $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/listing-renewed.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'Listing renewed successfully', 'wb-listora' );

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:1rem;color:<?php echo esc_attr( $colors['text'] ); ?>;">
			<?php
			printf(
				/* translators: %s: author display name */
				esc_html__( 'Hi %s,', 'wb-listora' ),
				esc_html( $author_name )
			);
			?>
		</p>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: %s: listing title */
				esc_html__( 'Your listing "%s" has been renewed and is live again in the directory.', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			?>
		</p>
		<?php if ( ! empty( $new_expiry_date ) ) : ?>
		<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;border-radius:6px;padding:1rem;width:100%;">
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'New expiration date:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( $new_expiry_date ); ?>
				</td>
			</tr>
		</table>
		<?php endif; ?>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $listing_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['success'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'View Your Listing', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
