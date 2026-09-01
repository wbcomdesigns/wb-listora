---
journey: buy-credits-keeps-the-draft
plugin: wb-listora
priority: critical
roles: [member]
covers: []
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Pro active, Monetization feature ON"
  - "At least one plan costing more credits than the test member holds"
estimated_runtime_minutes: 10
---

# Going to buy credits does not throw the listing away

A member filled in a whole listing, reached the plan step, found they were short
of credits, and followed **Buy Credits**. The wizard keeps its state nowhere but
the DOM, so every field they had typed was gone — and they had to re-enter the
listing from scratch in order to spend the credits they had just bought.

Leaving the page is not avoidable: a real gateway redirects off-site. So the
contract is not "never navigate", it is **the trip must be survivable**.

Four defects had to be fixed before that was possible, and each is asserted here
separately, because any one of them regressing silently restores the data loss:

1. The ToS gate applied to draft saves. The checkbox is on the wizard's LAST
   step, so every autosave fired while the member was still typing returned 400
   into an empty `catch` — the draft feature was dead for the whole wizard.
2. Drafts were exempted, but the create path stamped `_listora_terms_accepted`
   unconditionally, recording consent nobody gave — and the preview step reads
   that meta to PRE-TICK the box.
3. The client discarded the saved draft's ID, so the second save posted as a new
   listing and was rejected as a duplicate. Only the FIRST save ever landed while
   the indicator kept saying "Draft saved".
4. `agree_terms` carried `'default' => false` on the `/submit` route, so
   `get_param()` never returned null and `check_terms_acceptance()`'s
   `$default_value` was dead — the "an edit is not a fresh acceptance" rule it
   documents never applied, and edits were refused unless they re-sent consent.

## Setup

- Test member: any member whose balance is BELOW the cost of at least one plan.
  ```bash
  wp eval 'echo \Wbcom\Credits\Credits::balance_money("wb-listora", USER_ID);'
  ```
  To lower a balance, hold THEN deduct — a bare `deduct_money()` routes through
  `deduct_with_hold_release()`, which writes a release for a hold that was never
  placed and nets to zero. That is API misuse, not a product bug; do not file it.
- Clear the submission rate limiter if you have been probing:
  ```bash
  wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%transient%listora%rate%\"");'
  ```

## Steps

### 1. The gate matrix still holds

- **Action**: post to `/listora/v1/submit` as the member, in each configuration.
- **Expect**, exactly:

  | Request | Result |
  |---|---|
  | `status=draft`, no `agree_terms` | **OK** — the mid-wizard autosave |
  | that draft's `_listora_terms_accepted` | **not set** — no consent was given |
  | live create, no `agree_terms` | **REFUSED** `listora_terms_required` |
  | publish that draft, no `agree_terms` | **REFUSED** `listora_terms_required` |
  | publish that draft with `agree_terms=1` | **OK**, and the meta IS then set |
  | ordinary edit of the live listing, `agree_terms` omitted | **OK** |
  | edit with `agree_terms=0` | **REFUSED** |

- **On fail**: the two directions fail differently and both matter. A draft that
  is refused means autosave is dead again (defect 1). A publish that is ALLOWED
  without consent means the legal gate has a hole — drafts are exempt on create,
  so `update_listing()` is the only place the submitter's consent is ever due.

### 2. A second autosave updates rather than duplicating

- **Action**: save a draft, then save again passing the returned `listing_id`.
- **Expect**: the second call returns OK and the title reflects the second save.
- **On fail**: `listora_duplicate_detected` means the ID is not being carried.

### 3. The buy-credits CTA is marked on BOTH surfaces

- **Action**: on the plan step, in the browser:
  ```js
  document.querySelectorAll('.listora-submission [data-listora-credit-buy]').length
  ```
- **Expect**: every buy-credits CTA in the form is matched — Pro's plan card AND
  Free's preview-step banner.
- **On fail**: an unmarked CTA still discards the member's work. Marking one and
  not the other is the exact "one surface bypassing the fix" split this closes.

### 4. Clicking it saves a draft and carries a return URL

- **Action**: fill title + description, reach the plan step, click **Buy Credits**.
- **Expect**: the destination URL carries `listora_return`, and that value decodes
  to the submission page with `edit=<new draft id>` and `listora_step=plan`. A
  draft now exists for this member.
- **On fail**: no `listora_return` means the draft save failed — the handler only
  attaches it when an ID came back. Check the rate limiter first.

### 5. A failed save is NOT silent

- **Action**: force the save to fail (exhaust the rate limiter), then click.
- **Expect**: the form's error region says the listing could not be saved and
  that clicking again continues without it. The first click does NOT navigate.
- **On fail**: silent navigation is the original bug wearing a fix.

### 6. The landing surface offers the way back

- **Action**: follow the CTA to the credits tab.
- **Expect**: a banner — "Your listing is saved." — with a **Back to your
  listing** link pointing at the return URL, at least 40px tall.
- **On fail**: without it the member finishes paying and is left with no sign
  their listing survived, which is the outcome the handoff exists to prevent.

### 7. NEGATIVE — an off-site return URL is refused

This is the most important step. `listora_return` arrives in a query string and
is rendered as a link the member is invited to click.

- **Action**: load the credits tab with
  `?listora_return=https%3A%2F%2Fevil.example.com%2Fphish`
- **Expect**: **no** return banner renders.
- **On fail**: that is an open redirect wearing the site's own chrome, which is
  considerably more convincing than a bare link.
- **Note**: other links on the page will still carry the parameter along in their
  own query strings — `add_query_arg` preserves it. That is not a finding: the
  href host stays same-site. The finding is a link whose TARGET is off-site.

### 7b. The way back survives an actual checkout

Steps 1-7 only prove the handoff. They do NOT prove the member can get back
after paying, and the two are different: WooCommerce redirects to Order Received
with its own query string, and an off-site gateway sends the buyer away and back
before that. `listora_return` does not survive either.

This step exists because the gap was invisible until someone ran a real
checkout. Simulating the redirect passes while the live flow strands the buyer
on a receipt whose only listing link starts a brand new one.

- **Setup**: WooCommerce active, a product mapped to credits, and a gateway the
  member can actually pay with. Two things commonly block this on a dev site:
  WooCommerce's **coming-soon mode** (`woocommerce_coming_soon`,
  `woocommerce_store_pages_only`) hides the store entirely, and a gateway with no
  Blocks integration (the dummy gateway is one) will not appear on a block
  checkout at all — switch the checkout page to `[woocommerce_checkout]` to see
  it. Restore both afterwards.
- **Action**: from the plan step, follow Buy Credits, add the pack to the cart,
  and complete a real checkout.
- **Expect** on the Order Received page: a notice reading *"Your credits are
  ready. Your listing is saved and waiting for you."* with a **Back to your
  listing** link pointing at the draft, at least 40px tall. Credits are on the
  balance.
- **On fail**: if the notice is missing, the return URL was not carried onto the
  order. It is stashed in the WooCommerce session when first seen and copied to
  order meta at `woocommerce_checkout_create_order`; the order copy is what makes
  it survive an off-site gateway, because the session may be gone by the time the
  buyer comes back.

### 7c. NEGATIVE — an unpaid order does not credit

Directly related, and easy to check while a cart is open: Cash on Delivery,
cheque and BACS move an order to `processing` while it is still **unpaid**.

- **Action**: enable COD, order the credit pack with it.
- **Expect**: **no credits** at `processing`. Mark the order `completed` (cash
  received) and the credits land, once.
- **On fail**: a buyer can order, take the credits, spend them and never pay.
  Note the guard is `get_date_paid()`, not `is_paid()` — `is_paid()` is a status
  test and returns true for an unpaid COD order at `processing`, so a fix
  written on it looks right and does nothing.

### 8. Returning restores the listing and lands on the plan

- **Action**: follow the return URL.
- **Expect**: title and description restored, `listing_id` populated, and the
  plan step in view.
- **On fail**: returning renders **single-form** layout, where every step is
  stacked and visible — so the resume must SCROLL, not switch steps. Handling
  only the wizard case does nothing on the exact path this feature exists for,
  since coming back from checkout is always an edit.

## Notes

- **Verify which user the browser is actually logged in as.** The autologin
  mu-plugin no-ops when a session already exists (`is_user_logged_in()` returns
  early), so `?autologin=member` silently leaves an admin session in place. A
  balance of `0.00` for a member you topped up is usually this, not a display
  bug — check `#wp-admin-bar-my-account` before filing one.
- **The hidden `listing_id` input only exists in edit mode.** On a fresh
  submission the client creates it. A change that assumes it is always present
  will pass every edit-mode test and fail the one case that matters.
