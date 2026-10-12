---
group: access-security
plugin: wb-listora
scope: Who may do what: capability map, member suspension, rate limits, spam layers, REST nonce gate, block-attribute and search-SQL trust boundaries
journeys: 8
site_profile: required
---
# Access and security: what an agent checks here

## Scope
Covers the Free plugin's gates: roles and capabilities (wp-admin screens, REST refusals, frontend submit gate), the suspended and role-stripped write gate, the rate limiter (public reads, per-account vs per-IP), spam layers on public writes, the contact-form REST nonce rule for authenticated apps, server-side trust of block attributes, and the search engine's SQL safety.

Does NOT cover: the review and claim moderation flows themselves (reviews, claims-verification), the contact form behaviour beyond its gate (contact-leads), the dashboard "Blocked Members" list (dashboard-account), settings screens layout (settings-setup), the search UI (directory-search). Pro visibility, Coming Soon and moderator surfaces are in the Pro folder of this group.

Most journeys here are `api` or `cli`; the browser work is the wp-admin screen sweep per role and the Users screen. Both still take the MICRO_CHECKS sweep on every browser surface below.

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Users list with "Listora" status column, Suspend / Reinstate row actions, bulk actions | `$SITE_URL/wp-admin/users.php` | {admin}, Listora moderators (editor cannot suspend without the cap) | `includes/admin/class-user-moderation.php` |
| 2 | Bulk result notice | `users.php?listora_bulk_done=suspend&listora_bulk_changed=N&listora_bulk_skipped=M` | {admin} | `class-user-moderation.php` (~:318) |
| 3 | User edit profile "Suspend member" block | `$SITE_URL/wp-admin/user-edit.php?user_id={id}` | {admin} | `class-user-moderation.php` (~:395) |
| 4 | Listora Dashboard | `admin.php?page=listora` | {admin}, {editor}, author, contributor | `includes/admin/class-admin.php`, `class-admin-hubs.php` |
| 5 | Reviews (Moderation hub) | `admin.php?page=listora-reviews` | {admin}, {editor} | `class-admin.php` (cap `moderate_listora_reviews`) |
| 6 | Claims (Moderation hub) | `admin.php?page=listora-claims` | {admin}, {editor} | `class-admin.php` (cap `manage_listora_claims`) |
| 7 | Settings | `admin.php?page=listora-settings` | {admin} only | `includes/admin/class-settings-page.php` |
| 8 | Listing Types | `admin.php?page=listora-listing-types` | {admin} only | `class-admin.php` (cap `manage_listora_types`) |
| 9 | Leads (Listings hub) | `admin.php?page=listora-leads` | {admin} | `includes/admin/class-leads-page.php` |
| 10 | Email log (Tools hub) | `admin.php?page=listora-email-log` | {admin} | `includes/admin/class-email-log-page.php` |
| 11 | All Listings | `edit.php?post_type=listora_listing` | {admin}, {editor}, author and contributor (own rows) | WordPress list table, `class-listing-columns.php` |
| 12 | Refusal page | any screen above as a role without the cap | subscriber, {other} | WordPress core ("Sorry, you are not allowed to access this page.") |
| 13 | Add Listing wizard and its "cannot submit" state | `$SITE_URL{add_listing}` | every role; {guest} gets the login prompt | `blocks/submission-form/` |
| 14 | Directory grid, map, featured block (seam probe) | `$SITE_URL{directory}` | {guest}, {admin} | `blocks/listing-grid/render.php`, `listing-map/render.php`, `listing-featured/render.php` |
| 15 | Block pages with hostile attributes (rendered output only) | a fixture page holding the blocks | {guest} | `includes/class-block-css.php`, `blocks/*/render.php` |
| 16 | REST refusals and 429 pages (read as JSON and headers) | `$SITE_URL/wp-json/listora/v1/...` | all rungs | `includes/class-rate-limiter.php`, `includes/rest/*` |

## Components on each surface
- **1 Users list**: `.wp-list-table.users`; column header "Listora" (`listora_member_status`); cell is `—` for normal users, `.listora-member-status--suspended` "Suspended" + "N ago" + reason (trimmed to 12 words), or `.listora-member-status--deactivated` "Deactivated by member"; row actions "Suspend" / "Reinstate" (nonce links); bulk dropdown entries "Suspend (Listora)" and "Reinstate (Listora)".
- **2 Notice**: `.notice.listora-notice.notice-success.is-dismissible`, text reports changed and skipped separately.
- **3 Profile block**: row "Suspend member": checkbox `#listora_member_suspended`, hint "Prevent this member from posting or editing content", reason input `#listora_member_suspended_reason` with help "Recorded for your own reference and shown in the Users list. It is not emailed to the member."
- **4 Dashboard / hubs**: `nav.listora-hub-tabs` (aria-label = hub name) with one `.listora-hub-tabs__tab.is-active[aria-current=page]`; sidebar menu "Listora" with hubs Dashboard, Listings, Moderation, Tools (only those the role can open).
- **5/6 Reviews, Claims**: `h1.listora-page-header__title` ("Reviews", "Claims"), hub tabs with the screen active, the moderation table with row actions, status filter.
- **7 Settings**: `nav.listora-settings-sidebar` (aria-label "Settings sections") with `a.listora-settings-nav-item[data-section]`, one `.is-active`; content pane `.listora-settings-pane`.
- **8 Listing Types / 9 Leads / 10 Email log**: page header, table, empty state; for editors none of these load.
- **11 All Listings**: status chips (All, Published, Pending, Draft ...), table, pager, row actions; author sees own rows only.
- **13 Add Listing**: stepper, type chooser, fields, submit; for a member whose role cannot submit: reason text plus link to My Dashboard.
- **14 Directory**: `.listora-grid__count` ("Showing 1-N of N listings"), `.listora-card` grid, map markers, featured cards.
- **15 Block output**: inline `<style>` per block (`.listora-block-<uniqueId>`), `--listora-*-columns` custom properties, card layout class.
- **16 REST**: JSON `{code, message, data:{status}}`; headers `Retry-After` on 429.

## States to force
| Surface | State | How |
|---|---|---|
| 1, 3 | suspended / not / deactivated by member | Suspend row action; `POST /me/deactivate` as the member |
| 1 | 0, 1, many suspended; long reason (200 chars) | suspend throwaway users with different reasons |
| 2 | all changed / some skipped (moderator and self selected) | bulk action with a mixed selection |
| 4-10 | each role | `?autologin={admin}`, `{editor}`, author, contributor, {owner} (subscriber), {guest} |
| 12 | refusal | open surface 7 as {editor} |
| 13 | role cannot submit | `wp user remove-cap {owner} submit_listora_listing` (restore after) |
| 14 | probe on / off | mu-plugin from search-engine-sql-safety-and-seams |
| 15 | hostile attributes | fixture page in block-attribute-trust-boundary |
| 16 | 429 | exceed a limiter from one IP on a scratch site |
| any | Pro active vs inactive | Pro adds `listora_moderator` role and audit log |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| Settings > Security > captcha provider (`wb_listora_settings.captcha_provider`) | none, recaptcha_v3 and others, with both keys | `/submit` returns `listora_captcha_missing` / `listora_captcha_failed`; with one key only, no captcha error |
| `wb_listora_rate_limit_config` filter (not a UI setting) | numbers per action | 429 threshold on that action only |
| `wb_listora_antispam_max_urls` filter | 0 disables | 4th link rejected only when cap is 3 |
| Contact form feature (`contact_form`) | on / off | contact route exists vs 403 `listora_contact_form_disabled` |
| "Delete data on uninstall" | on / off | caps removed from roles after uninstall |
| `search_cache_ttl` | 0 or seconds | seam probe results update immediately only at 0 |

## Fixtures and setup
- Profile: `docs/qa/sites/<site>.json`. Fixtures: `docs/qa/fixtures/2.0-fixtures.php`.
- Throwaway users are created per journey (prefix `QA-J1-`), one per role: `wp user create QA-J1-<role> qa-j1-<role>@example.test --role=<role>`.
- Application passwords: `wp user application-password create <login> qa-journey --porcelain`; revoke by uuid, never `--all`.
- Restore afterwards: captcha provider to `none`, every `mu-plugins/zz-qa-*.php` removed, `listora_rl_*` and `wb_listora_contact_*` transients deleted, `wp user delete` for QA users, removed caps re-added, `debug.log` truncated.

## Run order
1. role-cap-matrix (mixed, critical): cap table, wp-admin screens per role, REST refusal shape.
2. moderation-and-settings-screen-capabilities (browser, critical): editor opens Reviews and Claims, blocked from Settings and Types.
3. member-write-gate (mixed, critical): Users screen suspend/reinstate, then every write refused over an application password.
5. rest-nonce-gate-authenticated-app-writes (api, critical): app can contact an owner, anonymous cannot.
6. spam-protection-layers (api, high): honeypot, keywords, URL density, captcha, Retry-After.
7. listing-api-read-rate-limit (api, high): 120 guest reads a minute, members exempt.
8. rate-limit-counts-members-by-account (cli, high): members by account, guests by IP.
9. block-attribute-trust-boundary (api, critical): no attribute fatals the page or injects CSS.

Then run: wb-listora-pro/docs/qa/journeys/access-security/_group.md

## Group-specific micro checks
- G-01 On `users.php` a normal member's "Listora" cell is `—` (no word "Active"); a suspended member shows "Suspended", a relative time ("N minutes ago") and the reason cut at 12 words.
- G-02 After Suspend, the same row's action link reads "Reinstate" and vice versa; the bulk dropdown holds exactly "Suspend (Listora)" and "Reinstate (Listora)".
- G-03 The bulk notice's changed + skipped equals the number of ticked rows; ticking a moderator and your own row raises skipped by 2.
- G-04 As {editor} the sidebar has Dashboard, Listings and Moderation hubs only; the Moderation hub tab row shows Reviews and Claims with exactly one `aria-current="page"`.
- G-05 The refusal on Settings and Listing Types is WordPress's exact "Sorry, you are not allowed to access this page." for {editor}, subscriber and the Pro moderator; never a blank page or PHP notice.
- G-06 The Dashboard hub for an author/contributor lists own listings only; the row count in the All Listings chips equals the table total.
- G-07 Every 429 body's `data.retry_after` equals the `Retry-After` header; a second call 3 seconds later returns a number 2 to 4 lower.
- G-08 Contact-form 429 wording depends on the caller: signed-in "Too many messages. Try again in an hour." vs guest "Too many messages from your network. Try again in an hour."; both carry `Retry-After: 3600`.
- G-09 Suspension error text starts "Your account has been suspended" and the role-stripped text starts "Your account does not have a role on this site"; the codes `listora_member_suspended` and `listora_account_restricted` are never swapped.
- G-10 `DELETE /me` response lists `erasers_run` containing `wb-listora` (and `wb-listora-pro` when Pro is on) and the message "Your account and personal data have been permanently deleted."
- G-11 On the Directory with the seam probe, `.listora-grid__count` total equals the number of `.listora-card` across pages and equals the map marker count.
- G-12 The block output for `columns=0` prints `--listora-*-columns` of at least 1; no `class="..."` token holds a space, quote or angle bracket.
- G-13 The Settings sidebar `nav.listora-settings-sidebar` has exactly one `.is-active` item and its `data-section` matches `&tab=` in the URL.
- G-14 On the Add Listing wizard for a role without the submit cap the reason text names a next step and the My Dashboard link resolves; the dashboard no longer shows an Add Listing button.
- G-15 The Users profile "Suspend member" reason field helper says the reason is not emailed; after saving, the Users list shows that reason.

## Known defects (expected-fail)
- search-engine-sql-safety-and-seams step 2: an empty multi-value field filter returns 0 results and logs "Array to string conversion" instead of being treated as no filter.
- spam-protection-layers step 8: reviews and claims skip the keyword, URL and Akismet checks (moderation is the only guard).

## Not covered yet
- Users-screen suspend flows in a real browser at 390 and in dark (journeys read the strings from source).
- Profile "Suspend member" checkbox save path on `user-edit.php` (only the row action and bulk action are exercised).
- Reviews and claims REST write gates for the rate limiter in browser terms (429 toast on the dashboard form).
- Mobile wp-admin sweep (390) of Reviews, Claims, Leads, Email log per role.
- Leads and Email log REST routes by role, and uninstall cap clean-up outside a scratch site.
