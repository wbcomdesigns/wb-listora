<?php
/**
 * Email template: Listing Reported.
 *
 * Goes to administrators and moderators, never to the listing owner - telling
 * the owner would hand a harassment vector to anyone filing reports to needle
 * them, and warn a genuine bad actor that staff are looking (card 10317616906).
 *
 * The reporter's own words are NOT included. `details` is free text typed by a
 * stranger; the reason code and a link to the listing are what staff need to
 * act, and the full report is on the listing's Reports metabox.
 *
 * Variables available:
 *   $site_name        (string) Site name.
 *   $site_url         (string) Site home URL.
 *   $listing_title    (string) Listing title.
 *   $listing_url      (string) Public listing URL.
 *   $admin_review_url (string) Admin edit URL for the listing.
 *   $report_reason    (string) Reason code the reporter chose.
 *   $report_count     (int)    Total reports now on this listing.
 *   $colors (array), $variant ('danger'), $is_marketing, $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/listing-reported.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title    = __( 'A listing has been reported', 'wb-listora' );
$reported_count  = isset( $report_count ) ? (int) $report_count : 1;
$reported_reason = isset( $report_reason ) ? (string) $report_reason : '';

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:1rem;color:<?php echo esc_attr( $colors['text'] ); ?>;">
			<?php esc_html_e( 'Hi,', 'wb-listora' ); ?>
		</p>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: %s: listing title */
				esc_html__( 'Someone reported the listing "%s" on your directory. Nobody else has been told, and the listing is still published.', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
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
			<?php if ( '' !== $reported_reason ) : ?>
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;padding-bottom:0.4rem;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Reason given:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( $reported_reason ); ?>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<td style="font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;">
					<strong style="color:<?php echo esc_attr( $colors['text'] ); ?>;"><?php esc_html_e( 'Reports on this listing:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( number_format_i18n( $reported_count ) ); ?>
				</td>
			</tr>
		</table>
		<p style="margin:0 0 1rem;">
			<a href="<?php echo esc_url( $admin_review_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'Review this listing', 'wb-listora' ); ?>
			</a>
		</p>
		<?php if ( ! empty( $listing_url ) ) : ?>
		<p style="margin:0;font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;">
			<?php esc_html_e( 'See it as a visitor does:', 'wb-listora' ); ?>
			<a href="<?php echo esc_url( $listing_url ); ?>" style="color:<?php echo esc_attr( $colors['primary'] ); ?>;"><?php echo esc_html( $listing_url ); ?></a>
		</p>
		<?php endif; ?>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
