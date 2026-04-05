<?php
/**
 * Email template: Review Reply.
 *
 * Sent to the review author when the listing owner replies to their review.
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Listing title.
 *   $listing_url    (string) Listing URL anchored to the review.
 *   $reviewer_name  (string) Review author's display name.
 *   $reply_text     (string) Owner's reply content.
 *   $owner_name     (string) Listing owner's display name.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f0f0f1;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f1;padding:2rem 1rem;">
	<tr><td align="center">
	<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,sans-serif;color:#1e1e1e;">

		<!-- Header -->
		<tr>
			<td style="padding:1.5rem 2rem;background:#1e1e1e;text-align:center;">
				<p style="margin:0;font-size:1.1rem;font-weight:600;color:#ffffff;">
					<?php esc_html_e( 'New reply to your review', 'wb-listora' ); ?>
				</p>
			</td>
		</tr>

		<!-- Body -->
		<tr>
			<td style="padding:2rem;">
				<p style="margin:0 0 1rem;font-size:1rem;color:#1e1e1e;">
					<?php
					printf(
						/* translators: %s: reviewer display name */
						esc_html__( 'Hi %s,', 'wb-listora' ),
						esc_html( $reviewer_name )
					);
					?>
				</p>
				<p style="margin:0 0 1.5rem;font-size:0.95rem;color:#3c434a;line-height:1.6;">
					<?php
					printf(
						/* translators: 1: owner name, 2: listing title */
						esc_html__( '%1$s replied to your review of "%2$s".', 'wb-listora' ),
						'<strong>' . esc_html( $owner_name ) . '</strong>',
						esc_html( $listing_title )
					);
					?>
				</p>

				<!-- Reply quote -->
				<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:#f6f7f7;border-radius:6px;padding:1.25rem;width:100%;">
					<tr>
						<td style="border-left:3px solid #2271b1;padding-left:1rem;">
							<p style="margin:0;font-size:0.875rem;color:#3c434a;line-height:1.6;font-style:italic;">
								<?php echo esc_html( $reply_text ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p style="margin:0;">
					<a href="<?php echo esc_url( $listing_url ); ?>"
						style="display:inline-block;padding:0.7rem 1.5rem;background:#2271b1;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
						<?php esc_html_e( 'View Reply', 'wb-listora' ); ?>
					</a>
				</p>
			</td>
		</tr>

		<!-- Footer -->
		<tr>
			<td style="padding:1rem 2rem;border-top:1px solid #e0e0e0;text-align:center;">
				<p style="margin:0;font-size:0.8rem;color:#a7aaad;">
					<?php
					printf(
						/* translators: 1: site name, 2: site URL */
						esc_html__( 'This email was sent by %1$s.', 'wb-listora' ),
						'<a href="' . esc_url( $site_url ) . '" style="color:#a7aaad;">' . esc_html( $site_name ) . '</a>'
					);
					?>
				</p>
			</td>
		</tr>

	</table>
	</td></tr>
</table>
</body>
</html>
