<?php
/**
 * Email template: Listing Approved.
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Listing title.
 *   $listing_url    (string) Listing permalink.
 *   $author_name    (string) Listing author's display name.
 *   $dashboard_url  (string) User dashboard URL.
 *   $colors         (array)  Palette.
 *   $variant        (string) 'success'.
 *   $is_marketing   (bool)
 *   $unsubscribe_url (string)
 *   $logo_url       (string) Optional header logo.
 *   $footer_text    (string) Optional footer override.
 *
 * Override via: {theme}/wb-listora/emails/listing-approved.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'Your listing is live!', 'wb-listora' );

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
				esc_html__( 'Great news! Your listing "%s" has been approved and is now live in the directory.', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			?>
		</p>
		<p style="margin:0 0 1rem;">
			<a href="<?php echo esc_url( $listing_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['success'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'View Your Listing', 'wb-listora' ); ?>
			</a>
		</p>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $dashboard_url ); ?>"
				style="font-size:0.85rem;color:<?php echo esc_attr( $colors['primary'] ); ?>;text-decoration:underline;">
				<?php esc_html_e( 'Manage from your dashboard', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
