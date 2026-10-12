---
journey: lead-counted-without-analytics
plugin: wb-listora
priority: high
roles: [anonymous, member, owner, administrator]
covers: [contact-form, leads, analytics-lite, lead-analytics-count, card-10317861206]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published {listing} whose owner {owner} has an email"
  - "Pro active for steps 1, 2, 4 (Free-only: step 3)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: []
requires: "wb-listora-pro (steps 1, 2, 4)"
---

# A lead is counted exactly once, whatever else is switched off

## Why
A lead belongs to the FORM, not the reporting feature: Free saves it, fires `wb_listora_after_contact_form_submit( $listing_id, $lead, $request )`, and `Analytics_Lite::handle_lead_submission()` writes one `lead` count per message. Pro no longer has a form or toggle and must not also count; a missed or doubled count misreports the owner's leads.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Visitor-to-owner behaviour is `customer/16-listing-contact-form.md`; this file asserts only the counters.
- Counter: `${PREFIX}listora_analytics` (`event_type='lead'`, one row per listing per day, `count + 1`); lead table `${PREFIX}listora_leads`; email log `event_key=lead_received`. Record `COUNT_BASE`, `LEADS_BASE`, `MAIL_BASE` before each step; delete exactly the rows created (ids above the pre-run max).
- Code: `includes/class-contact-form.php:35,98,324`; `includes/features/class-analytics-lite.php:67,103,167`; `wb-listora-pro/includes/features/class-lead-form.php:34` (bridge only). Automated: Free `tests/integration/LeadRecordingTest.php`, Pro `tests/integration/LeadCountingTest.php`.

## Steps

### 1. Analytics ON, Pro active: exactly one count per message
- **Action**: send two messages as {guest} (name "QA-J1 sender", phone 555-0100) via `rest_do_request` with a real nonce.
- **Expect**: each `200 {"sent":true,"message":"Your message has been sent to the listing owner."}`; leads +1 each (name, email, phone stored); analytics `lead` count COUNT_BASE -> +1 -> +2; email log +1 `lead_received` each. Two messages from one browser both count (leads are deliberately not IP-deduped; views are).
- **Read-back**: the three counters.

### 2. Pro active, Analytics feature OFF: still counted
- **Action**: set `analytics` false in `wb_listora_pro_features`, send one message, restore the option.
- **Expect**: still +1 lead row, +1 analytics count, +1 email; the dashboard Leads figure moves, the charts stay dark.

### 3. Free only
- **Action**: Pro inactive, send one.
- **Expect**: one `lead` count and one leads row (Free is always loaded). Not run (needs Pro deactivated on the shared site).

### 4. No double count
- **Expect**: with Pro and Analytics on the count rose by exactly 1 per message (step 1). `Analytics::track_lead()` must not exist in Pro.
- **Read-back**: `grep -rn track_lead wb-listora-pro/includes` returns nothing.

### 5. Views stand-down does not swallow leads
- **Expect**: `wb_listora_pro_owns_analytics` returning true stops Free recording page VIEWS only (`class-analytics-lite.php:167`); it must not stop `record_lead()`.

### 6. Refused messages and boundary dates
- **Action**: a refused message (invalid email, honeypot) and a send at a UTC+14 / UTC-12 site timezone around midnight.
- **Expect**: refused messages do not count; the count lands on the site-local (or documented UTC) day consistently with views. Timezone rows Not verified live.
- **Read-back**: `event_date` of the row.

## Role ladder
| Actor | Expect |
|---|---|
| {guest} | public form, nonce required (missing nonce `403 listora_invalid_nonce`); counts |
| Logged-in member ({other}) | counts; adds sender identity |
| {owner} | no form on own listing; receives the lead under My Dashboard > Leads and the email |
| member-other (not the owner) | does NOT see it there |
| {editor} / {admin} (control) | read all under Listora > Listings > Leads |
| Blocked sender | `403 listora_contact_blocked` and no count (`customer/blocked-member-cannot-contact-owner.md`) |

## Pass criteria
1. Exactly +1 lead row, +1 `lead` analytics count and +1 email per accepted message, with or without Pro/Analytics.
2. Refused messages count nothing.
3. No Pro listener also counts.

## Fail diagnostics
| Symptom | File |
|---|---|
| Free-only site counts none | `includes/features/class-analytics-lite.php:67,103` |
| Double count | a Pro listener on `wb_listora_after_contact_form_submit` that also writes `lead` |
| Lead lost when mail fails | `class-contact-form.php` `Leads::record()` must run before the hook |
| 403 on a real form | nonce check `class-contact-form.php:160-177` |

## Not verified
Step 3 (Free only), blocked-sender path, 390px form layout, timezone rows.

## Cleanup
Delete the QA-J1 lead rows, restore the analytics count, delete their email-log rows by id (not by LIKE: other runs share the table).
