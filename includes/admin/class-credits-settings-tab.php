<?php
/**
 * Settings > Credits tab.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings > Credits: credit costs, listing limits per role and the payment webhook.
 */
class Credits_Settings_Tab {

	/**
	 * Render the Credits tab — credit costs, listing limits per role, SDK config,
	 * and action buttons linking to transactions/mappings/pricing plans.
	 */
	public static function render(): void {
		$s   = get_option( Settings_Page::OPTION_KEY, array() );
		$d   = wb_listora_get_default_settings();
		$opt = esc_attr( Settings_Page::OPTION_KEY );

		// Pro upsell (rendered only when Pro is inactive). Tells admins what
		// additional monetization features unlock with the paid plugin so they
		// don't assume "credits" is the ceiling.
		if ( function_exists( 'wb_listora_render_pro_cta' ) ) {
			wb_listora_render_pro_cta(
				array(
					'title'       => __( 'Sell more than credits', 'wb-listora' ),
					'description' => __( 'Pro adds pricing plans, coupons, credit packs, webhooks, and full transaction history.', 'wb-listora' ),
					'features'    => array(
						__( 'Flexible pricing plans (one-off + subscription)', 'wb-listora' ),
						__( 'Coupon codes with expiry and usage limits', 'wb-listora' ),
						__( 'WooCommerce, PMPro, MemberPress adapters', 'wb-listora' ),
						__( 'Transaction log + refund tooling', 'wb-listora' ),
					),
				)
			);
		}

		$featured_duration_value = (int) ( $s['featured_duration_days'] ?? $d['featured_duration_days'] );
		$low_threshold           = (int) get_option( 'wb_listora_low_credit_threshold', 5 );
		$webhook_url             = rest_url( WB_LISTORA_REST_NAMESPACE . '/webhooks/payment' );
		/**
		 * Filters the webhook signing secret for the Pro payment endpoint.
		 *
		 * Free no longer reads `wb_listora_pro_webhook_secret` directly; Pro
		 * answers this filter from `Webhook_Receiver::get_secret()` so Free
		 * stays decoupled from Pro's option namespace (Invariant #12).
		 *
		 * @param string $default Default empty secret returned when Pro is not active.
		 * @param array  $context Caller context. Currently `['context' => 'settings_page']`.
		 */
		$webhook_secret = (string) apply_filters( 'wb_listora_webhook_secret', '', array( 'context' => 'settings_page' ) );

		self::render_subnav();
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block" data-listora-subtab="pricing">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Credit Costs', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Credits charged for submitting listings and upgrading them to Featured. Set any cost to 0 to make that action free.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="listora_default_listing_credit_cost"><?php esc_html_e( 'Listing submission cost', 'wb-listora' ); ?></label></th>
							<td>
								<input
									type="number"
									id="listora_default_listing_credit_cost"
									name="<?php echo esc_attr( $opt ); ?>[default_listing_credit_cost]"
									value="<?php echo esc_attr( $s['default_listing_credit_cost'] ?? $d['default_listing_credit_cost'] ); ?>"
									min="0"
									step="1"
									class="small-text"
								/>
								<span><?php esc_html_e( 'credits', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Charged when submitting a standard listing without a paid plan. Set to 0 for free submissions.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="listora_featured_credit_cost"><?php esc_html_e( 'Featured upgrade cost', 'wb-listora' ); ?></label></th>
							<td>
								<input
									type="number"
									id="listora_featured_credit_cost"
									name="<?php echo esc_attr( $opt ); ?>[featured_credit_cost]"
									value="<?php echo esc_attr( $s['featured_credit_cost'] ?? $d['featured_credit_cost'] ); ?>"
									min="0"
									step="1"
									class="small-text"
								/>
								<span><?php esc_html_e( 'credits', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Charged when a user upgrades an existing listing to Featured. Set to 0 to make upgrades free.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="listora_featured_duration_days"><?php esc_html_e( 'Featured duration', 'wb-listora' ); ?></label></th>
							<td>
								<?php wb_listora_render_optional_number( 'listora_featured_duration_days', $opt . '[featured_duration_days]', (int) $featured_duration_value, __( 'Permanent', 'wb-listora' ), __( 'days', 'wb-listora' ), 30 ); ?>
								<p class="description"><?php esc_html_e( 'How long a listing stays featured after upgrade. Tick Permanent to never expire.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<?php self::render_listing_limits_section( $s ); ?>

			<section class="listora-settings-block" data-listora-subtab="payments">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Payment Webhook', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Endpoint your payment provider POSTs to when a customer completes a purchase. Listora verifies the HMAC signature, credits the matching user, and writes a transaction ledger row.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="wb_listora_low_credit_threshold"><?php esc_html_e( 'Low Balance Alert', 'wb-listora' ); ?></label></th>
							<td>
								<input
									type="number"
									id="wb_listora_low_credit_threshold"
									name="wb_listora_low_credit_threshold"
									value="<?php echo esc_attr( (string) $low_threshold ); ?>"
									min="0"
									step="1"
									class="small-text"
								/>
								<span><?php esc_html_e( 'credits', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Email the user when their credit balance drops below this amount. Set to 0 to disable.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<?php if ( wb_listora_is_pro_active() ) : // Payment webhook + secret are Pro monetization; the route is not registered on Free. ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook URL', 'wb-listora' ); ?></th>
							<td>
								<div class="listora-copy-field">
									<input type="text" readonly value="<?php echo esc_attr( $webhook_url ); ?>" class="large-text listora-copy-field__input" />
									<button type="button" class="button listora-copy-btn wp-element-button" data-copy-target="<?php echo esc_attr( $webhook_url ); ?>">
										<i data-lucide="copy"></i>
										<span class="listora-copy-btn__label"><?php esc_html_e( 'Copy', 'wb-listora' ); ?></span>
									</button>
								</div>
								<p class="description"><?php esc_html_e( 'Paste this URL into your payment provider as the webhook destination. See the integration guide below for per-provider setup steps.', 'wb-listora' ); ?></p>
							</td>
						</tr>
							<?php if ( '' !== $webhook_secret ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook Secret', 'wb-listora' ); ?></th>
							<td>
								<div class="listora-copy-field">
									<input type="text" readonly value="<?php echo esc_attr( $webhook_secret ); ?>" class="large-text listora-copy-field__input" />
									<button type="button" class="button listora-copy-btn wp-element-button" data-copy-target="<?php echo esc_attr( $webhook_secret ); ?>">
										<i data-lucide="copy"></i>
										<span class="listora-copy-btn__label"><?php esc_html_e( 'Copy', 'wb-listora' ); ?></span>
									</button>
								</div>
								<p class="description"><?php esc_html_e( 'Shared secret used to verify incoming webhook requests. Keep this value private.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<?php endif; ?>
						<?php endif; // wb_listora_is_pro_active webhook rows ?>
					</tbody>
				</table>

				<?php if ( wb_listora_is_pro_active() ) : ?>
				<details class="listora-help-block">
					<summary><?php esc_html_e( 'How to wire this up (Stripe, PayPal, WooCommerce, custom)', 'wb-listora' ); ?></summary>

					<div class="listora-help-block__body">
						<h4><?php esc_html_e( 'Request contract', 'wb-listora' ); ?></h4>
						<p class="description"><?php esc_html_e( 'Your provider (or a small relay you control) sends a signed POST to the Webhook URL above. Listora rejects anything missing the headers below.', 'wb-listora' ); ?></p>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Header', 'wb-listora' ); ?></th>
									<th><?php esc_html_e( 'Value', 'wb-listora' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr><td><code>X-Listora-Signature</code></td><td><code>sha256=&lt;hmac&gt;</code> where <code>&lt;hmac&gt;</code> = HMAC-SHA256 of <code>&lt;timestamp&gt;.&lt;raw-body&gt;</code> keyed with your Webhook Secret</td></tr>
								<tr><td><code>X-Listora-Timestamp</code></td><td><?php esc_html_e( 'Unix seconds at time of request. Anything more than 5 minutes off the server clock is rejected (replay defence).', 'wb-listora' ); ?></td></tr>
								<tr><td><code>Content-Type</code></td><td><code>application/json</code></td></tr>
							</tbody>
						</table>

						<h4><?php esc_html_e( 'JSON body fields', 'wb-listora' ); ?></h4>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Field', 'wb-listora' ); ?></th>
									<th><?php esc_html_e( 'Required', 'wb-listora' ); ?></th>
									<th><?php esc_html_e( 'Description', 'wb-listora' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr><td><code>user_email</code></td><td><?php esc_html_e( 'yes', 'wb-listora' ); ?></td><td><?php esc_html_e( 'WordPress user the credits are added to (matched by email).', 'wb-listora' ); ?></td></tr>
								<tr><td><code>credits</code> <em><?php esc_html_e( 'or', 'wb-listora' ); ?></em> <code>amount</code></td><td><?php esc_html_e( 'one of', 'wb-listora' ); ?></td><td><?php esc_html_e( '`credits` = exact number to add. `amount` = currency value (multiplied by your Credit Rate setting). At least one must be > 0.', 'wb-listora' ); ?></td></tr>
								<tr><td><code>transaction_id</code></td><td><?php esc_html_e( 'recommended', 'wb-listora' ); ?></td><td><?php esc_html_e( 'Provider transaction reference, stored on the ledger row for reconciliation.', 'wb-listora' ); ?></td></tr>
								<tr><td><code>gateway</code></td><td><?php esc_html_e( 'recommended', 'wb-listora' ); ?></td><td><code>stripe</code>, <code>paypal</code>, <code>woocommerce</code>, <?php esc_html_e( 'or your own slug.', 'wb-listora' ); ?></td></tr>
								<tr><td><code>plan_slug</code></td><td><?php esc_html_e( 'optional', 'wb-listora' ); ?></td><td><?php esc_html_e( 'If set, also assigns the matching Pricing Plan + applies its featured/duration perks.', 'wb-listora' ); ?></td></tr>
								<tr><td><code>event</code></td><td><?php esc_html_e( 'optional', 'wb-listora' ); ?></td><td><?php esc_html_e( 'Defaults to `payment.completed`. Used in audit-log entries.', 'wb-listora' ); ?></td></tr>
								<tr><td><code>timestamp</code></td><td><?php esc_html_e( 'optional', 'wb-listora' ); ?></td><td><?php esc_html_e( 'Unix seconds inside the payload (separate from the header). Lets you sign payloads without sending the header — handy for providers that strip custom headers.', 'wb-listora' ); ?></td></tr>
							</tbody>
						</table>

						<h4><?php esc_html_e( 'Stripe', 'wb-listora' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'Stripe Dashboard → Developers → Webhooks → Add endpoint.', 'wb-listora' ); ?></li>
							<li><?php esc_html_e( 'Paste the Webhook URL above; subscribe to event `checkout.session.completed` (or `payment_intent.succeeded` if you use raw PaymentIntents).', 'wb-listora' ); ?></li>
							<li><?php esc_html_e( 'Stripe signs with its own header (`Stripe-Signature`). Listora does NOT read that header — you need a tiny relay (Cloudflare Worker, custom mu-plugin, or Pipedream) that re-signs Stripe\'s payload as `<timestamp>.<body>` with the Listora secret and POSTs here. A 20-line example lives in `docs/integrations/stripe-relay.md`.', 'wb-listora' ); ?></li>
							<li><?php esc_html_e( 'In the relay, map Stripe fields → Listora fields:', 'wb-listora' ); ?> <code>customer_email → user_email</code>, <code>amount_total/100 → amount</code>, <code>payment_intent → transaction_id</code>, <code>"stripe" → gateway</code>.</li>
						</ol>

						<h4><?php esc_html_e( 'PayPal', 'wb-listora' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'PayPal Developer Dashboard → My Apps & Credentials → your app → Webhooks → Add webhook.', 'wb-listora' ); ?></li>
							<li><?php esc_html_e( 'Webhook URL = your relay endpoint (PayPal posts IPN-style notifications that need translating to the Listora payload shape).', 'wb-listora' ); ?></li>
							<li><?php esc_html_e( 'Subscribe to `PAYMENT.CAPTURE.COMPLETED` (REST API) or use IPN with `txn_type=web_accept` / `payment_status=Completed`.', 'wb-listora' ); ?></li>
							<li><?php esc_html_e( 'Relay maps:', 'wb-listora' ); ?> <code>payer.email_address → user_email</code>, <code>amount.value → amount</code>, <code>id → transaction_id</code>, <code>"paypal" → gateway</code>.</li>
						</ol>

						<h4><?php esc_html_e( 'WooCommerce (same site)', 'wb-listora' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'Easiest path: skip the webhook entirely. Install the matching credit adapter under Listora → Settings → Credits → Credit Mappings; the SDK reads WooCommerce order completion directly and tops up credits in-process.', 'wb-listora' ); ?></li>
							<li><?php esc_html_e( 'Alternative (cross-site WC): wire WooCommerce → Settings → Advanced → Webhooks → Add webhook. Topic `Order updated`. Delivery URL = the Listora Webhook URL above. Add a small `woocommerce_webhook_payload` filter that emits the Listora payload shape with the order\'s `billing_email`, `total`, `id`, and `"woocommerce"`.', 'wb-listora' ); ?></li>
						</ol>

						<h4><?php esc_html_e( 'Test it works (curl)', 'wb-listora' ); ?></h4>
						<p class="description"><?php esc_html_e( 'Run this from a terminal to confirm the endpoint accepts a signed request. Replace `<SECRET>` with the Webhook Secret above.', 'wb-listora' ); ?></p>
<pre style="overflow:auto;background:var(--listora-bg-surface,#f6f7f7);padding:12px;border-radius:6px;font-size:12px;"><code>TS=$(date +%s)
BODY='{"user_email":"customer@example.com","credits":10,"transaction_id":"test_001","gateway":"manual","timestamp":'$TS'}'
SIG="sha256=$(printf "%s.%s" "$TS" "$BODY" | openssl dgst -sha256 -hmac "&lt;SECRET&gt;" -hex | sed 's/^.* //')"
curl -X POST "<?php echo esc_html( $webhook_url ); ?>" \
	-H "Content-Type: application/json" \
	-H "X-Listora-Timestamp: $TS" \
	-H "X-Listora-Signature: $SIG" \
	-d "$BODY"</code></pre>
						<p class="description"><?php esc_html_e( 'Expected response: HTTP 200 with `{"success":true,"credits_added":10,...}`. Errors return a JSON body with `code` and `message` — check the Audit Log (Listora → Audit Log) for rejection reasons.', 'wb-listora' ); ?></p>
					</div>
				</details>
				<?php endif; ?>
			</section>

			<section class="listora-settings-block" data-listora-subtab="pricing">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Plans and transactions', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Where credits are bundled into plans, and where every credit movement is recorded.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Transactions', 'wb-listora' ); ?></th>
							<td>
								<?php if ( wb_listora_is_pro_active() ) : // The transactions admin page is registered by Pro; a live button on Free-only is a dead link. Mirrors the Pricing plans row. ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-transactions' ) ); ?>" class="button">
										<?php esc_html_e( 'View Transaction Log', 'wb-listora' ); ?>
									</a>
									<p class="description"><?php esc_html_e( 'Per-user credit ledger with filters and CSV export.', 'wb-listora' ); ?></p>
								<?php else : ?>
									<button type="button" class="button" disabled="disabled">
										<?php esc_html_e( 'View Transaction Log', 'wb-listora' ); ?>
									</button>
									<p class="description"><?php esc_html_e( 'The transaction log appears here once monetization is turned on.', 'wb-listora' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Pricing plans', 'wb-listora' ); ?></th>
							<td>
								<?php if ( post_type_exists( 'listora_plan' ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=listora_plan' ) ); ?>" class="button">
										<?php esc_html_e( 'Manage Pricing Plans', 'wb-listora' ); ?>
									</a>
									<p class="description"><?php esc_html_e( 'Bundle credits + listing limits into named plans assigned at submission time.', 'wb-listora' ); ?></p>
								<?php else : ?>
									<button type="button" class="button" disabled="disabled">
										<?php esc_html_e( 'Manage Pricing Plans', 'wb-listora' ); ?>
									</button>
									<p class="description"><?php esc_html_e( 'The plans manager appears here once monetization is turned on.', 'wb-listora' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
		// Copy-to-clipboard button handler lives in assets/js/admin/settings-page.js (no inline JS).
	}

	/**
	 * Sub-tabs across the top of Settings > Credits. Every section carries
	 * data-listora-subtab; the script shows one group at a time, and without
	 * it every section shows. All of them stay in the tab's one form, so one
	 * Save covers every sub-tab.
	 */
	private static function render_subnav(): void {
		/**
		 * Filter the Settings > Credits sub-tabs: key => label. Sections
		 * opt in with data-listora-subtab="<key>".
		 *
		 * @since 1.9.0
		 *
		 * @param array<string, string> $subtabs Sub-tabs.
		 */
		$subtabs = (array) apply_filters(
			'wb_listora_credits_subtabs',
			array(
				'pricing'  => __( 'Pricing', 'wb-listora' ),
				'limits'   => __( 'Limits', 'wb-listora' ),
				'payments' => __( 'Payments', 'wb-listora' ),
			)
		);
		?>
		<div class="listora-subtabs" data-listora-subtabs role="tablist" aria-label="<?php esc_attr_e( 'Credits settings', 'wb-listora' ); ?>">
			<?php foreach ( $subtabs as $key => $label ) : ?>
				<button type="button" class="listora-subtabs__tab" role="tab" data-subtab="<?php echo esc_attr( (string) $key ); ?>" aria-selected="false"><?php echo esc_html( (string) $label ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render the "Listing Limits per Role" section inside the Submissions tab.
	 *
	 * @param array<string, mixed> $s Current settings.
	 */
	private static function render_listing_limits_section( $s ): void {
		$roles_obj  = wp_roles();
		$role_names = $roles_obj instanceof \WP_Roles ? $roles_obj->get_names() : array();

		$limits_map = isset( $s['listing_limits_per_role'] ) && is_array( $s['listing_limits_per_role'] )
			? $s['listing_limits_per_role']
			: array();

		// Only roles that can submit a listing: a limit on a role without the
		// capability never applies.
		$role_names    = array_filter(
			$role_names,
			static function ( $role ) use ( $roles_obj ) {
				$object = $roles_obj->get_role( $role );
				return $object && ! empty( $object->capabilities[ \WBListora\Core\Capabilities::CAP_SUBMIT_LISTING ] );
			},
			ARRAY_FILTER_USE_KEY
		);
		$default_limit = isset( $s['listing_limits_default'] ) ? (int) $s['listing_limits_default'] : -1;
		$overflow_cost = (int) get_option( \WBListora\Core\Listing_Limits::OVERFLOW_COST_OPTION, 10 );

		$period = isset( $s['listing_limits_period'] ) && in_array( $s['listing_limits_period'], array( 'lifetime', 'calendar_month', 'rolling_30d' ), true )
			? $s['listing_limits_period']
			: 'lifetime';

		$behavior = isset( $s['listing_beyond_limit_behavior'] ) && in_array( $s['listing_beyond_limit_behavior'], array( 'block', 'credits' ), true )
			? $s['listing_beyond_limit_behavior']
			: 'block';

		$default_is_unlimited = -1 === (int) $default_limit;
		$default_num_value    = $default_is_unlimited ? '' : (string) max( 0, (int) $default_limit );
		$opt                  = esc_attr( Settings_Page::OPTION_KEY );
		?>
		<section class="listora-settings-block" data-listora-subtab="limits">
			<div class="listora-settings-block__head">
				<h3 class="listora-settings-block__title"><?php esc_html_e( 'Listing Limits per Role', 'wb-listora' ); ?></h3>
				<p class="listora-settings-block__desc"><?php esc_html_e( 'Configure how many listings each role can submit per period. Beyond the limit, block the submission or allow overflow in exchange for credits.', 'wb-listora' ); ?></p>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Limit period', 'wb-listora' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Limit Period', 'wb-listora' ); ?></legend>
								<div class="listora-field-group">
									<label>
										<input type="radio" name="<?php echo esc_attr( $opt ); ?>[listing_limits_period]" value="lifetime" <?php checked( 'lifetime', $period ); ?> />
										<strong><?php esc_html_e( 'Lifetime', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'count every listing a user has ever submitted.', 'wb-listora' ); ?></span>
									</label>
									<label>
										<input type="radio" name="<?php echo esc_attr( $opt ); ?>[listing_limits_period]" value="calendar_month" <?php checked( 'calendar_month', $period ); ?> />
										<strong><?php esc_html_e( 'Calendar month', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'resets on the 1st of each month.', 'wb-listora' ); ?></span>
									</label>
									<label>
										<input type="radio" name="<?php echo esc_attr( $opt ); ?>[listing_limits_period]" value="rolling_30d" <?php checked( 'rolling_30d', $period ); ?> />
										<strong><?php esc_html_e( 'Rolling 30 days', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'counts submissions in the last 30 days from today.', 'wb-listora' ); ?></span>
									</label>
								</div>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Per-role limits', 'wb-listora' ); ?></th>
						<td>
							<table class="listora-table listora-role-limits-table">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Role', 'wb-listora' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Unlimited', 'wb-listora' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Listings per period', 'wb-listora' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									if ( empty( $role_names ) ) {
										echo '<tr><td colspan="3">' . esc_html__( 'No roles registered.', 'wb-listora' ) . '</td></tr>';
									} else {
										foreach ( $role_names as $role_slug => $role_label ) :
											$has_value    = isset( $limits_map[ $role_slug ] );
											$raw_value    = $has_value ? (int) $limits_map[ $role_slug ] : null;
											$is_unlimited = $has_value && -1 === $raw_value;
											$num_value    = ( $has_value && $raw_value >= 0 ) ? (string) $raw_value : '';
											$field_id     = 'listora_limit_role_' . $role_slug;
											$unlim_id     = 'listora_limit_unlim_' . $role_slug;
											// Administrators short-circuit to unlimited in
											// Listing_Limits::get_user_limit(), so this row is shown inert
											// rather than as an editable field whose value is discarded.
											$row_is_inert = 'administrator' === $role_slug;
											?>
											<tr>
												<td>
													<label for="<?php echo esc_attr( $field_id ); ?>">
														<strong><?php echo esc_html( translate_user_role( $role_label ) ); ?></strong>
													</label>
													<?php if ( $row_is_inert ) : ?>
														<span class="description"><?php esc_html_e( 'Always unlimited', 'wb-listora' ); ?></span>
													<?php endif; ?>
												</td>
												<td>
													<label for="<?php echo esc_attr( $unlim_id ); ?>">
														<input
															type="checkbox"
															id="<?php echo esc_attr( $unlim_id ); ?>"
															class="listora-limit-unlimited"
															data-role="<?php echo esc_attr( $role_slug ); ?>"
															name="<?php echo esc_attr( $opt ); ?>[listing_limits_unlimited][<?php echo esc_attr( $role_slug ); ?>]"
															value="1"
															<?php checked( $is_unlimited || $row_is_inert ); ?>
															<?php disabled( $row_is_inert ); ?>
														/>
														<?php esc_html_e( 'Unlimited', 'wb-listora' ); ?>
													</label>
												</td>
												<td>
													<input
														type="number"
														id="<?php echo esc_attr( $field_id ); ?>"
														name="<?php echo esc_attr( $opt ); ?>[listing_limits_per_role][<?php echo esc_attr( $role_slug ); ?>]"
														value="<?php echo esc_attr( $num_value ); ?>"
														min="0"
														step="1"
														class="small-text listora-limit-count"
														data-role="<?php echo esc_attr( $role_slug ); ?>"
														<?php disabled( $is_unlimited || $row_is_inert ); ?>
													/>
													<span class="description"><?php esc_html_e( 'per period', 'wb-listora' ); ?></span>
												</td>
											</tr>
											<?php
										endforeach;
									}
									?>
								</tbody>
							</table>
							<p class="description"><?php esc_html_e( 'Check "Unlimited" to remove the cap for a specific role. Otherwise, the number applies per period.', 'wb-listora' ); ?></p>
							<p class="description">
								<strong><?php esc_html_e( 'When a member holds more than one role, the most generous limit wins.', 'wb-listora' ); ?></strong>
								<?php esc_html_e( 'A member who is both Subscriber (2) and Contributor (10) gets 10, and any role marked Unlimited makes them unlimited. For the same reason, setting a role to 0 does not stop a member who also holds another role listed here. Administrators are always unlimited.', 'wb-listora' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="listora_limits_default"><?php esc_html_e( 'Default limit', 'wb-listora' ); ?></label>
						</th>
						<td>
							<label class="listora-inline-label">
								<input
									type="checkbox"
									id="listora_limits_default_unlimited"
									class="listora-limit-unlimited"
									data-role="__default__"
									name="<?php echo esc_attr( $opt ); ?>[listing_limits_default_unlimited]"
									value="1"
									<?php checked( $default_is_unlimited ); ?>
								/>
								<?php esc_html_e( 'Unlimited', 'wb-listora' ); ?>
							</label>
							<input
								type="number"
								id="listora_limits_default"
								name="<?php echo esc_attr( $opt ); ?>[listing_limits_default]"
								value="<?php echo esc_attr( $default_num_value ); ?>"
								min="0"
								step="1"
								class="small-text listora-limit-count"
								data-role="__default__"
								<?php disabled( $default_is_unlimited ); ?>
							/>
							<span class="description"><?php esc_html_e( 'per period', 'wb-listora' ); ?></span>
							<p class="description"><?php esc_html_e( 'Applied to a member whose roles are all absent from the table above - for example roles added by another plugin. A member with one listed role and one unlisted role uses the listed role\'s number, not this default. Check Unlimited to remove the cap.', 'wb-listora' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Beyond-limit behavior', 'wb-listora' ); ?></th>
						<td>
							<fieldset class="listora-beyond-limit-fieldset">
								<legend class="screen-reader-text"><?php esc_html_e( 'Beyond Limit Behavior', 'wb-listora' ); ?></legend>
								<div class="listora-field-group">
									<label>
										<input
											type="radio"
											name="<?php echo esc_attr( $opt ); ?>[listing_beyond_limit_behavior]"
											value="block"
											class="listora-beyond-limit-radio"
											data-target="block"
											<?php checked( 'block', $behavior ); ?>
										/>
										<strong><?php esc_html_e( 'Block submission', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'show "You have reached your limit" and stop the submission.', 'wb-listora' ); ?></span>
									</label>
									<label>
										<input
											type="radio"
											name="<?php echo esc_attr( $opt ); ?>[listing_beyond_limit_behavior]"
											value="credits"
											class="listora-beyond-limit-radio"
											data-target="credits"
											<?php checked( 'credits', $behavior ); ?>
										/>
										<strong><?php esc_html_e( 'Allow with credit cost', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'user pays the credit cost below for each additional listing.', 'wb-listora' ); ?></span>
									</label>
								</div>
							</fieldset>
						</td>
					</tr>

					<tr class="listora-overflow-cost-row" style="<?php echo 'credits' === $behavior ? '' : 'display:none;'; ?>">
						<th scope="row">
							<label for="listora_overflow_credit_cost"><?php esc_html_e( 'Overflow cost', 'wb-listora' ); ?></label>
						</th>
						<td>
							<?php wb_listora_render_optional_number( 'listora_overflow_credit_cost', 'wb_listora_overflow_credit_cost', (int) $overflow_cost, __( 'Off', 'wb-listora' ), __( 'credits per extra listing', 'wb-listora' ), 10 ); ?>
							<p class="description"><?php esc_html_e( 'Charged when a user submits beyond their role limit. Tick Off to disable the overflow path (the limit becomes a hard stop).', 'wb-listora' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</section>

		<?php
		// Beyond-limit radio + per-role unlimited toggle handlers live in
		// assets/js/admin/settings-page.js (no inline JS rule).
	}
}
