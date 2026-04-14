<?php
/**
 * Listing Submission — Step: Preview before submit.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/step-preview.php
 *
 * @package WBListora
 *
 * @var bool   $show_terms          Whether to show terms checkbox.
 * @var int    $terms_page_id       Terms page ID for link.
 * @var bool   $credit_enabled      Whether credits are active for this plugin.
 * @var int    $credit_balance      Current user's credit balance.
 * @var int    $credit_default_cost Cost of a listing when no paid plan is selected.
 * @var string $credit_purchase_url URL where users can buy more credits.
 * @var array  $view_data           Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;

$credit_enabled      = ! empty( $credit_enabled );
$credit_balance      = isset( $credit_balance ) ? (int) $credit_balance : 0;
$credit_default_cost = isset( $credit_default_cost ) ? (int) $credit_default_cost : 0;
$credit_purchase_url = isset( $credit_purchase_url ) ? (string) $credit_purchase_url : '';

// Decide initial visibility of the banner.
// Shown by default when credits are enabled AND there is a default cost (>0).
// When a plan is selected client-side the JS updates the cost and un-hides it.
$show_banner_initially = $credit_enabled && $credit_default_cost > 0;
$is_insufficient       = $credit_enabled && $credit_default_cost > 0 && $credit_balance < $credit_default_cost;
$remaining             = max( 0, $credit_balance - $credit_default_cost );
?>
<div class="listora-submission__step" data-step="preview" hidden>
	<h2><?php esc_html_e( 'Preview Your Listing', 'wb-listora' ); ?></h2>
	<p class="listora-submission__step-desc"><?php esc_html_e( 'Review your listing before submitting.', 'wb-listora' ); ?></p>

	<div class="listora-submission__preview-card">
		<div id="listora-preview-content">
			<p class="listora-submission__field-placeholder"><?php esc_html_e( 'Preview will appear here after filling in the form.', 'wb-listora' ); ?></p>
		</div>
	</div>

	<?php if ( $credit_enabled ) : ?>
	<?php
	$banner_classes = array( 'listora-submission__credit-banner' );
	if ( $is_insufficient ) {
		$banner_classes[] = 'listora-submission__credit-banner--insufficient';
	}
	?>
	<div
		class="<?php echo esc_attr( implode( ' ', $banner_classes ) ); ?>"
		data-listora-credit-banner
		data-default-cost="<?php echo esc_attr( $credit_default_cost ); ?>"
		data-balance="<?php echo esc_attr( $credit_balance ); ?>"
		data-purchase-url="<?php echo esc_attr( $credit_purchase_url ); ?>"
		<?php echo $show_banner_initially ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static literal ternary output. ?>
	>
		<span class="listora-submission__credit-icon" aria-hidden="true">
			<?php if ( $is_insufficient ) : ?>
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
					<line x1="12" y1="9" x2="12" y2="13"/>
					<line x1="12" y1="17" x2="12.01" y2="17"/>
				</svg>
			<?php else : ?>
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="10"/>
					<line x1="12" y1="16" x2="12" y2="12"/>
					<line x1="12" y1="8" x2="12.01" y2="8"/>
				</svg>
			<?php endif; ?>
		</span>
		<div class="listora-submission__credit-info">
			<p class="listora-submission__credit-cost-line">
				<?php
				printf(
					/* translators: %s: number of credits the listing submission will cost, wrapped in <strong>. */
					esc_html__( 'This listing will cost: %s', 'wb-listora' ),
					'<strong class="listora-submission__credit-cost-value"><span class="listora-submission__credit-cost" data-listora-credit-cost>' . esc_html( number_format_i18n( $credit_default_cost ) ) . '</span> ' . esc_html( _n( 'credit', 'credits', max( 1, $credit_default_cost ), 'wb-listora' ) ) . '</strong>'
				);
				?>
			</p>
			<p class="listora-submission__credit-balance-line">
				<?php
				printf(
					/* translators: %s: user's current credit balance, wrapped in <strong>. */
					esc_html__( 'Your balance: %s', 'wb-listora' ),
					'<strong class="listora-submission__credit-balance" data-listora-credit-balance>' . esc_html( number_format_i18n( $credit_balance ) ) . ' ' . esc_html( _n( 'credit', 'credits', max( 1, $credit_balance ), 'wb-listora' ) ) . '</strong>'
				);
				?>
			</p>
			<p
				class="listora-submission__credit-remaining"
				data-listora-credit-remaining-line
				<?php echo $is_insufficient ? 'hidden' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static literal ternary output. ?>
			>
				<?php
				printf(
					/* translators: %s: credits remaining after this submission, wrapped in <strong>. */
					esc_html__( 'Remaining after submit: %s', 'wb-listora' ),
					'<strong><span data-listora-credit-remaining>' . esc_html( number_format_i18n( $remaining ) ) . '</span> ' . esc_html( _n( 'credit', 'credits', max( 1, $remaining ), 'wb-listora' ) ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php if ( $credit_purchase_url ) : ?>
			<a
				href="<?php echo esc_url( $credit_purchase_url ); ?>"
				class="listora-btn listora-btn--primary listora-submission__credit-buy"
				data-listora-credit-buy
				<?php echo $is_insufficient ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static literal ternary output. ?>
			>
				<?php esc_html_e( 'Buy More Credits', 'wb-listora' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php // ─── CAPTCHA Widget ─── ?>
	<?php \WBListora\Captcha::render_widget( 'submission' ); ?>

	<?php if ( $show_terms ) : ?>
	<div class="listora-submission__field listora-submission__terms">
		<label class="listora-submission__checkbox-label">
			<input type="checkbox" name="agree_terms" required />
			<?php
			if ( $terms_page_id > 0 ) {
				printf(
					/* translators: %s: link to terms page */
					wp_kses_post( __( 'I agree to the <a href="%s" target="_blank">Terms of Service</a>', 'wb-listora' ) ),
					esc_url( get_permalink( $terms_page_id ) )
				);
			} else {
				esc_html_e( 'I agree to the Terms of Service', 'wb-listora' );
			}
			?>
		</label>
	</div>
	<?php endif; ?>
</div>
