<?php
/**
 * Email template: Listing Verify Email (sent to guest submitter).
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Listing title preview.
 *   $author_name    (string) Submitter's display name.
 *   $verify_url     (string) Full verification URL with token.
 *   $expiry_hours   (int)    Hours until the link expires.
 *   $colors         (array)  Palette from Notifications::get_palette().
 *   $variant        (string) Variant string (neutral by default).
 *   $is_marketing   (bool)   Unsubscribe link toggle.
 *   $unsubscribe_url (string) Dashboard URL for marketing preferences.
 *   $logo_url       (string) Optional header logo.
 *   $footer_text    (string) Optional footer override.
 *
 * Override via: {theme}/wb-listora/emails/listing-verify-email.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = sprintf(
	/* translators: %s: site name */
	__( 'Verify your email at %s', 'wb-listora' ),
	$site_name
);

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:1rem;color:<?php echo esc_attr( $colors['text'] ); ?>;">
			<?php
			printf(
				/* translators: %s: user name */
				esc_html__( 'Hi %s,', 'wb-listora' ),
				esc_html( $author_name )
			);
			?>
		</p>
		<p style="margin:0 0 1.25rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: %s: listing title */
				esc_html__( 'Thanks for submitting your listing "%s". One last step — please confirm your email address so we can publish it.', 'wb-listora' ),
				'<strong style="color:' . esc_attr( $colors['text'] ) . ';">' . esc_html( $listing_title ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			?>
		</p>

		<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;border-radius:6px;padding:1rem;width:100%;">
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Listing:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( $listing_title ); ?>
				</td>
			</tr>
		</table>

		<p style="margin:0 0 1.5rem;text-align:center;">
			<a href="<?php echo esc_url( $verify_url ); ?>"
				style="display:inline-block;padding:0.85rem 2rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:6px;font-weight:600;font-size:1rem;">
				<?php esc_html_e( 'Verify Email', 'wb-listora' ); ?>
			</a>
		</p>

		<p style="margin:0 0 1rem;font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.5;">
			<?php esc_html_e( 'Or copy and paste this link into your browser:', 'wb-listora' ); ?>
			<br />
			<a href="<?php echo esc_url( $verify_url ); ?>" style="color:<?php echo esc_attr( $colors['primary'] ); ?>;word-break:break-all;font-size:0.8rem;">
				<?php echo esc_html( $verify_url ); ?>
			</a>
		</p>

		<p style="margin:1.5rem 0 0;padding-top:1rem;border-top:1px solid <?php echo esc_attr( $colors['border'] ); ?>;font-size:0.85rem;color:<?php echo esc_attr( $colors['text_subtle'] ); ?>;line-height:1.5;">
			<?php
			printf(
				/* translators: %d: hours until link expires */
				esc_html( _n( 'This link expires in %d hour.', 'This link expires in %d hours.', (int) $expiry_hours, 'wb-listora' ) ),
				(int) $expiry_hours
			);
			?>
			<br />
			<?php esc_html_e( 'If you did not submit this listing, ignore this email — no account changes will be made.', 'wb-listora' ); ?>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
