<?php
/**
 * Email template: Claim Submitted (admin notification).
 *
 * Variables available:
 *   $site_name       (string) Site name.
 *   $site_url        (string) Site home URL.
 *   $listing_title   (string) Listing title.
 *   $listing_url     (string) Listing permalink.
 *   $claimant_name   (string) Claimant's display name.
 *   $claimant_email  (string) Claimant's email address.
 *   $admin_url       (string) Admin claims management URL.
 *   $colors (array), $variant ('neutral'), $is_marketing, $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/claim-submitted.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'New claim request', 'wb-listora' );

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:1rem;color:<?php echo esc_attr( $colors['text'] ); ?>;">
			<?php esc_html_e( 'Hi Admin,', 'wb-listora' ); ?>
		</p>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: 1: claimant name, 2: listing title */
				esc_html__( '%1$s has submitted a claim for the listing "%2$s" and is awaiting your review.', 'wb-listora' ),
				'<strong>' . esc_html( $claimant_name ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				'<strong>' . esc_html( $listing_title ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			?>
		</p>
		<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;border-radius:6px;padding:1rem;width:100%;">
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;padding-bottom:0.4rem;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Claimant:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( $claimant_name ); ?>
				</td>
			</tr>
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;padding-bottom:0.4rem;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Email:', 'wb-listora' ); ?></strong>
					<a href="mailto:<?php echo esc_attr( $claimant_email ); ?>" style="color:<?php echo esc_attr( $colors['primary'] ); ?>;"><?php echo esc_html( $claimant_email ); ?></a>
				</td>
			</tr>
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Listing:', 'wb-listora' ); ?></strong>
					<a href="<?php echo esc_url( $listing_url ); ?>" style="color:<?php echo esc_attr( $colors['primary'] ); ?>;"><?php echo esc_html( $listing_title ); ?></a>
				</td>
			</tr>
		</table>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $admin_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'Review Claim', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
