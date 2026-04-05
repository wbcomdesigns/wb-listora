<?php
/**
 * Email template: Listing Expiring Soon.
 *
 * Variables available:
 *   $site_name      (string) Site name.
 *   $site_url       (string) Site home URL.
 *   $listing_title  (string) Listing title.
 *   $author_name    (string) Listing author's display name.
 *   $days           (int)    Days until expiration.
 *   $expiry_date    (string) Formatted expiration date.
 *   $renew_url      (string) Dashboard URL for managing listings.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$is_urgent = ( (int) $days <= 1 );
$header_bg = $is_urgent ? '#d63638' : '#dba617';
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
			<td style="padding:1.5rem 2rem;background:<?php echo esc_attr( $header_bg ); ?>;text-align:center;">
				<p style="margin:0;font-size:1.1rem;font-weight:600;color:#ffffff;">
					<?php
					if ( $is_urgent ) {
						esc_html_e( 'Your listing expires tomorrow', 'wb-listora' );
					} else {
						printf(
							/* translators: %d: number of days until expiration */
							esc_html__( 'Your listing expires in %d days', 'wb-listora' ),
							(int) $days
						);
					}
					?>
				</p>
			</td>
		</tr>

		<!-- Body -->
		<tr>
			<td style="padding:2rem;">
				<p style="margin:0 0 1rem;font-size:1rem;color:#1e1e1e;">
					<?php
					printf(
						/* translators: %s: author display name */
						esc_html__( 'Hi %s,', 'wb-listora' ),
						esc_html( $author_name )
					);
					?>
				</p>

				<?php if ( $is_urgent ) : ?>
				<p style="margin:0 0 1.5rem;font-size:0.95rem;color:#d63638;line-height:1.6;font-weight:600;">
					<?php
					printf(
						/* translators: %s: listing title */
						esc_html__( 'Your listing "%s" will expire tomorrow. After expiration, it will no longer be visible in the directory.', 'wb-listora' ),
						esc_html( $listing_title )
					);
					?>
				</p>
				<?php else : ?>
				<p style="margin:0 0 1.5rem;font-size:0.95rem;color:#3c434a;line-height:1.6;">
					<?php
					printf(
						/* translators: 1: listing title, 2: expiration date */
						esc_html__( 'Your listing "%1$s" will expire on %2$s. After expiration, it will no longer be visible in the directory.', 'wb-listora' ),
						'<strong>' . esc_html( $listing_title ) . '</strong>',
						'<strong>' . esc_html( $expiry_date ) . '</strong>'
					);
					?>
				</p>
				<?php endif; ?>

				<p style="margin:0 0 1.5rem;font-size:0.9rem;color:#3c434a;line-height:1.5;">
					<?php esc_html_e( 'Renew your listing now to keep it visible and continue reaching customers.', 'wb-listora' ); ?>
				</p>
				<p style="margin:0;">
					<a href="<?php echo esc_url( $renew_url ); ?>"
						style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo $is_urgent ? '#d63638' : '#2271b1'; ?>;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
						<?php esc_html_e( 'Renew Listing', 'wb-listora' ); ?>
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
						/* translators: 1: site name */
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
