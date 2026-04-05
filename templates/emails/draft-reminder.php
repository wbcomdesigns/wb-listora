<?php
/**
 * Email template: Draft Reminder.
 *
 * Nudge email sent for abandoned draft listings after 48 hours.
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Draft listing title.
 *   $edit_url       (string) URL to continue editing the listing.
 *   $user_name      (string) Listing author's display name.
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
			<td style="padding:1.5rem 2rem;background:#dba617;text-align:center;">
				<p style="margin:0;font-size:1.1rem;font-weight:600;color:#ffffff;">
					<?php esc_html_e( 'You have an unfinished listing', 'wb-listora' ); ?>
				</p>
			</td>
		</tr>

		<!-- Body -->
		<tr>
			<td style="padding:2rem;">
				<p style="margin:0 0 1rem;font-size:1rem;color:#1e1e1e;">
					<?php
					printf(
						/* translators: %s: user display name */
						esc_html__( 'Hi %s,', 'wb-listora' ),
						esc_html( $user_name )
					);
					?>
				</p>
				<p style="margin:0 0 1.5rem;font-size:0.95rem;color:#3c434a;line-height:1.6;">
					<?php
					printf(
						/* translators: %s: listing title */
						esc_html__( 'You started a listing "%s" but haven\'t finished it yet. Complete it now to get it published and visible to visitors.', 'wb-listora' ),
						'<strong>' . esc_html( $listing_title ) . '</strong>'
					);
					?>
				</p>
				<p style="margin:0 0 1.5rem;text-align:center;">
					<a href="<?php echo esc_url( $edit_url ); ?>"
						style="display:inline-block;padding:0.85rem 2rem;background:#2271b1;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;font-size:1rem;">
						<?php esc_html_e( 'Finish Your Listing', 'wb-listora' ); ?>
					</a>
				</p>
				<p style="margin:0;font-size:0.85rem;color:#646970;line-height:1.5;">
					<?php esc_html_e( 'If you no longer wish to complete this listing, you can simply ignore this email.', 'wb-listora' ); ?>
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
