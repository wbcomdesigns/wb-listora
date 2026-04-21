<?php
/**
 * Shared email footer — site-name line + optional unsubscribe for marketing emails.
 *
 * Override via: {theme}/wb-listora/emails/parts/footer.php
 *
 * @package WBListora
 *
 * @var array  $colors          Palette.
 * @var string $site_name       Site name.
 * @var string $site_url        Site home URL.
 * @var bool   $is_marketing    Whether to include an unsubscribe link.
 * @var string $unsubscribe_url Dashboard URL the user manages preferences at.
 * @var string $footer_text     Optional override for the branding line. When empty the
 *                              default "This email was sent by {site_name}" renders.
 *                              Filter: wb_listora_email_footer_text.
 */

defined( 'ABSPATH' ) || exit;

$is_marketing    = $is_marketing ?? false;
$unsubscribe_url = $unsubscribe_url ?? '';
$footer_text     = $footer_text ?? '';
?>
		<tr>
			<td style="padding:1rem 2rem;border-top:1px solid <?php echo esc_attr( $colors['border'] ); ?>;text-align:center;">
				<p style="margin:0;font-size:0.8rem;color:<?php echo esc_attr( $colors['text_subtle'] ); ?>;">
					<?php if ( '' !== $footer_text ) : ?>
						<?php echo esc_html( $footer_text ); ?>
					<?php else : ?>
					<?php
					printf(
						/* translators: %s: site name link */
						esc_html__( 'This email was sent by %s.', 'wb-listora' ),
						'<a href="' . esc_url( $site_url ) . '" style="color:' . esc_attr( $colors['text_subtle'] ) . ';">' . esc_html( $site_name ) . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
					);
					?>
					<?php endif; ?>
				</p>
				<?php if ( $is_marketing && $unsubscribe_url ) : ?>
				<p style="margin:0.4rem 0 0;font-size:0.75rem;color:<?php echo esc_attr( $colors['text_subtle'] ); ?>;">
					<a href="<?php echo esc_url( $unsubscribe_url ); ?>" style="color:<?php echo esc_attr( $colors['text_subtle'] ); ?>;">
						<?php esc_html_e( 'Manage email preferences', 'wb-listora' ); ?>
					</a>
				</p>
				<?php endif; ?>
			</td>
		</tr>

	</table>
	</td></tr>
</table>
</body>
</html>
