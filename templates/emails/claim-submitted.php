<?php
/**
 * Email template: Claim Submitted (admin notification).
 *
 * Variables available:
 *   $site_name       (string) Site name.
 *   $site_url        (string) Site home URL.
 *   $listing_title   (string) Listing title.
 *   $listing_url     (string) Listing permalink.
 *   $claimant_name   (string) Claimant's display name.
 *   $claimant_email  (string) Claimant's email address.
 *   $admin_url       (string) Admin claims management URL.
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
					<?php esc_html_e( 'New claim request', 'wb-listora' ); ?>
				</p>
			</td>
		</tr>

		<!-- Body -->
		<tr>
			<td style="padding:2rem;">
				<p style="margin:0 0 1rem;font-size:1rem;color:#1e1e1e;">
					<?php esc_html_e( 'Hi Admin,', 'wb-listora' ); ?>
				</p>
				<p style="margin:0 0 1.5rem;font-size:0.95rem;color:#3c434a;line-height:1.6;">
					<?php
					printf(
						/* translators: 1: claimant name, 2: listing title */
						esc_html__( '%1$s has submitted a claim for the listing "%2$s" and is awaiting your review.', 'wb-listora' ),
						'<strong>' . esc_html( $claimant_name ) . '</strong>',
						'<strong>' . esc_html( $listing_title ) . '</strong>'
					);
					?>
				</p>
				<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:#f6f7f7;border-radius:6px;padding:1rem;width:100%;">
					<tr>
						<td style="font-size:0.85rem;color:#646970;padding-bottom:0.4rem;">
							<strong style="color:#1e1e1e;"><?php esc_html_e( 'Claimant:', 'wb-listora' ); ?></strong>
							<?php echo esc_html( $claimant_name ); ?>
						</td>
					</tr>
					<tr>
						<td style="font-size:0.85rem;color:#646970;padding-bottom:0.4rem;">
							<strong style="color:#1e1e1e;"><?php esc_html_e( 'Email:', 'wb-listora' ); ?></strong>
							<a href="mailto:<?php echo esc_attr( $claimant_email ); ?>" style="color:#2271b1;"><?php echo esc_html( $claimant_email ); ?></a>
						</td>
					</tr>
					<tr>
						<td style="font-size:0.85rem;color:#646970;">
							<strong style="color:#1e1e1e;"><?php esc_html_e( 'Listing:', 'wb-listora' ); ?></strong>
							<a href="<?php echo esc_url( $listing_url ); ?>" style="color:#2271b1;"><?php echo esc_html( $listing_title ); ?></a>
						</td>
					</tr>
				</table>
				<p style="margin:0;">
					<a href="<?php echo esc_url( $admin_url ); ?>"
						style="display:inline-block;padding:0.7rem 1.5rem;background:#2271b1;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
						<?php esc_html_e( 'Review Claim', 'wb-listora' ); ?>
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
