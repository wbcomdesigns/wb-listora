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
 * @var bool   $is_single_form      True in single-form layout — steps render stacked and must NOT emit `hidden`.
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

/*
 * Terms already accepted on this listing?
 *
 * The server treats an edit that never mentions the field as an edit rather
 * than a fresh acceptance, and defaults it to true for exactly that reason.
 * The form was not agreeing with it: the box rendered unticked every time, and
 * since 1.6.0 the client validates EVERY step before submitting, so a member
 * editing their own listing had to re-accept the Terms on every single save or
 * the Save button appeared to do nothing.
 *
 * Reflecting the recorded consent keeps the two halves telling the same story.
 * A listing with no consent on file still renders unticked and must be
 * accepted — which is the right outcome for anything created before 1.6.0 or
 * imported.
 */
$terms_already_accepted = false;

if ( ! empty( $edit_listing_id ) ) {
	$terms_already_accepted = (bool) get_post_meta( (int) $edit_listing_id, '_listora_terms_accepted', true );
}
?>
<div class="listora-submission__step" data-step="preview" <?php echo empty( $is_single_form ) ? 'hidden' : ''; ?>>
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
					'<strong class="listora-submission__credit-balance" data-listora-credit-balance>' . esc_html( number_format_i18n( (float) $credit_balance, isset( $credit_decimals ) ? (int) $credit_decimals : 2 ) ) . ' ' . esc_html( _n( 'credit', 'credits', (int) ceil( max( 1, (float) $credit_balance ) ), 'wb-listora' ) ) . '</strong>'
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
			<?php
			// Deliberately NOT the native `required` attribute. The form's submit
			// is intercepted by the block's own handler, but native constraint
			// validation runs FIRST — and when this control is not rendered (a
			// theme hiding the step, a conditional layout) the browser refuses to
			// submit with "not focusable" and shows the user nothing at all: no
			// message, no console entry, no request. `data-listora-required` is
			// the block's own convention (see featured_image in step-media.php);
			// validateStep() handles checkboxes via `field.checked`.
			?>
			<input type="checkbox" name="agree_terms" aria-required="true" data-listora-required="agree_terms" <?php checked( $terms_already_accepted ); ?> />
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
		<p class="listora-submission__field-error listora-submission__field-error--agree-terms" role="alert" hidden></p>
	</div>
	<?php else : ?>
	<?php
	/*
	 * `showTerms` off — send acceptance implicitly.
	 *
	 * The REST gate added in 1.6.0 refuses a submission with no `agree_terms`.
	 * That is correct for a site that ASKS the question, but this branch is a
	 * site that deliberately turned the question off, and without this input it
	 * sends nothing: every submission on those sites would 400 with "Please
	 * accept the Terms of Service to continue." for a checkbox the owner
	 * removed on purpose, and the only escape would be a filter in a
	 * mu-plugin documented nowhere the owner will look.
	 *
	 * `showTerms` is a BLOCK attribute, so the REST layer cannot read it —
	 * this hidden field is how the block tells the server what it decided.
	 *
	 * It is not a hole: the checkbox was never a security control. Any REST
	 * caller could always send `agree_terms: true`, and the gate's real job is
	 * to make the web form record consent when consent is being asked for.
	 * A site that shows no terms has no consent to record.
	 */
	?>
	<input type="hidden" name="agree_terms" value="1" />
	<?php endif; ?>
</div>
