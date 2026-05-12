<?php
/**
 * User Dashboard — Credits tab content.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/user-dashboard/tab-credits.php
 *
 * @package WBListora
 *
 * @var int    $user_id              Current user ID.
 * @var string $default_tab          Default active tab slug.
 * @var int    $credit_balance       Current credit balance.
 * @var int    $credit_threshold     Low balance warning threshold.
 * @var array  $credit_packs         List of available credit packs for purchase.
 * @var array  $credit_ledger        Recent ledger entries (transactions).
 * @var string $credit_purchase_url  Fallback credit purchase URL.
 * @var string $direct_checkout_base SDK /checkout/{gateway} REST endpoint base.
 * @var string $direct_return_url    Return URL for Stripe/PayPal redirects.
 * @var string $direct_rest_nonce    REST nonce for the checkout request.
 * @var string $purchase_status      'success' | 'cancel' | 'error' | '' (post-redirect banner).
 * @var int    $purchase_credits     Credits just purchased (success path).
 * @var string $purchase_gateway     Gateway slug used for the latest purchase.
 * @var array  $view_data            Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data            = $view_data ?? get_defined_vars();
$purchase_status      = isset( $purchase_status ) ? (string) $purchase_status : '';
$purchase_credits     = isset( $purchase_credits ) ? (int) $purchase_credits : 0;
$purchase_gateway     = isset( $purchase_gateway ) ? (string) $purchase_gateway : '';
$direct_checkout_base = isset( $direct_checkout_base ) ? (string) $direct_checkout_base : '';
$direct_return_url    = isset( $direct_return_url ) ? (string) $direct_return_url : '';
$direct_rest_nonce    = isset( $direct_rest_nonce ) ? (string) $direct_rest_nonce : '';

do_action( 'wb_listora_before_dashboard_credits', $view_data );

$is_low       = ( $credit_threshold > 0 && $credit_balance < $credit_threshold );
$balance_mods = 'listora-dashboard__balance-card';
if ( $is_low ) {
	$balance_mods .= ' listora-dashboard__balance-card--low';
}

// Entry type metadata: label + sign class.
$entry_types = array(
	'topup'     => array(
		'label' => __( 'Top-up', 'wb-listora' ),
		'sign'  => 'positive',
	),
	'refund'    => array(
		'label' => __( 'Refund', 'wb-listora' ),
		'sign'  => 'positive',
	),
	'deduction' => array(
		'label' => __( 'Deduction', 'wb-listora' ),
		'sign'  => 'negative',
	),
	'hold'      => array(
		'label' => __( 'Hold', 'wb-listora' ),
		'sign'  => 'negative',
	),
);

// Primary "Buy Credits" CTA:
//   - packs configured → jump to the packs grid below
//   - no packs but external purchase URL → link out
//   - neither → suppress the CTA entirely (nothing to buy; the empty-state
//     card below explains why and points at the admin)
$buy_cta_url = '';
if ( ! empty( $credit_packs ) ) {
	$buy_cta_url = '#listora-credit-packs';
} elseif ( ! empty( $credit_purchase_url ) ) {
	$buy_cta_url = $credit_purchase_url;
}
$show_buy_cta = '' !== $buy_cta_url;
?>
<div role="tabpanel" id="dash-panel-credits" aria-labelledby="dash-tab-credits" class="listora-dashboard__panel"
	<?php echo 'credits' !== $default_tab ? 'hidden' : ''; ?>>

	<?php
	// ─── Post-checkout banner (success / cancel / error) ───
	// Stripe/PayPal redirect here with ?wbcom_credits=success|cancel|error
	// after the user completes (or cancels) checkout. The webhook may still
	// be in-flight for a few seconds — we re-fetch balance via JS to catch
	// the topup once it lands.
	if ( '' !== $purchase_status ) :
		$banner_class = 'listora-dashboard__credits-banner listora-dashboard__credits-banner--' . sanitize_html_class( $purchase_status );
		?>
		<div class="<?php echo esc_attr( $banner_class ); ?>" role="status" aria-live="polite"
			data-listora-credits-banner data-status="<?php echo esc_attr( $purchase_status ); ?>" data-credits="<?php echo esc_attr( (string) $purchase_credits ); ?>" data-gateway="<?php echo esc_attr( $purchase_gateway ); ?>">
			<?php if ( 'success' === $purchase_status ) : ?>
				<strong><?php esc_html_e( 'Thank you!', 'wb-listora' ); ?></strong>
				<?php
				if ( $purchase_credits > 0 ) {
					printf(
						/* translators: %d: number of credits added. */
						esc_html( _n( '%d credit has been added to your account.', '%d credits have been added to your account.', $purchase_credits, 'wb-listora' ) ),
						(int) $purchase_credits
					);
				} else {
					esc_html_e( 'Your credits have been added.', 'wb-listora' );
				}
				?>
				<span class="listora-dashboard__credits-banner-balance" data-listora-credits-balance-status>
					<?php esc_html_e( 'Updating your balance…', 'wb-listora' ); ?>
				</span>
			<?php elseif ( 'cancel' === $purchase_status ) : ?>
				<strong><?php esc_html_e( 'Checkout canceled.', 'wb-listora' ); ?></strong>
				<?php esc_html_e( 'No charge was made — pick a pack below to try again.', 'wb-listora' ); ?>
			<?php else : // error ?>
				<strong><?php esc_html_e( 'We couldn\'t process your purchase.', 'wb-listora' ); ?></strong>
				<?php esc_html_e( 'Please try again or contact support if the issue persists.', 'wb-listora' ); ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php // ─── A. Balance Card ─── ?>
	<div class="<?php echo esc_attr( $balance_mods ); ?>" role="region" aria-labelledby="listora-credit-balance-heading">
		<div class="listora-dashboard__balance-card-inner">
			<div class="listora-dashboard__balance-content">
				<h3 id="listora-credit-balance-heading" class="listora-dashboard__balance-label">
					<?php esc_html_e( 'Credit Balance', 'wb-listora' ); ?>
				</h3>
				<p class="listora-dashboard__balance-value">
					<span class="listora-dashboard__balance-number"><?php echo esc_html( number_format_i18n( $credit_balance ) ); ?></span>
					<span class="listora-dashboard__balance-unit"><?php echo esc_html( _n( 'credit', 'credits', $credit_balance, 'wb-listora' ) ); ?></span>
				</p>
				<?php if ( $is_low ) : ?>
				<p class="listora-dashboard__balance-warning" role="status">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
					<?php
					printf(
						/* translators: %d: low credit threshold value. */
						esc_html__( 'Low balance — top up soon (threshold: %d).', 'wb-listora' ),
						(int) $credit_threshold
					);
					?>
				</p>
				<?php endif; ?>
			</div>
			<?php if ( $show_buy_cta ) : ?>
			<div class="listora-dashboard__balance-actions">
				<a href="<?php echo esc_url( $buy_cta_url ); ?>" class="listora-btn listora-btn--primary"<?php echo ( $buy_cta_url === $credit_purchase_url && '' !== $credit_purchase_url ) ? ' target="_blank" rel="noopener"' : ''; ?>>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
					<?php esc_html_e( 'Buy Credits', 'wb-listora' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php // ─── B. Credit Packs ─── ?>
	<section class="listora-dashboard__credits-section<?php echo empty( $credit_packs ) ? ' listora-dashboard__credits-section--empty' : ''; ?>" id="listora-credit-packs" aria-labelledby="listora-credit-packs-heading">

		<?php if ( empty( $credit_packs ) ) : ?>
		<?php
		// No section heading here when the grid is empty — the red balance
		// card already has a "Buy Credits" CTA and a second "Buy Credits"
		// heading next to a "No credit packs available" empty state reads
		// as duplicated label + misleading copy.
		?>
		<div class="listora-dashboard__empty">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
			<h3><?php esc_html_e( 'No credit packs available', 'wb-listora' ); ?></h3>
			<p><?php esc_html_e( 'No credit packs configured yet. Ask your administrator to set up credit mappings.', 'wb-listora' ); ?></p>
			<?php if ( $credit_purchase_url ) : ?>
			<a href="<?php echo esc_url( $credit_purchase_url ); ?>" class="listora-btn listora-btn--secondary">
				<?php esc_html_e( 'Visit Store', 'wb-listora' ); ?>
			</a>
			<?php endif; ?>
		</div>
		<?php else : ?>
		<h3 id="listora-credit-packs-heading" class="listora-dashboard__section-title">
			<?php esc_html_e( 'Buy Credits', 'wb-listora' ); ?>
		</h3>
		<div class="listora-dashboard__credit-packs">
			<?php foreach ( $credit_packs as $pack_index => $pack ) : ?>
			<article class="listora-dashboard__credit-pack" style="--row-index: <?php echo (int) $pack_index; ?>">
				<header class="listora-dashboard__credit-pack-header">
					<?php if ( ! empty( $pack['adapter_label'] ) ) : ?>
					<span class="listora-dashboard__credit-pack-badge"><?php echo esc_html( $pack['adapter_label'] ); ?></span>
					<?php endif; ?>
					<h4 class="listora-dashboard__credit-pack-title">
						<?php echo esc_html( $pack['item_label'] ? $pack['item_label'] : __( 'Credit Pack', 'wb-listora' ) ); ?>
					</h4>
				</header>

				<div class="listora-dashboard__credit-pack-body">
					<p class="listora-dashboard__credit-pack-credits">
						<span class="listora-dashboard__credit-pack-credits-number"><?php echo esc_html( number_format_i18n( (int) $pack['credits'] ) ); ?></span>
						<span class="listora-dashboard__credit-pack-credits-label"><?php echo esc_html( _n( 'credit', 'credits', (int) $pack['credits'], 'wb-listora' ) ); ?></span>
					</p>
					<?php if ( ! empty( $pack['price_html'] ) ) : ?>
					<p class="listora-dashboard__credit-pack-price">
						<?php echo wp_kses_post( $pack['price_html'] ); ?>
					</p>
					<?php endif; ?>
				</div>

				<footer class="listora-dashboard__credit-pack-footer">
					<?php if ( 'direct' === ( $pack['adapter'] ?? '' ) && ! empty( $pack['gateways'] ) ) : ?>
						<?php
						// Direct-payment adapter — render one button per
						// SDK-registered gateway (Stripe, PayPal). JS in
						// the dashboard view module handles the click,
						// POSTs to /checkout/{gateway} with return_url,
						// and redirects to the hosted checkout URL.
						foreach ( (array) $pack['gateways'] as $direct_gw ) :
							?>
							<button
								type="button"
								class="listora-btn listora-btn--primary listora-btn--sm listora-dashboard__credit-pack-buy-direct"
								data-listora-credits-checkout
								data-gateway="<?php echo esc_attr( (string) $direct_gw['id'] ); ?>"
								data-credits="<?php echo esc_attr( (string) ( $pack['credits'] ?? 0 ) ); ?>"
								data-price-cents="<?php echo esc_attr( (string) ( $pack['price_cents'] ?? 0 ) ); ?>"
								data-currency="<?php echo esc_attr( (string) ( $pack['currency'] ?? 'USD' ) ); ?>"
								data-checkout-base="<?php echo esc_attr( $direct_checkout_base ); ?>"
								data-return-url="<?php echo esc_attr( $direct_return_url ); ?>"
								data-rest-nonce="<?php echo esc_attr( $direct_rest_nonce ); ?>"
							>
								<?php
								printf(
									/* translators: %s: gateway label (Stripe / PayPal). */
									esc_html__( 'Buy with %s', 'wb-listora' ),
									esc_html( (string) $direct_gw['label'] )
								);
								?>
							</button>
							<?php
						endforeach;
						?>
					<?php elseif ( ! empty( $pack['buy_url'] ) ) : ?>
						<a href="<?php echo esc_url( $pack['buy_url'] ); ?>" class="listora-btn listora-btn--primary listora-btn--sm">
							<?php echo esc_html( $pack['buy_label'] ); ?>
						</a>
					<?php elseif ( 'direct' === ( $pack['adapter'] ?? '' ) ) : ?>
						<span class="listora-dashboard__credit-pack-unavailable">
							<?php esc_html_e( 'No payment gateway configured.', 'wb-listora' ); ?>
						</span>
					<?php else : ?>
						<span class="listora-dashboard__credit-pack-unavailable">
							<?php esc_html_e( 'Unavailable', 'wb-listora' ); ?>
						</span>
					<?php endif; ?>
				</footer>
			</article>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</section>

	<?php // ─── C. Transaction History ─── ?>
	<section class="listora-dashboard__credits-section" aria-labelledby="listora-credit-history-heading">
		<h3 id="listora-credit-history-heading" class="listora-dashboard__section-title">
			<?php esc_html_e( 'Transaction History', 'wb-listora' ); ?>
		</h3>

		<?php if ( empty( $credit_ledger ) ) : ?>
		<div class="listora-dashboard__empty">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
			<h3><?php esc_html_e( 'No transactions yet', 'wb-listora' ); ?></h3>
			<p><?php esc_html_e( 'Your credit activity will appear here.', 'wb-listora' ); ?></p>
		</div>
		<?php else : ?>
		<div class="listora-dashboard__transactions" role="table" aria-label="<?php esc_attr_e( 'Credit transactions', 'wb-listora' ); ?>">
			<div class="listora-dashboard__transactions-head" role="row">
				<span role="columnheader"><?php esc_html_e( 'Date', 'wb-listora' ); ?></span>
				<span role="columnheader"><?php esc_html_e( 'Type', 'wb-listora' ); ?></span>
				<span role="columnheader" class="listora-dashboard__transactions-amount-col"><?php esc_html_e( 'Amount', 'wb-listora' ); ?></span>
				<span role="columnheader"><?php esc_html_e( 'Note', 'wb-listora' ); ?></span>
			</div>

			<?php
			foreach ( $credit_ledger as $row_index => $entry ) :
				$entry      = (array) $entry;
				$entry_type = isset( $entry['entry_type'] ) ? (string) $entry['entry_type'] : '';
				$amount     = isset( $entry['amount'] ) ? (int) $entry['amount'] : 0;
				$note       = isset( $entry['note'] ) ? (string) $entry['note'] : '';
				$created    = isset( $entry['created_at'] ) ? (string) $entry['created_at'] : '';

				$type_info = isset( $entry_types[ $entry_type ] )
					? $entry_types[ $entry_type ]
					: array(
						'label' => ucfirst( $entry_type ),
						'sign'  => $amount >= 0 ? 'positive' : 'negative',
					);

				$row_class  = 'listora-dashboard__transaction';
				$row_class .= ' listora-dashboard__transaction--' . $type_info['sign'];

				$timestamp     = $created ? strtotime( $created . ' UTC' ) : 0;
				$date_display  = $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : esc_html__( '—', 'wb-listora' );
				$amount_prefix = $amount > 0 ? '+' : ( $amount < 0 ? '' : '' );
				?>
			<div class="<?php echo esc_attr( $row_class ); ?>" role="row" style="--row-index: <?php echo (int) $row_index; ?>">
				<span class="listora-dashboard__transaction-date" role="cell" data-label="<?php esc_attr_e( 'Date', 'wb-listora' ); ?>">
					<?php echo esc_html( $date_display ); ?>
				</span>
				<span class="listora-dashboard__transaction-type" role="cell" data-label="<?php esc_attr_e( 'Type', 'wb-listora' ); ?>">
					<span class="listora-dashboard__transaction-type-pill listora-dashboard__transaction-type-pill--<?php echo esc_attr( $entry_type ? $entry_type : 'unknown' ); ?>">
						<?php echo esc_html( $type_info['label'] ); ?>
					</span>
				</span>
				<span class="listora-dashboard__transaction-amount" role="cell" data-label="<?php esc_attr_e( 'Amount', 'wb-listora' ); ?>">
					<?php echo esc_html( $amount_prefix . number_format_i18n( $amount ) ); ?>
				</span>
				<span class="listora-dashboard__transaction-note" role="cell" data-label="<?php esc_attr_e( 'Note', 'wb-listora' ); ?>">
					<?php echo $note ? esc_html( $note ) : '<span aria-hidden="true">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both branches safe: esc_html() or static literal markup. ?>
				</span>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</section>
</div>
<?php
do_action( 'wb_listora_after_dashboard_credits', $view_data );
