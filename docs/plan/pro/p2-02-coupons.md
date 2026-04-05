# P2-02 — Coupon Codes / Promo Pricing

## Scope: Pro Only

---

## Overview

A full coupon system operating at the Listora credit level — completely independent of whatever payment provider the site owner uses. Coupons reduce the credit cost of a listing plan, not the dollar amount. This means coupons work identically whether credits are purchased via Stripe, PayPal, WooCommerce, Razorpay, bank transfer, or any other method.

### Why It Matters

- Directory owners need promotional tools to drive initial adoption ("First 50 listings free")
- Agencies want to offer discount codes to their clients' users
- Seasonal promotions ("Summer Special: 50% off Premium plans") increase conversion
- Partner referral programs need trackable codes
- Credit-level discounts mean the same coupon works regardless of payment gateway

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Site owner | Create a "LAUNCH50" coupon for 50% off all plans | I can promote my new directory and attract early listing owners |
| 2 | Site owner | Set a per-user limit of 1 on a coupon | Each user can only use the discount once |
| 3 | Site owner | Create a fixed-credit coupon for 10 free credits | I can give partners a set bonus regardless of plan chosen |
| 4 | Listing owner | Enter a coupon code during plan selection | I get a discount on my listing plan |
| 5 | Site owner | See how many times each coupon was used | I can measure the effectiveness of my promotions |
| 6 | Admin | Set a minimum plan requirement for a coupon | The "GOLDONLY" code only works on Gold/Premium plans |
| 7 | Site owner | Set an expiry date on a coupon | Time-limited promotions end automatically |

---

## Technical Design

### Data Model

#### Coupon Entity

Stored as custom post type `listora_coupon` (not publicly queryable, admin-only).

```
post_title             -> "LAUNCH50"  (the code itself, uppercase)
post_status            -> "publish" (active) or "draft" (inactive)
post_content           -> "Launch promotion — 50% off all plans" (admin notes)

Meta:
_listora_coupon_code          -> "LAUNCH50" (canonical, uppercase, indexed)
_listora_coupon_type          -> "percentage" | "fixed_credits"
_listora_coupon_value         -> 50 (percentage) or 10 (fixed credits)
_listora_coupon_usage_limit   -> 100 (total uses, 0 = unlimited)
_listora_coupon_usage_count   -> 47 (current usage count)
_listora_coupon_per_user      -> 1 (max uses per user, 0 = unlimited)
_listora_coupon_expiry        -> "2026-06-30" (ISO date, empty = never expires)
_listora_coupon_min_plan      -> "premium" (plan slug, empty = any plan)
_listora_coupon_min_credits   -> 0 (minimum plan credit cost to qualify)
_listora_coupon_plan_restrict -> JSON: ["premium","gold"] (restrict to specific plans, empty = all)
_listora_coupon_created_by    -> 1 (user ID)
```

#### Coupon Usage Log

```sql
CREATE TABLE {prefix}listora_coupon_usage (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id    BIGINT(20) UNSIGNED NOT NULL,
    user_id      BIGINT(20) UNSIGNED NOT NULL,
    plan_id      BIGINT(20) UNSIGNED DEFAULT NULL,
    listing_id   BIGINT(20) UNSIGNED DEFAULT NULL,
    discount     DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_coupon (coupon_id),
    KEY idx_user (user_id),
    KEY idx_coupon_user (coupon_id, user_id)
) {charset_collate};
```

### Validation Logic

```php
function validate_coupon( string $code, int $plan_id, int $user_id ): WP_Error|array {
    // 1. Find coupon by code (case-insensitive lookup)
    $coupon = get_coupon_by_code( strtoupper( $code ) );
    if ( ! $coupon ) return new WP_Error( 'invalid_code', 'Coupon code not found.' );

    // 2. Check status
    if ( $coupon->post_status !== 'publish' ) return new WP_Error( 'inactive', 'This coupon is no longer active.' );

    // 3. Check expiry
    $expiry = get_post_meta( $coupon->ID, '_listora_coupon_expiry', true );
    if ( $expiry && strtotime( $expiry ) < time() ) return new WP_Error( 'expired', 'This coupon has expired.' );

    // 4. Check global usage limit
    $limit = (int) get_post_meta( $coupon->ID, '_listora_coupon_usage_limit', true );
    $count = (int) get_post_meta( $coupon->ID, '_listora_coupon_usage_count', true );
    if ( $limit > 0 && $count >= $limit ) return new WP_Error( 'limit_reached', 'This coupon has reached its usage limit.' );

    // 5. Check per-user limit
    $per_user = (int) get_post_meta( $coupon->ID, '_listora_coupon_per_user', true );
    if ( $per_user > 0 ) {
        $user_uses = $wpdb->get_var( ... ); // COUNT from coupon_usage WHERE coupon_id AND user_id
        if ( $user_uses >= $per_user ) return new WP_Error( 'user_limit', 'You have already used this coupon.' );
    }

    // 6. Check plan restriction
    $plan_restrict = json_decode( get_post_meta( $coupon->ID, '_listora_coupon_plan_restrict', true ), true );
    if ( ! empty( $plan_restrict ) ) {
        $plan_slug = get_post_field( 'post_name', $plan_id );
        if ( ! in_array( $plan_slug, $plan_restrict, true ) ) {
            return new WP_Error( 'plan_mismatch', 'This coupon is not valid for the selected plan.' );
        }
    }

    // 7. Check minimum credit cost
    $min_credits = (int) get_post_meta( $coupon->ID, '_listora_coupon_min_credits', true );
    $plan_cost = (int) get_post_meta( $plan_id, '_listora_plan_credits', true );
    if ( $min_credits > 0 && $plan_cost < $min_credits ) {
        return new WP_Error( 'min_not_met', 'This coupon requires a plan costing at least ' . $min_credits . ' credits.' );
    }

    // 8. Calculate discount
    $type  = get_post_meta( $coupon->ID, '_listora_coupon_type', true );
    $value = (float) get_post_meta( $coupon->ID, '_listora_coupon_value', true );

    if ( $type === 'percentage' ) {
        $discount = floor( $plan_cost * ( $value / 100 ) );
    } else {
        $discount = min( $value, $plan_cost ); // fixed credits, never more than plan cost
    }

    return [
        'valid'          => true,
        'coupon_id'      => $coupon->ID,
        'code'           => $code,
        'type'           => $type,
        'value'          => $value,
        'discount'       => $discount,
        'original_cost'  => $plan_cost,
        'final_cost'     => max( 0, $plan_cost - $discount ),
    ];
}
```

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/coupons/class-coupon-manager.php` | Validation, application, usage tracking |
| `includes/coupons/class-coupon-post-type.php` | CPT registration |
| `includes/rest/class-coupons-controller.php` | REST endpoints for CRUD + validate |
| `includes/admin/class-coupons-page.php` | Admin list + create/edit page (Pattern B) |

### Files to Modify (wb-listora-pro)

| File | Change |
|------|--------|
| `includes/rest/class-submission-controller.php` (Pro override) | Add coupon validation to plan selection step |
| `blocks/listing-submission/view.js` (Pro extension) | Add coupon code input field in plan selection UI |

### API Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `GET` | `/listora/v1/coupons` | Admin | List all coupons |
| `POST` | `/listora/v1/coupons` | Admin | Create coupon |
| `GET` | `/listora/v1/coupons/{id}` | Admin | Get single coupon |
| `PUT` | `/listora/v1/coupons/{id}` | Admin | Update coupon |
| `DELETE` | `/listora/v1/coupons/{id}` | Admin | Delete coupon |
| `POST` | `/listora/v1/coupons/validate` | Authenticated | Validate code against plan |

#### Validate Request/Response

```
POST /listora/v1/coupons/validate
Body: { "code": "LAUNCH50", "plan_id": 42 }

Response (success):
{
  "valid": true,
  "code": "LAUNCH50",
  "type": "percentage",
  "value": 50,
  "discount": 12,
  "original_cost": 25,
  "final_cost": 13,
  "message": "Coupon applied! 50% off (12 credits saved)"
}

Response (failure):
{
  "valid": false,
  "code": "error_key",
  "message": "This coupon has expired."
}
```

---

## UI Mockup

### Admin: Coupon List Page (Listora > Coupons)

```
┌─────────────────────────────────────────────────────────────┐
│ Coupons                                     [+ Add Coupon]  │
│                                                             │
│ | Code       | Type       | Value | Used   | Exp.   | St.  │
│ |------------|------------|-------|--------|--------|------│
│ | LAUNCH50   | Percentage | 50%   | 47/100 | Jun 30 | ●    │
│ | PARTNER10  | Fixed      | 10 cr | 12/∞   | —      | ●    │
│ | SUMMER25   | Percentage | 25%   | 0/50   | Aug 31 | ●    │
│ | EXPIRED01  | Fixed      | 5 cr  | 23/25  | Mar 01 | ○    │
│                                                             │
│ ● Active  ○ Expired/Inactive                                │
│                                                             │
│ Showing 4 coupons                                           │
└─────────────────────────────────────────────────────────────┘
```

### Admin: Create/Edit Coupon

```
┌─────────────────────────────────────────────────────────────┐
│ Add Coupon                                                  │
│                                                             │
│ Coupon Code *                                               │
│ [ LAUNCH50                        ]  [Generate Random]      │
│                                                             │
│ Discount Type                                               │
│ (●) Percentage off credits                                  │
│ ( ) Fixed credit amount                                     │
│                                                             │
│ Discount Value *                                            │
│ [ 50 ] %                                                    │
│                                                             │
│ ── Restrictions ──────────────────────────────────────────── │
│                                                             │
│ Usage Limit (total)                                         │
│ [ 100     ] (0 = unlimited)                                 │
│                                                             │
│ Per-User Limit                                              │
│ [ 1       ] (0 = unlimited)                                 │
│                                                             │
│ Expiry Date                                                 │
│ [ 2026-06-30 ]  (leave empty = never expires)               │
│                                                             │
│ Restrict to Plans                                           │
│ ☐ Basic   ☑ Standard   ☑ Premium   ☐ Gold                 │
│ (unchecked = all plans eligible)                            │
│                                                             │
│ Minimum Plan Cost                                           │
│ [ 0       ] credits (0 = no minimum)                        │
│                                                             │
│ Admin Notes                                                 │
│ ┌───────────────────────────────────────────────────────┐   │
│ │ Launch promotion for Q2. Shared via partner email.   │   │
│ └───────────────────────────────────────────────────────┘   │
│                                                             │
│                                        [Cancel]  [Save]     │
└─────────────────────────────────────────────────────────────┘
```

### Frontend: Coupon Input in Plan Selection Step

```
┌─────────────────────────────────────────────────────────────┐
│ Choose Your Listing Package                                 │
│                                                             │
│  ┌──────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │  Basic   │  │  Standard    │  │  ● Premium   │         │
│  │  Free    │  │  10 credits  │  │  25 credits  │         │
│  │  30 days │  │  90 days     │  │  365 days    │         │
│  └──────────┘  └──────────────┘  └──────────────┘         │
│                                                             │
│ Have a coupon code?                                         │
│ [ LAUNCH50          ]  [Apply]                              │
│                                                             │
│ ✓ Coupon applied! 50% off (12 credits saved)               │
│                                                             │
│ Plan cost:     25 credits                                   │
│ Discount:     -12 credits (LAUNCH50)                        │
│ ─────────────────────                                       │
│ Total:         13 credits                                   │
│                                                             │
│ Your balance:  30 credits                                   │
│                                                             │
│                                          [Continue →]       │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Register `listora_coupon` CPT + meta fields | 2 |
| 2 | Create `listora_coupon_usage` table + migration | 1 |
| 3 | Build `Coupon_Manager` — validation logic (all 8 checks) | 4 |
| 4 | Build REST controller — CRUD + validate endpoint | 4 |
| 5 | Build admin list page (Pattern B) | 3 |
| 6 | Build admin create/edit form with all fields | 3 |
| 7 | Frontend coupon input in plan selection step (Interactivity API) | 4 |
| 8 | Real-time validation UX (apply button, success/error message, price update) | 3 |
| 9 | Usage tracking — increment on plan purchase, record in usage log | 2 |
| 10 | Random code generator utility | 0.5 |
| 11 | Integration with plan purchase flow (deduct discounted cost) | 2 |
| 12 | Audit log integration (log coupon_created, coupon_used events) | 1 |
| 13 | Automated tests (validation edge cases, usage limits, expiry) | 3 |
| 14 | Documentation | 1.5 |
| **Total** | | **34 hours** |

---

## Competitive Context

| Competitor | Coupons? | Our Advantage |
|-----------|---------|---------------|
| GeoDirectory | Basic coupon addon ($29) | Included in Pro, credit-level (gateway-independent) |
| Directorist | No native coupons | Full coupon system with per-user limits |
| HivePress | No | Works with any payment provider via credit system |
| ListingPro | Basic discount codes | More validation rules, usage tracking, plan restrictions |
| MyListing | WooCommerce coupons only | Independent of WooCommerce, works with any gateway |

**Our edge:** Credit-level coupons are payment-gateway agnostic. Whether the user bought credits via Stripe, PayPal, or bank transfer, the coupon reduces the credit cost uniformly. Competitors that tie coupons to WooCommerce or a specific gateway limit flexibility. Our per-user limits, plan restrictions, and usage log provide enterprise-grade promotional tools.

---

## Effort Estimate

**Total: ~34 hours (4-5 dev days)**

- Data model + CPT: 3h
- Validation engine: 4h
- REST API: 4h
- Admin UI: 6h
- Frontend integration: 7h
- Usage tracking: 3h
- Tests + docs: 4.5h
- QA + edge cases: 2.5h
