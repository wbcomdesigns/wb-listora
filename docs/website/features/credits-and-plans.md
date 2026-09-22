# Credits and Pricing Plans

> **Availability:** Pro only. Requires [WB Listora Pro](../getting-started/activating-pro.md). Free sites can use listing limits per role without a credit system.

## Monetization is opt-in by default (since 1.2.0)

On a fresh install of WB Listora Pro, the entire credit and pricing-plan system is **off by default**. Users can submit listings at no cost until you choose to enable it.

To switch it on, go to **Listora → Settings → Features** and enable the **Monetization** toggle. This single toggle activates the credit system, pricing plans, coupons, and the payment webhook receiver together.

**Upgrading from a version before 1.2.0?** Nothing changes for you. The toggle is automatically set to ON for existing installs to preserve your current setup.

**Why the submission form no longer shows a Plan step:**

If you installed WB Listora Pro for the first time and the "Choose a Plan" step is missing from the submission form, Monetization is off (the default). Enable the toggle in **Settings → Features** and the plan step appears immediately. See [Submission Settings](../settings/submission-settings.md) for other form controls.

## What it does

WB Listora Pro includes a credit-based payment system. Users purchase credits (via your payment provider of choice), and spend those credits to activate listing plans. Each plan determines how long a listing stays active, whether it gets featured placement, and what perks it includes.

![Credits And Plans - screenshot from the modernized 1.0.5 site](../images/credits-and-plans.png)

## Why you'd use it

- Monetize your directory without a WooCommerce store - credits work with any payment gateway via webhook.
- Pricing plans give you flexible packaging: a free basic plan, a paid featured plan, and a premium plan can all coexist.
- Credits are reusable - users can top up once and submit multiple listings over time.
- The webhook-based topup system is payment-processor-agnostic: Stripe, PayPal, Paddle, or any custom solution works.

![Transactions admin - credit purchases + plan activations with gateway, amount, and status](../images/transactions.png)

## How to use it

### For site owners (admin steps)

There are two ways to sell credits, and you can run both at once.

| | Direct pack (Stripe / PayPal) | Mapped product (WooCommerce, PMPro, MemberPress) |
|---|---|---|
| Setup | Paste API keys, define packs. 5-10 min | Install and configure the other plugin, then map. 20-40 min |
| Tax / VAT invoices | No - flat price, no tax calculation | Yes, handled by that plugin |
| Recurring subscriptions | No, one-time packs | Yes |

**Step 1: Connect a payment path**

Go to **Listora → Settings → Credits**. If neither path is configured yet, the tab
opens on a **Connect a payment path** card that offers both and explains which
suits you.

*Direct packs:* enter your Stripe or PayPal keys in the gateway fields on that
same tab, then use **Add Direct Pack (Stripe / PayPal)** to define each pack -
credits, price, currency and an optional label. No other plugin needed.

*Mapped products:* with WooCommerce, PMPro or MemberPress active, use
**Add New Mapping** to point one of their products or plans at a credit amount.
Buying that product credits the customer.

**Step 2: Check the Active Mappings table**

Every pack and mapping you create appears in **Active Mappings**, showing the
provider, the product, the credits granted and the price.

- **Pricing** shows the real price for a direct pack. Mapped products show a dash:
  WooCommerce, PMPro and MemberPress do not report a price to Listora, so the
  price is the one set on the product itself.
- **Edit** changes a row in place. Use it rather than removing and re-adding -
  a customer part-way through checkout while the row is missing receives nothing.
- A direct pack is edited in the **Direct Pack** form, a mapped product in the
  **Mapping** form, because only a direct pack carries its own price and label.

**Step 3: The webhook (recommended, not required)**

The return from Stripe or PayPal claims the credits on its own, so a purchase
completes without a webhook. Adding one is still worth it: it syncs refunds and
credits the buyer even if they close the tab before the redirect lands.

1. Copy the **Webhook URL** and **Webhook Secret** from the Credits tab.
2. In Stripe or PayPal, create a webhook that fires on payment success and posts
   to that URL, using the secret as the HMAC key.

**Step 4: Set the Credits page**

**Listora → Settings → General → Pages** holds the **Buy Credits** page. Pro
creates it when you enable the feature; the setting is there so you can re-map it
or see its status. That URL is what every "Buy Credits" link points at.

**Step 5: Create pricing plans**

1. Go to **Listora → Pricing Plans → Add New Plan**.
2. Fill in the plan settings:
- **Plan title** - the name shown to users (e.g., "Basic", "Featured", "Premium").
- **Plan Price (credits)** - credits required to purchase this plan. Set to `0` for a free plan.
- **Credit Cost** - credits deducted per listing submission on this plan.
- **Display Price** - optional label shown to users (e.g., "$29/month"). This is for display only; actual charging happens through the path you set up in Step 1.
- **Duration (days)** - how long the listing stays active. Set to `0` for permanent listings.
- **Listing types** - restrict the plan to certain listing types, or leave empty for all. The submission form hides a plan the chosen type cannot use.
- **Featured Plan** - tick this to highlight the plan as recommended in the plan selection step.
- **Badge Text** - optional label on the plan card (e.g., "Most Popular", "Best Value").
- **Plan Perks** - one checkbox: **Mark listing as Featured**, which is enforced when the plan activates.
3. Publish the plan.
4. Repeat for each plan you want to offer.

> **Plan Perks used to list "Priority support" and "Analytics dashboard access".**
> Both were removed in 1.8.0. Nothing in either plugin ever read them - the
> Analytics dashboard is open to every member who owns a listing, and priority
> support had no consumer - so they were bullets on a pricing card promising
> something the product never withheld. Do not build a pricing tier around them.

**Step 6: Verify the plan selection step**

When a user submits a new listing, a **Choose a Plan** step appears in the
submission form showing the plans available for the listing type they picked.
Plans the user can't afford are greyed out with a "Buy Credits" link.

**Adding credits manually:**

Go to **Users → Edit User** and use the **Listora Credits** panel to add credits directly without a payment. Useful for comping credits to early adopters or resolving disputes.

### For end users (visitor/user-facing)

1. Go to the credits purchase page to buy credits.
2. When submitting a listing, the **Choose a Plan** step shows all available plans with their credit cost, duration, and perks.
3. Select a plan. If you have a coupon code, enter it in the coupon field - the credit cost adjusts immediately.
4. Your credit balance is shown on the plan selection screen. After submitting, the credit cost is deducted from your balance.
5. View your current balance and transaction history in **User Dashboard → Credits**.

## Low balance alerts

**Where:** the **Low Balance Alert** field in Listora's credit settings. Default: 5 credits. Set it to `0` to switch the alert off entirely.

When a member's balance falls to the threshold, they are emailed once. The setting has existed for some time; until Pro 1.6.0 nothing acted on it, so the field promised an email that was never sent.

The alert is **once per crossing, not once ever**. The member is marked as notified when the mail goes out, and that mark is cleared as soon as their balance climbs back above the threshold. So a member who runs low, tops up, and later runs low again is warned both times - but a member sitting below the threshold for a month is not emailed every day.

Requires Pro: the threshold is a Free setting because credits are a shared surface, but the notifier that sends the mail ships in Pro.

## Payments and reconciliation

**Where:** **Listora > Payments** (Pro).

The Payments screen shows what the gateway actually charged for each transaction, not just what Listora recorded. That distinction matters at reconciliation time: a refund processed at the gateway can be matched against the payment it reverses, so the ledger and the gateway can be squared without exporting both and comparing by hand.

The Transactions screen and the credit settings tab are **hidden while monetization is off**, so a site that does not sell credits never shows its owner a rate table or a gateway picker it has no use for.

## Tax, VAT and GST compliance (EU / UK / Australia and others)

Credits are a **digital service**. If you sell them to consumers in jurisdictions with consumption tax - EU/UK **VAT**, Australia/New Zealand **GST**, and similar - you are generally responsible for charging the correct tax at the buyer's location, issuing a compliant tax invoice, and (for business buyers in the EU) handling **reverse-charge** with a validated VAT ID. The rules, rates, thresholds, and invoice fields vary by country - **confirm your obligations with a tax advisor**. This page describes what the plugin does, not legal advice.

**Which purchase route you use determines whether tax and invoicing are handled:**

- **WooCommerce route (recommended when you owe VAT/GST).** Sell credits as a WooCommerce product and let WooCommerce + a tax extension (e.g. WooCommerce Tax / an EU-VAT plugin) and an invoice/PDF plugin do the work. WooCommerce then collects the billing address, applies the correct location-based VAT/GST rate, supports B2B VAT-ID reverse-charge, and issues compliant invoices. WB Listora's WooCommerce credit top-up consumes the completed Woo order, so the **tax and invoice are produced by your store**, configured the way you already run it. **This is the compliant path for tax-liable merchants today.**
- **Direct Stripe / PayPal gateways.** The built-in direct credit checkout charges a **flat credit price with no tax calculation, no billing-address or VAT-ID collection, and no invoice** - it is a fast, low-setup way to take payment, suitable where you have **no tax-collection obligation** (or where you handle tax/invoicing entirely outside the plugin). It does **not** make you EU-VAT or GST compliant on its own.

> **Rule of thumb:** if you are registered for (or required to register for) VAT/GST on digital sales to consumers, route credit purchases through **WooCommerce**. Use the direct Stripe/PayPal gateways only where you have no tax obligation on the sale.

Automatic tax calculation and invoicing on the direct gateways (e.g. Stripe Tax) is on the roadmap; until then, the WooCommerce route is the supported way to stay compliant.

## Receipts and refunds (since 1.2.0)

After a successful credit purchase, users receive a receipt email with a summary of what they bought. You can also retrieve a receipt link from the admin: **Listora → Transactions → (transaction row)**.

Refunds can be issued from your payment provider (Stripe, PayPal, etc.) using the standard refund flow. The webhook receiver picks up the refund event, deducts the refunded credits from the user's balance, and rolls back any listing plan that was activated with those credits (the listing returns to a paused state until the user tops up again).

## Tips

- Create a free plan (0 credits) alongside paid plans - this lets listing owners submit basic listings without buying credits, then upgrade to paid plans for featured placement.
- Set `Duration (days)` to `0` for the free plan and a finite number (e.g., 30, 90, or 365) for paid plans. This creates a natural renewal cycle.
- The webhook system is idempotent - duplicate webhook calls (e.g., Stripe retries) will not double-credit a user. Since 1.1.0 the event is recorded before the credit is granted, replayed events are ignored, and a webhook with no transaction id can no longer double-credit. Refunds deduct the real refunded amount, and a refund that arrives after a plan has activated rolls that plan back.
- Sort plans by setting a low **Sort Order** number for the plan you want shown first.
- If you use the Wbcom Credits SDK alongside other Wbcom plugins, all credit balances are unified - users see a single balance across all products.

## Common issues

| Symptom | Fix |
|---------|-----|
| Plan step not appearing in submission form | Confirm at least one plan is published under **Listora → Pricing Plans** |
| Credits not added after payment | Check the webhook URL and secret are entered correctly in your payment platform |
| User sees "Not enough credits" on all plans | The user's balance is 0 - direct them to the credits purchase page |
| Plan duration not applying | Confirm **Duration (days)** is set to a non-zero value on the plan |

## Related features

- [Coupons](coupons.md)
- [Analytics](analytics.md)
- [License Management](../getting-started/pro-license.md)
