# 21 — Payments & Credit System (Pro)

## Scope: Pro Only

---

## Architecture: Webhook + Credits (Not Gateway Modules)

### The Problem With Gateway Modules
Every directory plugin builds Stripe integration, then PayPal, then adds Razorpay as an addon, then Mollie... This is:
- Addon bloat (the exact thing we're fighting against)
- Massive maintenance burden (each gateway has its own API, webhooks, edge cases)
- Excludes 70% of the world's payment methods
- Forces the plugin to handle PCI compliance, card forms, subscription billing

### Our Approach: Universal Webhook + Credit System

```
┌─────────────────┐     webhook      ┌──────────────────┐
│                 │ ──────────────→ │                  │
│  ANY Payment    │  "user X paid   │   WB Listora     │
│  System         │   $24.99"       │   Credit System  │
│                 │ ←────────────── │                  │
│  Stripe         │   "confirmed"   │   Adds credits   │
│  PayPal         │                 │   Activates plan │
│  WooCommerce    │                 │                  │
│  Razorpay       │                 │                  │
│  Bank Transfer  │                 │                  │
│  Manual/Admin   │                 │                  │
└─────────────────┘                 └──────────────────┘
```

**The plugin NEVER touches money.** It only knows: "User X has Y credits" and "Plan Z costs N credits."

---

## How It Works

### Step 1: Site Owner Configures Credits
```
Settings → Payments

Credit Value: 1 credit = [ $1.00 ] [ USD ▾ ]

Or: credits are abstract units (admin decides pricing externally)
```

### Step 2: Site Owner Sets Up Payment System (Outside Plugin)
They can use ANY of these — the plugin doesn't care:
- **Stripe Checkout** — create a payment link, paste URL
- **PayPal** — create a PayPal.me link or button
- **WooCommerce** — sell "credit packs" as WooCommerce products
- **Easy Digital Downloads** — same approach
- **Razorpay/Mollie/Square** — any gateway with webhook support
- **Bank Transfer** — admin manually adds credits
- **LemonSqueezy/Paddle** — modern checkout, webhook to plugin

### Step 3: Payment Happens (External)
User pays via whatever system the site owner chose. The payment system sends a webhook to:
```
POST https://site.com/wp-json/listora/v1/webhooks/payment
```

### Step 4: Plugin Receives Webhook, Adds Credits
Plugin validates the webhook, credits the user, activates the plan.

---

## Credit System

### User Credit Balance
```sql
-- Stored in usermeta (simple, WP-native)
_listora_credit_balance    → DECIMAL (current balance)
_listora_lifetime_credits  → DECIMAL (total ever purchased)
```

### Credit Transactions Log
```sql
CREATE TABLE {prefix}listora_credit_log (
    id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT(20) UNSIGNED NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    balance_after   DECIMAL(10,2) NOT NULL,
    type            VARCHAR(30) NOT NULL DEFAULT 'credit',
    reference_type  VARCHAR(30) DEFAULT NULL,
    reference_id    BIGINT(20) UNSIGNED DEFAULT NULL,
    description     VARCHAR(500) NOT NULL DEFAULT '',
    source          VARCHAR(50) NOT NULL DEFAULT 'webhook',
    gateway_txn_id  VARCHAR(255) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_type (type),
    KEY idx_created (created_at DESC),
    KEY idx_gateway (gateway_txn_id)
) {charset_collate};
```

**Transaction types:**
| Type | Description | Example |
|------|-------------|---------|
| `credit` | Credits added (purchase) | "+100 credits via Stripe" |
| `debit` | Credits spent (plan purchase) | "-25 credits for Premium plan" |
| `refund` | Credits returned | "+25 credits (refund for Premium)" |
| `admin_add` | Admin manually added | "+50 credits by Admin" |
| `admin_remove` | Admin manually removed | "-10 credits by Admin" |
| `expire` | Credits expired (if expiry enabled) | "-5 credits expired" |
| `bonus` | Promotional credits | "+20 bonus credits (welcome offer)" |

---

## Webhook Endpoint

### Endpoint
```
POST /wp-json/listora/v1/webhooks/payment
```

### Payload Format (Standard)
```json
{
  "event": "payment.completed",
  "user_email": "john@example.com",
  "amount": 24.99,
  "currency": "USD",
  "credits": 25,
  "transaction_id": "txn_abc123",
  "gateway": "stripe",
  "plan_slug": "premium",
  "metadata": {
    "listing_id": 123,
    "coupon": "LAUNCH50"
  }
}
```

### Authentication
Two options (site owner configures one):

**Option A: Shared Secret (Simple)**
```
Header: X-Listora-Webhook-Secret: whsec_xxxxxxxxxxxx
```
Plugin validates: `hash_equals($stored_secret, $received_secret)`

**Option B: HMAC Signature (Secure)**
```
Header: X-Listora-Signature: sha256=xxxxxxxxxx
```
Plugin validates: `hash_equals(hash_hmac('sha256', $payload, $secret), $signature)`

### Webhook Processing
```php
1. Validate authentication (secret or HMAC)
2. Check transaction_id not already processed (idempotency)
3. Find user by email (or create if auto-registration enabled)
4. If "credits" field: add credits to user balance
5. If "plan_slug" field: activate plan for user's listing
6. Log transaction in credit_log
7. Fire action: do_action('wb_listora_payment_received', $user_id, $data)
8. Return 200 OK
```

### Idempotency
The `gateway_txn_id` column prevents duplicate processing. If the same `transaction_id` arrives twice, second request returns `200 OK` without adding credits again.

---

## Plan Purchase Flow (User Journey)

### Frontend: Listing Submission

**Step 5: Choose Plan**
```
┌─────────────────────────────────────────────────────┐
│ Choose Your Listing Package                         │
│                                                     │
│ ┌──────────┐  ┌──────────┐  ┌──────────┐          │
│ │ Basic    │  │ Standard │  │ Premium  │          │
│ │          │  │          │  │          │          │
│ │  FREE    │  │ 10 credits│ │ 25 credits│         │
│ │  0 cr.   │  │  ($9.99) │  │ ($24.99) │          │
│ │          │  │          │  │          │          │
│ │ ✓ Listed│  │ ✓ Listed│  │ ✓ Listed│          │
│ │ 30 days │  │ 90 days │  │ 365 days│          │
│ │ 5 photos│  │ 20 photos│  │ Unlmtd  │          │
│ │         │  │ ✓ Badge │  │ ✓ Badge │          │
│ │         │  │          │  │ ✓ Top   │          │
│ │         │  │          │  │ ✓ Stats │          │
│ │[Select] │  │[Select]  │  │[Select]  │          │
│ └──────────┘  └──────────┘  └──────────┘          │
│                                                     │
│ Your balance: 30 credits                            │
│ [← Back]                            [Continue →]    │
└─────────────────────────────────────────────────────┘
```

**If user has enough credits:**
```
Step 6: Confirm
"You're about to spend 25 credits for the Premium plan."
"Balance after: 5 credits"
[Confirm & Publish]
```

Credits deducted → listing published → done. No external redirect needed.

**If user has insufficient credits:**
```
┌─────────────────────────────────────────────────────┐
│ You need 25 credits but have 5.                     │
│                                                     │
│ Buy credits:                                        │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│ │ 10 cr.   │ │ 25 cr.   │ │ 50 cr.   │            │
│ │  $9.99   │ │ $24.99   │ │ $44.99   │            │
│ │ [Buy →]  │ │ [Buy →]  │ │ [Buy →]  │            │
│ └──────────┘ └──────────┘ └──────────┘            │
│                                                     │
│ Clicking "Buy" will open the payment page.          │
│ After payment, return here to continue.             │
└─────────────────────────────────────────────────────┘
```

"Buy" button links to the external payment page (Stripe Checkout, PayPal, WooCommerce product, etc.) configured by site owner.

After payment → webhook fires → credits added → user refreshes or returns → credits available → purchase plan → listing published.

---

## Admin: Credit Management

### Settings → Payments
```
┌─────────────────────────────────────────────────────┐
│ Credit System                                       │
│                                                     │
│ ☑ Enable paid listings (credit system)             │
│                                                     │
│ Credit Value: 1 credit = [ 1.00 ] [ USD ▾ ]        │
│ (Display only — actual pricing set in your          │
│  payment system)                                    │
│                                                     │
│ ┌─ Credit Packs (shown to users) ───────────────┐  │
│ │ | Pack    | Credits | Display Price | Buy URL  │  │
│ │ |---------|---------|---------------|----------|  │
│ │ | Small   | 10      | $9.99         | [URL]    │  │
│ │ | Medium  | 25      | $24.99        | [URL]    │  │
│ │ | Large   | 50      | $44.99        | [URL]    │  │
│ │ | [+ Add Pack]                                 │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ "Buy URL" can be:                                   │
│  - Stripe Checkout link                             │
│  - PayPal payment link                              │
│  - WooCommerce product URL                          │
│  - Any payment page URL                             │
│                                                     │
│ ┌─ Webhook Settings ────────────────────────────┐   │
│ │ Webhook URL (give this to your payment system):│   │
│ │ https://site.com/wp-json/listora/v1/webhooks/ │   │
│ │ payment                            [📋 Copy]   │   │
│ │                                                │   │
│ │ Webhook Secret:                                │   │
│ │ [ whsec_xxxxxxxxxxxxxxxx ] [Regenerate]        │   │
│ │                                                │   │
│ │ Auth mode: (•) Shared secret  ( ) HMAC-SHA256  │   │
│ └────────────────────────────────────────────────┘   │
│                                                     │
│ ┌─ Quick Setup Guides ──────────────────────────┐   │
│ │ Connect your payment system:                   │   │
│ │ [Stripe ↗] [PayPal ↗] [WooCommerce ↗]        │   │
│ │ [Razorpay ↗] [LemonSqueezy ↗] [Manual ↗]     │   │
│ │                                                │   │
│ │ Each link opens a step-by-step guide for       │   │
│ │ connecting that payment system to the webhook. │   │
│ └────────────────────────────────────────────────┘   │
│                                                     │
│                                      [Save Changes] │
└─────────────────────────────────────────────────────┘
```

### Admin: Manage User Credits
```
Users → Edit User → Listora Credits

Current Balance: 45 credits
Lifetime Credits: 120 credits

[+ Add Credits]  [− Remove Credits]

Transaction History:
| Date       | Type   | Amount | Balance | Description              |
|------------|--------|--------|---------|--------------------------|
| Mar 15     | credit | +25    | 45      | Stripe payment txn_abc   |
| Mar 10     | debit  | -25    | 20      | Premium plan for #123    |
| Mar 1      | credit | +50    | 45      | PayPal payment pp_xyz    |
| Feb 15     | bonus  | +20    | -5      | Welcome bonus            |
```

### Admin: Quick Add Credits (No Payment)
For manual payments (bank transfer, cash, etc.):
```
┌──────────────────────────────────┐
│ Add Credits Manually             │
│                                  │
│ User:    [ john@example.com  ▾ ] │
│ Credits: [ 25 ]                  │
│ Reason:  [ Bank transfer ref#42] │
│                                  │
│ [Add Credits]                    │
└──────────────────────────────────┘
```

---

## Gateway Quick Setup Guides

The plugin doesn't BUILD gateway integrations. It provides DOCUMENTATION for connecting each one. These are in-admin help pages or links to wblistora.com/docs.

### Stripe (Simplest)
```
1. Create a Stripe Payment Link for each credit pack
   - Product: "25 Directory Credits"
   - Price: $24.99
   - Payment Link URL: https://buy.stripe.com/xxx

2. Paste the Payment Link URL as the "Buy URL" for that credit pack

3. In Stripe Dashboard → Webhooks:
   - Endpoint URL: [your webhook URL from settings]
   - Events: checkout.session.completed
   - Signing secret: [paste into Listora webhook secret]

4. Done! When someone buys credits via Stripe,
   the webhook fires and credits are added automatically.
```

### WooCommerce
```
1. Create WooCommerce products for each credit pack
   - Product: "25 Directory Credits" — $24.99
   - Product: "50 Directory Credits" — $44.99

2. Use "Buy URL" = the WooCommerce product URL

3. Install "WooCommerce Webhooks" (built-in):
   - Topic: Order completed
   - Delivery URL: [your webhook URL]
   - Secret: [your webhook secret]

4. Map WooCommerce product → credit amount in Listora settings

5. Done! Any WooCommerce payment gateway now works:
   Stripe, PayPal, Razorpay, Mollie, Square, bank transfer...
```

### PayPal
```
1. Create a PayPal payment button/link for each credit pack
2. Use PayPal IPN or Webhooks:
   - Notification URL: [your webhook URL]
3. PayPal sends payment notification → credits added
```

### Manual / Bank Transfer
```
1. User requests credits via contact form or dashboard
2. User pays via bank transfer, Venmo, cash, etc.
3. Admin goes to Users → Edit User → Add Credits manually
4. Credits appear in user's balance immediately
```

---

## Coupon / Promo Codes

Coupons reduce the CREDIT COST of a plan, not the payment amount (since the plugin doesn't handle money).

```
Coupon: LAUNCH50
Effect: 50% off plan credit cost
Plan "Premium" normally costs 25 credits
With coupon: costs 13 credits
```

### Coupon Storage
```json
// Stored in option: wb_listora_coupons
{
  "LAUNCH50": {
    "code": "LAUNCH50",
    "type": "percentage",
    "amount": 50,
    "usage_limit": 100,
    "usage_count": 23,
    "expires_at": "2026-06-30",
    "plan_ids": [],
    "one_per_user": true,
    "active": true
  }
}
```

### Coupon UI (at plan selection)
```
Have a coupon? [ LAUNCH50 ] [Apply]
✓ 50% off! Premium: 25 → 13 credits
```

---

## Subscription / Recurring Billing

The plugin does NOT handle recurring billing internally. Instead:

**Option A: Credit packs (simplest)**
- User buys 50 credits
- Uses them over time on plans
- When credits run low, buys more
- No subscription needed

**Option B: External subscription → webhook**
- Stripe/PayPal subscription charges monthly
- Each successful charge → webhook fires → credits added
- If payment fails → no webhook → no credits → listing expires naturally
- No dunning logic needed in the plugin

This is MUCH simpler than building subscription lifecycle management.

---

## Plan Activation

When user has credits and selects a plan:

```php
function activate_plan($user_id, $plan_id, $listing_id, $coupon_code = null) {
    $plan = get_post($plan_id);
    $cost = get_post_meta($plan_id, '_listora_plan_credits', true);

    // Apply coupon
    if ($coupon_code) {
        $cost = apply_coupon($cost, $coupon_code);
    }

    // Check balance
    $balance = get_user_meta($user_id, '_listora_credit_balance', true);
    if ($balance < $cost) {
        return new WP_Error('insufficient_credits', 'Not enough credits');
    }

    // Deduct credits
    update_user_meta($user_id, '_listora_credit_balance', $balance - $cost);

    // Log transaction
    log_credit_transaction($user_id, -$cost, 'debit', 'plan', $plan_id, "Plan: {$plan->post_title}");

    // Activate plan on listing
    update_post_meta($listing_id, '_listora_plan_id', $plan_id);
    update_post_meta($listing_id, '_listora_plan_activated', current_time('mysql'));

    $duration = get_post_meta($plan_id, '_listora_plan_duration_days', true);
    if ($duration > 0) {
        $expiry = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
        update_post_meta($listing_id, '_listora_expiration_date', $expiry);
    }

    // Apply plan perks
    if (get_post_meta($plan_id, '_listora_plan_featured', true)) {
        update_post_meta($listing_id, '_listora_is_featured', 1);
    }

    // Publish listing
    wp_update_post(['ID' => $listing_id, 'post_status' => 'publish']);

    do_action('wb_listora_plan_activated', $listing_id, $plan_id, $user_id);
}
```

---

## REST API

```
GET    /listora/v1/credits                   → user's credit balance + history (auth)
POST   /listora/v1/credits/purchase-plan     → spend credits to activate plan (auth)
POST   /listora/v1/credits/admin-add         → admin add credits to user (admin)
POST   /listora/v1/coupons/validate          → validate coupon code (auth)
GET    /listora/v1/credit-packs              → available credit packs with buy URLs (public)
POST   /listora/v1/webhooks/payment          → webhook receiver (external)
```

---

## Why This Is Better

| Aspect | Gateway Modules (Old) | Webhook + Credits (New) |
|--------|----------------------|------------------------|
| Gateways supported | 2-3 (Stripe, PayPal) | Unlimited (any with webhooks) |
| Code to maintain | 2-3 gateway classes | 1 webhook receiver |
| PCI compliance | Our problem | Payment system's problem |
| Subscription billing | We build it | Payment system handles it |
| Card forms | We render them | Payment system renders them |
| New country/currency | Need new gateway | Already works |
| WooCommerce compatible | No (we replaced it) | Yes (via webhook) |
| Bank transfers | Not possible | Admin adds credits manually |
| Failed payment handling | We build dunning | External handles retries |
| Security surface | Card data touches our code | Only webhook secret touches our code |
| Time to implement | 3-4 weeks | 1 week |

---

## Free Plugin Behavior

When Pro is not active:
- No credit system
- No plan selection step
- All listings are free, no payment
- Listings never expire (unless admin sets expiration)
- Zero payment-related UI visible
