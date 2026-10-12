---
journey: submission-cost-charged
plugin: wb-listora
priority: critical
roles: [member, other-member, logged-out, admin]
covers: [credits, submission, drafts, email-verification, credits-sdk, card-10336800031]
prerequisites:
  - "Listora > Settings > Credits > Credit Costs > Listing submission cost (`#listora_default_listing_credit_cost`, option `wb_listora_settings[default_listing_credit_cost]`) = 5"
  - "No published Pro plan applies to the type under test. This site has one published plan (id 3630 Standard Listing, 10 credits) which makes a non-draft submit return 400 `listora_plan_required`; set it to draft for the run and restore it"
  - "A member with 0 credits (QA-J1-owner) and one with a balance (QA-J1-member holds 5)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Every listing that goes to review or live pays the submission cost, once

Regression sentinel for `Submission_Controller::submit_listing()` / `update_listing()` / `verify_endpoint()` (balance pre-check via `submission_credits_short()` plus `charge_submission()` under `wb_listora_with_credits_lock()`), `wb_listora_listing_submission_cost()` / `wb_listora_member_listing_cost()` (`wb-listora.php:1152`, `:1074`), the SDK consumer's hold record (`Consumer::on_hold/on_deduct/on_refund`), and `Credit_Lock` clearing the SDK balance cache.

## Steps

### 1. Short balance is refused before anything is created
- **Action**: member with 0 credits: Add Listing, fill every step, Submit (or `POST /listora/v1/submit` with `agree_terms:true`).
- **Expect**: HTTP 402, code `insufficient_credits`, `data = {required:5, balance:0, credits_buy_url:".../my-listings/?tab=credits"}`. Wizard error block shows "Submitting a listing costs 5 credits and you have 0. Buy credits, then submit again." followed by a **Buy credits** link (`a.listora-btn--secondary`). No `listora_listing` post is created (count by author = 0). Preview step reads "Your balance: 0 credits" (`[data-listora-credit-balance]`).
- **Positive control**: step 2 (same member after top-up succeeds).

### 2. Enough credits: charged once
Top up 20.
- Manual moderation: Submit -> `pending`, ledger `hold -5`; approve in wp-admin -> `refund +5` then `deduction -5`; balance 15.
- Auto-approve: Submit -> `publish`, hold settled at once; balance 15.

### 3. No refund after settling, no second charge
Deactivate that live listing, then reactivate it. Expect balance stays 15.

### 4. Drafts
Save Draft -> balance unchanged. Submit the draft (`PUT /listora/v1/submit/{id}`) -> charged 5 (pending holds, auto-approve settles). A card then reads "Listing Submitted!" (2.0.0 draft wording), not "Listing Updated!".

### 4b. A forged plan id does not waive the draft charge
Member with 0 credits, plans off. Save a draft, then `PUT /listora/v1/submit/{id}` body `{"agree_terms":true,"plan_id":999999}`.
- **Expect**: HTTP 402 `insufficient_credits`; listing stays `draft` (manual and auto-approve).
- Verified 2026-10-12 with QA-J1-owner (balance 0, cost 5): 402 and `post_status = draft`.

### 5. Plans pay instead
With a published plan on the type, submit choosing it: only the plan is charged (Standard 10), not the submission cost.

### 6. Two at once
Balance 5, cost 5, auto-approve; send two submissions at the same moment.
- **Expect**: one 201 (published). The other is either 402 with its listing kept as a draft (`data.listing_id` exists) or 503 `listora_submission_retry` with nothing created. Balance 0, never negative; no response carries an id of a listing that does not exist; no "on null" warnings in debug.log; no `listora_search_index` / field_index / geo / hours row for a missing listing.

### 7. Role ladder
| Actor | Expect |
|---|---|
| Member (owner) | steps 1-6 |
| Another member | `PUT /submit/{owner draft id}` refused 403 |
| Logged out | `POST /submit` 401/403, no listing |
| Admin | control: states what admin pays (cost 0 or charged) - record it, do not assume |

### 8. Rate limit note
Repeated `/submit` calls from one IP hit `429 listora_rate_limit` (10/hour/IP, 30/hour/user, `includes/class-rate-limiter.php:60`) before the credit check. Use `add_filter( 'wb_listora_rate_limit_bypass', '__return_true' )` in the `wp eval-file` runner; a 429 is not a credit failure.

### 9. Restore
Submission cost and plans back as they were (2026-10-12 original: cost 2, plan 3630 published); delete QA-J1- listings; reset the member's balance.

## Fail diagnostics
- Draft submitted with `plan_id=999999` goes live free -> `update_listing()` trusts the request `plan_id`; charge must run after `wb_listora_listing_submitted` (`includes/rest/class-submission-controller.php:1288` `charge_submission()` call on the submit transition; pre-checks at `:1192` and `:1295`).
- A 0-credit member lists free -> pre-check at `class-submission-controller.php:925` (charge at `:917`) or `wb_listora_member_listing_cost()` (`wb-listora.php:1074`) no longer reads the setting.
- Deactivate refunds -> the SDK hold record `_wbcom_credits_wb-listora_listing_submission` is not written.
- A 402/201 names a missing listing -> post-COMMIT existence check in `submit_listing()` (`:882` `listora_submission_retry`).
- Both racing submissions charged -> `includes/db/class-credit-lock.php:58` `Credit_Lock::run()` no longer clears the SDK balance cache.

## Not verified
Only step 1 (402, message, buy URL, nothing created) and step 4b (402, stays draft) were run on 2026-10-12, via REST in wp-cli. Steps 2, 3, 4, 5, 6, the wizard "Buy credits" link rendering and the Preview balance line were not run and are carried over from the previous journey; re-run them before relying on them. The 402 message is composed in `submission_credits_short()` (`:1024`) and the link in `view.js:408`.

Verified via: wp-cli `rest_do_request` (no browser).
