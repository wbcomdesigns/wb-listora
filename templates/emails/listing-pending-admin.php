<?php
/**
 * Email template: Listing Pending Admin Review.
 *
 * Variables available:
 *   $site_name        (string) Site name.
 *   $site_url         (string) Site home URL.
 *   $listing_title    (string) Listing title.
 *   $admin_review_url (string) Admin edit URL for the listing.
 *   $author_name      (string) Submitting author's display name.
 *   $listing_type     (string) Listing type label.
 *   $colors (array), $variant ('neutral'), $is_marketing, $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/listing-pending-admin.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'New listing needs your review', 'wb-listora' );

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
				esc_html__( 'New listing "%1$s" submitted by %2$s needs your review.', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				'<strong>' . esc_html( $author_name ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
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
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;padding-bottom:0.4rem;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Submitted by:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( $author_name ); ?>
				</td>
			</tr>
			<?php if ( ! empty( $listing_type ) ) : ?>
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Type:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( $listing_type ); ?>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $admin_review_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'Review Listing', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
