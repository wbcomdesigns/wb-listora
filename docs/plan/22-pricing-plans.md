# 22 — Pricing Plans (Pro)

## Scope: Pro Only

---

## Overview

Pricing plans let site owners monetize their directory. Listing owners choose a plan during submission, spend credits (see `21-payments.md` for credit system), and their listing gets plan-specific perks (duration, featured placement, image limits, analytics access).

**Plans cost CREDITS, not money.** The plugin never handles money directly — credits are added via webhooks from any payment system (Stripe, PayPal, WooCommerce, Razorpay, bank transfer, etc.).

---

## Plan Data Model

Plans stored as a custom post type: `listora_plan` (not publicly queryable, admin-only).

### Plan Fields

```
post_title             → "Premium"
post_content           → Plan description (shown to listing owners)
post_status            → "publish" or "draft"

Plan meta:
_listora_plan_credits       → 25 (credit cost, 0 = free plan)
_listora_plan_display_price → "$24.99" (display only — for UI, not used in logic)
_listora_plan_duration_days → 365 (how long listing stays active)
_listora_plan_features      → JSON: {
    "images_limit": 20,        // max gallery images (0 = unlimited)
    "is_featured": true,       // featured badge + priority sort
    "analytics_access": true,  // owner analytics dashboard
    "lead_form": true,         // contact owner form
    "video_embed": true,       // video on listing
    "social_links": true,      // social links display
    "priority_support": true   // support badge
}
_listora_plan_listing_types  → JSON: [] (empty = all types, or ["restaurant","hotel"])
_listora_plan_sort_order     → 2 (display order on pricing page)
_listora_plan_stripe_price   → "price_xxx" (Stripe Price ID, synced)
_listora_plan_paypal_plan    → "P-xxx" (PayPal Plan ID, synced)
_listora_plan_badge_text     → "Popular" (optional badge on plan card)
_listora_plan_badge_color    → "#E74C3C"
```

---

## Default Plans (Created on Pro Activation)

| Plan | Price | Interval | Duration | Features |
|------|-------|----------|----------|----------|
| Basic | Free | — | 30 days | Listed, 5 images |
| Standard | $9.99 | /month | 90 days | Listed, 20 images, badge |
| Premium | $24.99 | /month | 365 days | Featured, unlimited images, analytics, lead form |

Site owners customize these or create their own.

---

## Admin UI: Plan Manager

```
Listora → Pricing Plans

┌─────────────────────────────────────────────────────────────┐
│ Pricing Plans                                  [+ Add Plan] │
│                                                             │
│ | ≡ | Plan     | Price      | Duration | Featured | Status │
│ |---|----------|------------|----------|----------|--------|
│ | ≡ | Basic    | Free       | 30 days  | No       | Active │
│ | ≡ | Standard | $9.99/mo   | 90 days  | No       | Active │
│ | ≡ | Premium  | $24.99/mo  | 365 days | Yes      | Active │
│ | ≡ | Trial    | Free 14d   | 14 days  | No       | Draft  │
│                                                             │
│ Drag ≡ to reorder plans                                    │
└─────────────────────────────────────────────────────────────┘
```

### Plan Edit Screen

```
┌─────────────────────────────────────────────────────────────┐
│ Edit Plan: Premium                                          │
│                                                             │
│ Plan Name:       [ Premium                              ]   │
│ Description:     [ Get maximum visibility with featur...]   │
│                                                             │
│ ┌─ Pricing ──────────────────────────────────────────────┐  │
│ │ Price:         [ 24.99 ]                               │  │
│ │ Currency:      [ USD ▾ ]                               │  │
│ │ Billing:       (•) Monthly  ( ) Yearly  ( ) One-time   │  │
│ │ Free trial:    [ 0 ] days (0 = no trial)               │  │
│ └────────────────────────────────────────────────────────┘  │
│                                                             │
│ ┌─ Listing Duration ─────────────────────────────────────┐  │
│ │ Active for:    [ 365 ] days after approval             │  │
│ │ After expiry:  (•) Move to expired  ( ) Auto-renew     │  │
│ └────────────────────────────────────────────────────────┘  │
│                                                             │
│ ┌─ Plan Perks ───────────────────────────────────────────┐  │
│ │ Max images:    [ 0 ] (0 = unlimited)                   │  │
│ │ ☑ Featured badge + priority sorting                   │  │
│ │ ☑ Analytics dashboard access                          │  │
│ │ ☑ Lead form (contact owner)                           │  │
│ │ ☑ Video embed                                         │  │
│ │ ☐ Priority support badge                              │  │
│ └────────────────────────────────────────────────────────┘  │
│                                                             │
│ ┌─ Restrictions ─────────────────────────────────────────┐  │
│ │ Available for types: [ All Types ▾ ]                   │  │
│ │ Or specific: ☑ Restaurant ☑ Hotel ☐ Job              │  │
│ └────────────────────────────────────────────────────────┘  │
│                                                             │
│ ┌─ Display ──────────────────────────────────────────────┐  │
│ │ Badge text:    [ Most Popular ]                        │  │
│ │ Badge color:   [ #E74C3C ] [Pick]                      │  │
│ │ Sort order:    [ 3 ]                                   │  │
│ └────────────────────────────────────────────────────────┘  │
│                                                             │
│ ┌─ Gateway Sync ─────────────────────────────────────────┐  │
│ │ Stripe Price ID:  price_xxx (auto-synced)              │  │
│ │ PayPal Plan ID:   P-xxx (auto-synced)                  │  │
│ │ [Re-sync with Stripe]  [Re-sync with PayPal]           │  │
│ └────────────────────────────────────────────────────────┘  │
│                                                             │
│                                   [Save Plan] [Delete]      │
└─────────────────────────────────────────────────────────────┘
```

---

## Coupon Codes

### Coupon Data Model

Stored in `wp_options` as `wb_listora_coupons` (serialized array). For sites with many coupons, consider a custom table in future.

```json
{
  "LAUNCH50": {
    "code": "LAUNCH50",
    "type": "percentage",
    "amount": 50,
    "usage_limit": 100,
    "usage_count": 23,
    "expires_at": "2026-06-30",
    "plan_ids": [],
    "min_amount": 0,
    "one_per_user": true,
    "active": true
  },
  "FIRST10": {
    "code": "FIRST10",
    "type": "fixed",
    "amount": 10,
    "usage_limit": 0,
    "usage_count": 156,
    "expires_at": null,
    "plan_ids": [123, 456],
    "min_amount": 20,
    "one_per_user": true,
    "active": true
  }
}
```

### Coupon Types
| Type | Example | Behavior |
|------|---------|----------|
| `percentage` | 50% off | Reduces price by percentage |
| `fixed` | $10 off | Reduces price by fixed amount |
| `free_trial` | 30 days free | Extends trial period |

### Coupon UI (Checkout)
```
┌─────────────────────────────────────────┐
│ Have a coupon?                          │
│ [ LAUNCH50      ] [Apply]              │
│ ✓ Coupon applied: 50% off!             │
│                                         │
│ Package: Premium — $24.99/mo            │
│ Discount: -$12.50                       │
│ Total: $12.49/mo                        │
└─────────────────────────────────────────┘
```

### Admin: Coupon Management
```
Settings → Payments → Coupons

| Code     | Type       | Discount | Used | Limit | Expires    | ⋮  |
|----------|------------|----------|------|-------|------------|    |
| LAUNCH50 | 50% off    | 50%      | 23   | 100   | Jun 30     | ✎ |
| FIRST10  | $10 off    | $10      | 156  | ∞     | Never      | ✎ |

[+ Add Coupon]
```

---

## Tax & VAT Handling

### Settings
```
┌─────────────────────────────────────────────────────────────┐
│ Tax Settings                                                │
│                                                             │
│ Tax mode:                                                   │
│ ( ) No tax                                                  │
│ (•) Flat rate tax                                           │
│ ( ) VAT (European Union)                                    │
│                                                             │
│ ── Flat Rate ──                                             │
│ Tax rate: [ 10 ]%                                           │
│ Tax label: [ Tax ]                                          │
│                                                             │
│ ── VAT Mode ──                                              │
│ Your VAT number: [ DE123456789 ]                            │
│ Business country: [ Germany ▾ ]                             │
│ ☑ Validate customer VAT numbers (reverse charge for B2B)   │
│ ☑ Apply VAT based on customer country                      │
│                                                             │
│ VAT Rates (auto-populated for EU):                          │
│ | Country | Standard | Reduced |                            │
│ | DE      | 19%      | 7%      |                            │
│ | FR      | 20%      | 5.5%    |                            │
│ | ...     | ...      | ...     |                            │
│                                                             │
│ Prices include tax: (•) No (add on top)  ( ) Yes (inclusive)│
└─────────────────────────────────────────────────────────────┘
```

### VAT Logic
1. Customer selects country during checkout
2. If customer provides valid VAT number → reverse charge (0% VAT)
3. If customer is in same country as business → apply domestic rate
4. If customer is in different EU country → apply customer's country rate
5. VAT validation via VIES API (EU Commission)

---

## Refunds

### Admin: Issue Refund
From payment details or listing management:
```
┌─────────────────────────────────────────┐
│ Issue Refund                            │
│                                         │
│ Payment: #INV-2026-0042                 │
│ Amount paid: $24.99                     │
│                                         │
│ Refund amount: [ 24.99 ] (max: $24.99) │
│ Reason: [ Customer requested       ▾ ] │
│ Notes: [                            ]   │
│                                         │
│ ☑ Downgrade listing to Basic plan      │
│ ☐ Deactivate listing                   │
│                                         │
│ [Process Refund]                        │
└─────────────────────────────────────────┘
```

### Refund Flow
1. Admin clicks "Refund" on payment
2. Plugin calls gateway API to process refund (Stripe/PayPal)
3. Payment status → `refunded` or `partially_refunded`
4. Listing downgraded or deactivated based on admin choice
5. Email notification sent to listing owner
6. `refund_amount`, `refund_reason`, `refunded_at` updated in payments table

---

## Invoices

### Auto-Generated Invoice
On successful payment, generate invoice with:
- Sequential invoice number: `INV-2026-0001`
- Site owner business details (from settings)
- Customer billing details
- Plan name, price, tax breakdown
- Payment method and date
- Coupon discount (if applied)

### Invoice Display
- PDF download from user dashboard ("My Payments" → Download Invoice)
- PDF generated server-side using basic HTML → PDF (wp_mail compatible)
- Invoice template filterable: `apply_filters('wb_listora_invoice_template', $html, $payment)`

---

## Subscription Lifecycle

```
Create → Trial (optional) → Active → Payment Due → Paid → Active (renewed)
                                                      ↓
                                              Payment Failed → Grace (3 days)
                                                                    ↓
                                                              Retry Failed → Suspended
                                                                              ↓
                                                                        Cancelled → Listing Expired
```

### Dunning (Failed Payment Recovery)
1. Payment fails → listing stays active for 3-day grace period
2. Email: "Your payment failed, please update your card"
3. Day 1: Stripe/PayPal auto-retries
4. Day 3: Second email warning
5. Day 7: If still failed → subscription cancelled, listing → expired
6. Listing owner can re-subscribe at any time to reactivate

---

## REST API

```
GET    /listora/v1/plans                     → list active plans (public)
GET    /listora/v1/plans/{id}                → single plan details (public)
POST   /listora/v1/payments/checkout         → create checkout session (auth)
GET    /listora/v1/payments                  → user's payment history (auth)
GET    /listora/v1/payments/{id}             → single payment details (auth)
GET    /listora/v1/payments/{id}/invoice     → download invoice PDF (auth)
POST   /listora/v1/payments/{id}/refund      → issue refund (admin)
POST   /listora/v1/coupons/validate          → validate coupon code (auth)
POST   /listora/v1/webhooks/stripe           → Stripe webhook handler
POST   /listora/v1/webhooks/paypal           → PayPal webhook handler
```

---

## Claim + Upgrade Connection

When a listing owner claims a listing:
1. Basic claim (Free): submit proof → admin approves → owner gets edit access
2. Claim + upgrade (Pro): submit proof → admin approves → prompt to choose a paid plan → payment → listing gets plan perks

This is configured per-listing-type: `_listora_claim_requires_plan` (boolean).

---

## Free Plugin Behavior (No Pro)

When Pro is not active:
- All listings are free, no duration limits
- No plan selection step in submission form
- No payment step
- Listings never expire (unless admin manually sets expiration)
- `wb_listora_is_pro_active()` check hides plan/payment UI
