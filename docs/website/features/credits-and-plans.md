# Credits and Pricing Plans

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md). Free sites can use listing limits per role without a credit system.

## What it does

WB Listora Pro includes a credit-based payment system. Users purchase credits (via your payment provider of choice), and spend those credits to activate listing plans. Each plan determines how long a listing stays active, whether it gets featured placement, and what perks it includes.

## Why you'd use it

- Monetize your directory without a WooCommerce store — credits work with any payment gateway via webhook.
- Pricing plans give you flexible packaging: a free basic plan, a paid featured plan, and a premium plan can all coexist.
- Credits are reusable — users can top up once and submit multiple listings over time.
- The webhook-based topup system is payment-processor-agnostic: Stripe, PayPal, Paddle, or any custom solution works.

## How to use it

### For site owners (admin steps)

**Step 1: Set up the webhook**

1. Go to **Listora → Settings → Pro** and scroll to the **Credit System** section.
2. Copy the **Webhook URL** and **Webhook Secret**.
3. In your payment platform (e.g., Stripe), create a webhook that fires on payment success and posts to that URL. Set the webhook secret as the HMAC key.
4. When a payment succeeds, the webhook credits the purchasing user automatically.

**Step 2: Configure the credits page**

1. Create a page on your site where users can purchase credits (e.g., an embedded payment form or a link to your payment platform).
2. Go to **Listora → Settings → Pro → Credit System** and set **Credits Page** to that page.
3. This page URL is used for "Buy Credits" links throughout the plugin (e.g., when a user can't afford a plan).

**Step 3: Create pricing plans**

1. Go to **Listora → Pricing Plans → Add New Plan**.
2. Fill in the plan settings:
   - **Plan title** — the name shown to users (e.g., "Basic", "Featured", "Premium").
   - **Plan Price (credits)** — credits required to purchase this plan. Set to `0` for a free plan.
   - **Credit Cost** — credits deducted per listing submission on this plan.
   - **Display Price** — optional label shown to users (e.g., "$29/month"). This is for display only; actual charging happens via your webhook.
   - **Duration (days)** — how long the listing stays active. Set to `0` for permanent listings.
   - **Featured Plan** — tick this to highlight the plan as recommended in the plan selection step.
   - **Badge Text** — optional label on the plan card (e.g., "Most Popular", "Best Value").
   - **Plan Perks** — checkboxes for: Mark listing as Featured, Priority support, Analytics dashboard access.
3. Publish the plan.
4. Repeat for each plan you want to offer.

**Step 4: Verify the plan selection step**

When a user submits a new listing, a **Choose a Plan** step appears in the submission form showing all published plans. Plans the user can't afford are greyed out with a "Buy Credits" link.

**Adding credits manually:**

Go to **Users → Edit User** and use the **Listora Credits** panel to add credits directly without a payment. Useful for comping credits to early adopters or resolving disputes.

### For end users (visitor/user-facing)

1. Go to the credits purchase page to buy credits.
2. When submitting a listing, the **Choose a Plan** step shows all available plans with their credit cost, duration, and perks.
3. Select a plan. If you have a coupon code, enter it in the coupon field — the credit cost adjusts immediately.
4. Your credit balance is shown on the plan selection screen. After submitting, the credit cost is deducted from your balance.
5. View your current balance and transaction history in **User Dashboard → Credits**.

## Tips

- Create a free plan (0 credits) alongside paid plans — this lets listing owners submit basic listings without buying credits, then upgrade to paid plans for featured placement.
- Set `Duration (days)` to `0` for the free plan and a finite number (e.g., 30, 90, or 365) for paid plans. This creates a natural renewal cycle.
- The webhook system is idempotent — duplicate webhook calls (e.g., Stripe retries) will not double-credit a user.
- Sort plans by setting a low **Sort Order** number for the plan you want shown first.
- If you use the Wbcom Credits SDK alongside other Wbcom plugins, all credit balances are unified — users see a single balance across all products.

## Common issues

| Symptom | Fix |
|---------|-----|
| Plan step not appearing in submission form | Confirm at least one plan is published under **Listora → Pricing Plans** |
| Credits not added after payment | Check the webhook URL and secret are entered correctly in your payment platform |
| User sees "Not enough credits" on all plans | The user's balance is 0 — direct them to the credits purchase page |
| Plan duration not applying | Confirm **Duration (days)** is set to a non-zero value on the plan |

## Related features

- [Coupons](coupons.md)
- [Analytics](analytics.md)
- [License Management](../getting-started/pro-license.md)
