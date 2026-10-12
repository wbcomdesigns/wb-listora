---
journey: dashboard-my-listings-statuses-counts-filters
plugin: wb-listora
priority: high
roles: [subscriber, other-member, guest, admin]
covers: [user-dashboard, dashboard-listings-rest, listora-payment-status, counter-vs-list, row-state-derivation, server-side-filter, pagination, timezone-expiring, cross-cutting-check-8, card-10318160202, card-10294421959, bc-10393803313, "#9962484094"]
prerequisites:
  - "Site reachable at $SITE_URL; Renewal feature ON (`wb_listora_feature_enabled( 'renewal' )`; the Expiry dropdown only renders then and is hidden on a free directory with expiry off)"
  - "$OWNER_MULTI: a member with more than one page (> 20) of listings, some expired, some expiring inside `renewal_window_days`; $OWNER_MIXED: a member with one listing per status of `wb_listora_member_listing_statuses()` (derive the status list from the helper); $OWNER_PAYMENT: a member whose ONLY listing is `listora_payment` (create `QA-J1- payment only` if none exists, delete it afterwards); $OWNER_SINGLE: one active listing and nothing expiring"
  - "{admin} owns the large set when 2000+ rows exist, otherwise seed 2000+ `QA-J1-` listings with WP-CLI before steps 3, 7, 9"
estimated_runtime_minutes: 16
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:regression/dashboard-badge-counts-every-status.md"
  - "ora:regression/member-listing-statuses-shared.md"
  - "ora:regression/dashboard-active-filter-status.md"
  - "ora:regression/dashboard-renewal-filter-server-side.md"
fails_on: [8bc550fb]
---

# My Listings counts, lists and filters every status the same way on the web, in the badge and in the app API

## Why
A badge that sums four statuses while the panel lists eight shows "My Listings 0" above a listing; a client-side filter blanks page 1 while the matches sit on page 2; two status lists drift between the web and the app. The member cannot find or trust their own listings.

One list: `wb_listora_member_listing_statuses()` (`includes/helpers.php:347-358`, filter `wb_listora_member_listing_statuses`): `publish, pending, draft, listora_expired, listora_rejected, listora_deactivated, pending_verification, listora_payment`. The badge/tiles read it through `Dashboard_Data::stats()` (key `total`, `includes/core/class-dashboard-data.php:43,97-100,115`), the rows through `blocks/user-dashboard/render.php:96`, the app through `GET /listora/v1/dashboard/listings`.

Row state: `expired` (post status `listora_expired`), else `expiring` (published and inside the renewal window), else `active` ONLY when `post_status === 'publish'`, else `inactive` (deactivated, draft, pending, rejected, awaiting credits, pending verification). Filters are a GET form applied server-side: `listings_filter` (`active`|`expiring`|`expired`), `listings_status`, `listings_search`, `listings_page`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Dashboard `$DASH` = `wb_listora_get_dashboard_url()`, tab `listings`.
- Count helper: `wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"listora_listing\" AND post_author=UID AND post_status=\"STATUS\"");'`. Derive every expected number from it (and `N_PAGES = ceil(N / 20)`), never from this file.
- If a member has no expired listing beyond page 1, pick their OLDEST published listing, `wp post update <id> --post_status=listora_expired`, restore to `publish` afterwards.
- Test-data trap: a member whose listings are all `publish` passes every assertion while the badge bug is present; the badge fixture MUST use a status outside the headline four.

## Steps

### 1. Badge equals rows (non-headline status only)
- **Action**: as $OWNER_PAYMENT read `#dash-tab-listings` and count `.listora-dashboard__listing-row` in `#dash-panel-listings`.
- **Expect**: badge `1`, panel shows 1 row (pill "Awaiting Credits"). A badge of 0 beside a rendered row is never legitimate.

### 2. Every status counts
- **Action**: as $OWNER_MIXED read the badge and the rows.
- **Expect**: badge and `.listora-dashboard__listing-row` count both equal the number of listings $OWNER_MIXED owns across the helper's statuses (N); with one listing per each status the badge is the helper's length (8) and 8 rows render.
- **Read-back**: `GET /listora/v1/dashboard/listings` `total` = N and its rows include status `listora_payment`.

### 3. One list, not two
- **Action**: `grep -rn "'listora_deactivated', 'pending_verification'" includes/ blocks/` and `grep -n "wb_listora_member_listing_statuses\|listings_statuses = array" blocks/user-dashboard/render.php includes/core/class-dashboard-data.php`.
- **Expect**: the literal appears only at `includes/helpers.php:356` (the admin list `includes/admin/class-listing-columns.php:601` is intentionally separate); both consumers read the helper.

### 4. The filter hook moves web AND app at once
- **Action**: mu-plugin `add_filter( 'wb_listora_member_listing_statuses', fn( $s ) => array_diff( $s, array( 'draft' ) ) );` with a draft present.
- **Expect**: the draft disappears from the web tab AND the REST response; `total` drops by one in both. Remove the mu-plugin.

### 5. An explicit status request
- **Action**: `GET /dashboard/listings?status=listora_payment`.
- **Expect**: `total` = count of the member's `listora_payment` rows, only those rows (the status is in `Status_Manager::get_statuses()`, so it passes the enum).

### 6. Row states are honest
- **Action**: as $OWNER_MIXED read each row's `data-listora-state` and pill.
- **Expect**: published rows `active` ("Published"); deactivated, rejected, awaiting credits, pending, draft `inactive` with their pills; the expired one `expired` ("Expired"); a published listing inside the renewal window `expiring`.

### 7. Expired finds matches on any page
- **Action**: as $OWNER_MULTI choose Expiry > **Expired** (`#listora-renewal-filter`).
- **Expect**: URL gains `listings_filter=expired`; every row `data-listora-state="expired"`; the pager reads "Page 1 of N_PAGES (N_EXPIRED items)"; NO "Nothing matches this filter" box.
- **On fail**: client-side filter back (`src/blocks/user-dashboard/view.js:535-545`).

### 8. Active hides inactive rows, the pager keeps the filter
- **Action**: choose **Active**, click Apply (the view script also submits on change), click **Next** on the "Listings pagination" nav.
- **Expect**: `listings_filter=active&listings_page=2`; rows all `active`; the select still shows Active; the Status select (`#listora-listings-status`) untouched; deactivated, rejected, awaiting, pending and draft rows never appear under Active.
- **On fail**: `blocks/user-dashboard/render.php:145-281,1041`, `tab-listings.php:165-174`.

### 9. Any expiry restores the full list; the page number clamps
- **Action**: choose **Any expiry**; then open `?listings_page=9999`.
- **Expect**: `listings_filter` removed, the pager shows every page; `9999` clamps to the last real page (rows shown, not the empty state).

### 10. A filter that truly matches nothing
- **Action**: as $OWNER_SINGLE open `?tab=listings&listings_filter=expiring`.
- **Expect**: the `[data-listora-filter-empty]` card (`role=status`) "None of your listings are in this state right now. Choose another filter to see the rest." with the select showing "Expiring soon", no pager, NOT the "No listings yet" first-run state; a Clear link resets the form.
- **On fail**: `tab-listings.php:526-545`.

### 11. Status and search compose
- **Action**: `?tab=listings&listings_status=listora_payment` and `&listings_search=<title fragment>`.
- **Expect**: only that status / title across all pages; counts match the pager.

### 12. Edge data: one listing, one bucket
- **Action**: give a member four published listings: expiry in 3 days; expiry `garbage`; two expiry rows (3 days and 60 days); expiry in 60 days.
- **Expect**: Expiring soon = the 3-day and the two-row listing; Active = the garbage-date and 60-day listings plus any with no date. No listing under both, none under neither; Active + Expiring soon = All published.

### 13. Expiring soon and the cron agree across timezones (bc-10393803313)
- **Action**: set site timezone UTC+14 (`Pacific/Kiritimati`), give a listing `_listora_expiration_date` = tomorrow 00:00 UTC; open `listings_filter=expiring` and `active`; run `wb_listora_check_expirations`. Repeat at UTC-12 (`Etc/GMT+12`), then restore UTC.
- **Expect**: the row's "days left" equals `ceil((expiry_utc - time()) / 86400)` and the listing sits under exactly one of Expiring soon / Active, the same bucket the cron's reminder uses; no row "Expired" while still published.
- **Read-back**: `wp eval 'echo \WBListora\Workflow\Status_Manager::expiry_timestamp(ID) - time();'` matches the dashboard's days to within one.
- **fails_on**: 8bc550fb. Not verified live.

### 14. The stat cache cannot serve a stale shape
- **Expect**: stats are cached per user and type in the `wb_listora_dashboard` object-cache group keyed with the group's `last_changed` stamp (`Dashboard_Data::stats()` + `Core\Cache::key()`); bumped on every listing, review, favourite and claim write (`includes/core/class-cache.php:66-80`), so a new listing changes the badge on the next render; no `listora_dashboard_stats_*` transient exists.
- **Read-back**: create then trash a listing and re-read the badge each time.

### 15. Scale: 2000+ rows
- **Action**: as the large-set owner open `?tab=listings`, then `listings_filter=active` and `expired`.
- **Expect**: each page returns well under a second with a `COUNT` plus a LIMIT/OFFSET query (not `count( list )`); badge = total COUNT; pager "Page 1 of N_PAGES (N items)" for each filter; the Status select offers All statuses plus each helper status; REST paginates (`has_more`, `cursor`/`next_cursor`, `pages`); draft rows (the bulk) never appear under Active.

### 16. 390px
- **Expect**: rows stack, step 10 renders the same, `scrollWidth <= 390`, selects and Apply >= 40px tall.

## Role ladder
| Rung | Dashboard / REST |
|---|---|
| owner | sees all own listings in all member statuses |
| {other} | only their own; opening someone else's non-public URL is 404; badge equals their own rows |
| {guest} | `/my-listings/` shows the sign-in prompt, no badge; REST 401 |
| moderator / {admin} | own listings only on the member dashboard (admin lists everything in wp-admin) |

## Pass criteria
1. Badge, rows and REST `total` agree for every member status; the status list exists once and one filter moves web and app together.
2. Row states are honest (`active` only for `publish`).
3. The filter, status, search and page apply to the whole list server-side and survive the pager; an empty result is the filter-empty card.
4. Active + Expiring soon = all published; the cron and dashboard agree across timezones.
5. 2000+ rows stay fast and correctly counted.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Badge 0 with a row visible | stats sum a literal subset | `class-dashboard-data.php:97-100` |
| Panel misses a status | rows query list diverged | `render.php:96`, `:272` |
| Two lists | literal re-added | `includes/helpers.php:347-358` |
| Draft/deactivated rows `active` | `publish` check dropped | `tab-listings.php:165-174` |
| Filter blanks page 1 | client-side filter back | `view.js:535-545` |
| Wrong rows / pager | arg handling | `render.php:145-281` |
| Pager drops the filter | pager args | `render.php:1041`, `wb_listora_render_pagination()` |
| "No listings yet" on a filtered empty result | empty-state branch | `tab-listings.php:526-545` |
| Filter select missing | renewal off | `tab-listings.php:26,136` |

## Not verified
- Steps 1, 8-9 click-throughs, 11, 12, 14, 16 were not run; URL forms and counts for steps 2, 6, 7, 10 and 15 were fetched directly against a large seeded set; the payment-only member and stat-cache bump need fixtures; no persistent object cache was installed where this ran, so cross-request staleness is unobservable there. Automated twin: `tests/integration/DashboardListingTypeTest::test_a_paused_listing_is_visible_to_the_block_and_the_app`.

## Cleanup
Restore any listing set to `listora_expired`; delete seeded `QA-J1-` rows and the `QA-J1- payment only` listing; remove the mu-plugin; restore site timezone to UTC.
