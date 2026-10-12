---
group: emails-alerts
plugin: wb-listora
scope: Free email system - the Notifications tab (list, editor, preview, test send), the Email Log screen, status emails, the branded mail shell and the send-filter contract
journeys: 4
site_profile: required
---
# Emails and alerts (Free): what an agent checks here

## Scope
Covers every email Listora Free sends and the two owner screens that control and prove them: Settings > Notifications (18 emails in 4 groups, editor, preview, test send) and Tools > Email Log (list, drawer, resend, retention, export, clear). Also the approve/reject mails, the branded header/footer shell and the `wb_listora_send_notification` filter.
Does NOT cover: the Pro digest, Pro-only emails and saved-search alerts (Pro folder `wb-listora-pro/docs/qa/journeys/emails-alerts/_group.md`); the contact form that triggers `lead_received` and the Leads inbox (`contact-leads`); the moderation row actions that cause the status change (`admin-listings`); the nightly retention/cleanup job (`platform-lifecycle`); review/claim triggers (`reviews`, `claims-verification`).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Notifications tab, email list | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=notifications` | admin (`manage_listora_settings`) | `includes/admin/class-settings-page.php` `render_notifications_tab` |
| 2 | Template editor (expanded row) | surface 1, "Edit template" on any row | admin | `class-email-templates-page.php` `render_editor` |
| 3 | Preview dialog | surface 1, "Preview" on any row | admin | `class-settings-page.php` `dialog#listora-email-preview`, `assets/js/admin/email-templates.js` |
| 4 | Send a test email block | bottom of surface 1 | admin | `class-settings-page.php` (`#listora-notification-test-*`) |
| 5 | Email Log list | `$SITE_URL/wp-admin/admin.php?page=listora-email-log` (Tools hub) | admin | `includes/admin/class-email-log-page.php` + `class-admin-table.php` |
| 6 | Email Log row drawer | surface 5, click a row | admin | `Email_Log_Page::detail` |
| 7 | Clear-log confirm dialog | surface 5, "Clear log" | admin | `Email_Log_Page::render` (`data-confirm-*`) |
| 8 | The email as received | Mailpit/catcher, or surface 6 iframe | recipient | `templates/emails/*.php`, `templates/emails/parts/header.php`, `parts/footer.php` |
| 9 | Non-admin attempt | surfaces 1 and 5 as {editor}/{owner}/{guest} | editor, owner, guest | capability gate in `Email_Log_Page`, settings page |
| 10 | REST/CLI endpoints | `/wp-json/listora/v1/settings/notifications/{test,log,log/export,log/retention,preview}`; `wp listora test-email` | admin, cli | `includes/rest/class-settings-controller.php`, `includes/class-cli-commands.php` |

## Components on each surface
1. Notifications tab: `#listora-email-templates.listora-settings-pane`; one `section.listora-settings-block` per group (Listings 10, Reviews 4, Claims 3, Account 1); each `ul.listora-email-list` > `li.listora-email-row`: toggle checkbox (hidden 0 input + checkbox, `aria-describedby` the description), `.listora-email-row__name`, "Customized" badge (`.listora-badge--featured`, only when an override exists), `.listora-email-row__desc`, `.listora-email-row__to` ("To: ..."), buttons "Edit template" (`[data-listora-email-edit]`, `aria-expanded`, `aria-controls`) and "Preview" (`[data-listora-email-preview]`); Save Changes button.
2. Editor (`.listora-email-row__editor`, `hidden` until opened): Subject input `#listora-email-subject-{event}` (`.listora-email-templates__subject`), Message textarea `#listora-email-body-{event}` (rows 6), legend `#listora-email-legend-{event}` with "Placeholders (click to copy):" and `code.listora-email-templates__placeholder` chips (`.is-copied` after click), "Go back to the built-in email on save" checkbox (`.listora-email-templates__reset`, only when customised).
3. Preview dialog: `dialog#listora-email-preview`, title h2, "Close" (`[data-listora-preview-close]`), Subject line (`[data-listora-preview-subject]`), `iframe.listora-email-preview[sandbox]` titled "Email preview", footnote "Sample details, your saved template. Nothing is sent."
4. Test block: select `#listora-notification-test-event` (optgroups per group), email input `#listora-notification-test-recipient` (default admin email), button `#listora-notification-test-send` "Send test email", status `#listora-notification-test-status[aria-live=polite]`.
5. Email Log: page header "Email Log" + description; header actions "Export CSV" and "Clear log"; notice area (`.notice.listora-notice`); retention form `#listora-email-log-retention` select + "Save" + helper text; `.listora-filter-tabs` All / Sent / Failed with `.listora-filter-tab__count`; search input "Search subjects"; filter selects "Any email" and "Recipient email"; count text "N emails"; `table.listora-table` columns Sent (sortable), Email (label + clamped subject + "Resent" badge), To, Result (Sent/Failed badge + error); row actions "Send again" (in the row menu) or primary "Resend" on failed rows; `.listora-pagination-bar` with per-page chooser; empty state "No emails logged yet".
6. Drawer: `.listora-drawer` head with close, To, Subject, `iframe.listora-email-preview[sandbox][title="Email content"]`.
7. Confirm dialog: title "Clear the email log?", message "Every entry is deleted. Emails already sent are not affected.", Confirm/Cancel.
8. Received email: header block (title + site name), content cell, footer "This email was sent by <site>." (linked), "Manage email preferences" only on marketing mail, plain-text part.
9. Denied states: 403 "You do not have permission to manage the email log." and WP "Sorry, you are not allowed" on the settings page.
10. API/CLI: JSON shapes in the journeys; `wp listora test-email` list/send/unknown-key output.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | no overrides | `wp option delete wb_listora_email_templates` |
| 1 | one customised / all customised | Save a subject on one / every row; "Customized" badge appears |
| 1 | email switched off | untick toggle, Save; send the trigger, expect nothing logged |
| 1 | Pro active (extra groups/rows) | activate wb-listora-pro; run Pro `_group.md` too |
| 2 | opened/closed, long content | click Edit template twice; 5000-char message |
| 4 | invalid recipient / mail failure | type `not-an-email`; `add_filter('pre_wp_mail','__return_false')` |
| 5 | empty / one / many / 5000+ | `DELETE .../log`; one test send; seed rows with `${PREFIX}listora_email_log` INSERT (QA subject prefix) |
| 5 | failed row | seed `success=0, error='QA failure'` or break SMTP |
| 5 | filtered to zero | search a string that matches nothing |
| 5 | retention variants | choose each of 7/15/30/90 days/1 year/Forever, Save |
| 5-6 | resent chain | "Send again" on a row; new row carries `resent_from` |
| all | 1440 / 1024 / 390, light/dark admin colour scheme | resize; wp-admin profile colour scheme |
| 8 | custom body | set Subject/Message on `listing_approved`, approve a pending listing |
| 9 | each role | log in as {editor}, {owner}, {guest} |
| RTL | not supported by journeys yet | `list-page-rtl.css` exists; switch site language to RTL and open surfaces 1 and 5 |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_email_templates` (Notifications > Edit template) | subject/body per event | "Customized" badge on the row; Preview and sent mail use the text inside the branded shell |
| `wb_listora_settings[notifications][event]` (row toggle) | 0 / 1 | off = no mail and no log row for that event |
| Email Log retention (`#listora-email-log-retention`) | 7, 15, 30, 90 (default), 365, 0 (Forever) | helper text under the select; older rows removed on Save and nightly |
| Test recipient field | any email | where the `[TEST]` mail lands; defaults to admin email |
| Filter `wb_listora_should_send_notification`, `wb_listora_notification_log_enabled` | true/false | skipped sends fire `wb_listora_notification_skipped`; log rows stop when disabled |
| Pro active | on/off | extra email rows and Tools hub tabs (Audit Log, Webhooks) |

## Fixtures and setup
- `wp eval-file docs/qa/fixtures/2.0-fixtures.php seed` (personas {owner}, {other}, {editor}, {admin}); `clean` afterwards.
- Mail catcher (Mailpit) or any working `wp_mail`; recipient `qa-test@example.test`.
- Baseline: `wp option get wb_listora_email_templates`, `SELECT MAX(id) FROM ${PREFIX}listora_email_log` (`MAIL_BASE`).
- Pending listing titled `QA approval mail` owned by {owner} for the status-email journey.
- Restore: reset every template, re-enable toggles, delete log rows with the QA recipient/subject, delete QA listings and leads.

## Run order
1. `email-templates-editor.md` - browser, high: list, legend, branded shell, preview, reset, off switch.
2. `email-log-and-test.md` - mixed, high: Email Log screen, test send (screen, REST, CLI), resend, retention, export, clear.
3. `listing-status-emails.md` - mixed, high: approve/reject send exactly one logged mail; saved-template shell sentinel.
4. `send-notification-to-arg.md` - cli, high: 4-argument filter contract Pro's digest depends on.
Then run: `wb-listora-pro/docs/qa/journeys/emails-alerts/_group.md` (Pro active).

## Group-specific micro checks
- G-01 Notifications tab row count equals the "Email" options in `#listora-notification-test-event` and equals the keys printed by `wp listora test-email` (18 on Free alone).
- G-02 Group section headings are Listings / Reviews / Claims / Account in that order and the row counts are 10 / 4 / 3 / 1.
- G-03 Each toggle's screen-reader name is `Send "<email name>"` and its `aria-describedby` points at that row's description element.
- G-04 Clicking "Edit template" flips that button's `aria-expanded` to true and un-hides exactly one `.listora-email-row__editor`; clicking again hides it; focus stays on the button.
- G-05 The legend differs per email: `lead_received` shows `{sender_name}`, `listing_approved` does not.
- G-06 "Customized" badge count equals the number of keys in `wb_listora_email_templates` that hold a non-empty subject or body.
- G-07 The "Go back to the built-in email on save" checkbox exists only on customised rows.
- G-08 Email Log helper text matches the select: 90 -> "Entries older than 90 days are removed each day.", Forever -> "Entries are kept until you clear the log."
- G-09 View tab counts All = Sent + Failed, and the "N emails" count text equals the active tab's count when no filter is set.
- G-10 Sent column sorts ascending/descending and the arrow state matches; default order is newest first and a Resent row sits above the row it repeated.
- G-11 A failed row shows primary "Resend" and the error text under the red "Failed" badge; a sent row shows "Send again" inside the row menu, never both.
- G-12 The long subject in the Email column is clamped to the cell (no horizontal page scroll at 390) and the full subject appears in the drawer.
- G-13 Row-action confirm text names the recipient: "The same email goes to <recipient> again."
- G-14 Drawer iframe is sandboxed (no scripts), titled "Email content", and its scroll is inside the drawer at 390.
- G-15 After "Clear log" the tab counts read 0 / 0 / 0 and the empty state icon and copy show; Export CSV with 0 rows still returns a header row.
- G-16 Received email subject is plain text (no `&amp;`, no `{token}` left for known variables), and the plain-text part has no `&quot;`.
- G-17 Footer text "This email was sent by <site>." links to `$SITE_URL` and the "Manage email preferences" link is absent on transactional mail.
- G-18 Test send subject starts with `[TEST]` on screen, REST and CLI alike, and the status line `#listora-notification-test-status` clears or changes between two sends.
- G-19 Preview dialog closes with Close and Esc, returns focus to the Preview button that opened it, and its Close button is >= 40 px at 390.
- G-20 Dates in the Sent column follow the site's date/time format and time zone, not UTC, when the site zone is UTC+14.

## Known defects (expected-fail)
- `email-templates-editor.md` step 7: a saved-search alert footer lacks "Manage email preferences" (`is_marketing` is false in Pro's `merge_shared_envelope`); found by source read, not yet run live.

## Not covered yet
- Email Log scale (5000+ rows) is a step marked "not verified"; pagination/per-page persistence (`wb_listora_table_per_page_email_log`) has no step.
- Sort order and filter combinations in the Email Log (event + recipient + view + search together) are not asserted.
- No journey sends each of the 18 emails and checks its content/variables (only `lead_received`, `listing_approved`, `listing_rejected` are exercised).
- Expiry/renewal/draft-reminder/review-reminder/claim emails have no trigger journey in this group (owners: `platform-lifecycle`, `reviews`, `claims-verification`) and nobody checks they log once.
- RTL, dark admin colour scheme and the 1024 breakpoint of both screens are untested; the emailed HTML in dark-mode mail clients is untested.
