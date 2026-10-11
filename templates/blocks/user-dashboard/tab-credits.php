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
 * @var float  $credit_balance       Current credit balance in MAJOR units (credits).
 * @var int    $credit_threshold     Low balance warning threshold, in credits.
 * @var string $credit_currency      Store currency ISO 4217 code.
 * @var int    $credit_decimals      Decimal places for that currency (2 USD, 0 JPY).
 * @var array  $credit_packs         List of available credit packs for purchase.
 * @var array  $credit_ledger        Recent ledger entries; amounts are raw MINOR units.
 * @var int    $credit_ledger_page   Current history page (1-based).
 * @var int    $credit_ledger_pages  History page count.
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

/*
 * A member sent here from the submission wizard has a saved draft waiting.
 * Without a way back they finish paying and are left on the dashboard with no
 * sign their listing survived — so they start it again from scratch, which is
 * the outcome the draft-and-return handoff exists to prevent.
 */
$listora_return_url = function_exists( 'wb_listora_get_submission_return_url' )
	? wb_listora_get_submission_return_url()
	: '';

do_action( 'wb_listora_before_dashboard_credits', $view_data );

if ( '' !== $listora_return_url ) :
	?>
	<div class="listora-return-notice" role="status">
		<strong><?php esc_html_e( 'Your listing is saved.', 'wb-listora' ); ?></strong>
		<?php esc_html_e( 'Come back to it once you have the credits you need.', 'wb-listora' ); ?>
		<a class="listora-return-notice__link" href="<?php echo esc_url( $listora_return_url ); ?>">
			<?php esc_html_e( 'Back to your listing', 'wb-listora' ); ?>
		</a>
	</div>
	<?php
endif;

$is_low       = ( $credit_threshold > 0 && $credit_balance < $credit_threshold );
$balance_mods = 'listora-dashboard__balance-card';
if ( $is_low ) {
	$balance_mods .= ' listora-dashboard__balance-card--low';
}

// Entry type metadata: label + sign class.
$entry_types = wb_listora_credit_entry_types();

// Primary "Buy Credits" CTA:
//   - packs configured AND at least one payment gateway is enabled → jump
//     to the packs grid below (vendor can actually check out).
//   - no packs / no gateway but an external purchase URL → link out
//     (admin has pointed credits buying at a WooCommerce shop, etc.).
//   - none of the above → suppress every CTA entirely (nothing to buy;
//     the empty-state card below explains why and points at the admin).
//
// `has_payment_gateway` is true when SDK Gateway_Registry::get_available()
// returns ≥ 1 gateway — i.e. Stripe / PayPal / a third-party adapter is
// enabled AND configured. Packs without a gateway would render but every
// checkout button would 409 from the SDK — we shouldn't bait customers.
$has_payment_gateway = ! empty( $has_payment_gateway );

$buy_cta_url = '';
if ( ! empty( $credit_packs ) && $has_payment_gateway ) {
	$buy_cta_url = '#listora-credit-packs';
} elseif ( ! empty( $credit_purchase_url ) ) {
	$buy_cta_url = $credit_purchase_url;
}
/*
 * Resolve buyability BEFORE the balance card, because the card's CTA needs it
 * too: no "Buy Credits" button above "credits cannot be purchased".
 */
$listora_monetization = isset( $monetization_status ) && is_array( $monetization_status )
	? $monetization_status
	: wb_listora_get_monetization_status();

$listora_state = $listora_monetization['state'] ?? 'disabled';

$show_buy_cta = '' !== $buy_cta_url && 'ready' === $listora_state;
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
			data-listora-credits-banner data-status="<?php echo esc_attr( $purchase_status ); ?>" data-credits="<?php echo esc_attr( (string) $purchase_credits ); ?>" data-gateway="<?php echo esc_attr( $purchase_gateway ); ?>"
			<?php /* The claim + balance-poll calls are authenticated; without this they run as anonymous and 401. */ ?>
			data-rest-nonce="<?php echo esc_attr( $direct_rest_nonce ); ?>"
			<?php /* Resolved REST URLs: a hardcoded /wp-json/ path breaks on subdirectory installs and plain permalinks. */ ?>
			data-claim-url="<?php echo esc_url( rest_url( 'wbcom-credits/v1/wb-listora/claim/' ) ); ?>"
			data-balance-url="<?php echo esc_url( rest_url( 'wbcom-credits/v1/wb-listora/balance' ) ); ?>"
			<?php
			/*
			 * The balance route returns the RAW ledger integer, which in money
			 * mode is MINOR units (10000 for 100.00 credits), while the card
			 * renders MAJOR units. These carry the conversion the JS poll needs;
			 * the template knows the store currency's decimals authoritatively.
			 */
			?>
			data-balance-decimals="<?php echo esc_attr( (string) (int) $credit_decimals ); ?>"
			<?php /* The same balance the card is showing, in the route's own MINOR units, so the poll compares like with like instead of parsing display text. */ ?>
			data-start-balance="<?php echo esc_attr( (string) (int) round( (float) $credit_balance * pow( 10, (int) $credit_decimals ) ) ); ?>"
			data-pending-text="<?php esc_attr_e( 'Your payment provider has not confirmed this payment yet. Your credits will appear once it does - refresh in a moment.', 'wb-listora' ); ?>"
			data-failed-text="<?php esc_attr_e( 'We could not find this payment on your account, so no credits were added. If you were charged, contact the site administrator.', 'wb-listora' ); ?>"
			<?php /* Confirmed wording rendered here so it stays translatable; JS swaps it in once crediting is verified. */ ?>
			data-confirmed-text="
			<?php
			echo esc_attr(
				$purchase_credits > 0
				/* translators: %d: number of credits added. */
				? sprintf( _n( '%d credit added.', '%d credits added.', $purchase_credits, 'wb-listora' ), (int) $purchase_credits )
				: __( 'Credits added.', 'wb-listora' )
			);
			?>
				">
			<?php if ( 'success' === $purchase_status ) : ?>
				<?php
				/*
				 * Say what is TRUE at this moment: the payment went through.
				 *
				 * This used to open with "N credits have been added to your
				 * account" the instant the member returned from the gateway —
				 * before anything had credited them. If the webhook was slow the
				 * balance sat at 0 underneath that sentence, and someone who had
				 * just paid real money was told it was done while the page
				 * disagreed. That reads as a site that takes your money and
				 * loses it.
				 *
				 * The banner now states the payment, and the balance line below
				 * reports the crediting as it happens — JS swaps it to the
				 * confirmed total once the claim or the webhook lands.
				 */
				?>
				<strong><?php esc_html_e( 'Payment received.', 'wb-listora' ); ?></strong>
				<?php
				if ( $purchase_credits > 0 ) {
					printf(
						/* translators: %d: number of credits being added. */
						esc_html( _n( 'Adding %d credit to your account…', 'Adding %d credits to your account…', $purchase_credits, 'wb-listora' ) ),
						(int) $purchase_credits
					);
				} else {
					esc_html_e( 'Adding your credits…', 'wb-listora' );
				}
				?>
				<span class="listora-dashboard__credits-banner-balance" data-listora-credits-balance-status>
					<?php esc_html_e( 'Confirming with your payment provider…', 'wb-listora' ); ?>
				</span>
			<?php elseif ( 'cancel' === $purchase_status ) : ?>
				<strong><?php esc_html_e( 'Checkout canceled.', 'wb-listora' ); ?></strong>
				<?php esc_html_e( 'No charge was made — pick a pack below to try again.', 'wb-listora' ); ?>
			<?php else : // error ?>
				<strong><?php esc_html_e( 'We couldn\'t process your purchase.', 'wb-listora' ); ?></strong>
				<?php esc_html_e( 'Please try again or contact support if the issue persists.', 'wb-listora' ); ?>
			<?php endif; ?>

			<?php
			/*
			 * A way out. The banner renders from `?wbcom_credits=` in the URL, so
			 * it would return on every reload or bookmark. A link, not a button:
			 * without JS it drops the query args and reloads a clean Credits tab;
			 * with JS the handler removes the node and rewrites history.
			 */
			// `token` + `PayerID` are PayPal's half of the same return; a payment
			// token should not stay in a URL the member may copy or bookmark.
			$listora_banner_dismiss_url = remove_query_arg(
				array( 'wbcom_credits', 'credits', 'gateway', 'session_id', 'token', 'PayerID' )
			);
			?>
			<a
				class="listora-dashboard__credits-banner-dismiss"
				href="<?php echo esc_url( $listora_banner_dismiss_url ); ?>"
				data-listora-credits-banner-dismiss
				aria-label="<?php esc_attr_e( 'Dismiss this message', 'wb-listora' ); ?>"
			>
				<span aria-hidden="true">&times;</span>
			</a>
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
					<span class="listora-dashboard__balance-number"><?php echo esc_html( wb_listora_format_credits( $credit_balance ) ); ?></span>
					<span class="listora-dashboard__balance-unit"><?php echo esc_html( _n( 'credit', 'credits', $credit_balance, 'wb-listora' ) ); ?></span>
				</p>
				<?php if ( $credit_balance < 0 ) : ?>
				<p class="listora-dashboard__balance-warning listora-dashboard__balance-warning--owed" role="status">
					<?php
					// A refund can remove credits that were already spent: the
					// balance goes negative and nothing
					// can be paid for until it is topped back up.
					printf(
						/* translators: %s: credits owed */
						esc_html__( 'You owe %s credits. Paid actions are paused until you top up.', 'wb-listora' ),
						esc_html( wb_listora_format_credits( abs( $credit_balance ) ) )
					);
					?>
				</p>
				<?php elseif ( $is_low ) : ?>
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
				<a href="<?php echo esc_url( $buy_cta_url ); ?>" class="listora-btn listora-btn--primary">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
					<?php esc_html_e( 'Buy Credits', 'wb-listora' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php // ─── B. Credit Packs ─── ?>
	<?php
	/*
	 * Buyability comes from the ONE resolver, not a local rule: a pack sold as
	 * an external WooCommerce product has no direct gateway but is purchasable.
	 */
	$listora_packs_buyable = ! empty( $credit_packs ) && 'ready' === ( $listora_monetization['state'] ?? '' );
	?>
	<section class="listora-dashboard__credits-section<?php echo ! $listora_packs_buyable ? ' listora-dashboard__credits-section--empty' : ''; ?>" id="listora-credit-packs" aria-labelledby="listora-credit-packs-heading">

		<?php if ( ! $listora_packs_buyable ) : ?>
			<?php
			// No section heading when the grid won't render — the red balance
			// card already had a "Buy Credits" CTA (now also suppressed) and a
			// second "Buy Credits" heading next to a "No credit packs available"
			// empty state reads as duplicated label + misleading copy.
			?>
			<?php
			// One message per state, from the resolver, so this tab, the Buy
			// Credits page and the admin screens describe the site alike. Only
			// someone who can fix it reads owner wording; members never do.
			$listora_empty = array( 'icon' => 'credit-card' );
			if ( current_user_can( 'manage_listora_settings' ) && 'ready' !== $listora_state && '' !== ( $listora_monetization['owner_message'] ?? '' ) ) {
				$listora_empty['title'] = $listora_monetization['owner_message'];
				if ( '' !== ( $listora_monetization['fix_url'] ?? '' ) ) {
					$listora_empty['cta'] = array(
						'label' => $listora_monetization['fix_label'],
						'url'   => $listora_monetization['fix_url'],
					);
				}
			} elseif ( 'no_packs' === $listora_state && $credit_purchase_url ) {
				// Packs live in an external store: the copy must match the button.
				$listora_empty['title']       = __( 'Buy credits from our store', 'wb-listora' );
				$listora_empty['description'] = __( 'Credits are purchased from our store. Head there to top up your balance.', 'wb-listora' );
				$listora_empty['cta']         = array(
					'label' => __( 'Visit Store', 'wb-listora' ),
					'url'   => $credit_purchase_url,
				);
			} elseif ( 'no_packs' === $listora_state ) {
				$listora_empty['title']       = __( 'Credits are not on sale yet', 'wb-listora' );
				$listora_empty['description'] = __( 'This site has not put any credit packs on sale. Check back soon.', 'wb-listora' );
			} elseif ( 'needs_gateway' === $listora_state ) {
				$listora_empty['title']       = __( 'Checkout is unavailable right now', 'wb-listora' );
				$listora_empty['description'] = __( 'Credits cannot be purchased at the moment. Please try again later.', 'wb-listora' );
			} else {
				$listora_empty['title']       = __( 'Credits are not available', 'wb-listora' );
				$listora_empty['description'] = __( 'This site does not sell credits.', 'wb-listora' );
			}
			wb_listora_render_empty_state( $listora_empty );
			?>
		<?php else : ?>
		<h3 id="listora-credit-packs-heading" class="listora-dashboard__section-title">
			<?php esc_html_e( 'Buy Credits', 'wb-listora' ); ?>
		</h3>
			<?php wb_listora_render_credit_billing_form(); ?>
		<div class="listora-dashboard__credit-packs">
			<?php foreach ( $credit_packs as $pack_index => $pack ) : ?>
			<article class="listora-dashboard__credit-pack" style="--row-index: <?php echo (int) $pack_index; ?>">
				<?php
				// The pack is named by what the member gets. The store product's
				// name and which plugin sells it are the owner's plumbing, not a
				// member's concern.
				?>
				<header class="listora-dashboard__credit-pack-header">
					<h4 class="listora-dashboard__credit-pack-title">
						<?php
						printf(
							/* translators: %s: number of credits */
							esc_html( _n( '%s credit', '%s credits', (int) $pack['credits'], 'wb-listora' ) ),
							esc_html( wb_listora_format_credits( $pack['credits'] ) )
						);
						?>
					</h4>
				</header>

				<div class="listora-dashboard__credit-pack-body">
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
								data-currency="<?php echo esc_attr( (string) ( $pack['currency'] ?? wb_listora_get_currency_format()['code'] ) ); ?>"
								data-checkout-base="<?php echo esc_attr( $direct_checkout_base ); ?>"
								data-return-url="<?php echo esc_attr( $direct_return_url ); ?>"
								data-rest-nonce="<?php echo esc_attr( $direct_rest_nonce ); ?>"
								data-error-text="<?php esc_attr_e( 'We could not start checkout for this pack. Please try again, or contact the site administrator if it keeps happening.', 'wb-listora' ); ?>"
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
							<?php // Member-facing: "no payment gateway configured" is the owner's problem stated in the owner's words. ?>
							<?php esc_html_e( 'Not available to buy right now', 'wb-listora' ); ?>
						</span>
					<?php else : ?>
						<span class="listora-dashboard__credit-pack-unavailable">
							<?php esc_html_e( 'Not available to buy right now', 'wb-listora' ); ?>
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
			<?php
			wb_listora_render_empty_state(
				array(
					'icon'        => 'clipboard',
					'title'       => __( 'No transactions yet', 'wb-listora' ),
					'description' => __( 'Your credit activity will appear here.', 'wb-listora' ),
				)
			);
			?>
			<?php
		else :
			// Extensions (Pro's Receipt feature) can append a per-row actions
			// cell — e.g. a "Receipt" link on purchase rows. The column only
			// renders when a listener is attached, so Free-only sites keep the
			// 4-column table with no empty trailing column.
			$listora_credit_row_actions = has_action( 'wb_listora_dashboard_credit_row_actions' );
			?>
		<div class="listora-dashboard__transactions<?php echo $listora_credit_row_actions ? ' listora-dashboard__transactions--has-actions' : ''; ?>" role="table" aria-label="<?php esc_attr_e( 'Credit transactions', 'wb-listora' ); ?>">
			<div class="listora-dashboard__transactions-head" role="row">
				<span role="columnheader"><?php esc_html_e( 'Date', 'wb-listora' ); ?></span>
				<span role="columnheader"><?php esc_html_e( 'Type', 'wb-listora' ); ?></span>
				<span role="columnheader" class="listora-dashboard__transactions-amount-col"><?php esc_html_e( 'Amount', 'wb-listora' ); ?></span>
				<span role="columnheader"><?php esc_html_e( 'Note', 'wb-listora' ); ?></span>
				<?php if ( $listora_credit_row_actions ) : ?>
				<span role="columnheader"><?php esc_html_e( 'Receipt', 'wb-listora' ); ?></span>
				<?php endif; ?>
			</div>

			<?php
			foreach ( $credit_ledger as $row_index => $entry ) :
				$entry      = (array) $entry;
				$entry_type = isset( $entry['entry_type'] ) ? (string) $entry['entry_type'] : '';
				// Ledger rows store integer MINOR units under money mode, so a
				// 50-credit purchase is written as 5000. Printing the raw column
				// showed members a transaction history 100x their real figures.
				$amount    = isset( $entry['amount'] )
					? \Wbcom\Credits\Money::to_major( (int) $entry['amount'], $credit_currency )
					: 0.0;
				// The SDK's English notes become translated lines; Listora's and an admin's own notes stay.
				$note      = wb_listora_credit_note( $entry );
				$created   = isset( $entry['created_at'] ) ? (string) $entry['created_at'] : '';

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
					<?php echo esc_html( $amount_prefix . wb_listora_format_credits( $amount ) ); ?>
				</span>
				<span class="listora-dashboard__transaction-note" role="cell" data-label="<?php esc_attr_e( 'Note', 'wb-listora' ); ?>">
					<?php echo $note ? esc_html( $note ) : '<span aria-hidden="true">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both branches safe: esc_html() or static literal markup. ?>
				</span>
				<?php if ( $listora_credit_row_actions ) : ?>
				<span class="listora-dashboard__transaction-actions" role="cell" data-label="<?php esc_attr_e( 'Receipt', 'wb-listora' ); ?>">
					<?php
					/**
					 * Append per-row actions to a credit-history row.
					 *
					 * Pro's Receipt feature renders a "View receipt" link on
					 * purchase rows here. Listeners must escape their own output.
					 *
					 * @since 1.2.0
					 *
					 * @param array $entry The credit-ledger row (normalized to an array).
					 */
					do_action( 'wb_listora_dashboard_credit_row_actions', $entry );
					?>
				</span>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
			<?php
			wb_listora_render_pagination(
				array(
					'tab'         => 'credits',
					'page_arg'    => 'credits_page',
					'page'        => isset( $credit_ledger_page ) ? (int) $credit_ledger_page : 1,
					'total_pages' => isset( $credit_ledger_pages ) ? (int) $credit_ledger_pages : 0,
					'label'       => __( 'Transaction history pagination', 'wb-listora' ),
				)
			);
			?>
		<?php endif; ?>
	</section>
</div>
<?php
do_action( 'wb_listora_after_dashboard_credits', $view_data );
