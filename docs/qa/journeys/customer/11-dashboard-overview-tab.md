---
journey: dashboard-overview-tab
plugin: wb-listora
priority: high
roles: [member, logged-out, role-without-submit-cap, admin]
covers: [user-dashboard, overview-tab, dashboard-stats, stats-cache-invalidation, next-steps, unknown-tab-notice, login-gate]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "Dev auto-login mu-plugin installed"
  - "The dashboard page = $SITE_URL/my-listings/ (block listora/user-dashboard; `dashboard_page` setting = page id 7). The page titled Dashboard at /dashboard/ is an unrelated Directorist shortcode page"
  - "A member with at least one published listing (fixture: QA-J1-owner) and a second member with nothing (QA-J1-other)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Dashboard Overview tab

A member opens My Dashboard Overview and sees five summary tiles (Active, Pending, Reviews, Saved, Claims), a "Next steps" list when something needs attention, and the "Your Listings" limit card. Tile values equal the database, update right after a favourite (no cache clearing), and a logged-out visitor only gets the login prompt.

Verified via: Playwright (isolated contexts) and curl REST; DB checks through WP-CLI.

## Setup

- Overview URL: `$SITE_URL/my-listings/?tab=overview`. Without `?tab=` the block opens its `defaultTab` (block.json default `listings`, so "My Listings" is active); an unknown tab falls back to Overview with a notice.
- Baseline for MEMBER (user id U): `published = COUNT(*) FROM wp_posts WHERE post_type='listora_listing' AND post_status='publish' AND post_author=U`; `pending` the same with `pending`; `reviews = written + received` where written = member's reviews, received = approved reviews by others on the member's listings; `favorites = COUNT(*) FROM wp_listora_favorites WHERE user_id=U`; `claims = COUNT(*) FROM wp_listora_claims WHERE user_id=U` (all statuses).

## Steps

### 1. Open Overview
- **Action**: log in as MEMBER (`?autologin=QA-J1-owner`), open `$SITE_URL/my-listings/?tab=overview`.
- **Expect**: `#dash-tab-overview` has class `is-active` and `aria-selected="true"`; `#dash-panel-overview` is visible (no `hidden`) and other panels have `hidden`; the heading area reads "Overview". Nav items (`a.listora-dashboard__nav-item`, `data-listora-tab-label`): Overview, My Listings (count), Reviews (count), My Claims, Leads, Analytics, Favorites (count), My Needs, My Responses, Saved Searches, Credits, Profile (the set depends on enabled features and role).
- **On fail**: `blocks/user-dashboard/render.php:53-72,665`

### 2. Five tiles, values match the database
- **Action**: read `.listora-dashboard__stat` elements: label `.listora-dashboard__stat-label`, value `.listora-dashboard__stat-value`.
- **Expect**: exactly the labels `Active`, `Pending`, `Reviews`, `Saved`, `Claims` in that order (these are the strings the tiles use; the nav says "My Listings"/"Favorites"/"My Claims"), with values equal to the baseline (QA-J1-owner at verification: Active 4, Pending 0, Reviews 0, Saved 0, Claims 0). Each tile is an `<a>` with an `aria-label` such as "Active listings: 4. Open My Listings."; Active links to `?tab=listings&listings_status=publish`, Pending to `...&listings_status=pending`, Reviews/Saved/Claims switch the tab.
- **On fail**: `includes/core/class-dashboard-data.php:90-125`, `blocks/user-dashboard/render.php:770-880`

### 3. "Your Listings" limit card
- **Expect**: `.listora-dashboard__limit` (region) with "Active + Pending" = published + pending, "Limit" and "Remaining" ("∞" and "Unlimited" when no limit is configured; a bounded limit shows numbers and a "Buy credits"/"Add listing" CTA).

### 4. Next steps appear only when there is something to do
- **Action**: as a member with one pending listing (for example QA-J1-member after submitting without approval, or any member whose listing is `pending`) open Overview.
- **Expect**: `section.listora-dashboard__todos` titled "Next steps" with `li.listora-dashboard__todo` text "1 listing is awaiting review by the site team" linking to `/my-listings/?tab=listings&listings_status=pending`. Other possible lines: "N listings have expired and can be renewed", "N claim(s) awaiting a decision", "N listings are paused and need credits to go live", "N listings are waiting for you to verify your email", low credit balance.
- **POSITIVE CONTROL**: for a member with nothing pending/expired/claimed (QA-J1-owner or QA-J1-other) the whole section is absent.

### 5. Counters update immediately (cache is invalidated on writes)
- **Action**: as QA-J1-other note the Saved tile (0); `POST /listora/v1/favorites {"listing_id": <any published>}` (or click Save on a listing); reload Overview; then `DELETE /listora/v1/favorites/<id>` and reload.
- **Expect**: Saved `0` -> `1` -> `0` with no `wp transient delete` or waiting; `GET /listora/v1/dashboard/stats` shows `favorites` change in step. (Dashboard stats are cached under `Cache::GROUP_DASHBOARD` and bumped by favourites, claims and reviews: `includes/core/class-cache.php:77-81`.)
- **On fail**: `includes/core/class-cache.php:166` (`bump_dashboard`), `includes/rest/class-favorites-controller.php:288,360`

### 6. REST shape
- **Action**: `GET /wp-json/listora/v1/dashboard/stats` as MEMBER with a nonce.
- **Expect**: `200` with top-level keys `listings` (`published, pending, expired, draft`), `reviews`, `reviews_written`, `reviews_received`, `favorites`, `claims` (`pending, approved, rejected`) and any extension keys added through the `wb_listora_rest_prepare_dashboard_stats` filter (`includes/rest/class-dashboard-controller.php:317`). Logged out: `401 listora_unauthorized`.

### 7. A member with nothing
- **Action**: log in as QA-J1-other (no listings), open Overview.
- **Expect**: all five tiles `0`; no Next steps; "Your Listings" card shows 0 Active + Pending. (There is no "Get started" empty card on Overview; the My Listings tab carries the empty state and the Add Listing call to action.)

### 8. Unknown tab
- **Action**: open `$SITE_URL/my-listings/?tab=nope`.
- **Expect**: Overview opens with `p.listora-dashboard__notice[role=status]` reading `There is no "nope" section in your dashboard. Showing Overview instead.`

### 9. ROLE LADDER
| Actor | Action | Expect |
|---|---|---|
| Logged out | open `/my-listings/` | `.listora-dashboard__login-prompt` with `<h2>` "My Dashboard", "Please log in to view your dashboard.", button "Log In" (to `wp-login.php?redirect_to=...`); no tiles; `GET /dashboard/stats` returns `401` |
| Member | steps 1-8 | own numbers only (QA-J1-other never sees QA-J1-owner's 4 listings) |
| Role without `submit_listora_listing` (`QA-J1-nocap`, WooCommerce customer) | open Overview | tiles all 0; nav has no "Leads" tab; no Add Listing call to action |
| Admin (control) | open Overview | the admin's own numbers (published 78+ ...), not the site totals: the dashboard is per user, not a moderation view |

### 10. Layout
- **Action**: repeat step 1 at 390x844.
- **Expect**: `scrollWidth <= 390`; the five tiles lay out two per row (each about 169x74, well above 40px); nav items are 118x53 and reachable by keyboard; the heading reads "Overview".

## Pass criteria

1. Five tiles with labels Active/Pending/Reviews/Saved/Claims and DB-accurate values.
2. Next steps present only when something needs action.
3. Saved changes immediately after a favourite write.
4. REST stats shape stable; extension filter honoured.
5. Logged out: login prompt and 401.
6. Unknown tab shows the notice and Overview.

## Not verified

- Dashboard tabs hidden when a feature is switched off (2.0.0) were not toggled on the shared site; the nav above is the default state.
- Cache keys can only be read where a persistent object cache is installed; the invalidation was verified by the rendered values.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Tile values wrong | aggregation | `includes/core/class-dashboard-data.php:90-125` (`get_stats`) |
| Saved does not change after favourite | cache not bumped | `includes/core/class-cache.php:77`, `includes/rest/class-favorites-controller.php:288` |
| No Next steps despite pending listing | todo builder | `blocks/user-dashboard/render.php:685-760` |
| Wrong tab opens by default | `defaultTab` / `?tab=` handling | `blocks/user-dashboard/render.php:53-72`, `blocks/user-dashboard/block.json:31` |
| Stats REST missing keys | prepare filter | `includes/rest/class-dashboard-controller.php:317` |
| Logged-out sees fatal / blank | login gate | `blocks/user-dashboard/render.php:36-45` |
