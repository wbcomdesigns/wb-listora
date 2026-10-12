---
journey: dashboard-overview-tab
plugin: wb-listora
priority: high
roles: [owner, other, nocap-member, admin, guest]
covers: [user-dashboard, overview-tab, dashboard-stats, stats-cache-invalidation, object-cache-group-bump, next-steps, login-gate, bc-10350524500, bc-10393557341, "#9982046916"]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Dashboard page = `wp eval 'echo wb_listora_get_dashboard_url();'` (block listora/user-dashboard); a page titled Dashboard at /dashboard/ may be unrelated"
  - "{owner} has at least one published listing; {other} has nothing; a role without `submit_listora_listing` (QA-J1-nocap, e.g. a shop customer)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/dashboard-stats-transient-bust.md
  - ora:customer/11-dashboard-overview-tab.md (old steps 8 and 11 moved out)
fails_on:
  - 299118df
  - 237b67e8
---

# Dashboard Overview shows five accurate tiles that refresh right after a write

## Why
A member opens Overview and sees five tiles (Active, Pending, Reviews, Saved, Claims), "Next steps" when something needs attention, and the "Your Listings" limit card. Wrong or stale numbers (or a login-gate fatal) mislead every member.

Stats come from `Dashboard_Data::stats( $user_id, $type )` cached in object-cache group `wb_listora_dashboard` under a key that embeds the group's `last_changed` stamp (`Core\Cache::key()`, `includes/core/class-cache.php:106`). A write invalidates by BUMPING the group (`class-cache.php:66-80`). The old 60s transient keys no longer exist; do not assert on them.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Overview URL `$DASH/?tab=overview`. Without `?tab=` the block opens `defaultTab` (block.json default `listings`). Unknown/off tab ids: see `dashboard-tabs-follow-features`.
- Baseline for MEMBER (user id U): `published` / `pending` = `wp post list --post_type=listora_listing --author=U --post_status=<s> --format=count`; `reviews = written + received` (received = approved reviews by others on U's listings); `favorites = COUNT(*) FROM wp_listora_favorites WHERE user_id=U`; `claims = COUNT(*) FROM wp_listora_claims WHERE user_id=U` (all statuses).
- REST nonce: `wp-admin/admin-ajax.php?action=rest-nonce`.
- Cache note: without a persistent object cache (`wp_using_ext_object_cache()` false) `wp_cache_*` lives for one request, so a stale read cannot occur; steps 5-6 still prove the write path and the wiring.

## Steps

### 1. Open Overview
- **Action**: `?autologin={owner}`, open `$DASH/?tab=overview`.
- **Expect**: `#dash-tab-overview` has `is-active` and `aria-selected="true"`; `#dash-panel-overview` visible, other panels `hidden`; heading reads "Overview". Nav items (`a.listora-dashboard__nav-item[data-listora-tab-label]`) are the tabs the member's features allow.
- **Read-back**: n/a (no write). On fail: `blocks/user-dashboard/render.php:53-72,665`.

### 2. Five tiles match the database
- **Action**: read `.listora-dashboard__stat` (`__stat-label`, `__stat-value`).
- **Expect**: labels `Active`, `Pending`, `Reviews`, `Saved`, `Claims` in that order (the nav says "My Listings"/"Favorites"/"My Claims"), values equal Setup baseline. Each tile is an `<a>` with an `aria-label` such as "Active listings: N. Open My Listings."; Active links to `?tab=listings&listings_status=publish`, Pending to `...pending`, the rest switch tab.
- **Read-back**: baseline SQL/CLI counts; negative control: a pending listing is absent from Active.

### 3. Active and Pending open different, filtered lists (card 10350524500)
- **Action**: as a member with >= 1 publish and >= 1 pending listing click Active, read URL and rows; back; click Pending.
- **Expect**: Active: `listings_status=publish`, every row badge "Published", row count = tile; Pending likewise. The lists share no row; Reviews/Saved/Claims open `?tab=reviews|favorites|claims`.
- **Read-back**: `post_status='publish'` count equals the Active rows.
- **fails_on**: 299118df (parent of 4b0fd91f). Not verified live.

### 4. Every tile icon is tinted (card 10393557341)
- **Action** ({owner}, light, dark, 390): read computed `background-color` of each `.listora-dashboard__stat-icon`.
- **Expect**: all five non-transparent (alpha > 0) with a token text colour; Claims (`--claims`) no different; tint differs in dark mode.
- **Read-back**: the Active tint is non-transparent on the same load (positive control: a transparent Claims tint is a defect, not a read failure).
- **fails_on**: 237b67e8. Not verified live.

### 5. "Your Listings" limit card
- **Action**: read `.listora-dashboard__limit`.
- **Expect**: "Active + Pending" = published + pending; "Limit" and "Remaining" ("∞"/"Unlimited" with no limit; a bounded limit shows numbers and a "Buy credits"/"Add listing" CTA).
- **Read-back**: Active + Pending equals tile sum.

### 6. Next steps only when there is something to do
- **Action**: member with one pending listing opens Overview.
- **Expect**: `section.listora-dashboard__todos` "Next steps" with `li.listora-dashboard__todo` "1 listing is awaiting review by the site team" linking to `?tab=listings&listings_status=pending`. Other lines: "N listings have expired and can be renewed", "N claim(s) awaiting a decision", "N listings are paused and need credits to go live", "N listings are waiting for you to verify your email", low credit balance.
- **Read-back**: positive control: {other} (nothing pending/expired/claimed) has no section at all.

### 7. Counters update on the very next render (cache bump)
- **Action**: as {other} note the Saved tile; `POST /listora/v1/favorites {"listing_id": <any published>}`; reload Overview; `DELETE /listora/v1/favorites/<id>`; reload. Then `POST /listora/v1/listings/<listing {owner} does not own>/reviews` (QA-J1- body) and `DELETE /listora/v1/reviews/<id>`, reloading each time.
- **Expect**: Saved N -> N+1 -> N with no waiting or `wp transient delete`; Reviews +1 then -1; `GET /listora/v1/dashboard/stats` moves in step. Responses `201 {"favorited":true}` / `200 {"favorited":false}`.
- **Read-back**: control: a dashboard whose count never moves would also look fresh, so the number moving is the control. Another member's tiles do not change when {other} writes (stats keyed per user and type). With a persistent object cache `wp eval 'echo wp_cache_get_last_changed( "wb_listora_dashboard" );'` differs before and after.

### 8. Invalidation is wired
- **Action**: `wp eval 'foreach ( ["wb_listora_after_add_favorite","wb_listora_after_remove_favorite","wb_listora_after_create_review","wb_listora_after_delete_review","wb_listora_after_update_claim"] as $h ) { echo $h, " => ", has_action( $h ) ? "wired" : "MISSING", "\n"; }'`
- **Expect**: every hook `wired` (to `WBListora\Core\Cache::bump_*`).
- **Read-back**: n/a (no write). Pinned dashboards: the key carries the listing type (see `dashboard-listing-type`).

### 9. REST shape
- **Action**: `GET /wp-json/listora/v1/dashboard/stats` as {owner} with nonce.
- **Expect**: `200` with `listings` (`published, pending, expired, draft`), `reviews`, `reviews_written`, `reviews_received`, `favorites`, `claims` (`pending, approved, rejected`) plus keys added through `wb_listora_rest_prepare_dashboard_stats` (`includes/rest/class-dashboard-controller.php:317`). Logged out: `401 listora_unauthorized`.
- **Read-back**: values equal the tiles.

### 10. A member with nothing (N = 0)
- **Action**: {other} with no listings opens Overview.
- **Expect**: all five tiles `0`; no Next steps; limit card 0 Active + Pending. (No "Get started" card on Overview; the My Listings tab carries the empty state and Add Listing CTA.)
- **Read-back**: stats REST all zero. Also test N = 1 and N > 1 on a tile (singular/plural aria-label).

### 11. Layout at 390
- **Action**: repeat step 1 at 390x844.
- **Expect**: `scrollWidth <= 390`; tiles two per row (about 169x74, above 40px); nav items about 118x53 and keyboard reachable.
- **Read-back**: dark mode identical values. Dark not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-11, own numbers only ({other} never sees {owner}'s listings) |
| {other} | steps 7, 10 |
| nocap-member (no `submit_listora_listing`) | tiles all 0; no "Leads" tab; no Add Listing CTA |
| {admin} (control) | the admin's own numbers, not site totals: the dashboard is per user |
| {guest} | `.listora-dashboard__login-prompt` with `<h2>` "My Dashboard", "Please log in to view your dashboard.", Log In (to `wp-login.php?redirect_to=...`); no tiles; `/dashboard/stats` 401 |

## Pass criteria
1. Five tiles, labels in order, values DB-accurate; Active and Pending open distinct filtered lists.
2. Next steps present only when something needs action.
3. Saved/Reviews change on the very next render after a write; hooks wired.
4. REST stats shape stable and extension filter honoured; guest gets prompt and 401.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Tile values wrong | aggregation | `includes/core/class-dashboard-data.php:90-125` |
| Count lags after a write | bump hook missing | `includes/core/class-cache.php:66-80`, `includes/rest/class-favorites-controller.php:288,360` |
| Count wrong for a pinned page | key lacks type | `includes/core/class-dashboard-data.php:46` |
| Group never invalidates | key built without `last_changed` | `includes/core/class-cache.php:106-109` |
| Stale only with Redis | cached outside the group | `includes/rest/class-dashboard-controller.php:597-625` |
| No Next steps despite pending | todo builder | `blocks/user-dashboard/render.php:685-760` |
| Wrong default tab | `defaultTab` | `blocks/user-dashboard/render.php:53-72`, `block.json:31` |
| Logged-out fatal/blank | login gate | `blocks/user-dashboard/render.php:36-45` |

## Not verified
Persistent-cache assertions (no Redis on the reference site); review create/delete in step 7 and dark mode were not run live. Steps 7 (favourite part) and 8 were run live.

## Cleanup
Remove the favourite and the `QA-J1-` review created in step 7.
