---
journey: submission-cost-charged
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [credits, submission, drafts, email-verification, credits-sdk, card-10336800031]
prerequisites:
  - "Settings > Credit Costs > Listing submission cost = 5; a listing type with no Pro plan (or Pro plans unpublished)"
  - "A member with 0 credits"
estimated_runtime_minutes: 10
---

# Every listing that goes to review or live pays the submission cost, once

Regression sentinel for `Submission_Controller::submit_listing()` / `update_listing()` / `verify_endpoint()` (balance check + `charge_submission()` under `wb_listora_with_credits_lock()`), `wb_listora_listing_submission_cost()` / `wb_listora_member_listing_cost()`, the SDK consumer's hold record (`Consumer::on_hold/on_deduct/on_refund`), and `Credit_Lock` clearing the SDK balance cache.

## Background

Card 10336800031. Nothing checked the balance before a listing was created; the SDK consumer held the cost on `wb_listora_after_create_listing` and silently gave up when the balance was short, so a member with too few credits listed free. On auto-approve the hold was never settled. Saving a draft held the cost and a draft submitted later skipped the charge. Found while fixing: deactivating a listing whose cost was settled refunded it, and two submissions at the same moment were both charged against credits for one (a pre-check's cached balance).

## Steps

### 1. Short balance is refused before anything is created
Member with 0 credits: Add Listing, fill every step, Submit.
- **Expect**: HTTP 402 `insufficient_credits`; the wizard shows "Submitting a listing costs 5 credits and you have 0. Buy credits, then submit again." with a **Buy credits** button (dashboard Credits tab). No listing is created. The preview says "Your balance: 0 credits".

### 2. Enough credits: charged once
Top up 20.
- Manual moderation: Submit -> **pending**, ledger `hold -5`; approve in wp-admin -> `refund +5`, `deduction -5`; balance **15**.
- Auto-approve: Submit -> **publish**, hold settled at once; balance **15**.

### 3. No refund after settling, no second charge
Deactivate that live listing, then reactivate it.
- **Expect**: balance stays **15** (no refund, no re-charge).

### 4. Drafts
Save Draft -> balance unchanged. Submit the draft (`PUT /listora/v1/submit/{id}`) -> charged 5 (pending holds, auto-approve settles).

### 4b. A forged plan id does not waive the draft charge
Plans off (Free only, or Pro Pricing Plans disabled), member with 0 credits. Save a draft, then submit it with a plan id no plan has: `PUT /listora/v1/submit/{id}` body `{"agree_terms":true,"plan_id":999999}`.
- **Expect**: HTTP 402 `insufficient_credits`; the listing stays **draft** (manual and auto-approve). Before 1.9.0 the request `plan_id` skipped the charge and the listing went pending/live for free.

### 5. Plans pay instead
With a Pro plan on the type, submit choosing it -> only the plan is charged (Standard 10), not the submission cost.

### 6. Two at once
Balance 5, cost 5, auto-approve; send two submissions at the same moment.
- **Expect**: one 201 (published). The other is either 402 with its listing kept as a **draft** (`data.listing_id`, and that listing exists), or 503 `listora_submission_retry` with nothing created (the two collided in the database). Balance **0**, never negative. No response ever carries the id of a listing that does not exist, and no "on null" warnings in debug.log. After a 503, no `listora_search_index` / `field_index` / `geo` / `hours` row exists for a listing that does not.

### 7. Restore
Submission cost and plans back as they were; delete test listings; reset the member's balance.

## Fail diagnostics
- A draft submitted with `plan_id=999999` goes live free -> `update_listing()` trusts the request `plan_id` again; the charge must run after `wb_listora_listing_submitted` (Pro's plan handler), as on create.
- A 0-credit member lists free -> the pre-check in `submit_listing()` is gone, or `wb_listora_member_listing_cost()` no longer reads the setting.
- Deactivate refunds -> the SDK consumer's hold record is not written (`_wbcom_credits_wb-listora_listing_submission`).
- A 402 or 201 names a listing that does not exist -> the post-COMMIT existence check in `submit_listing()` is gone (a deadlock rolls the insert back while $wpdb only logs it).
- Both racing submissions charged -> `Credit_Lock::run()` no longer clears the SDK balance cache.
