---
journey: plan-submit-charged-once
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [credits, credits-sdk, pricing-plans, submission, free-pro-seam]
prerequisites:
  - "Combo (Pro active), a published paid plan (Standard, 10 credits) on the listing type, Pricing Plans ON"
  - "A member with 25 credits"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A paid-plan submission is charged once and goes to review or live

Free/Pro seam on credit holds: Free's `listing_submission` SDK consumer settles on
`wb_listora_listing_submission_settle` and releases on `wb_listora_listing_submission_release` (`wb-listora.php:923-924`),
which `wb-listora.php:1391` / `:1400` fire from approve/reject ONLY when `wb_listora_listing_plan_pays()` (`wb-listora.php:1132`) is
false. Before the fix Free consumed Pro's plan hold as "Listing Submission" and the next top-up charged the plan a second time.

Role ladder: the member pays; another member and logged-out visitors cannot submit (401); admin approving a pending paid listing is the
control for settle-once (approval must not charge the plan again).

## Steps

### 1. Paid plan, enough credits (verified over HTTP, cookie + `listora_nonce`)
- **Action**: Add Listing (`$SITE_URL/add-listing/`), pick **Standard**, submit; or `POST /listora/v1/submit` with `plan_id`,
  `listora_nonce` (from the form), `agree_terms=1`.
- **Expect**: 201; the wizard success card matches the real status. Observed on this site (Business type, moderation `manual`):
  `status: "publish"`, message "Your listing is live." (plan activation publishes it). The pending text, when it is pending, is
  "Your listing was submitted and is waiting for review. We will email you when it is approved." Never `listora_payment`.
- **Ledger** (`wp_listora_credit_ledger`, amounts in cents): one `hold` -1000 "Plan: Standard for listing #N", one `hold_release`
  +1000 "Hold released on approval", one `spend` -1000 "Plan: Standard for listing #N". Balance 25 -> 15. No
  `_listora_pending_plan_failure` meta, no "needs credits" email, `_listora_plan_id` set, `_listora_expiration_date` = +30 days.
- **Note**: the old journey said `pending` for manual moderation; with a plan that pays, the listing goes live on activation.

### 2. No second charge
- **Action**: top up 10 more (`Credit_System::add_credits`).
- **Expect**: balance 25 and the listing is not charged again (verified: status stays `publish`, no new `spend`).

### 3. Submission cost still settles without a plan
- **Setup**: plans off (or a type with no plans), submission cost 5, manual moderation. Submit, then approve as admin.
- **Expect**: hold 5 on submit, settled on approval ("Listing Submission - credits deducted"), balance down 5 once. Reject
  instead -> the hold is released/refunded.

### 4. Role ladder
- Logged out `POST /submit` -> 401; a member whose role is stripped -> 403 `listora_account_restricted`; admin submitting is exempt
  from the plan-required gate (`edit_others_listora_listings`).

### 5. Restore
Costs and plans back; delete test listings, plan and user; reset the balance.

## Fail diagnostics
- Listing parked in `listora_payment` with `Credits::deduct() failed to consume the hold`: the consumer's `deduct_on` is back on
  `wb_listora_after_approve_listing`, or `wb_listora_listing_plan_pays()` (`wb-listora.php:1132`) no longer sees the plan meta.
- Step 3 hold never settles: the gated listeners at `wb-listora.php:1380-1402` are gone.
- Wrong confirmation text: `includes/rest/class-submission-controller.php:1056-1068` (`submission_message()`).

## Not verified
Step 3 (no-plan submission cost) and step 4 control were not run; in-process CLI submissions are forced to draft by the plan gate
(use HTTP). Browser wizard rendering at 390 not checked.
