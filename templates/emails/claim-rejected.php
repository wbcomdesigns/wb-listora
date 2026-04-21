<?php
/**
 * Email template: Claim Rejected.
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Listing title.
 *   $author_name    (string) Claimant's display name.
 *   $admin_notes    (string) Admin notes explaining rejection.
 *   $colors (array), $variant ('danger'), $is_marketing, $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/claim-rejected.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'Claim not approved', 'wb-listora' );

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:1rem;color:<?php echo esc_attr( $colors['text'] ); ?>;">
			<?php
			printf(
				/* translators: %s: claimant display name */
				esc_html__( 'Hi %s,', 'wb-listora' ),
				esc_html( $author_name )
			);
			?>
		</p>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: %s: listing title */
				esc_html__( 'Your ownership claim for "%s" was not approved.', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			?>
		</p>
		<?php if ( ! empty( $admin_notes ) ) : ?>
		<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;border-radius:6px;padding:1rem;width:100%;">
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Notes from admin:', 'wb-listora' ); ?></strong><br/>
					<?php echo esc_html( $admin_notes ); ?>
				</td>
			</tr>
		</table>
		<?php endif; ?>
		<p style="margin:0;font-size:0.9rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.5;">
			<?php esc_html_e( 'If you believe this was an error, please contact us for further assistance.', 'wb-listora' ); ?>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
