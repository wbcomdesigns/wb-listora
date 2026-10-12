---
journey: paused-listing-always-has-a-route-forward
plugin: wb-listora
priority: critical
roles: [member, other-member, anonymous, admin]
covers: [BC-10194590910, credits, pricing-plans, money-journey, dead-end]
prerequisites:
  - "Combo (Pro active), Pricing Plans (monetization) ON, one published plan with a credit cost (QA-J1 Standard, 10 credits)"
  - "A member whose balance already covers that plan"
estimated_runtime_minutes: 8
covers_card: 10194590910
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A vendor paused on credits is never stranded

A listing in `listora_payment` ("Paused - credits needed to activate") must always offer a real way forward, or say
honestly that there is none. The dashboard is `wb_listora_get_page_url('dashboard')` (on this site
`$SITE_URL/my-listings/`, tab `#listings`); the card is `.listora-dashboard__paused-note[data-listing-id]`.

Fixtures (all prefixed `QA-J1-`, deleted afterwards): member M with 25 credits; plan P (10 credits, 30 days, sort order set);
L1 = `listora_payment` with only `_listora_plan_id` = P; L2 = `listora_payment` with `_listora_pending_plan_id` = 999999 (plan gone);
L3 = `publish`; L4 = `listora_payment` with no plan meta; another member O (e.g. `subscriber2`).

## Steps

### 1. Enough credits: activate directly (member M, dashboard)
- **Expect** on L1's card: "Paused - credits needed to activate" (`.listora-dashboard__paused-title`), "Plan: QA-J1 Standard - costs 10
  credits to activate.", "Your balance: 25", no "Short by" row, "You already have enough credits for this plan. Activate it now - no
  further payment is needed.", and the button **Activate now** (`.listora-dashboard__paused-cta`, `data-wp-on--click="actions.activatePausedListing"`).
- **Action**: click **Activate now**.
- **Expect**: `POST /listora/v1/listings/{id}/activate-plan` returns 200 `{"activated":true,"status":"publish","message":"Listing activated."}`;
  listing `publish`, balance 15 (down by exactly 10), `_listora_pending_plan_id` and `_listora_pending_plan_failure` gone.
  **Zero console errors** (assert the console, not just the database).

### 2. Short of credits: the old path still works
- **Setup**: set P to 40 credits, listing with `_listora_pending_plan_id` = P, balance 15.
- **Expect**: card shows "Short by: 25", the "Top up credits and this listing activates automatically" copy, link **Buy 25 credits to resume**
  (to `...#credits`), and NO Activate now button. `activate-plan` returns an error and changes nothing (see step 5).
- **Action**: credit +30 (`Credit_System::add_credits`, as a top-up does).
- **Expect**: the listing flips to `publish` with no click; balance 15+30-40 = 5. (Verified 2026-10-12.)

### 3. Plan deleted: say so, offer no false hope
- **Expect** on L2's card: "The plan this listing was created with is no longer available, so it cannot activate on its own. Please
  contact the site owner - buying credits will not resolve this." No **Buy credits** button, no Activate now, no "activates automatically".

### 4. Both meta keys resume
- **Verify**: `Pricing_Plans::resolve_paused_plan_id( L1 )` (`wb-listora-pro/includes/features/class-pricing-plans.php:1576`) returns P although only
  `_listora_plan_id` is set; a top-up sweep (`auto_resume_pending_listings()`, `:1595`) resumes it.

### 5. The endpoint defends itself (role ladder)
`POST /listora/v1/listings/{id}/activate-plan` with the REST nonce:
| Caller / state | Expect (verified) |
|---|---|
| logged out | 401 `listora_not_logged_in` |
| another member (O) on L1 | 403 `listora_forbidden` "You do not have permission to activate this listing." |
| owner, L3 not paused | 409 `listora_not_paused` |
| owner, L4 no plan recorded | 409 `listora_no_plan` |
| owner, L2 plan gone | error `listora_plan_not_found`, no status change, no deduction (**currently HTTP 500, see product bug**) |
| owner, balance < cost | `listora_insufficient_credits`, no status change, no deduction (**currently HTTP 500**) |
| owner, L1 enough credits | 200 (control; admin as control also allowed) |

### 6. A paused listing with NO plan recorded (L4)
- **Expect (target)**: the card must not promise automatic activation, because nothing can activate it.
- **Observed 2026-10-12**: L4's card reads "Top up credits and this listing activates automatically - no further action needed." with a
  **Buy credits** button, which is the false-promise dead end. See PRODUCT BUG in the report (`templates/blocks/user-dashboard/tab-listings.php:353`).

### 7. Mobile (390)
- **Expect**: the card stacks, Activate now / Buy credits >= 40px tall, no horizontal page scroll.

## Test-data trap
A listing whose plan exists AND whose owner is short of credits exercises none of this. The states that broke are "plan gone",
"no plan" and "already has enough".

## Fail diagnostics
- Card shows no plan/cost: `templates/blocks/user-dashboard/tab-listings.php:349-356` (two meta keys read).
- Buy credits shown for a gone plan: `tab-listings.php:404` (`$plan_missing`) and `:424`.
- Activate button throws: `src/interactivity/store.js:1524` (`activatePausedListing`, REST call at `:1545`).
- Route behaviour: `wb-listora-pro/includes/features/class-pricing-plans.php:207` (route), `:244-285` (403/409), `:3701` and `:3859` (WP_Error without a status).

## Not verified
Mobile layout (390) and light/dark theme rendering were checked by DOM only, not in a browser.
