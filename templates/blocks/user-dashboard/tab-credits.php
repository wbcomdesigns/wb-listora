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
 * @var array  $view_data            Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

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

// Primary "Buy Credits" CTA: jump to packs section, or use configured URL.
$buy_cta_url = '#listora-credit-packs';
if ( empty( $credit_packs ) && $credit_purchase_url ) {
	$buy_cta_url = $credit_purchase_url;
}
?>
<div role="tabpanel" id="dash-panel-credits" aria-labelledby="dash-tab-credits" class="listora-dashboard__panel"
	<?php echo 'credits' !== $default_tab ? 'hidden' : ''; ?>>

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
			<div class="listora-dashboard__balance-actions">
				<a href="<?php echo esc_url( $buy_cta_url ); ?>" class="listora-btn listora-btn--primary">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
					<?php esc_html_e( 'Buy Credits', 'wb-listora' ); ?>
				</a>
			</div>
		</div>
	</div>

	<?php // ─── B. Credit Packs ─── ?>
	<section class="listora-dashboard__credits-section" id="listora-credit-packs" aria-labelledby="listora-credit-packs-heading">
		<h3 id="listora-credit-packs-heading" class="listora-dashboard__section-title">
			<?php esc_html_e( 'Buy Credits', 'wb-listora' ); ?>
		</h3>

		<?php if ( empty( $credit_packs ) ) : ?>
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
					<?php if ( ! empty( $pack['buy_url'] ) ) : ?>
					<a href="<?php echo esc_url( $pack['buy_url'] ); ?>" class="listora-btn listora-btn--primary listora-btn--sm">
						<?php echo esc_html( $pack['buy_label'] ); ?>
					</a>
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
