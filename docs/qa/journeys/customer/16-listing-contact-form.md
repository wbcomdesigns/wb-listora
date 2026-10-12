---
journey: listing-contact-form
plugin: wb-listora
priority: high
roles: [anonymous, member, owner, administrator]
covers: [contact-form, anti-spam, honeypot, rate-limit, leads-store, leads-dashboard-tab, leads-admin, leads-rest, leads-email]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing owned by a member (OWNER) so the owner differs from the admin; a second member (OTHER). Create QA-J1-<n> and delete it afterwards."
  - "Contact form feature ON (Listora > Settings > Features), Directory public (Pro visibility = public)"
estimated_runtime_minutes: 6
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Contact the owner: one form that stores the lead and emails the owner

`POST /listora/v1/listings/{id}/contact-form` (`Contact_Form::handle_submit`): per-listing nonce, honeypot `hp`, `Anti_Spam::check()` (keyword blacklist, URL density, Akismet), the `wb_listora_before_contact_form_submit` filter, then caps of 3 messages per hour per sender-and-listing (user id when logged in, otherwise IP) and 20 per day per listing. An accepted message is saved in `wp_listora_leads` first, then emailed to the owner (`lead_received`, Reply-To the sender). Pro no longer renders its own form.

Verified via: curl/DOM for the rendered form, REST and DB for every submit outcome, REST per role for leads. The form submit was not clicked in a browser (the shared Playwright browser was being driven by other sessions); the client script path was read in `src/interactivity/store.js`.

## Steps

### 1. One form renders, for visitors only
- **Action**: open `/listing/<slug>/` as anonymous, then as OTHER, then as OWNER, at 1440 and 390.
- **Expect (anonymous / OTHER)**: exactly one `.listora-contact-form` with heading "Contact owner" and fields `name` ("Your name *"), `email` ("Your email *"), `phone` ("Phone (optional)"), `message` ("Message *"), a hidden `hp` honeypot (`aria-hidden`, `tabindex="-1"`), a `_listora_contact_nonce` hidden input, and a "Send message" submit button. No `.listora-lead-form` anywhere.
- **Expect (OWNER)**: NO contact form on their own listing (observed in source: `render_form` returns early for the author). POSITIVE CONTROL for the whole journey: OTHER sees it on the same page.
- **Expect (feature off)**: no form for anyone, and the REST route answers `403 listora_contact_form_disabled` ("Contacting listing owners is turned off on this site.").
- **Expect (390)**: no horizontal scroll, "Send message" >= 40px (measured 46px on the profile equivalent; re-measure here).

### 2. Happy path saves a lead and emails the owner
- **Action**: fill name, email, phone, message; click "Send message".
- **Expect**: `POST .../listings/<id>/contact-form` `200 {sent:true, message:"Your message has been sent to the listing owner."}`; inline `.listora-contact-form__message` (it is `aria-live="polite" aria-atomic="true"`) shows that text, the form resets. Sending state label is "Sending…".
- **DB**: `wp_listora_leads` row with `listing_id`, `owner_id` = OWNER, `sender_id` = 0 for a visitor (user id for a member), `name`, `email`, `phone`, `message`.
- **Mailpit**: "New message from <name> about <listing>" to OWNER, Reply-To = the sender, branded header/footer, link "See all your leads" to `?tab=leads` (`includes/workflow/class-notifications.php:940`). Not verified on this refresh (no Mailpit read).

### 3. Required fields and bad email
- **Action**: submit with an empty message in the browser, then REST with `email: "nope"`, then REST with `message: ""`.
- **Expect**: client message "Please fill in all required fields." (`listoraI18n.leadRequired`) and no request; REST `400 listora_invalid_email` "Please provide a valid email address."
- **Observed gap (PRODUCT-BUG)**: REST with `message: ""` and a valid nonce returns `200 {sent:true}` and stores a lead with an empty message; only the browser form refuses it. Until fixed, expect that 200 and delete the row; once fixed, expect a 400.

### 4. Who sees the lead (role ladder)
- **Action**: `GET /listora/v1/leads` and `GET /listora/v1/leads/<id>` as OWNER, OTHER, anonymous, editor, admin; plus the UI: OWNER `$DASH/?tab=leads`, admin Listora > Listings > Leads.
- **Expect**: OWNER list contains the lead (JSON array) and the single route returns `200` with email, phone, message; OTHER list is empty and the single route `404 listora_lead_not_found`; anonymous list `401`, single route `404`; editor list empty (leads are the owner's); admin lists all (control) and can Delete in the row menu.
- **UI**: OWNER's Leads tab shows the message with "Reply by email" (`mailto:` with subject "Re: <listing>") and tap-to-call; empty state "No messages yet" / "When someone uses the contact form on your listing, their message lands here and in your inbox."

### 5. Honeypot trips silently
- **Action**: REST with `hp: "spam"` and a valid nonce.
- **Expect**: `200 {sent:true}` (bots are told they won), NO `wp_listora_leads` row, NO email. POSITIVE CONTROL: the same body with `hp: ""` creates the row (step 2).

### 6. Nonce
- **Action**: REST without `_wpnonce` while logged out.
- **Expect**: `403 listora_invalid_nonce` "Security check failed. Reload the page and try again." Logged-in app clients (application password) pass by being authenticated.

### 7. Anti-spam
- **Action**: message "Visit my CASINO site" (blacklist substring, case-insensitive), then a message with 4 `http://` URLs, then 3 URLs.
- **Expect**: `422 listora_spam_keyword` ("Your submission contains content that looks like spam. Please review and try again."), `422 listora_spam_urls` ("Your description contains too many links (max 3). Trim a few and resubmit."), then `200` for exactly 3 URLs (POSITIVE CONTROL: the cap is "more than 3"). No row and no email on 422. Admins and editors are exempt (`wb_listora_antispam_should_check`). Akismet runs only when Akismet is active; it fails open.

### 8. Rate limits
- **Action**: four valid submissions from one IP (or user) to one listing within an hour.
- **Expect**: the 4th returns `429 listora_rate_limit`, "Too many messages from your network. Try again in an hour." (user scope: "Too many messages. Try again in an hour."), header `Retry-After: 3600`, `data.retry_after` 3600. A 21st message to the same listing in a day returns `429 listora_listing_rate_limit` ("This listing has received too many messages today. Try again tomorrow."). Another listing is unaffected (counter is per listing).
- **Reset** (QA): delete transients `wb_listora_contact_*`.

### 9. Blocked sender
- **Expect**: a member the owner has blocked (or who blocked the owner) gets `403 listora_contact_blocked` "This message cannot be sent." and no form is rendered to them. Source-verified; not executed.

### 10. Filters and extension points
- `wb_listora_render_contact_form` can suppress the form; `wb_listora_contact_form_per_listing_daily_cap` tunes the daily cap; `wb_listora_before_contact_form_submit` returning a `WP_Error` refuses the message (no row, no email); `wb_listora_after_contact_form_submit` receives `{name,email,phone,message,lead_id}`.

### 11. Retention and privacy
- **Action**: Listora > Listings > Leads, "Keep leads for" = 3 months; Tools > Export / Erase Personal Data for the sender's email.
- **Expect**: the daily cleanup deletes leads older than the window (`Leads::prune`, option `wb_listora_lead_retention_months`, default 12); the export has a group "Listora contact messages"; erase removes that sender's rows (matched by email or `sender_id`). Not executed on this refresh.

## Pass criteria

1. Visitors and other members see one form; the owner does not.
2. An accepted message is stored (with phone and owner) and emailed; every refusal stores and sends nothing.
3. Only the owner and admin can read a lead; everyone else gets an empty list or 404.
4. Honeypot, nonce, spam and rate-limit responses match the table above.

## Fail diagnostics

| Symptom | Likely cause | File:line |
|---|---|---|
| Form renders twice / lead-form class present | Pro bridge still rendering | `includes/class-contact-form.php:340` (`render_form`), `wb-listora-pro/includes/features/class-lead-form.php:33` |
| Owner sees own form | author guard | `includes/class-contact-form.php:345` |
| Honeypot stores a lead | order of checks | `includes/class-contact-form.php:189` |
| Spam text accepted | anti-spam skipped | `includes/class-contact-form.php:208`, `includes/class-anti-spam.php:35` |
| 4th send accepted | counter | `includes/class-contact-form.php:250` |
| No lead row but email sent | save order | `includes/class-contact-form.php:314` (`Leads::record`) |
| Other member reads a lead | owner scope | `includes/rest/class-leads-controller.php` |
| No owner email | listener | `includes/workflow/class-notifications.php:165`, `:940` |

## Not verified

- Browser click-through and Mailpit content; dark mode and unknown-theme screenshots.
- Steps 9 and 11 (source-verified only).
