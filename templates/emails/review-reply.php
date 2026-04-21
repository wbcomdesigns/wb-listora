<?php
/**
 * Email template: Review Reply.
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Listing title.
 *   $listing_url    (string) Listing URL anchored to the review.
 *   $reviewer_name  (string) Review author's display name.
 *   $reply_text     (string) Owner's reply content.
 *   $owner_name     (string) Listing owner's display name.
 *   $colors (array), $variant ('neutral'), $is_marketing, $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/review-reply.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'New reply to your review', 'wb-listora' );

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:1rem;color:<?php echo esc_attr( $colors['text'] ); ?>;">
			<?php
			printf(
				/* translators: %s: reviewer display name */
				esc_html__( 'Hi %s,', 'wb-listora' ),
				esc_html( $reviewer_name )
			);
			?>
		</p>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: 1: owner name, 2: listing title */
				esc_html__( '%1$s replied to your review of "%2$s".', 'wb-listora' ),
				'<strong>' . esc_html( $owner_name ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_html( $listing_title )
			);
			?>
		</p>

		<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;border-radius:6px;padding:1.25rem;width:100%;">
			<tr>
				<td style="border-left:3px solid <?php echo esc_attr( $colors['primary'] ); ?>;padding-left:1rem;">
					<p style="margin:0;font-size:0.875rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;font-style:italic;">
						<?php echo esc_html( $reply_text ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p style="margin:0;">
			<a href="<?php echo esc_url( $listing_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'View Reply', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
