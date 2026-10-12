---
group: contact-leads
plugin: wb-listora
scope: The listing Contact owner form, the lead record, the owner email, the owner's dashboard Leads tab, the admin Leads screen with retention and privacy tools, blocking and the lead counter
journeys: 4
site_profile: required
---
# Contact and leads (Free): what an agent checks here

## Scope
Covers the Contact owner form on a listing page, its anti-spam, nonce and rate limits, the saved lead (`listora_leads`), the `lead_received` email with Reply-To, the owner's My Dashboard > Leads tab, the admin Listora > Listings > Leads screen (views, search, listing filter, retention, Reply, Delete), the personal-data export and erase for leads, lead ownership following a listing's author, member blocking on the contact route, and the one `lead` analytics count.

Not covered here: the Block / Report buttons on reviews that create a block (`reviews`), the listing page outside the contact card (`listing-detail`), spam layers shared by every form and CAPTCHA configuration (`access-security`), email template editing and the Email Log screen as such (`emails-alerts`), Pro's analytics screens that display the lead total (`analytics-audit`), dashboard tabs in general (`dashboard-account`), quick-edit author change as a listing action (`admin-listings`), claim approval moving leads (`claims-verification`). Pro alias and hooks: `wb-listora-pro/docs/qa/journeys/contact-leads/_group.md` (run this Free group first).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Contact owner form on the listing page (sidebar) | `$SITE_URL/listing/{listing}/` | guest, member, editor, admin; never the listing's author | `includes/class-contact-form.php:340` (`render_form`), hooked on `wb_listora_after_listing_fields` priority 20 (`:66`), placed by `templates/blocks/listing-detail/sidebar.php:178` |
| 2 | Form result line (success, error) | after Send message | the sender | `.listora-contact-form__message`, `src/interactivity/store.js` `submitContactForm` |
| 3 | Owner dashboard Leads tab | `$SITE_URL{dashboard}?tab=leads[&lead_listing=ID&leads_page=N]` | owner of at least one listing with a lead | `templates/blocks/user-dashboard/tab-leads.php`, `includes/core/class-leads-dashboard-tab.php` |
| 4 | Admin Listora > Listings > Leads | `$SITE_URL/wp-admin/admin.php?page=listora-leads[&period=7d|30d|90d&listing=ID&s=term&paged=N]` | {admin} (cap `manage_listora_settings`) | `includes/admin/class-leads-page.php` |
| 5 | Retention form (inside 4) | "Keep leads for" select + Save | {admin} | `class-leads-page.php:110-120` |
| 6 | WordPress privacy tools: Export / Erase Personal Data | `wp-admin/export-personal-data.php`, `erase-personal-data.php` | {admin} | `includes/core/class-leads.php:45-46` (`register_exporter`, `register_eraser`) |
| 7 | Lead email (to owner) | Email Log or mail catcher | owner | `includes/workflow` notifications (`class-notifications.php:940`) |
| 8 | Contact REST routes | `POST /wp-json/listora/v1/listings/{id}/contact-form`; `GET/DELETE /listora/v1/leads[/{id}]`; `GET /listora/v1/settings/app-config` (`contact_path`) | per role | `includes/rest/class-leads-controller.php`, `class-contact-form.php:98-325` |
| 9 | Listing Quick Edit author field | Listings table > Quick Edit > Author | {admin} | `class-listing-columns.php`, lead follow via `Leads::follow_owner` |
| 10 | Settings > Features > Contact Owner Form | `admin.php?page=listora-settings` Features tab | {admin} | `includes/class-features.php:43` |

## Components on each surface
**1 Form**: `.listora-contact-form` (`data-wp-interactive="listora/directory"`) > `h3.listora-contact-form__title` "Contact owner"; `form.listora-contact-form__form` (`data-wp-context` with `listingId`, `contactPath`); fields in `.listora-submission__field`: Your name * (`input[name=name]`, id `wb-listora-contact-{id}-name`), Your email * (`input[type=email][name=email]`), Phone (optional) (`input[type=tel][name=phone][autocomplete=tel]`), Message * (`textarea[name=message][rows=4]`); honeypot wrapper `div[style="position:absolute;left:-9999px"][aria-hidden=true]` > `input[name=hp][tabindex=-1]`; hidden `_listora_contact_nonce`; `button.listora-btn--primary` "Send message" (label "Sending..." while busy); `.listora-contact-form__message[hidden][aria-live=polite][aria-atomic=true]`.

**3 Leads tab**: nav `#dash-tab-leads` "Leads" (present for members who own at least one listing, absent for members with none), panel `#dash-panel-leads.listora-dashboard__leads`; filter `form.listora-dashboard__filters` (select `#listora-lead-listing`, Apply, Clear) only when the member has leads on more than one listing; `ul.listora-dashboard__leads-list` of `li.listora-dashboard__lead-row`: `h3.listora-dashboard__lead-title` (sender name), meta `.listora-dashboard__lead-meta` (listing link, `.listora-dashboard__lead-date`), `p.listora-dashboard__lead-message`, actions `.listora-dashboard__lead-actions` ("Reply by email" mailto with `subject=Re: <title>`, phone button `tel:`); pager "Leads pages" with `leads_page`; empty state "No messages yet" / "No messages for this listing yet".

**4 Admin Leads**: shared header "Leads" and hub tabs `.listora-hub-tabs` (All Listings, Leads); retention `form.listora-inline-form` (`select#listora-leads-retention`: 3, 6, 12, 24 months, Until deleted; Save; muted privacy hint); notice area ("Lead deleted.", "Retention saved. Older leads were removed."); views All / Last 7 / 30 / 90 days; search "Search names, emails and messages"; filter "Listing ID or title"; table columns Received (sortable), From (name, mailto email, phone), Listing (link to edit screen), Message (clamped); row actions Reply, Delete (confirm); footer count "N leads", per-page, pagination; empty state "No leads yet".

**6 Privacy**: exporter group "Listora contact messages" (Listing, Name, Phone, Message, Sent) and eraser entry of the same name.

**7 Email**: subject "New message from <name> about <listing title>", header "New message from a visitor", phone and message, footer "This email was sent by <site name>.", link "See all your leads" to `?tab=leads`, header `Reply-To: <name> <email>`.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | guest, member, owner (no form), blocked pair (no form), feature OFF (no form), `wb_listora_render_contact_form` false | autologin personas; Settings > Features; filter in a mu-plugin |
| 1-2 | empty message, whitespace message, 5000-char message, markup, emoji, bad email, phone with letters, honeypot filled, missing nonce, spam keyword, 4 links, 3 links | type or POST per `listing-contact-form.md` steps 6-10 |
| 1-2 | 3 per hour then 4th, 20 per day per listing, another listing unaffected | repeat sends; delete `wb_listora_contact_*` transients |
| 3 | 0 leads, 1, many (> 20), leads on 1 vs 2+ listings, member with listings but no leads (tab present?), member with none (tab absent) | seed `${PREFIX}listora_leads` rows named `QA-J1 ...` |
| 4 | 0 rows, 1 row, 2+ pages, retention 3 / 12 / 0, stale delete link, search with no match | seed rows 13, 5 and 0 months old (script in `leads-admin-retention-privacy-ownership.md`) |
| 6 | export and erase by typed email and by member account email | Tools screens |
| all | 390, dark, unbridged theme, RTL if used | profile themes |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_features['contact_form']` (Settings > Features > Contact Owner Form) | on / off | form on surface 1 vanishes; route answers `403 listora_contact_form_disabled`; Pro alias disappears (404 `rest_no_route`) |
| `wb_listora_lead_retention_months` (admin Leads, "Keep leads for") | 3, 6, 12, 24, 0 | immediate prune and notice; daily cleanup honours it |
| `wb_listora_render_contact_form` filter | bool | form render |
| `wb_listora_contact_form_per_listing_daily_cap` filter (default 20) | int | 429 `listora_listing_rate_limit` threshold |
| Rate limits `contact` (3 per hour per sender and listing) | per sender | 429 `listora_rate_limit` with `Retry-After: 3600` |
| CAPTCHA / Akismet (Settings > Spam) | on / off | extra refusals; fail open |
| Member block between sender and owner | blocked / not | form hidden, route `403 listora_contact_blocked` |
| Pro Analytics feature | on / off | `lead` count still +1 exactly (Free records it) |

## Fixtures and setup
- Seed: `wp eval-file $WP_PATH/wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed`; baselines `LEADS_BASE`, `MAIL_BASE`, `COUNT_BASE` as in `listing-contact-form.md` Setup. A local mail catcher (Mailpit) or the Email Log screen.
- Reset throttles between runs: `DELETE FROM ${PREFIX}options WHERE option_name LIKE '%transient%wb_listora_contact%'`.
- Restore afterwards: `wb_listora_lead_retention_months` to 12, Contact Owner Form ON, remove blocks (`DELETE /me/blocks/{id}`), delete QA leads and email-log rows by id, fixtures `clean`.

## Run order
1. `contact-leads/listing-contact-form.md` - browser, critical: form, lead, email, owner inbox, spam and rate limits.
2. `contact-leads/leads-admin-retention-privacy-ownership.md` - browser, high: admin screen, retention, delete, privacy export and erase, ownership follow.
3. `contact-leads/blocked-member-cannot-contact-owner.md` - api, critical: both routes refuse a blocked pair.
4. `contact-leads/lead-counted-without-analytics.md` - api, high: exactly one lead row, one count, one email.
Then run: `wb-listora-pro/docs/qa/journeys/contact-leads/_group.md`.

## Group-specific micro checks
- G-01 Logged-in prefill: the form renders empty `name` and `email` for a signed-in member (the template prints no `value`). Report as a gap if the sender's display name and email are expected to be prefilled; assert at least that the member's typed values are kept after a 422 or 429.
- G-02 The honeypot field is off-screen via inline `position:absolute;left:-9999px`: at RTL and at 390 it must not create horizontal scroll (`scrollWidth <= clientWidth`) and must not be reachable by Tab (`tabindex=-1`) or announced (`aria-hidden`); autofill must not fill it (`autocomplete=off`).
- G-03 Success state: after Send the four fields are cleared (`form.reset()`), the success line is visible inside the form, `aria-live=polite` announces it once, the button label returns from "Sending..." to "Send message", and a second send of the same text is allowed until the rate limit.
- G-04 Error states keep input: a 400 invalid email shows "Please provide a valid email address." next to the form, keeps all four values, and focus moves to the message line or the email field; a 429 shows the rate-limit text, not the raw code.
- G-05 Phone handling: the phone is stored as typed, the dashboard `tel:` href contains only digits and `+` (`preg_replace( '/[^0-9+]/', '' )`), the visible button text is the phone as typed, and a phone of only letters yields no `tel:` button.
- G-06 Reply by email: the href starts `mailto:<email>?subject=Re%3A%20<listing title>` with the title URL-encoded (spaces, ampersand, quotes, emoji survive); the sender email is never shown as visible text except inside the button target.
- G-07 Dashboard Leads nav count (if the tab shows one) equals the number of rows across pages; with leads on 2+ listings the filter select lists only listings that have leads; `lead_listing=999999` is handled (empty state "No messages for this listing yet", no error).
- G-08 Date and time in the lead row use `date_format` + `time_format` of the site in the site timezone (`created_local`); at UTC+14 and UTC-12 the shown day differs from the UTC day by the offset and matches the admin Received column.
- G-09 Admin Received column: "Last 7 days" counts a lead created 6 days 23 hours ago and excludes 7 days 1 hour; views All / 7 / 30 / 90 have monotonic counts (7d <= 30d <= 90d <= All).
- G-10 Admin counts agree: "N leads" under the table equals `SELECT COUNT(*)` for the active view and search; after Delete it drops by one and the Delete notice appears once; the owner's Leads tab loses the same row.
- G-11 Retention select: the selected option matches the stored `wb_listora_lead_retention_months` (12 shows "12 months"; 0 shows "Until deleted"); saving the same value again still prints "Retention saved. Older leads were removed." and removes nothing newer than the window.
- G-12 Owner scope: the Leads tab never shows a lead for a listing the member no longer authors after Quick Edit (rows move with `owner_id`); a lead for a hard-deleted listing is still readable in admin with a plain-text listing label (no broken link).
- G-13 Block symmetry: after A blocks B, A's listing hides B's form and B's listing hides A's form; the 403 text on a forced POST does not say who blocked whom.
- G-14 Required marks: the `*` on name, email and message labels matches the `required` attributes; the phone label says "(optional)"; the form has exactly one `h3` and no duplicate ids when two contact forms render on one page (block plus sidebar).
- G-15 Email: exactly one `lead_received` row per accepted message, none for a 422, 429, honeypot or blocked send; the body contains the phone only when one was given; the "See all your leads" link opens the Leads tab for the owner and falls back sensibly for a logged-out click.

## Known defects (expected-fail)
| Journey | Step | Defect |
|---|---|---|
| `contact-leads/listing-contact-form.md` | 6 | REST `POST .../contact-form` with an empty `message` and a valid nonce returns `200 {sent:true}` and stores a lead with an empty message; only the browser form refuses it |

## Not covered yet
- Prefill and pre-validation behaviour for signed-in members (G-01) and the client-side "Please fill in all required fields." path (`listoraI18n.leadRequired`) on the real form.
- Message length limits: no maximum is asserted for name, email, phone or message (5000-character and multibyte boundary), nor how the dashboard row and the email truncate or wrap them.
- Admin Leads screen: Reply action target (mailto vs compose), sort by Received ascending, per-page 50/100, and paging at 2000+ leads (the scale step is Not verified).
- Lead deletion by the owner from the dashboard (the tab has no Delete control; REST `DELETE /leads/{id}` exists): confirm whether a UI is expected (three entry points rule).
- Owner notification preferences: a member who turns lead emails off (`Notifications::should_send`) still sees the lead in the tab; no journey asserts it.
- The contact card on pages that embed the listing detail block outside a single listing, and a theme override of the sidebar that drops the `wb_listora_after_listing_fields` hook (form silently missing).
