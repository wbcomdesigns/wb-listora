---
journey: buy-credits-keeps-the-draft
plugin: wb-listora
priority: critical
roles: [member, anonymous, administrator]
covers: [submission-draft, terms-gate, buy-credits-handoff, return-to-listing, open-redirect-guard]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Pro active, Monetization feature ON"
  - "At least one plan costing more credits than the test member holds (reference site: plan 3630 'Standard Listing', 10 credits; subscriber2 holds 0)"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Going to buy credits does not throw the listing away

A member filled in a whole listing, reached the plan step, found they were short of credits and followed **Buy Credits**. The wizard keeps its state nowhere but the DOM, so every field was gone and they had to re-enter the listing to spend the credits they had just bought. Leaving the page is not avoidable (a real gateway redirects off-site), so the contract is: the trip must be survivable.

Four defects had to be fixed first, each asserted separately because any one regressing silently restores the data loss:
1. The ToS gate applied to draft saves, but the checkbox is on the wizard's LAST step, so every autosave returned 400 into an empty `catch`: the draft feature was dead for the whole wizard.
2. Drafts were exempted, but the create path stamped `_listora_terms_accepted` unconditionally, recording consent nobody gave (and the preview step reads that meta to PRE-TICK the box).
3. The client discarded the saved draft's ID, so the second save posted as a new listing and was refused as a duplicate while the indicator kept saying "Draft saved".
4. `agree_terms` carried `'default' => false` on `/submit`, so `get_param()` never returned null and `check_terms_acceptance()`'s `$default_value` was dead: "an edit is not a fresh acceptance" never applied.

Code: `includes/rest/class-submission-controller.php` (`TERMS_META_KEY` `:265`, `check_terms_acceptance()` `:482-520`, the draft exemption `:677-695`), `src/blocks/listing-submission/view.js:3665-3760` (`initBuyCreditsHandoff()`), `includes/class-template-helpers.php:285-330` (`wb_listora_get_submission_return_url()`), `includes/workflow/class-return-to-listing.php` (checkout path).

## Setup
- A test member whose balance is BELOW the cost of at least one plan: `wp eval 'echo \Wbcom\Credits\Credits::balance_money("wb-listora", USER_ID);'`. To lower a balance, hold THEN deduct: a bare `deduct_money()` routes through `deduct_with_hold_release()`, which writes a release for a hold never placed and nets to zero (API misuse, not a product bug).
- Clear the submission rate limiter if you have been probing: `wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%transient%listora%rate%\"");'`
- Delete every QA-J1- listing created below at the end.

## Steps (1, 2 and 7 executed 2026-10-12; 3-6, 7b, 7c, 8 need a browser/WooCommerce and were not)

### 1. The gate matrix still holds (verified via `POST /listora/v1/submit` as subscriber2, plan_id=3630)
| Request | Result observed |
|---|---|
| `status=draft`, no `agree_terms` | `201`, a draft: the mid-wizard autosave |
| that draft's `_listora_terms_accepted` | empty: no consent was given |
| live create (`status=publish`), no `agree_terms` | `400 listora_terms_required` |
| publish that draft (with `listing_id`), no `agree_terms` | `400 listora_terms_required`; the post stays `draft` |
| publish that draft with `agree_terms=1` | consent IS stamped (`_listora_terms_accepted` = a date); with 0 credits the outcome is `402 insufficient_credits` and the listing stays a draft, "Awaiting Credits" |
| edit of a LIVE listing, `agree_terms` omitted | allowed by the terms rule (not re-run to completion here: a live listing with a plan requirement answers `listora_plan_required` unless `plan_id` is sent; resend with `plan_id`) |
| edit with `agree_terms=0` | expected `listora_terms_required`; NOT exercised: without `plan_id` the call answered `listora_plan_required` first, and the fixture member has no credits. Re-run on a live, planned listing |

- **On fail**: the two directions fail differently and both matter. A draft that is refused means autosave is dead again (defect 1). A publish that is ALLOWED without consent is a hole in the legal gate: drafts are exempt on create, so `update_listing()` is the only place the submitter's consent is ever due.
- Note: without `plan_id`, a monetised directory answers `listora_plan_required` ("Please select a listing plan to continue.") before the terms check on the publish transition; always send `plan_id` when probing the terms matrix.

### 2. A second autosave updates rather than duplicating (verified)
- **Action**: save a draft, then save again passing the returned `listing_id`.
- **Expect**: the second call returns `200`, same ID, and the title reflects the second save (verified: `QA-J1 buy credits draft` -> `... draft 2`).
- **On fail**: `listora_duplicate_detected` (409) means the ID is not being carried.

### 3. The buy-credits CTA is marked on BOTH surfaces
- **Action**: on the plan step in the browser: `document.querySelectorAll('.listora-submission [data-listora-credit-buy]').length`
- **Expect**: every buy-credits CTA in the form matches: Pro's plan-card link (`a.listora-plan-card__buy-link[data-listora-credit-buy]`, `wb-listora-pro/includes/features/class-pricing-plans.php:649`) AND Free's preview-step banner (`templates/blocks/listing-submission/step-preview.php:132`).
- **On fail**: an unmarked CTA still discards the member's work.

### 4. Clicking it saves a draft and carries a return URL
- **Action**: fill title + description, reach the plan step, click **Buy Credits** (the label becomes "Saving..." while the draft saves).
- **Expect**: the destination URL carries `listora_return`, and that value decodes to the submission page with `edit=<new draft id>` and `listora_step=plan`. A draft now exists for this member.
- **On fail**: no `listora_return` means the draft save failed; the handler only attaches it when an ID came back. Check the rate limiter first.

### 5. A failed save is NOT silent
- **Action**: force the save to fail (exhaust the rate limiter), then click.
- **Expect**: the form's error region says "We could not save your listing just now. Click Buy Credits again to continue anyway - your details on this page will be lost." and the first click does NOT navigate.
- **On fail**: silent navigation is the original bug wearing a fix.

### 6. The landing surface offers the way back
- **Action**: follow the CTA to the credits page (`/buy-credits/`, or the dashboard Credits tab `/my-listings/?tab=credits`).
- **Expect**: a banner "Your listing is saved." with a **Back to your listing** link pointing at the return URL, >= 40px tall. Verified in the served HTML on both pages with a same-site `listora_return`: the link is `.../add-listing/?edit=123&listora_step=plan`.
- **On fail**: without it the member finishes paying with no sign their listing survived.

### 7. NEGATIVE: an off-site return URL is refused (verified)
`listora_return` arrives in a query string and is rendered as a link the member is invited to click, so this is the most important step.
- **Action**: load the credits page with `?listora_return=https%3A%2F%2Fevil.example.com%2Fphish`.
- **Expect**: NO return banner renders on `/buy-credits/` or `/my-listings/?tab=credits` (verified: "no banner").
- **POSITIVE CONTROL**: the same page with a same-site `listora_return` shows the banner (step 6), so the absence above is the guard and not a broken page.
- **On fail**: an open redirect wearing the site's own chrome (`wp_validate_redirect()` in `wb_listora_get_submission_return_url()`).
- **Note**: other links on the page still carry the parameter in their own query strings; that is not a finding while the href host stays same-site. The finding is a link whose TARGET is off-site.

### 7b. The way back survives an actual checkout
Steps 1-7 only prove the handoff. WooCommerce redirects to Order Received with its own query string, and an off-site gateway sends the buyer away and back first; `listora_return` survives neither. Simulating the redirect passes while the live flow strands the buyer on a receipt whose only listing link starts a brand new one.
- **Setup**: WooCommerce active, a product mapped to credits, and a gateway the member can pay with. On a dev site WooCommerce's coming-soon mode (`woocommerce_coming_soon`, `woocommerce_store_pages_only`) hides the store, and a gateway with no Blocks integration (the dummy gateway) will not appear on a block checkout: switch the checkout page to `[woocommerce_checkout]`. Restore both afterwards.
- **Action**: from the plan step follow Buy Credits, add the pack, complete a real checkout.
- **Expect** on Order Received: a notice "Your credits are ready. Your listing is saved and waiting for you." with a **Back to your listing** link at the draft (`includes/workflow/class-return-to-listing.php:117-120`), >= 40px tall; credits are on the balance.
- **On fail**: the return URL was not carried onto the order. It is stashed in the WooCommerce session when first seen and copied to order meta `_listora_return_url` at `woocommerce_checkout_create_order` (`class-return-to-listing.php:29,40`); the order copy is what survives an off-site gateway.

### 7c. NEGATIVE: an unpaid order does not credit
Cash on Delivery, cheque and BACS move an order to `processing` while it is still unpaid.
- **Action**: enable COD, order the credit pack with it.
- **Expect**: NO credits at `processing`. Mark the order `completed` (cash received) and the credits land, once.
- **On fail**: a buyer can order, take the credits, spend them and never pay. The guard is `get_date_paid()`, not `is_paid()`: `is_paid()` is a status test and returns true for an unpaid COD order at `processing`.

### 8. Returning restores the listing and lands on the plan
- **Action**: follow the return URL.
- **Expect**: title and description restored, `listing_id` populated, and the plan step in view.
- **On fail**: returning renders the single-form layout, where every step is stacked and visible, so the resume must SCROLL, not switch steps. Handling only the wizard case does nothing on the exact path this feature exists for, since coming back from checkout is always an edit (`view.js:3761-3790`).

### 9. Role ladder
- **Member (draft owner)**: steps 1-8.
- **Another member opening that `?edit=<id>` URL**: must not load someone else's draft (404/403), and the banner link must not leak the title.
- **Logged out**: the wizard shows the login prompt; no draft is created; the credits page shows no banner for a bad return URL.
- **Administrator (control)**: can open the draft in wp-admin; a draft saved from the wizard shows status Draft.

## Notes
- **Verify which user the browser is logged in as.** The autologin mu-plugin no-ops when a session already exists, so `?autologin=member` silently leaves an admin session in place. A balance of `0.00` for a member you topped up is usually this: check `#wp-admin-bar-my-account` before filing a bug.
- **The hidden `listing_id` input only exists in edit mode.** On a fresh submission the client creates it; a change that assumes it is always present passes every edit-mode test and fails the one that matters.

## Not verified
Steps 3-6, 7b, 7c and 8 (browser, WooCommerce checkout) were not run; the banner and the off-site refusal (step 6, 7) were checked on the served HTML; the gate matrix (1-2) was run through `rest_do_request` as subscriber2 with the rate limiter cleared and fixtures removed.
