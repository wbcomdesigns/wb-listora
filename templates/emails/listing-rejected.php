<?php
/**
 * Email template: Listing Rejected.
 *
 * Variables available:
 *   $site_name         (string) Site name.
 *   $site_url          (string) Site home URL.
 *   $listing_title     (string) Listing title.
 *   $author_name       (string) Listing author's display name.
 *   $rejection_reason  (string) Reason for rejection.
 *   $edit_url          (string) Frontend edit URL.
 *   $colors (array), $variant ('danger'), $is_marketing, $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/listing-rejected.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'Your listing needs changes', 'wb-listora' );

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
				esc_html__( 'Your listing "%s" needs a few changes before it can be published.', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			?>
		</p>
		<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:#fcf9e8;border-left:4px solid <?php echo esc_attr( $colors['warning'] ); ?>;border-radius:0 6px 6px 0;padding:1rem;width:100%;">
			<tr>
				<td>
					<p style="margin:0 0 0.3rem;font-size:0.8rem;font-weight:600;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;text-transform:uppercase;letter-spacing:0.05em;">
						<?php esc_html_e( 'Feedback from our team', 'wb-listora' ); ?>
					</p>
					<p style="margin:0;font-size:0.9rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.5;">
						<?php echo esc_html( $rejection_reason ); ?>
					</p>
				</td>
			</tr>
		</table>
		<p style="margin:0 0 0.75rem;font-size:0.9rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;">
			<?php esc_html_e( 'Please update your listing and resubmit for review.', 'wb-listora' ); ?>
		</p>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $edit_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'Edit Listing', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
