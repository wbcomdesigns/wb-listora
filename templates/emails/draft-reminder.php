<?php
/**
 * Email template: Draft Reminder.
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Draft listing title.
 *   $edit_url       (string) URL to continue editing the listing.
 *   $user_name      (string) Listing author's display name.
 *   $colors (array), $variant ('warning'), $is_marketing (true), $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/draft-reminder.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'You have an unfinished listing', 'wb-listora' );

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:1rem;color:<?php echo esc_attr( $colors['text'] ); ?>;">
			<?php
			printf(
				/* translators: %s: user display name */
				esc_html__( 'Hi %s,', 'wb-listora' ),
				esc_html( $user_name )
			);
			?>
		</p>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: %s: listing title */
				esc_html__( 'You started a listing "%s" but haven\'t finished it yet. Complete it now to get it published and visible to visitors.', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			?>
		</p>
		<p style="margin:0 0 1.5rem;text-align:center;">
			<a href="<?php echo esc_url( $edit_url ); ?>"
				style="display:inline-block;padding:0.85rem 2rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:1rem;">
				<?php esc_html_e( 'Finish Your Listing', 'wb-listora' ); ?>
			</a>
		</p>
		<p style="margin:0;font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.5;">
			<?php esc_html_e( 'If you no longer wish to complete this listing, you can simply ignore this email.', 'wb-listora' ); ?>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
