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
			<td style="padding:1.5rem 2rem;background:#d63638;text-align:center;">
				<p style="margin:0;font-size:1.1rem;font-weight:600;color:#ffffff;">
					<?php esc_html_e( 'Claim not approved', 'wb-listora' ); ?>
				</p>
			</td>
		</tr>

		<!-- Body -->
		<tr>
			<td style="padding:2rem;">
				<p style="margin:0 0 1rem;font-size:1rem;color:#1e1e1e;">
					<?php
					printf(
						/* translators: %s: claimant display name */
						esc_html__( 'Hi %s,', 'wb-listora' ),
						esc_html( $author_name )
					);
					?>
				</p>
				<p style="margin:0 0 1.5rem;font-size:0.95rem;color:#3c434a;line-height:1.6;">
					<?php
					printf(
						/* translators: %s: listing title */
						esc_html__( 'Your ownership claim for "%s" was not approved.', 'wb-listora' ),
						'<strong>' . esc_html( $listing_title ) . '</strong>'
					);
					?>
				</p>
				<?php if ( ! empty( $admin_notes ) ) : ?>
				<table cellpadding="0" cellspacing="0" style="margin:0 0 1.5rem;background:#f6f7f7;border-radius:6px;padding:1rem;width:100%;">
					<tr>
						<td style="font-size:0.85rem;color:#646970;">
							<strong style="color:#1e1e1e;"><?php esc_html_e( 'Notes from admin:', 'wb-listora' ); ?></strong><br/>
							<?php echo esc_html( $admin_notes ); ?>
						</td>
					</tr>
				</table>
				<?php endif; ?>
				<p style="margin:0;font-size:0.9rem;color:#3c434a;line-height:1.5;">
					<?php esc_html_e( 'If you believe this was an error, please contact us for further assistance.', 'wb-listora' ); ?>
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
