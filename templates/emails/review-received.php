<?php
/**
 * Email template: Review Received.
 *
 * Variables available:
 *   $site_name       (string) Site name.
 *   $site_url        (string) Site home URL.
 *   $listing_title   (string) Listing title.
 *   $listing_url     (string) Listing URL anchored to #reviews.
 *   $author_name     (string) Listing owner's display name.
 *   $reviewer_name   (string) Reviewer's display name.
 *   $review_rating   (string) Star string e.g. "★★★★★".
 *   $review_title    (string) Review title (may be empty).
 *   $review_content  (string) Trimmed review content.
 *   $colors (array), $variant ('neutral'), $is_marketing, $unsubscribe_url, $logo_url, $footer_text
 *
 * Override via: {theme}/wb-listora/emails/review-received.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$header_title = __( 'New review on your listing', 'wb-listora' );

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title', 'logo_url', 'site_name' ) );
?>
<tr>
	<td style="padding:2rem;">
		<p style="margin:0 0 1rem;font-size:1rem;color:<?php echo esc_attr( $colors['text'] ); ?>;">
			<?php
			printf(
				/* translators: %s: author display name */
				esc_html__( 'Hi %s,', 'wb-listora' ),
				esc_html( $author_name )
			);
			?>
		</p>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: 1: reviewer name, 2: listing title */
				esc_html__( '%1$s left a review on your listing "%2$s".', 'wb-listora' ),
				'<strong>' . esc_html( $reviewer_name ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_html( $listing_title )
			);
			?>
		</p>

		<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;border-radius:6px;padding:1.25rem;width:100%;">
			<tr>
				<td>
					<p style="margin:0 0 0.5rem;font-size:1.1rem;color:<?php echo esc_attr( $colors['warning'] ); ?>;letter-spacing:0.05em;">
						<?php echo esc_html( $review_rating ); ?>
					</p>
					<?php if ( ! empty( $review_title ) ) : ?>
					<p style="margin:0 0 0.5rem;font-size:0.95rem;font-weight:600;color:<?php echo esc_attr( $colors['text'] ); ?>;">
						<?php echo esc_html( $review_title ); ?>
					</p>
					<?php endif; ?>
					<?php if ( ! empty( $review_content ) ) : ?>
					<p style="margin:0;font-size:0.875rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.5;">
						<?php echo esc_html( $review_content ); ?>&hellip;
					</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p style="margin:0;">
			<a href="<?php echo esc_url( $listing_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $colors['primary'] ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'View Review', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template( 'emails/parts/footer.php', compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url', 'footer_text' ) );
