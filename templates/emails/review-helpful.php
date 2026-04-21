<?php
/**
 * Email template: Review Helpful Milestone.
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Listing title.
 *   $listing_url    (string) Listing URL anchored to the review.
 *   $reviewer_name  (string) Review author's display name.
 *   $helpful_count  (int)    Current helpful vote count.
 *   $milestone      (int)    Milestone that was reached.
 *   $colors (array), $variant ('success'), $is_marketing, $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/review-helpful.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'Your review is making an impact!', 'wb-listora' );

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
				/* translators: 1: listing title, 2: milestone number */
				esc_html__( 'Your review of "%1$s" just reached %2$s helpful votes!', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				'<strong>' . esc_html( number_format_i18n( $milestone ) ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			?>
		</p>

		<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;width:100%;">
			<tr>
				<td align="center">
					<table cellpadding="0" cellspacing="0" style="background:#f0f6fc;border-radius:8px;padding:1.25rem 2rem;">
						<tr>
							<td align="center">
								<p style="margin:0 0 0.25rem;font-size:2rem;font-weight:700;color:<?php echo esc_attr( $colors['primary'] ); ?>;">
									<?php echo esc_html( number_format_i18n( $milestone ) ); ?>
								</p>
								<p style="margin:0;font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;text-transform:uppercase;letter-spacing:0.05em;">
									<?php esc_html_e( 'helpful votes', 'wb-listora' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>

		<p style="margin:0 0 1.5rem;font-size:0.9rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.5;">
			<?php esc_html_e( 'People find your insights valuable. Keep sharing your experiences!', 'wb-listora' ); ?>
		</p>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $listing_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'View Your Review', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
