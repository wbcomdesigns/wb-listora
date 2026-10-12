---
journey: listing-contact-form
plugin: wb-listora
priority: critical
roles: [anonymous, member, owner, other-member, editor, administrator]
covers: [contact-form, leads, lead-email, anti-spam, honeypot, rate-limit, leads-store, leads-dashboard-tab, leads-rest, leads-email, 2.0.0-leads]
prerequisites:
  - "Site reachable at $SITE_URL; Free active (Pro optional); Settings > Features > Contact form ON; directory public"
  - "Fixtures seeded: `wp eval-file $WP_PATH/wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed` ({listing} published, owned by {owner} with a real email), or create a QA-J1 listing owned by {owner}; {other} is a member without listings; a member with listings and no leads = {author2}"
  - "Local mail catcher (Mailpit) or the Email Log screen to read the owner email"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:customer/2.0-leads-guest-to-owner-inbox.md"]
---

# A visitor's contact message is saved as a lead, emailed to the owner and readable in their dashboard

## Why
Free owns the one contact form: a message is stored as a lead BEFORE any mail so a failed mail never loses an enquiry. A form that renders for the owner, leaks leads to others, or accepts spam breaks owner trust.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `$LISTING_URL` = permalink of the {listing}; `LISTING_ID`.
- Baselines: `LEADS_BASE` = `SELECT COUNT(*) FROM {prefix}listora_leads`; `MAIL_BASE` = `SELECT COALESCE(MAX(id),0) FROM {prefix}listora_email_log`.
- `POST /listora/v1/listings/{id}/contact-form` (`Contact_Form::handle_submit`, `includes/class-contact-form.php`): per-listing nonce `wb_listora_contact_form_{id}`, honeypot `hp`, `Anti_Spam::check()` (keyword blacklist, URL density, Akismet), filter `wb_listora_before_contact_form_submit`, then 3 messages per hour per sender-and-listing (user id when logged in, otherwise IP) and 20 per day per listing. Pro no longer renders its own form (`pro:customer/lead-form-deprecated-route.md`).
- The form allows 3 messages per hour per sender per listing: send exactly one message per rung and wait out the hour (or use another listing) before re-running; reset by deleting `wb_listora_contact_*` transients.

## Steps

### 1. One form renders, for visitors only
- **Action**: open `$LISTING_URL` as {guest}, then {other}, then {owner}, at 1440 and 390.
- **Expect (guest/other)**: exactly one `.listora-contact-form`, heading "Contact owner", fields `name` ("Your name *"), `email` ("Your email *"), `phone` ("Phone (optional)"), `message` ("Message *"), hidden `hp` honeypot (off-screen, `aria-hidden`, `tabindex="-1"`), hidden `_listora_contact_nonce`, button "Send message"; no `.listora-lead-form`. **(owner)** NO form on their own listing (positive control for the journey: {other} sees it on the same page). **(feature off)** no form for anyone and the route answers `403 listora_contact_form_disabled` ("Contacting listing owners is turned off on this site."). **(390)** no horizontal scroll, "Send message" >= 40px.
- **Read-back**: `document.querySelectorAll('.listora-contact-form').length===1`.

### 2. Guest sends a message with a phone number
- **Action**: Your name `J20 Guest`, email `j20-guest@example.com`, phone `+1 555 0100`, message `J20 lead message, please call me back.`, "Send message" (sending label "Sending...").
- **Expect**: `POST .../listings/<id>/contact-form` `200 {"sent":true,"message":"Your message has been sent to the listing owner."}`; `.listora-contact-form__message--success` (aria-live=polite, aria-atomic=true) shows that text, fields cleared (`form.reset()`), no page reload.
- **Read-back**: step 3.

### 3. The lead exists and belongs to the owner
- **Action**: `SELECT listing_id,owner_id,sender_id,name,email,phone,message FROM {prefix}listora_leads ORDER BY id DESC LIMIT 1`.
- **Expect**: count = LEADS_BASE + 1; `owner_id` = {owner}'s ID; `sender_id` = 0 (guest; the user id for a member); name/email/phone/message exactly as typed.

### 4. The owner is emailed, branded, and Reply goes to the sender
- **Action**: read the mail catcher, or `SELECT event_key,recipient,subject,success,headers FROM {prefix}listora_email_log WHERE id > MAIL_BASE ORDER BY id DESC LIMIT 1`.
- **Expect**: `event_key=lead_received`; `recipient`={owner}'s email; subject `New message from J20 Guest about <listing title>`; `success=1`; headers contain `Reply-To: J20 Guest <j20-guest@example.com>`; body carries the phone and message, header title "New message from a visitor", footer "This email was sent by <site name>.", and a "See all your leads" link to `?tab=leads` (`class-notifications.php:940`).
- **Read-back**: exactly one new log row. Mail content in Mailpit: Not verified live.

### 5. Owner reads it in My Dashboard > Leads (1440 then 390)
- **Action**: `$SITE_URL/my-listings/?autologin={owner}&tab=leads`.
- **Expect**: sidebar tab "Leads"; `.listora-dashboard__lead-row` with `.listora-dashboard__lead-title`="J20 Guest", listing link, message, "Reply by email" whose href starts `mailto:j20-guest@example.com?subject=Re%3A%20<listing title>` and a `tel:+15550100` button labelled "+1 555 0100". Empty state "No messages yet" / "When someone uses the contact form on your listing, their message lands here and in your inbox." At 390 the row stacks, no horizontal scroll, both buttons >= 40px.
- **Read-back**: row count equals leads for the owner's listings.

### 6. Required fields, bad email, empty message
- **Action**: submit with empty message in the browser; REST with `email:"nope"`; REST with `message:""` and a valid nonce.
- **Expect**: client "Please fill in all required fields." (`listoraI18n.leadRequired`), no request; `400 listora_invalid_email` "Please provide a valid email address."; `400` for an empty message.
- **expected-fail: contact form stores a lead for an empty message over REST** - currently `200 {sent:true}` and a lead with empty message is stored; only the browser form refuses it. Delete the row if it appears.
- **Read-back**: lead count unchanged after the 400s.

### 7. Who reads a lead (role ladder)
- **Action**: `GET /listora/v1/leads` and `GET /leads/<id>` as each rung of the table; UI `$DASH/?tab=leads`; admin Listora > Listings > Leads.
- **Expect**: per the table; a member with nothing to show has no "Leads" tab and `?tab=leads` falls back to Overview.
- **Read-back**: `X-WP-Total` equals SQL.

### 8. Honeypot trips silently
- **Action**: REST with `hp:"spam"` and a valid nonce.
- **Expect**: `200 {sent:true}` (bots are told they won), NO lead, NO email. Positive control: same body with `hp:""` creates the row (step 2).
- **Read-back**: lead count unchanged.

### 9. Nonce
- **Action**: REST without `_wpnonce`, logged out.
- **Expect**: `403 listora_invalid_nonce` "Security check failed. Reload the page and try again." Logged-in app clients (application password) pass by being authenticated.

### 10. Anti-spam
- **Action**: message "Visit my CASINO site" (case-insensitive substring), then 4 `http://` URLs, then 3 URLs.
- **Expect**: `422 listora_spam_keyword`, `422 listora_spam_urls` ("Your description contains too many links (max 3). Trim a few and resubmit."), `200` for exactly 3 (cap is "more than 3"). No row and no email on 422. Admins and editors exempt (`wb_listora_antispam_should_check`). Akismet only when active; fails open. Cross-surface matrix: `system/spam-protection-layers.md`.
- **Read-back**: lead count unchanged after the 422s.

### 11. Rate limits
- **Action**: four valid submissions from one IP (or user) to one listing within an hour; a 21st to the same listing in a day; one to another listing.
- **Expect**: 4th `429 listora_rate_limit` "Too many messages from your network. Try again in an hour." (user scope "Too many messages. Try again in an hour."), `Retry-After: 3600`, `data.retry_after` 3600; 21st `429 listora_listing_rate_limit` "This listing has received too many messages today. Try again tomorrow."; another listing unaffected.
- **Read-back**: delete transients `wb_listora_contact_*` to reset.

### 12. Filters and extension points
- **Expect**: `wb_listora_render_contact_form` can suppress the form; `wb_listora_contact_form_per_listing_daily_cap` tunes the daily cap; `wb_listora_before_contact_form_submit` returning a `WP_Error` refuses the message (no row, no email); `wb_listora_after_contact_form_submit` receives `{name,email,phone,message,lead_id}`.

## Role ladder
| Rung | Action | Expect |
|---|---|---|
| {guest} | step 1-3 | form, `200`, lead with `sender_id` 0 |
| {other} (member) | send | form present, lead `sender_id` = their id |
| {owner} | open listing / dashboard | NO form on own listing; Leads tab lists the lead; `GET /leads` `200` with the lead, `GET /leads/<id>` `200` (email, phone, message), `DELETE` own lead `200 {deleted:true}` |
| {other} (reads) | `GET /leads/<id>` | `404 listora_lead_not_found` (same answer as a missing ID); list empty |
| Member with other listings ({author2}) | `?tab=leads` | tab present, only leads of their listings, never "J20 Guest" |
| {guest} reads | `GET /leads` / `GET /leads/<id>` | `401` / `404` |
| {editor} | `GET /leads` | empty (leads are the owner's) |
| {admin} (control) | `admin.php?page=listora-leads` | lists all (admin journey: `admin/leads-admin-retention-privacy-ownership.md`), can Delete |
| Blocked sender | send | `403 listora_contact_blocked` (`customer/blocked-member-cannot-contact-owner.md`) |

## Pass criteria
1. Visitors and other members see one form; the owner does not.
2. An accepted message is stored (with phone and owner), emailed with Reply-To the sender, and shown in the owner's dashboard; every refusal stores and sends nothing.
3. Only the owner and admin can read a lead; others get an empty list or 404.
4. Honeypot, nonce, spam and rate-limit responses match the steps.
5. Step 6's empty-message REST case is the one known failing step.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Form renders twice / lead-form class | Pro bridge rendering | `includes/class-contact-form.php:340`, `wb-listora-pro/includes/features/class-lead-form.php:33` |
| Owner sees own form | author guard | `class-contact-form.php:345` |
| Honeypot stores a lead | order of checks | `class-contact-form.php:189` |
| Spam text accepted | anti-spam skipped | `class-contact-form.php:208`, `includes/class-anti-spam.php:35` |
| 4th send accepted | counter | `class-contact-form.php:250` |
| 200 but no lead / email sent but no lead | `Leads::record` failed / save order | `class-contact-form.php:314`, `class-leads.php::create_table`, activator/migrator |
| Lead saved, no email | mail toggle off or `wb_listora_should_send_notification` | `Notifications::should_send`, `class-notifications.php:165`, `:940` |
| No Reply-To | header block | `class-notifications.php` ~1560 |
| Other member reads a lead | owner scope | `includes/rest/class-leads-controller.php`, `Leads::user_can_manage` |
| Member with no leads sees a Leads tab | visibility rule | `class-leads-dashboard-tab.php` |

## Not verified
Browser click-through, Mailpit content, dark and unknown-theme screenshots, steps 11-12 and the 390 tap targets (measured 46px on the profile equivalent).

## Cleanup
`wp eval-file $WP_PATH/wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php clean` (also removes the leads on fixture listings); delete `wb_listora_contact_*` transients and any QA-J1 lead/email-log rows by id.
