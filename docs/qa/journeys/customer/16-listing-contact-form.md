---
journey: listing-contact-form
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [contact-form, anti-spam, honeypot, rate-limit]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing with a contact email (capture LISTING_ID); contact-form feature ON"
  - "Pro lead_form feature OFF (the two never co-render)"
estimated_runtime_minutes: 5
covers_card: null
---

# Anonymous contact form on listing detail (with anti-spam gate)

Covers `POST /listora/v1/listings/{id}/contact-form` (`Contact_Form::handle_rest_submission`)
— anonymous lead-gen with nonce + honeypot + `Anti_Spam::check()` (keyword
blacklist + URL-density cap + Akismet, fails open) + per-IP-per-listing (3/hr)
and per-listing (20/day) caps. Previously unguarded.

## Steps

### 1. Form renders when feature on + Pro lead_form off
- **Action**: visit `/listing/<slug>` as anonymous.
- **Expect**: the contact form renders (name/email/message + honeypot field hidden). `Contact_Form::should_render()` returns true only because `wb_listora_pro_feature_enabled('lead_form')` is false.

### 2. Happy path
- **Action**: fill valid name/email/message, submit → `POST /listings/{id}/contact-form` with a valid nonce.
- **Expect**: 200; owner receives the notification email; success message shown inline. Honeypot empty.

### 3. Honeypot trips silently
- **Action**: submit with the honeypot field populated.
- **Expect**: request is rejected (treated as success client-side to not tip off bots, but NO email sent / no row recorded).

### 4. Anti-spam keyword / URL density
- **Action**: submit a message heavy with URLs / blacklisted keywords.
- **Expect**: `Anti_Spam::check()` rejects (or flags) — owner email not sent. (Akismet outage → fails open, still subject to keyword/URL caps.)

### 5. Rate limits
- **Action**: submit 4 times from the same IP within an hour for LISTING_ID.
- **Expect**: the 4th is rejected (per-IP-per-listing 3/hr cap). Per-listing 20/day cap likewise enforced.

### 6. Filter escape hatch
- **Expect**: `wb_listora_render_contact_form` filter can suppress the form; `wb_listora_contact_form_per_listing_daily_cap` can tune the cap.
