<?php
/**
 * Email template: Listing Submitted (admin notification).
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Listing title.
 *   $listing_url    (string) Listing permalink.
 *   $author_name    (string) Submitting author's display name.
 *   $status         (string) Submission status.
 *   $admin_url      (string) Admin edit URL.
 *   $colors         (array)  Palette from Notifications::get_palette().
 *   $variant        (string) Variant string (neutral).
 *   $is_marketing   (bool)   Unsubscribe link toggle.
 *   $unsubscribe_url (string) Dashboard URL for marketing preferences.
 *   $logo_url       (string) Optional header logo.
 *   $footer_text    (string) Optional footer override.
 *
 * Override via: {theme}/wb-listora/emails/listing-submitted.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = sprintf(
	/* translators: %s: site name */
	__( 'New listing on %s', 'wb-listora' ),
	$site_name
);

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
				/* translators: 1: listing title, 2: author name */
				esc_html__( 'A new listing "%1$s" has been submitted by %2$s and is awaiting your review.', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_html( $author_name )
			);
			?>
		</p>
		<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;border-radius:6px;padding:1rem;width:100%;">
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;padding-bottom:0.4rem;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Listing:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( $listing_title ); ?>
				</td>
			</tr>
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Submitted by:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( $author_name ); ?>
				</td>
			</tr>
		</table>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $admin_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'Review Listing', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
