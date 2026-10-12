---
journey: submission-cost-charged-once
group: credits-checkout
actor: customer
mode: mixed
plugin: wb-listora
priority: critical
roles: [subscriber, other-member, guest, admin]
covers: [credits, submission-cost, drafts, credits-sdk, hold-settle-release, free-pro-seam, credit-gate, card-10336800031]
prerequisites:
  - "Site reachable at $SITE_URL; Listora > Settings > Credits > Credit Costs > Listing submission cost (`#listora_default_listing_credit_cost`, option `wb_listora_settings[default_listing_credit_cost]`) = 5 (record the original, restore at the end)"
  - "Steps 1-4b, 6: no published Pro plan applies to the type under test (set any published plan to draft for the run and restore it); steps 5, 5b: Pro active with a published plan"
  - "{owner} with 0 credits and {other} holding at least the submission cost"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:regression/submission-cost-charged.md"
  - "ora:regression/plan-submit-charged-once.md"
fails_on: []
---

# Every listing that goes to review or live pays its cost once, whether by submission cost or by plan

## Why
A member with no credits who lists free, a member charged twice (plan hold then submission cost), or a refund after settling all lose the owner money or the member's trust.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Read `$COST_SUBMIT` (the setting above), `$COST_PLAN` (the published plan's credits) and each member's `$BALANCE` via `Credit_System`/`wbcom-credits`; derive, never assume.
- Use cookie + `X-WP-Nonce` for HTTP; a `wp eval-file` runner must `add_filter( 'wb_listora_rate_limit_bypass', '__return_true' )` (a 429 `listora_rate_limit` is not a credit failure; 10/h/IP, 30/h/user, `includes/class-rate-limiter.php:60`).
- In-process WP-CLI submissions are forced to draft by the plan gate; use HTTP for status assertions.

## Steps

### 1. Short balance is refused before anything is created
- **Action**: {owner} (0 credits): fill every step, Submit (or `POST /listora/v1/submit` with `agree_terms:true`).
- **Expect**: `402` `insufficient_credits`, `data = {required: $COST_SUBMIT, balance: 0, credits_buy_url: ".../my-listings/?tab=credits"}`; wizard error "Submitting a listing costs $COST_SUBMIT credits and you have 0. Buy credits, then submit again." followed by a **Buy credits** link (`a.listora-btn--secondary`); Preview reads "Your balance: 0 credits" (`[data-listora-credit-balance]`).
- **Read-back**: `SELECT COUNT(*) ... post_author={owner}` = 0 new `listora_listing` rows. With a Pro plan, a balance below the plan cost but above `$COST_SUBMIT` answers `400 listora_plan_required` ("Please select a listing plan to continue.") until a plan is chosen.
- **On fail**: pre-check `submission_credits_short()` (`includes/rest/class-submission-controller.php:1024`, `:925`), link `view.js:408`.

### 2. Enough credits: charged once
- **Action**: top {owner} up by `$COST_SUBMIT * 4`, submit.
- **Expect (manual moderation)**: `pending`, ledger `hold -$COST_SUBMIT`; approve in wp-admin: `refund +$COST_SUBMIT` then `deduction -$COST_SUBMIT`; balance = start - `$COST_SUBMIT`.
- **Expect (auto-approve)**: `publish`, hold settled at once; same balance.
- **Read-back**: sum of `wp_listora_credit_ledger` rows for the listing nets to `-$COST_SUBMIT`.

### 3. No refund after settling, no second charge
- **Action**: deactivate that live listing, then reactivate it.
- **Expect**: balance unchanged.
- **On fail**: SDK hold record `_wbcom_credits_wb-listora_listing_submission` not written.

### 4. Drafts: free to save, charged on submit
- **Action**: Save Draft; then `PUT /listora/v1/submit/{id}` to submit it.
- **Expect**: Save Draft leaves the balance unchanged; submitting charges `$COST_SUBMIT` (pending holds, auto-approve settles); the card reads "Listing Submitted!" (2.0.0 draft wording), not "Listing Updated!".

### 4b. A forged plan id does not waive the draft charge
- **Action**: {owner} with 0 credits, plans off: save a draft, `PUT /listora/v1/submit/{id}` `{"agree_terms":true,"plan_id":999999}`.
- **Expect**: `402 insufficient_credits`; listing stays `draft` (manual and auto-approve).
- **On fail**: `update_listing()` trusts the request `plan_id`; charge must run after `wb_listora_listing_submitted` (`class-submission-controller.php:1288`, pre-checks `:1192`, `:1295`).

### 5. A paid plan charges only the plan (Pro)
- **Action**: {owner} with `$COST_PLAN + 15` credits, a published plan on the type, submit choosing it (UI or `POST /submit` with `plan_id`, `listora_nonce`, `agree_terms=1`).
- **Expect**: `201`; success card matches the real status (plan activation publishes it: `status: "publish"`, "Your listing is live."; pending text, when pending, is "Your listing was submitted and is waiting for review. We will email you when it is approved."); never `listora_payment`. Only the plan is charged, not `$COST_SUBMIT`.
- **Read-back** (`wp_listora_credit_ledger`, cents): one `hold -$COST_PLAN*100` "Plan: <name> for listing #N", one `hold_release +`, one `spend -`; balance = start - `$COST_PLAN`; no `_listora_pending_plan_failure` meta, no "needs credits" email; `_listora_plan_id` set; `_listora_expiration_date` = now + plan duration.

### 5b. Approval and later top-ups never charge the plan again
- **Action**: top up `$COST_PLAN` more; as {admin} approve any pending paid listing.
- **Expect**: balance = previous + top-up; listing stays `publish`; no new `spend`.
- **On fail**: Free consumed Pro's plan hold as "Listing Submission": gated listeners `wb-listora.php:923-924`, `:1380-1402`, gate `wb_listora_listing_plan_pays()` `:1132`; symptom is a listing parked in `listora_payment` with `Credits::deduct() failed to consume the hold`.

### 6. Two at once
- **Action**: balance = cost = `$COST_SUBMIT`, auto-approve; send two submissions at the same moment.
- **Expect**: one `201` (published); the other is `402` with its listing kept as a draft (`data.listing_id` exists) when the balance was already spent, or `503 listora_submission_retry` with nothing created when the lock was held. Balance 0, never negative; no response id names a missing listing.
- **Read-back**: no "on null" warnings in debug.log; no `listora_search_index` / field_index / geo / hours rows for a missing listing.
- **On fail**: `includes/db/class-credit-lock.php:58` (`Credit_Lock::run()` must clear the SDK balance cache); `:882` post-COMMIT existence check.

### 7. Rejecting returns the hold
- **Action**: submit with manual moderation, reject as {admin}.
- **Expect**: hold released/refunded; balance back to start. Not verified live.

### 8. 390px
- **Expect**: the 402 error block and Buy credits link are readable, tap target >= 40px, no horizontal scroll. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-6 |
| {other} | `PUT /submit/{owner draft id}` refused `403 listora_forbidden` |
| {guest} | `POST /submit` `401 listora_unauthorized`, no listing |
| role stripped of the cap | `403 listora_account_restricted` |
| {admin} | control: record what admin pays (cost 0 or charged); exempt from the plan-required gate (`edit_others_listora_listings`) |

## Pass criteria
1. A short balance is refused before any post is created, with the real numbers and a Buy credits link.
2. Cost is held on submit, settled on approval (or at once on auto-approve), refunded on rejection, charged exactly once.
3. A plan that pays is the only charge; later top-ups and approvals never charge it again.
4. A forged plan id never waives the charge; concurrent submits never overdraw.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| 0-credit member lists free | pre-check or cost lookup | `class-submission-controller.php:925`, `wb-listora.php:1074` (`wb_listora_member_listing_cost()`) |
| Draft with `plan_id=999999` goes live | request plan trusted | `class-submission-controller.php:1288` |
| Deactivate refunds | SDK hold record not written | `Consumer::on_hold/on_deduct/on_refund` |
| Both racers charged | lock no longer clears cache | `class-credit-lock.php:58` |
| Wrong confirmation text | status wording | `class-submission-controller.php:1056-1068` (`submission_message()`) |

## Not verified
- Only steps 1 (402, message, buy URL, nothing created) and 4b (402, stays draft) were run on 2026-10-12 via REST in wp-cli, and step 5 (201 publish, ledger hold/release/spend, no second charge) over HTTP; steps 2, 3, 4, 6, 7, 8 and the wizard link/Preview balance rendering carried over unrun.

## Cleanup
Restore submission cost and plans to their recorded originals; delete `QA-J1-` listings; reset balances; remove the test plan/user.
