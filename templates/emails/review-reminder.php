<?php
/**
 * Email template: Review Reminder.
 *
 * Nudges a listing owner that approved reviews on their listing are still
 * awaiting a reply. Marketing-class (non-transactional) — carries the
 * unsubscribe footer.
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Listing title.
 *   $listing_url    (string) Listing URL anchored to #reviews.
 *   $author_name    (string) Listing owner's display name.
 *   $pending_count  (int)    Number of approved reviews still awaiting a reply.
 *   $colors (array), $variant ('neutral'), $is_marketing (true), $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/review-reminder.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'You have reviews waiting for a reply', 'wb-listora' );

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:1rem;color:<?php echo esc_attr( $colors['text'] ); ?>;">
			<?php
			printf(
				/* translators: %s: listing owner display name */
				esc_html__( 'Hi %s,', 'wb-listora' ),
				esc_html( $author_name )
			);
			?>
		</p>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				esc_html(
					/* translators: 1: number of pending reviews, 2: listing title */
					_n(
						'You have %1$d review on your listing "%2$s" still waiting for a reply. Responding shows visitors you are engaged and helps build trust.',
						'You have %1$d reviews on your listing "%2$s" still waiting for a reply. Responding shows visitors you are engaged and helps build trust.',
						(int) $pending_count,
						'wb-listora'
					)
				),
				(int) $pending_count,
				esc_html( $listing_title )
			);
			?>
		</p>
		<p style="margin:0 0 1.5rem;text-align:center;">
			<a href="<?php echo esc_url( $listing_url ); ?>"
				style="display:inline-block;padding:0.85rem 2rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:1rem;">
				<?php esc_html_e( 'Reply to Reviews', 'wb-listora' ); ?>
			</a>
		</p>
		<p style="margin:0;font-size:0.85rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.5;">
			<?php esc_html_e( 'If you would rather handle these later, you can simply ignore this email.', 'wb-listora' ); ?>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
