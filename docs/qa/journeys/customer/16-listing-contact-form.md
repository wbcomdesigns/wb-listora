---
journey: listing-contact-form
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [contact-form, anti-spam, honeypot, rate-limit, leads-store, leads-dashboard-tab, leads-admin, leads-rest, leads-email]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing with a contact email (capture LISTING_ID); contact-form feature ON"
  - "A Subscriber-role member owns the listing (OWNER); a second Subscriber exists (OTHER)"
estimated_runtime_minutes: 5
covers_card: null
---

# Contact the owner: one form that emails and stores the lead

Covers `POST /listora/v1/listings/{id}/contact-form` (`Contact_Form::handle_submit`):
nonce + honeypot + `Anti_Spam::check()` + per-sender (3/hr) and per-listing (20/day)
caps. Every accepted message is saved in `wp_listora_leads` and emailed to the owner
(`lead_received`, Reply-To the sender). The owner reads leads under My Dashboard >
Leads, the admin under Listings > Leads, apps through `GET /listora/v1/leads`.
Pro no longer renders a form; its old `/listings/{id}/contact` route is a deprecated
alias of this one.

## Steps

### 1. One form renders, with or without Pro
- **Action**: visit `/listing/<slug>` as anonymous, at 1440 and 390.
- **Expect**: exactly one `.listora-contact-form` (name, email, optional phone, message; honeypot hidden) inside a card; no `.listora-lead-form` anywhere.

### 2. Happy path saves and emails
- **Action**: fill name/email/phone/message, submit.
- **Expect**: 200 and the inline success message. A `wp_listora_leads` row with the phone and `owner_id` = OWNER. Mailpit: "New message from <name> about <listing>" to OWNER, Reply-To = the sender, branded header and footer, a "See all your leads" link to `?tab=leads`.

### 2a. Screen readers hear the result, and the lead is counted once
- **Expect**: the message box is `aria-live="polite"`; Analytics (Pro) or Analytics Lite counts exactly one `lead` event for the listing, with Pro's Analytics feature on or off.

### 2b. The owner, the admin and REST see it; nobody else does
- **Action**: as OWNER open `/my-dashboard/?tab=leads`; as admin open Listora > Listings > Leads; `GET /listora/v1/leads` as OWNER, as OTHER, and `GET /listora/v1/leads/{id}` as OTHER.
- **Expect**: the lead in the owner's tab (Reply by email, tap-to-call) and in the admin table (period views, search, listing filter, Delete in the row menu); REST lists 1 for OWNER, 0 for OTHER, 404 for OTHER on the single route.

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
- **Expect**: `wb_listora_render_contact_form` filter can suppress the form; `wb_listora_contact_form_per_listing_daily_cap` can tune the cap; `wb_listora_before_contact_form_submit` returning a WP_Error refuses the message (no row, no email).

### 7. Retention and privacy
- **Action**: Listora > Listings > Leads, set "Keep leads for" to 3 months; Tools > Export / Erase Personal Data for the sender's email.
- **Expect**: leads older than 3 months removed; the export has a "Listora contact messages" group; the erase removes that sender's leads.
