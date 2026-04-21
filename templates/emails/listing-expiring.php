<?php
/**
 * Email template: Listing Expiring Soon.
 *
 * The `$variant` is resolved by Notifications::resolve_variant() (danger when
 * $days <= 1, warning otherwise) so this template has no conditional logic.
 *
 * Override via: {theme}/wb-listora/emails/listing-expiring.php
 *
 * @package WBListora
 *
 * @var array  $colors          Palette.
 * @var string $variant         "danger" | "warning".
 * @var bool   $is_marketing    Whether to show unsubscribe link.
 * @var string $unsubscribe_url User preferences URL.
 * @var string $site_name       Site name.
 * @var string $site_url        Site home URL.
 * @var string $listing_title   Listing title.
 * @var string $author_name     Listing author's display name.
 * @var int    $days            Days until expiration.
 * @var string $expiry_date     Formatted expiration date.
 * @var string $renew_url       Dashboard URL for managing listings.
 */

defined( 'ABSPATH' ) || exit;

$is_urgent    = ( 'danger' === $variant );
$header_title = $is_urgent
	? __( 'Your listing expires tomorrow', 'wb-listora' )
	: sprintf(
		/* translators: %d: number of days until expiration */
		__( 'Your listing expires in %d days', 'wb-listora' ),
		(int) $days
	);
$cta_color    = $is_urgent ? $colors['danger'] : $colors['primary'];

wb_listora_get_template( 'emails/parts/header.php', compact( 'colors', 'variant', 'header_title' ) );
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

		<?php if ( $is_urgent ) : ?>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['danger'] ); ?>;line-height:1.6;font-weight:600;">
			<?php
			printf(
				/* translators: %s: listing title */
				esc_html__( 'Your listing "%s" will expire tomorrow. After expiration, it will no longer be visible in the directory.', 'wb-listora' ),
				esc_html( $listing_title )
			);
			?>
		</p>
		<?php else : ?>
		<p style="margin:0 0 1.5rem;font-size:0.95rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.6;">
			<?php
			printf(
				/* translators: 1: listing title, 2: expiration date */
				esc_html__( 'Your listing "%1$s" will expire on %2$s. After expiration, it will no longer be visible in the directory.', 'wb-listora' ),
				'<strong>' . esc_html( $listing_title ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				'<strong>' . esc_html( $expiry_date ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			?>
		</p>
		<?php endif; ?>

		<p style="margin:0 0 1.5rem;font-size:0.9rem;color:<?php echo esc_attr( $colors['text_muted'] ); ?>;line-height:1.5;">
			<?php esc_html_e( 'Renew your listing now to keep it visible and continue reaching customers.', 'wb-listora' ); ?>
		</p>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $renew_url ); ?>"
				style="display:inline-block;padding:0.7rem 1.5rem;background:<?php echo esc_attr( $cta_color ); ?>;color:<?php echo esc_attr( $colors['white'] ); ?>;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.9rem;">
				<?php esc_html_e( 'Renew Listing', 'wb-listora' ); ?>
			</a>
		</p>
	</td>
</tr>
<?php
wb_listora_get_template(
	'emails/parts/footer.php',
	compact( 'colors', 'site_name', 'site_url', 'is_marketing', 'unsubscribe_url' )
);
