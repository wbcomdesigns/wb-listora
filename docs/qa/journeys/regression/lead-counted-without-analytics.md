---
journey: lead-counted-without-analytics
plugin: wb-listora
priority: high
roles: [administrator, subscriber, anonymous]
covers: [contact-form, lead-form, analytics-lite]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing with an owner who has an email address"
estimated_runtime_minutes: 6
covers_card: 10317861206
---

# A lead is counted whatever else is switched off

Regression sentinel for lead recording.

## Background

Lead counting has now moved twice for the same reason. It lived in Pro's `Lead_Form`, which does not load while the Lead Capture toggle is off (card 10226117669). It was moved to Pro's `Analytics`, which does not load while the Analytics toggle is off (card 10317861206). Either way an owner switched a contact form on, left the other feature off, and got enquiries in their inbox against a Leads figure that stayed 0. A Free-only site counted none at all, even though Free owns the contact form.

A lead belongs to the FORM, not to the reporting feature. Turning Analytics off should stop the charts, not stop the data.

`Analytics_Lite::record_lead()` in Free owns it now, always loaded, writing the same `lead` rows into the same table Pro reads. **Pro must not also write them** - the two-writers version double-counts every enquiry on every Pro site, which is the opposite failure and much harder to notice.

## Steps

### 1. Free-only site counts a lead
- **Action**: Pro inactive. Send a message through the contact form on a listing.
- **Expect**: one `lead` row for that listing in `{prefix}listora_analytics`.
- **On fail**: `includes/features/class-analytics-lite.php` → `record_lead()` / its `init()` registration.

### 2. Pro active, Analytics OFF - the reported case
- **Action**: Settings → Features → turn Analytics off. Send a message.
- **Expect**: still counted. The dashboard Leads figure moves; the charts stay dark.

### 3. Pro active, Analytics ON - exactly one writer
- **Action**: turn Analytics back on. Send one message.
- **Expect**: the count goes up by exactly **1**, not 2. A listener re-added to Pro's `Analytics` is the failure this step exists for.
- **Check**: `Analytics::track_lead()` does not exist. It was deleted, not left unhooked.

### 4. Both forms feed the same counter
- **Action**: with Lead Capture on, submit Pro's lead form; with it off, submit Free's contact form.
- **Expect**: one lead each. Each route fires only its own hook.

### 5. Leads are not IP-deduped
- **Action**: send two messages about the same listing from the same browser.
- **Expect**: 2. Views are deduped per IP; leads deliberately are not - two enquiries are two emails the owner has to answer.

### 6. The view stand-down does not swallow leads
- **Verify**: `wb_listora_pro_owns_analytics` returning true stops Free recording page VIEWS and must not stop it recording leads. That conflation is how they went missing.

## Automated coverage

Free `tests/integration/LeadRecordingTest.php` (6 tests) and Pro `tests/integration/LeadCountingTest.php` (4 tests, including the no-double-count assertion).
