---
group: analytics-audit
plugin: wb-listora
scope: Free analytics-lite - recording a listing view once, and showing the view count to the owner and the admin only
journeys: 1
site_profile: required
---
# Analytics and audit (Free): what an agent checks here

## Scope
Covers the Free recorder (`Analytics_Lite`) that writes one `view` count per listing per site-calendar day, the dedupe window, bot and admin exclusion, and the three places Free shows the number: the owner's dashboard My Listings row, the wp-admin listings table Views column, and the `views` key on the listing REST response. It also covers the stand-down rule when Pro owns recording, and Free's `lead` count (the count itself is asserted in `contact-leads`).

Not covered here: Pro's Analytics screen, search report, favourites and saved-search panels, retention and the audit log (Pro half, run `wb-listora-pro/docs/qa/journeys/analytics-audit/_group.md` afterwards), click events (phone, website, email, direction), the contact form that creates a lead (`contact-leads`), the owner dashboard layout as such (`dashboard-account`), generic admin list columns and sorting (`admin-listings`).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | A real front-end listing view (the recorded event) | `$SITE_URL/listing/{listing}/` with a normal browser UA | guest, member (counted); admin (not counted) | `includes/features/class-analytics-lite.php:67,103,167` |
| 2 | Owner dashboard My Listings row view tag | `$SITE_URL{dashboard}?tab=listings` | {owner}, {other} (own rows only) | `templates/blocks/user-dashboard/tab-listings.php:285-310`, prefetch in `blocks/user-dashboard/render.php` |
| 3 | Admin listings table Views column | `$SITE_URL/wp-admin/edit.php?post_type=listora_listing` (column hidden by default, enable in Screen Options) | {admin} | `includes/admin/class-listing-columns.php:173,207,334,464-506` |
| 4 | Listing REST `views` key | `GET /wp-json/listora/v1/listings/{id}` and `/detail`, `GET /listings?per_page=N` | guest (key absent), owner, admin | `includes/rest/class-listings-controller.php` (`prepare_views`) |
| 5 | Aggregate table | `${PREFIX}listora_analytics` (unique `(listing_id, event_type, event_date)`) | DB | `includes/class-activator.php` |
| 6 | Pro stand-down filter | `apply_filters( 'wb_listora_pro_owns_analytics', false )` | system | `class-analytics-lite.php:67` |

## Components on each surface
**2 Row tag**: `span.listora-dashboard__view-count` (modifier `--empty` when 0) containing a 12 px eye SVG and the text "N view(s)" (`_n`, `number_format_i18n`); shown for `publish`, `listora_expired`, `listora_deactivated` rows only, never for pending, draft or `pending_verification`.

**3 Column**: header "Views" (sortable, `orderby=listora_views`); cell = `span.dashicons.dashicons-visibility` + number as a link to the front-end listing; zero renders "0", not a dash; sort uses a grouped LEFT JOIN and breaks ties by ID descending.

**4 REST**: key `views` present only when `is_user_logged_in()` and the viewer is the post author or has `manage_listora_settings`; lists use one batched `prepare_views()` query per page.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | first view today (no row), repeat inside one hour, a view after the window, bot UA, admin session, a view at UTC+14 and UTC-12 near midnight | clear transients `_transient_listora_view_<LID>_%`; set site timezone (restore UTC) |
| 2 | listing with 0 views, 1 view, 1,234 views, pending listing (no tag), expired listing (tag) | `INSERT ... ON DUPLICATE KEY` into `listora_analytics` for a QA listing; change `post_status` |
| 3 | column hidden by default, column enabled, sort asc and desc, 2000+ listings | Screen Options; seed a QA-prefixed set |
| 4 | guest, other member, owner, admin; list endpoint with `per_page=100` | cookie + nonce per persona |
| 6 | Pro active with analytics ON, then OFF | `wb_listora_pro_set_feature( "analytics", false )` (restore) |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_pro_features['analytics']` (Settings > Features, Pro) | on / off | ON: Pro records, Free records nothing; OFF: Free records (+1 per window) |
| `wb_listora_pro_owns_analytics` filter | bool | stand-down of Free's recorder (views only; leads are always Free's) |
| Site timezone (Settings > General) | UTC, UTC+14, UTC-12 | the `event_date` bucket follows the SITE date |
| `Analytics_Lite::DEDUPE_WINDOW` (1 hour) | const | repeat-view suppression |
| Screen Options > Columns > Views | on / off | surface 3 visibility |

## Fixtures and setup
- A published {listing} whose author is {owner}; `LID`, `TODAY=$(wp eval 'echo wb_listora_site_date();')`; read count with `SELECT count FROM ${PREFIX}listora_analytics WHERE listing_id=LID AND event_type='view' AND event_date='TODAY'`.
- Clear the dedupe transients before each counted view; use a normal desktop UA, not curl or headless (the bot check rejects them).
- Restore afterwards: dedupe transients, Pro analytics ON, timezone UTC, Screen Options column state, any seeded `listora_analytics` rows.

## Run order
1. `analytics-audit/owner-sees-view-count.md` - mixed, high: one view = +1, repeat = +0, REST gating, dashboard row, admin column, Pro stand-down, timezone.
Then run: `wb-listora-pro/docs/qa/journeys/analytics-audit/_group.md`.

## Group-specific micro checks
- G-01 The admin Views column is hidden by default (`default_hidden_columns` lists `listora_views`), so step 9 of the journey cannot find a "Views" header on a clean admin until the column is enabled in Screen Options; verify the default state and report if the journey text does not mention it.
- G-02 The Views cell is a link to the front-end listing (not to the edit screen) and the number uses `number_format_i18n` (thousands separator on 1,234) while the dashboard tag also formats with `number_format_i18n`; the two numbers for the same listing agree to the digit.
- G-03 Dashboard tag singular/plural: "1 view", "0 views", "2 views"; the zero state is muted (`--empty`) but still readable (contrast >= 4.5:1 in light and dark) and not hidden.
- G-04 Tag visibility by status: shown for publish, expired and deactivated rows; absent for pending, draft and pending_verification; trashed rows do not appear in My Listings at all.
- G-05 Dashboard row isolation: {other} never sees {owner}'s counts; a member who transfers a listing (author change) stops seeing its count and the new author sees the full history.
- G-06 REST gating by role: guest has no `views` key on `/listings/{id}`, `/listings/{id}/detail` and list items; a logged-in member who is not the author also has none; owner and admin have it; `views` is an integer, not a string.
- G-07 N+1 guard: loading My Listings (20 rows) and `GET /listings?per_page=20` as admin each issue one grouped views query, not one per row (Query Monitor or `SAVEQUERIES`).
- G-08 Counting rules: the first load by a guest is +1 exactly (not +2 when Pro is installed but analytics OFF), a reload within the hour is +0, a load by an administrator is +0, a bot UA is +0, a preview (`?preview=true`) and a 404 are +0.
- G-09 Day bucket: a view at 23:50 site-local and another at 00:10 land on two different `event_date` rows even though both fall in the same UTC day at UTC-12, and the aggregate keeps one row per listing per day.
- G-10 Stand-down is views-only: with Pro analytics ON a contact-form lead still increments the `lead` count once (Free) while a page view increments only Pro's recorder; the dashboard tag and the REST `views` still read the same table.
- G-11 Sort stability: sorting the admin table by Views twice in the same direction returns the same order; listings with equal counts are ordered by ID descending and appear on exactly one page.
- G-12 Page weight: the view recorder adds no visible UI to the listing page, no console error, and no request to a third-party host (F-02, F-03 on surface 1).

## Known defects (expected-fail)
None. No step in `owner-sees-view-count.md` is marked `expected-fail`; G-01 is a candidate for a documentation fix of step 9.

## Not covered yet
- Timezone step 11 of the journey is Not verified live.
- The frontend REST `views` key on the `/search` endpoint rows and on the Featured / grid blocks (only `/listings` and `/detail` are asserted).
- Views for a listing whose author is changed by claim approval (history keeps with the listing; the old author's dashboard stops showing it).
- Data retention for the Free `listora_analytics` table when Pro is absent (Free's own 90-day prune window) and its behaviour at 50k rows.
- Owner-visible analytics beyond the single number: there is no Free surface for clicks, favourites or leads totals (Pro REST `GET /analytics/listing/{id}` exists, no owner UI): decide whether an owner-facing surface is expected (three entry points rule).
