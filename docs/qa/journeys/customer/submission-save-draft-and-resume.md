---
journey: submission-save-draft-and-resume
plugin: wb-listora
priority: critical
roles: [subscriber, other-member, guest, admin]
covers: [save-draft, rate-limiter, terms-gate, autosave-id-carried, buy-credits-handoff, return-to-listing, open-redirect-guard, bc-10332281244, bc-10332258844]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Members {owner} and {other} that can submit; {owner} can reach Preview"
  - "Steps 6-14 need Pro active, Monetization ON and a plan whose cost exceeds {owner}'s balance (read both; lower a balance by hold THEN deduct, a bare deduct nets to zero)"
  - "Steps 11-12 (checkout return) need WooCommerce, a product mapped to credits and a payable gateway (switch checkout to `[woocommerce_checkout]` on dev; restore coming-soon settings after)"
estimated_runtime_minutes: 18
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:regression/save-draft-not-rate-limited.md"
  - "ora:regression/buy-credits-keeps-the-draft.md"
fails_on: []
---

# Save Draft is never rate-limited, carries its id, and following Buy Credits does not throw the listing away

## Why
A member who loses a half-filled listing to a rate limit, a duplicate refusal or a trip to buy credits re-enters everything or leaves. Drafts must save every time and the way back from checkout must restore the draft.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Add Listing page = `wb_listora_get_page_url( 'submission' )`. Credits page = `/buy-credits/` page or dashboard `?tab=credits`.
- Read `$COST` (cheapest plan) and `$BALANCE` ({owner}): `wp eval 'echo \Wbcom\Credits\Credits::balance_money("wb-listora", USER_ID);'`.
- Clear the submission limiter before probing: `wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%transient%listora%rate%\"");'`.
- Verify the browser session user (`#wp-admin-bar-my-account`): the autologin no-ops when a session exists.
- Delete every `QA-J1-` listing created at the end.

## Steps

### 1. Re-save one draft many times
- **Action**: as {owner} on Add Listing pick a type, title `QA-J1-draft`, press **Save Draft** 12 times.
- **Expect**: each press shows "Saving..." then "Saved"; no error box. First save `POST /wp-json/listora/v1/submit` returns `201 {"id":N,"status":"draft","message":"Draft saved."}`; later saves carry `listing_id=N` and return `200` "Your listing has been updated.".
- **Read-back**: one `post_status='draft'` row for N; title updated on each save.
- **On fail**: rate check moved above edit routing: `includes/rest/class-submission-controller.php:575-593`.

### 2. A second autosave updates, never duplicates
- **Action**: save a draft, then save again passing the returned `listing_id`; rename the title on the second.
- **Expect**: `200`, same id, title reflects the second save. A `409 listora_duplicate_detected` means the id is not carried.
- **On fail**: client discarded the draft id (`src/blocks/listing-submission/view.js` save-draft handler).

### 3. New listings are still limited (positive control)
- **Action**: as one member from one IP create 11 NEW listings (`QA-J1-rl-*`, no `listing_id`) inside an hour.
- **Expect**: the 11th returns `429` `listora_rate_limit`, `data.scope` `ip` (or `user` after 30), header `Retry-After: <seconds>`; retrying inside the window does not extend it.
- **Read-back**: limits 10/h/IP and 30/h/user (`includes/class-rate-limiter.php:60-65`). Cleanup: delete the drafts; `wp transient delete listora_rl_submission_ip_<hash>`.

### 4. A failed first save says so
- **Action**: open Add Listing, pick a type, leave the title empty, press **Save Draft**.
- **Expect**: `.listora-submission__error` shows "Title is required." (`400 listora_title_required`); a later successful save hides it.
- **Read-back**: expected-fail: re-saving an EXISTING draft with the title emptied must answer 400 and keep the old title, today it answers 200 and keeps it silently. See the report PRODUCT-BUGS entry "empty title on draft re-save".

### 5. Save Draft is hidden on Type and Preview
- **Action**: open Add Listing with no type preselected, walk to Preview.
- **Expect**: `.listora-submission__save-draft` carries `hidden` + `is-hidden` on Type, is visible from Basic Info through Media, hidden on the last (Preview) step; in single-form layout it is always visible.
- **On fail**: `view.js:1824-1866` (`updateNavButtons()`).

### 6. The terms gate matrix (Pro or Free, via `POST /listora/v1/submit` as {owner}, plan_id sent when a plan exists)
| Request | Expect |
|---|---|
| `status=draft`, no `agree_terms` | `201`, a draft (mid-wizard autosave); `_listora_terms_accepted` empty (no consent given) |
| live create, no `agree_terms` | `400 listora_terms_required` |
| publish that draft (`listing_id`), no `agree_terms` | `400 listora_terms_required`; post stays `draft` |
| publish that draft with `agree_terms=1` | consent stamped (`_listora_terms_accepted` = a date); with 0 credits `402 insufficient_credits`, listing stays draft "Awaiting Credits" |
| edit of a LIVE listing, `agree_terms` omitted | allowed (an edit is not a fresh acceptance) |
| edit with `agree_terms=0` | `listora_terms_required` (not exercised: needs a live, planned listing) |
- **On fail**: a refused draft means autosave is dead; a publish allowed without consent is a hole in the legal gate (`check_terms_acceptance()` `:482-520`, draft exemption `:677-695`, `TERMS_META_KEY` `:265`).
- **Note**: a monetised directory answers `listora_plan_required` before the terms check on the publish transition; always send `plan_id` when probing.

### 7. Both buy-credits CTAs are marked
- **Action**: on the plan step run `document.querySelectorAll('.listora-submission [data-listora-credit-buy]').length`.
- **Expect**: every buy-credits CTA carries the marker: Pro's `a.listora-plan-card__buy-link[data-listora-credit-buy]` AND Free's preview-step banner (`templates/blocks/listing-submission/step-preview.php:132`).

### 8. Clicking it saves a draft and carries a return URL
- **Action**: fill title and description, reach the plan step, click **Buy Credits** (label becomes "Saving...").
- **Expect**: destination URL carries `listora_return` decoding to the submission page with `edit=<new draft id>` and `listora_step=plan`; a draft exists for {owner}.
- **On fail**: no `listora_return` means the draft save failed (check the limiter first).

### 9. A failed save is NOT silent
- **Action**: exhaust the limiter, click Buy Credits.
- **Expect**: the form error region reads "We could not save your listing just now. Click Buy Credits again to continue anyway - your details on this page will be lost." and the first click does NOT navigate.

### 10. The landing surface offers the way back
- **Action**: follow the CTA to the credits page.
- **Expect**: banner "Your listing is saved." with a **Back to your listing** link to the return URL, >= 40px tall, on both `/buy-credits/` and `?tab=credits`.
- **Read-back**: the href equals `<submission page>?edit=<draft id>&listora_step=plan`.

### 11. NEGATIVE: an off-site return URL is refused
- **Action**: load the credits page with `?listora_return=https%3A%2F%2Fevil.example.com%2Fphish`.
- **Expect**: no return banner on either surface; positive control: the same page with a same-site `listora_return` shows it (step 10).
- **On fail**: open redirect (`wp_validate_redirect()` in `wb_listora_get_submission_return_url()`, `includes/class-template-helpers.php:285-330`). Other links carrying the parameter are not a finding while their host stays same-site.

### 12. The way back survives an actual checkout
- **Action**: from the plan step follow Buy Credits, add the pack, complete a real WooCommerce checkout.
- **Expect**: Order Received shows "Your credits are ready. Your listing is saved and waiting for you." with a **Back to your listing** link at the draft, >= 40px tall; credits are on the balance.
- **Read-back**: order meta `_listora_return_url` is set at `woocommerce_checkout_create_order` (`includes/workflow/class-return-to-listing.php:29,40`). Not verified live.

### 13. NEGATIVE: an unpaid order does not credit
- **Action**: enable COD, order the credit pack with it.
- **Expect**: no credits at `processing`; mark the order `completed` and the credits land once. Guard is `get_date_paid()`, not `is_paid()`.

### 14. Returning restores the listing and lands on the plan
- **Action**: follow the return URL.
- **Expect**: title and description restored, `listing_id` populated, the plan step in view (single-form layout scrolls, it does not switch steps).
- **On fail**: `view.js:3761-3790`.

### 15. Role ladder
| Actor | Action | Expect |
|---|---|---|
| {owner} | steps 1-14 | as above |
| {other} | `POST /submit` with `listing_id=N` / open `?edit=N` | `403 listora_forbidden`; draft not loaded; banner link does not leak the title |
| {guest} | `POST /submit` with `listing_id=N` | `401 listora_unauthorized`; wizard shows login prompt; credits page shows no banner for a bad return URL |
| {admin} (control) | re-save N via wp-admin/dashboard | `200`, edits the member's draft, no duplicate warning; draft shows status Draft in wp-admin |

### 16. 390px
- **Expect**: Save Draft / Buy Credits / Back to your listing >= 40px, error box readable, no horizontal scroll. Not verified live.

## Pass criteria
1. 12 re-saves of one draft succeed; the 11th NEW listing from one IP is 429 with Retry-After.
2. Drafts never need consent; publishing always does; an edit is not a fresh acceptance.
3. Every Buy Credits CTA saves a draft and carries a same-site return URL; off-site is refused.
4. The return lands on the restored plan step; unpaid orders never credit.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| 429 on re-save | rate check above edit routing | `class-submission-controller.php:575-593` |
| Button flips back silently | catch block | `view.js:429-465` |
| 409 duplicate on second save | draft id dropped | `view.js` (`listing_id` input only exists in edit mode; client must create it) |
| Draft refused for consent | ToS gate applies to drafts | `class-submission-controller.php:677-695` |
| Consent recorded nobody gave | create path stamps unconditionally | `TERMS_META_KEY` write `:265` |
| Banner missing | return URL guard | `class-template-helpers.php:285-330` |
| Credited unpaid COD | `is_paid()` used | `class-return-to-listing.php` |

## Not verified
- Steps 1-2 and 6 matrix rows 1-5 were run via REST as a subscriber; steps 7-10 and 14 browser, 12-13 WooCommerce checkout, 16 390px were not run; step 4 expected-fail is a documented product bug.

## Cleanup
Delete `QA-J1-` drafts, restore credits and plans, delete the limiter transient, restore WooCommerce coming-soon settings.
