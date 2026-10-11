<?php
/**
 * Email template: a visitor messaged a listing owner.
 *
 * Variables: $listing_title, $listing_url, $sender_name, $sender_email,
 * $sender_phone (may be empty), $message, $dashboard_url (the owner's Leads
 * tab), plus the shared shell: $colors, $variant, $site_name, $site_url,
 * $logo_url, $footer_text, $is_marketing, $unsubscribe_url.
 *
 * Override via: {theme}/wb-listora/emails/lead-received.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'New message about your listing', 'wb-listora' );

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: 1: sender name, 2: listing title */
				esc_html__( '%1$s sent a message about "%2$s".', 'wb-listora' ),
				'<strong>' . esc_html( $sender_name ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
				'<a href="' . esc_url( $listing_url ) . '" style="color:' . esc_attr( $colors['primary'] ) . ';">' . esc_html( $listing_title ) . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
			);
			?>
		</p>
		<div style="margin:0 0 1.25rem;padding:1rem;background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;border-radius:4px;font-size:0.95rem;color:<?php echo esc_attr( $colors['text'] ); ?>;line-height:1.6;">
			<?php echo nl2br( esc_html( $message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped, then line breaks. ?>
		</div>
		<p style="margin:0 0 1.5rem;font-size:0.9rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php esc_html_e( 'Email:', 'wb-listora' ); ?> <a href="mailto:<?php echo esc_attr( $sender_email ); ?>" style="color:<?php echo esc_attr( $colors['primary'] ); ?>;"><?php echo esc_html( $sender_email ); ?></a>
			<?php if ( '' !== (string) $sender_phone ) : ?>
				<br><?php esc_html_e( 'Phone:', 'wb-listora' ); ?> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $sender_phone ) ); ?>" style="color:<?php echo esc_attr( $colors['primary'] ); ?>;"><?php echo esc_html( $sender_phone ); ?></a>
			<?php endif; ?>
		</p>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $dashboard_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'See all your leads', 'wb-listora' ); ?>
			</a>
		</p>
		<p style="margin:1rem 0 0;font-size:0.8rem;color:<?php echo esc_attr( $colors['text_subtle'] ); ?>;">
			<?php esc_html_e( 'Reply to this email to answer them directly.', 'wb-listora' ); ?>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
