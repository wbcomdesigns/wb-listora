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
 * too. A "Buy Credits" button sitting directly above "credits cannot be
 * purchased at the moment" is the same contradiction this card is about, just
 * inside one screen instead of across two (BC 10208510192).
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
			<?php /* Confirmed wording rendered here so it stays translatable; JS swaps it in once crediting is verified. */ ?>
			data-confirmed-text="<?php echo esc_attr( $purchase_credits > 0
				/* translators: %d: number of credits added. */
				? sprintf( _n( '%d credit added.', '%d credits added.', $purchase_credits, 'wb-listora' ), (int) $purchase_credits )
				: __( 'Credits added.', 'wb-listora' ) ); ?>">
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
					<span class="listora-dashboard__balance-number"><?php echo esc_html( number_format_i18n( $credit_balance, $credit_decimals ) ); ?></span>
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
	<?php
	/*
	 * Buyability comes from the ONE resolver, not from a local rule.
	 *
	 * This template used `! empty( $credit_packs ) && $has_payment_gateway`,
	 * which tests only for a DIRECT gateway. A pack sold as an external
	 * WooCommerce product has no direct gateway but is perfectly purchasable —
	 * so this screen told members to "contact the administrator" on sites that
	 * were ready to take their money, while the Buy Credits page on the same
	 * site listed the packs as available (BC 10208510192).
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
		<div class="listora-dashboard__empty">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
			<?php
			/*
			 * One message per state, from the resolver — so this screen, the
			 * Buy Credits page and the admin screens cannot describe the same
			 * site differently.
			 *
			 * Member-facing wording only. A member is never told to create a
			 * pack or connect a gateway: it is not their action, and phrasing
			 * it that way makes the site read as broken rather than as
			 * not-yet-open.
			 */
			?>
			<?php
			/*
			 * Someone who can FIX this gets the owner-actionable sentence, the
			 * same one the Buy Credits page shows them.
			 *
			 * Both surfaces already agreed for members. They did not agree for
			 * an ADMIN: Buy Credits named the missing gateway while this tab
			 * said "try again later", so one person looking at one site got two
			 * explanations, which is the contradiction this card is about
			 * (BC 10208510192). Members still never see owner language — that
			 * is not their action.
			 */
			$listora_can_fix = current_user_can( 'manage_listora_settings' );
			?>
			<?php if ( $listora_can_fix && 'ready' !== $listora_state && '' !== ( $listora_monetization['owner_message'] ?? '' ) ) : ?>
				<h3><?php echo esc_html( $listora_monetization['owner_message'] ); ?></h3>
				<?php if ( '' !== ( $listora_monetization['fix_url'] ?? '' ) ) : ?>
					<p>
						<a href="<?php echo esc_url( $listora_monetization['fix_url'] ); ?>">
							<?php echo esc_html( $listora_monetization['fix_label'] ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php elseif ( 'no_packs' === $listora_state && $credit_purchase_url ) : ?>
				<?php
				/*
				 * No packs are mapped locally, but the owner HAS pointed at an
				 * external store — so credits are obtainable and the copy must
				 * match the button underneath. Saying "not on sale, check back
				 * soon" above a "Visit Store" button is a contradiction the
				 * member has to resolve for themselves.
				 */
				?>
				<h3><?php esc_html_e( 'Buy credits from our store', 'wb-listora' ); ?></h3>
				<p><?php esc_html_e( 'Credits are purchased from our store. Head there to top up your balance.', 'wb-listora' ); ?></p>
			<?php elseif ( 'no_packs' === $listora_state ) : ?>
				<h3><?php esc_html_e( 'Credits are not on sale yet', 'wb-listora' ); ?></h3>
				<p><?php esc_html_e( 'This site has not put any credit packs on sale. Check back soon.', 'wb-listora' ); ?></p>
			<?php elseif ( 'needs_gateway' === $listora_state ) : ?>
				<h3><?php esc_html_e( 'Checkout is unavailable right now', 'wb-listora' ); ?></h3>
				<p><?php esc_html_e( 'Credits cannot be purchased at the moment. Please try again later.', 'wb-listora' ); ?></p>
			<?php else : ?>
				<h3><?php esc_html_e( 'Credits are not available', 'wb-listora' ); ?></h3>
				<p><?php esc_html_e( 'This site does not sell credits.', 'wb-listora' ); ?></p>
			<?php endif; ?>
			<?php
			/*
			 * Only offer the store when there is something to do there. In
			 * `needs_gateway` there is by definition no reachable checkout, so
			 * a "Visit Store" button next to "checkout is unavailable" is a
			 * contradiction inside a single card — the member clicks it and
			 * arrives nowhere useful.
			 */
			?>
			<?php if ( $credit_purchase_url && 'no_packs' === $listora_state ) : ?>
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
		<div class="listora-dashboard__empty">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
			<h3><?php esc_html_e( 'No transactions yet', 'wb-listora' ); ?></h3>
			<p><?php esc_html_e( 'Your credit activity will appear here.', 'wb-listora' ); ?></p>
		</div>
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
				$amount  = isset( $entry['amount'] )
					? \Wbcom\Credits\Money::to_major( (int) $entry['amount'], $credit_currency )
					: 0.0;
				$note    = isset( $entry['note'] ) ? (string) $entry['note'] : '';
				$created = isset( $entry['created_at'] ) ? (string) $entry['created_at'] : '';

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
					<?php echo esc_html( $amount_prefix . number_format_i18n( $amount, $credit_decimals ) ); ?>
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
		<?php endif; ?>
	</section>
</div>
<?php
do_action( 'wb_listora_after_dashboard_credits', $view_data );
