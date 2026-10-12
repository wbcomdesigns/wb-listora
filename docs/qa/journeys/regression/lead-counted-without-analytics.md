---
journey: lead-counted-without-analytics
plugin: wb-listora
priority: high
roles: [administrator, member, anonymous, owner]
covers: [contact-form, leads, analytics-lite]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "A published listing whose owner has an email: listing 17 (varundubey, admin@wbcomdesigns.com)"
estimated_runtime_minutes: 6
covers_card: 10317861206
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A lead is counted whatever else is switched off

A lead belongs to the FORM, not the reporting feature. In 2.0.0 Free owns the one contact form: `POST /listora/v1/listings/{id}/contact-form` (`includes/class-contact-form.php:35,98`, nonce action `wb_listora_contact_form_{id}`, args `name`, `email`, `phone`, `message`). It saves the lead (`Core\Leads::record()`, table `{prefix}listora_leads`), then fires `wb_listora_after_contact_form_submit( $listing_id, $lead, $request )` (`:324`): `Analytics_Lite::handle_lead_submission()` (`includes/features/class-analytics-lite.php:67`) writes one count into `{prefix}listora_analytics` (`event_type='lead'`, one row per listing per day, `count + 1`), and `Notifications::lead_received()` emails the owner (`event_key=lead_received`). Pro no longer has its own form or toggle (Pro readme 2.0.0: "the Lead Capture Form toggle is gone"); `wb-listora-pro/includes/features/class-lead-form.php:34` only bridges the hook. Pro must not also count.

Verified via: `rest_do_request()` with a real nonce, logged out, listing 17, then removal of exactly the rows created (ids above the pre-run max), 2026-10-12.

## Steps

### 1. Analytics ON, Pro active: exactly one count per message
- **Action**: send two messages (name "QA-J1 sender", phone 555-0100).
- **Expect**: each 200 `{"sent":true,"message":"Your message has been sent to the listing owner."}`; `listora_leads` +1 each (name, email, phone stored); analytics `lead` count 1 -> 2 -> 3; email log +1 `lead_received` each ("New message from QA-J1 sender about The Golden Fork"). Two messages from one browser both count (leads are deliberately not IP-deduped; views are).

### 2. Pro active, Analytics (and Lead form) feature OFF: still counted
- **Action**: set `analytics` false in `wb_listora_pro_features`, send one message, restore the option.
- **Expect**: still +1 lead row, +1 analytics count (verified), +1 email. The dashboard Leads figure moves; the charts stay dark.

### 3. Free only
- **Action**: Pro inactive, send one.
- **Expect**: one `lead` count and one leads row (Free is always-loaded). Not run (needs Pro deactivated on the shared site).

### 4. No double count
- **Expect**: with Pro active and Analytics on the count rose by exactly 1 per message (step 1). `Analytics::track_lead()` must not exist in Pro (`grep -rn track_lead wb-listora-pro/includes` returns nothing: verified).

### 5. Views stand-down does not swallow leads
- `wb_listora_pro_owns_analytics` returning true stops Free recording page VIEWS only (`class-analytics-lite.php:167`); it must not stop `record_lead()`.

### 6. Role ladder
- Anonymous visitor (step 1: public form, nonce required: a missing nonce returns 403 `listora_invalid_nonce`, verified), logged-in member (adds sender identity), the listing OWNER receives it under My Dashboard > Leads (`/my-listings/?tab=leads`) and the email, another member does NOT see it there, editor/admin read all under Listora > Listings > Leads. Admin is the control.
- A member the owner has blocked gets 403 "This message cannot be sent." (`class-contact-form.php:307`).

### 7. Cleanup
- Delete the QA-J1 lead rows, restore the analytics count, delete their email-log rows by id (not by LIKE: other runs share the table).

## Automated coverage

Free `tests/integration/LeadRecordingTest.php`; Pro `tests/integration/LeadCountingTest.php`.

## Fail diagnostics

| Symptom | File |
|---|---|
| Free-only site counts none | `includes/features/class-analytics-lite.php:67,103` `handle_lead_submission()` / `record_lead()` |
| Double count | a Pro listener on `wb_listora_after_contact_form_submit` that also writes `lead` |
| Lead lost when mail fails | `includes/class-contact-form.php` `Leads::record()` runs before the hook (saved first) |
| 403 on a real form | `class-contact-form.php:160-177` nonce check |

## Not verified

Step 3 (Free only), blocked-sender path, 390px form layout.
