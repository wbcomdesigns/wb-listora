---
journey: email-templates-editor
group: emails-alerts
actor: admin
mode: browser
plugin: wb-listora
priority: high
roles: [administrator, editor, member-owner]
covers: [email-templates-editor, email-variables-legend, custom-body-keeps-branded-shell, email-preview, email-test-send, email-reset-to-default, email-toggle-off, marketing-preferences-link, 2.0.0-email]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} session; Mailpit or the Email Log screen"
  - "Option wb_listora_email_templates empty (no customised emails): wp option get wb_listora_email_templates"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
supersedes:
  - ora:admin/2.0-email-templates-editor-variables-branded-shell.md
fails_on: unknown (the saved-override shell regression 74f6610f is asserted in emails-alerts/listing-status-emails.md step 4)
site_profile: required
---

# Email Templates editor: every email, its variables, and the branded header/footer around the owner's words

## Why
Settings > Notifications lists every email (Free and Pro) as one row with an on/off switch, recipient, **Edit template** and **Preview**. The editor writes one non-autoloaded option, `wb_listora_email_templates` (event => subject/body). A customised body is wrapped in the same header and "sent by" footer (and preferences link on marketing mail) as the built-in email, so owners cannot lose branding by editing words.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Screen: `admin.php?page=listora-settings&tab=notifications`.

## Steps

### 1. The list
- **Action**: open the tab as {admin}.
- **Expect**: grouped sections "Listings", "Reviews", "Claims", "Account" (plus Pro groups with Pro) each a `ul.listora-email-list`; each `li.listora-email-row` has a toggle (screen-reader text `Send "<email name>"`), name, one-line description, "To: <recipient>" and buttons "Edit template" and "Preview". "New message from a visitor" reads "Sent to the listing owner when someone uses the contact form. Replying reaches the sender. Every message is also saved under Leads."

### 2. The editor shows every variable (legend)
- **Action**: "Edit template" on "New message from a visitor".
- **Expect**: row expands (`aria-expanded=true`) to a Subject field (placeholder "Leave blank to keep the built-in subject"), a Message textarea (placeholder "Leave blank to keep the built-in message") and "Placeholders (click to copy):" chips `{listing_title} {listing_url} {sender_name} {sender_email} {sender_phone} {message} {dashboard_url} {site_name} {site_url}`; clicking a chip copies it.
- **Positive control**: "Listing approved" has a different legend (listing/author variables): the legend is per email.

### 3. A custom body keeps the branded shell
- **Action**: Subject `QA {sender_name} wrote about {listing_title}`; Message `Hi, {sender_name} says: {message} (phone {sender_phone}) {not_a_variable}`; Save Changes; then "Preview" (`dialog#listora-email-preview`) and send a real one (contact form on a listing: `contact-leads/listing-contact-form.md`) or the test send below.
- **Expect**: row shows the "Customized" badge; `wp option get wb_listora_email_templates --format=json` holds both strings; the email subject has variables replaced (plain text); body in the standard HTML shell: header block with the email's title and site name, your text in the content cell with the sender's values HTML-escaped, footer "This email was sent by <site name>." linked to the site; a literal `{not_a_variable}` stays visible; `<script>` and event attributes are stripped (`wp_kses_post`); `Reply-To` is still the sender; the plain-text part contains no `&quot;` or `&amp;`.
- **Real status email**: the same shell guarantee for a saved override on an approval email: `emails-alerts/listing-status-emails.md` step 4.

### 4. Test send from the screen
- **Action**: "Send a test email": Email = "New message from a visitor", Send to = your address (defaults to the admin email).
- **Expect**: "Sent"; a row in Tools > Email Log (event `lead_received`) and in the catcher with the customised subject/body; a bad address shows an inline error, never a fatal. (Test-send over REST and CLI: `emails-alerts/email-log-and-test.md`.)

### 5. Reset
- **Action**: tick "Go back to the built-in email on save", Save Changes.
- **Expect**: badge gone; `wb_listora_email_templates` has no `lead_received` key; the next send uses the built-in subject "New message from <name> about <listing>".

### 6. Switch an email off
- **Action**: untick the toggle for "New message from a visitor", Save; send a contact message; then turn it back on.
- **Expect**: the lead is saved (Leads) but no email is sent and none is logged.

### 7. Marketing mail keeps its preferences link
- **Action** (Pro, else record "not run"): customise a marketing-class email (e.g. a saved-search alert) and trigger it.
- **Expect**: footer still has "Manage email preferences".
- **expected-fail**: saved-search alert footer has no "Manage email preferences" link: `Email_Helpers::merge_shared_envelope()` (`wb-listora-pro/includes/class-email-helpers.php:139`) sets `is_marketing` false and `Advanced_Search::send_alert_email()` never overrides it, so `parts/footer.php` hides the link. Found by source read, not run live; confirm in the received mail, then decide whether alerts are marketing mail.

### 8. Boundary input (added)
- **Action**: subject/message empty, 5000 characters, only whitespace, markup `<img onerror>`, emoji, `{listing_title}{listing_title}`.
- **Expect**: empty falls back to the built-in; markup is stripped; emoji preserved; no fatal. Not verified live.

### 9. 390px
- **Expect**: row actions wrap under the name, editor fields full width, legend chips wrap, the preview dialog fits with a visible Close button >= 40 px.

## Role ladder

| Rung | Expect |
|---|---|
| {admin} | steps 1-9 |
| {editor} | `admin.php?page=listora-settings` blocked (needs `manage_listora_settings`); `POST /listora/v1/settings/notifications/test` 403 |
| {owner} | cannot reach the screen; still RECEIVES the customised email as the recipient (step 3) |

## Pass criteria
1. Every email appears with recipient, toggle, Edit, Preview; the legend is per email and complete.
2. A custom body is wrapped in branded header/footer; member values escaped; unknown tokens stay visible.
3. Test send and Preview use the saved template; Reset returns the built-in email; the off switch stops sends.
4. Only users with `manage_listora_settings` can edit or test.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Custom body has no header/footer | override returned raw text | `Email_Templates_Page::apply_override` -> `wrap_body`; `templates/emails/parts/header.php` / `footer.php` |
| Legend missing a variable | event map out of step | `Email_Templates_Page::get_event_map()` vs `Notifications::lead_received` |
| Override ignored on REST/cron sends | filters registered only in admin | `Email_Templates_Page::init` (always loaded, deferred to `init`) |
| List missing a row | notifications render | `includes/admin/class-settings-page.php` notifications tab |

## Not verified
Steps 7 (Pro) and 8; step 3 real send beyond the test send.

## Cleanup
Reset `lead_received` to the built-in email; re-enable its toggle; delete the QA lead.
