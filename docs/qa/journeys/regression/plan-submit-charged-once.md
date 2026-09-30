---
journey: plan-submit-charged-once
plugin: wb-listora
priority: critical
roles: [member]
covers: [credits, credits-sdk, pricing-plans, submission, free-pro-seam]
prerequisites:
  - "Combo (Pro active), a published paid plan (Standard, 10 credits) on the listing type"
  - "A member with 25 credits"
estimated_runtime_minutes: 6
---

# A paid-plan submission is charged once and goes to review or live

Regression sentinel for the Free/Pro seam on credit holds: Free's `listing_submission` SDK consumer settles on `wb_listora_listing_submission_settle` and releases on `wb_listora_listing_submission_release`, which `wb-listora.php` fires from approve/reject only when `wb_listora_listing_plan_pays()` is false.

## Background

Found by the 1.9.0 pre-release smoke. The SDK consumer settles every open hold on the item, not only its own. On a plan listing Pro holds the plan cost on the listing id; when the listing published, Free's consumer (listening on `wb_listora_after_approve_listing`) consumed Pro's hold as "Listing Submission - credits deducted". Pro's own settle then failed, the listing was parked in `listora_payment` with a "needs credits" email, and the next top-up activated it and charged the plan a second time.

## Steps

### 1. Paid plan, enough credits
Member with 25 credits: Add Listing, pick **Standard**, submit.
- **Expect**: 201; status `pending` (manual) or `publish` (auto-approve), never `listora_payment`; balance **15**; ledger shows one hold, its release and one deduction noted "Plan: Standard"; no `_listora_pending_plan_failure` meta; no "needs credits" email.

### 2. No second charge
Top up 10 more.
- **Expect**: balance **25**; the listing is not charged again.

### 3. Submission cost still settles without a plan
Plans off, submission cost 5, manual moderation: submit, then approve.
- **Expect**: hold 5 on submit, settled on approval ("Listing Submission - credits deducted"), balance down 5 once. Reject instead -> the 5 is refunded.

### 4. Restore
Costs and plans back; delete test listings; reset the balance.

## Fail diagnostics
- Listing parked in `listora_payment` with `Credits::deduct() failed to consume the hold` -> the consumer's `deduct_on` is back on `wb_listora_after_approve_listing`, or `wb_listora_listing_plan_pays()` no longer sees the plan meta.
- Step 3 hold never settles -> the gated listeners in `wb-listora.php` are gone.
