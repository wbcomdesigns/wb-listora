---
group: plans-expiry-renewal
plugin: wb-listora
scope: Listing expiry (setting, dated types, new types), the owner renewal flow (reminder, dashboard row, modal, quote, renew REST), and the Free half of plans and monetization.
journeys: 2
site_profile: required
---
# Plans, expiry and renewal (Free): what an agent checks here

## Scope
Covers: the "Listing Lifecycle" settings, who gets an expiry date, the expiry cron pass, the dashboard Listings tab expiry affordances (countdown pill, Renew buttons, expiry filter), the renew modal and the `renewal-quote` / `renew` REST routes, reminder and renewed emails as evidence only.
Does NOT cover: plan CRUD, plan picker, coupons, credit spend (Pro folder of this group, see "Then run"); the Credits tab, Buy Credits and gateways (`credits-checkout`); the dashboard shell, tab order and big-list paging (`dashboard-account`); email template content and the Email Log screen (`emails-alerts`); listing status labels in wp-admin (`admin-listings`); the expired-listing visitor note on the single page (`listing-detail`).
Free-only run: deactivate Pro first (`wp plugin deactivate wb-listora-pro`). With Pro active and Monetization on, expiry is forced on and the free-directory default cannot be seen.

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Settings > General > Listing Lifecycle | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=general` | {admin} | `includes/admin/class-settings-page.php` |
| 2 | Settings > Features (Renewal toggle) | `admin.php?page=listora-settings&tab=features` | {admin} | `includes/admin/class-settings-page.php`, `includes/class-feature-manager.php` |
| 3 | Listing type editor, expiry field | `admin.php?page=listora-listing-types&action=new` (or edit) | {admin} | `includes/admin/` listing types page |
| 4 | Dashboard Listings tab | `$SITE_URL{dashboard}?tab=listings` | {owner}; {other} sees only own rows; {guest} gets the login prompt | `templates/blocks/user-dashboard/tab-listings.php`, `blocks/user-dashboard/render.php` |
| 5 | Dashboard Listings tab, expiry filter active | `$SITE_URL{dashboard}?tab=listings&listings_filter=expiring` (also `expired`, `active`) | {owner} | `tab-listings.php` (filter form, empty state) |
| 6 | Renew modal (opened from a row) | click `.listora-dashboard__renew-btn` or the row-menu "Renew" on surface 4 | {owner} | `tab-listings.php` (modal markup at the end), `src/blocks/user-dashboard/view.js` |
| 7 | Dashboard Overview "Expiring soon" widget | `$SITE_URL{dashboard}` (Overview) | {owner} | `blocks/user-dashboard/` overview template |
| 8 | Renewal REST | `GET /listora/v1/listings/{id}/renewal-quote`, `POST /listora/v1/listings/{id}/renew` | {owner}, {admin}; {other} 403; {guest} 401 | `includes/rest/class-listings-controller.php` |
| 9 | Email Log rows (evidence) | `admin.php?page=listora-email-log` | {admin} | owned by `emails-alerts` |
| 10 | Expiry cron pass | `wp eval 'do_action("wb_listora_check_expirations");'` | system | `includes/workflow/class-expiration-cron.php` |

## Components on each surface
- **1 Listing Lifecycle**: checkbox "Enable automatic listing expiration" (`wb_listora_settings[enable_expiration]`); help text under it; optional Monetization reason note `.listora-settings__note` (Pro only); number input "Default expiration" (days); renewal fields (renewal window days, renewal duration days, default renewal credit cost); Save button.
- **2 Features**: "Renewal" toggle that drives `wb_listora_feature_enabled('renewal')`.
- **3 Type editor**: sidebar field "Listing expires after (days)" `#listora-type-expiry` with hint "0 = never expires".
- **4 Listings tab** (page order): search input, status filter, expiry filter `#listora-renewal-filter[data-listora-listing-filter]` (options Any expiry, Active, Expiring soon, Expired; only when renewal is on), list of `.listora-dashboard__listing-row[data-listora-listing-id][data-listora-state]`, per row: title link, status pill, `.listora-dashboard__type-tag`, `.listora-dashboard__listing-expires` ("Expires <date>"), `.listora-dashboard__status--expiring` pill with clock icon ("Expires today" / "Expires tomorrow" / "Expires in N days"), Featured tag, views, `.listora-dashboard__renew-btn` "Renew Now", row menu (`.listora-dashboard__menu-item`, includes "Renew"), `.listora-dashboard__paused-note` for awaiting-credits rows (owned by Pro journey), pager, filtered-empty state, hidden shared renew modal.
- **6 Renew modal** `[data-listora-renew-modal]` (`role=dialog`, `aria-modal`): backdrop `[data-listora-renew-close]`, close button (aria-label Close), title `#listora-renew-modal-title`, listing name, `dl` grid with `[data-listora-renew-plan]`, `[data-listora-renew-cost]`, `[data-listora-renew-duration]`, `[data-listora-renew-balance]`, error `[data-listora-renew-error]` (assertive live region, server-rendered hidden), actions: Cancel `[data-listora-renew-close]`, "Buy more credits" `[data-listora-renew-buy]` (hidden unless purchase_url), Confirm `[data-listora-renew-confirm]`; success toast.
- **7 Overview widget**: "Expiring soon" list, present only when expiry is on and a listing is inside the window.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | expiry off (default), on, on with Default expiration 30 | untick/tick the box, set days; record and restore `enable_expiration`, `default_expiration` |
| 4 | no expiry meta (lifetime) | free directory default; no pill, no Renew |
| 4 | expiring (inside window) | `update_post_meta( id, '_listora_expiration_date', gmdate('Y-m-d H:i:s', time()+3*DAY_IN_SECONDS) )` |
| 4 | expires today / tomorrow / far | expiry now+2h, +1d, +60d; `days_left` 0, 1, 60 |
| 4 | expired | post_status `listora_expired` |
| 4 | draft / pending / rejected / deactivated with a stale past date | set status and past date; no pill, no Renew expected |
| 4 | 0 / 1 / many expiring | N = 0, 1, 3; "Expires in 1 day" vs "tomorrow" |
| 5 | filtered-empty | filter `expired` with none |
| 6 | cannot afford | Pro: balance below cost; see Pro journey |
| 6 | cost 0 (Free only) | `default_renewal_credit_cost` 0: Cost reads "Free" |
| 6 | failed renewal | mu-plugin `wp_insert_post_empty_content` true |
| 6 | no expiry result | Default expiration 0 and Renewal duration 0: "No expiry" |
| 8 | expiry off | quote `can_renew_now:false`, POST renew 409 |
| any | RTL | not supported by an owner switch here; use a site locale if the profile lists one |
| any | timezone | UTC+14 `Pacific/Kiritimati`, UTC-12 `Etc/GMT+12`, restore UTC |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `enable_expiration` (General) | off (default) / on | surface 4 pill, Renew, Overview widget, filter; new listing gets or lacks `_listora_expiration_date`; reminders queued or not |
| `default_expiration` | 0 / 30 | new listing expiry = publish + N days; plan cards say "N days" vs "Never expires" |
| `renewal_window_days` | 7 default | when Renew appears; `listora_renewal_too_early` after |
| `default_renewal_duration_days` | 0 / 14 | `duration_days` in the quote and modal |
| `default_renewal_credit_cost` | 0 default | modal Cost "Free" vs credits |
| Features > Renewal | on / off | expiry filter, Renew buttons, row menu item |
| Listing type `expiration_days` | 0 / N | new type default 0; quote fallback order |
| Site timezone | UTC, +14, -12 | "Expires in N days" text and stored UTC value agree |

## Fixtures and setup
- `docs/qa/fixtures/2.0-fixtures.php seed` for {owner}, {other}, {listing}; an Event with `end_date` and a Job with `deadline` (demo packs `event`, `job-board`).
- Free-only: `wp plugin deactivate wb-listora-pro`; reactivate after.
- Fire the cron once: `wp eval 'do_action("wb_listora_check_expirations");'`.
- Restore: `enable_expiration`, `default_expiration`, renewal settings, site timezone (UTC), the created listing type, test listings, failing-update mu-plugin, `fixtures clean`.

## Run order
1. `expiry-off-on-free-directory.md` - mixed, high: settings default, no date, no countdown, dated types, new types, own switch. Run first: it proves the baseline the renewal journey changes.
2. `listing-renewal.md` - mixed, high: reminder, row, modal, quote, renew, duration rules, failed renewal, 409 with expiry off, 390/dark.
Then run: `wb-listora-pro/docs/qa/journeys/plans-expiry-renewal/_group.md`.

## Group-specific micro checks
- G-01 With expiry off, `wb_listora_expiration_enabled()` is false AND the dashboard listing JSON `expiry` is `""`: no row shows `.listora-dashboard__listing-expires`, `.listora-dashboard__status--expiring` or `.listora-dashboard__renew-btn` (count = 0).
- G-02 `.listora-dashboard__status--expiring` text matches `days_left`: 0 -> "Expires today", 1 -> "Expires tomorrow", 2+ -> "Expires in N days" (plural via `_n`, never "1 days").
- G-03 A published row outside the window shows `.listora-dashboard__listing-expires` ("Expires <date>") and NOT the pill; an inside-window row shows the pill and NOT the muted date. Never both.
- G-04 The date in "Expires <date>" uses the site `date_format` and the site timezone: at UTC+14 and UTC-12 it equals the stored UTC value converted, not shifted a day.
- G-05 `data-listora-state` on a row equals its filter bucket: `expiring`, `expired`, `active` (published, not expiring); draft/pending rows carry none of the three buckets.
- G-06 Selecting "Expiring soon" in `#listora-renewal-filter` leaves only rows with `data-listora-state="expiring"`, and the filtered-empty state (not a blank list) shows when none.
- G-07 Renew Now button and the row-menu "Renew" item appear together or not at all for the same row; both carry the same `data-listora-renew-listing` id.
- G-08 `[data-listora-renew-error]` exists in the HTML before JS runs, once per Listings tab, with `aria-live="assertive"`, `aria-atomic="true"` and `hidden`; after an error it is the same node (not replaced).
- G-09 Modal Cost cell: 0 cost reads "Free" (not "0 credits"); Duration cell: 0 reads "No expiry"; 1 day reads "1 day".
- G-10 After a successful renew the row loses both Renew controls without a reload, the pill flips to the muted "Expires <Month D, YYYY>" and the toast message equals the REST `message`.
- G-11 Focus returns to the Renew button (or the next logical control) after the modal closes by Escape, backdrop and Cancel; Tab stays inside the modal while open.
- G-12 Confirm is disabled while the quote is loading and when it is unaffordable, and re-enabled after a top-up reload; double click on Confirm sends one POST.
- G-13 "Default expiration" help text reads "Leave at 0 for lifetime listings" and the checkbox help mentions reminders at 7 days and 1 day; no sentence about Monetization on a Free-only site.
- G-14 Listing type editor: `#listora-type-expiry` is 0 with hint "0 = never expires" on a brand new type; the REST type has `expiration_days` 0.
- G-15 The Overview "Expiring soon" count equals the number of rows in the Listings tab with the expiring pill (same window).
- G-16 The renew modal at 390 fits the viewport (panel height <= innerHeight, scrolls inside), Confirm/Cancel/close are >= 40px.

## Known defects (expected-fail)
- `listing-renewal` step 4: "Buy more credits" link `[data-listora-renew-buy]` stays hidden because the quote carries no `purchase_url` (PRODUCT-BUGS "renewal quote missing purchase_url").

## Not covered yet
- Renewal reminder at 1 day (only the 7 day reminder fires in a journey step); `_listora_expiry_reminded_1d` dedupe.
- Expiry reminders to the right recipient when the author is deleted (deleted-author lifecycle seed).
- A listing in `listora_deactivated`, `listora_rejected` or draft carrying a past date: no Renew expected, no step asserts it on the dashboard.
- Renewal when Features > Renewal is OFF but expiry is ON (affordances hidden, REST behaviour).
- Action Scheduler path of the expiry cron end to end, and 2000-listing paging of the expiry filter (lives in `dashboard-account`).
- Expired listing owner view of the single page (owned by `listing-detail`, not asserted here).
