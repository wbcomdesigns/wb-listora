---
journey: dashboard-listing-type
plugin: wb-listora
priority: normal
roles: [owner, other, admin, guest]
covers: [user-dashboard-block, dashboard-listings-rest, listing-type-control, covers_card 10213596281]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "{owner} owns listings of at least TWO different listing types"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/dashboard-listing-type.md
---

# A dashboard pinned to a listing type manages only that type

## Why
Sites running Jobs, Classifieds and Real Estate on separate pages pin each page's User Dashboard block to one type. The server rows, the Overview stat tile and `GET /listora/v1/dashboard/listings` must agree, or an owner sees "12 Active" above a list of 3. Scope is listings only: reviews, favourites and claims stay member-wide.

All three surfaces scope through `wb_listora_listing_type_query_args()` (`includes/helpers.php:262`), `wb_listora_count_user_listings()` (`:296`) and `Dashboard_Data::stats( $user_id, $type )`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- {owner} owns listings of type A and type B (create a `QA-J1-` listing of a second type, or use a typeless listing as the "other" case, and delete it afterwards). Capture `A` = `{type}`, `B`, the counts per type and `N_ALL` via `wp post list --author=<owner id> --post_type=listora_listing --format=count` with a `--tax_query` or REST.
- A page with the User Dashboard block pinned: `<!-- wp:listora/user-dashboard {"listingType":"<A>"} /-->`; an unpinned dashboard (`wb_listora_get_dashboard_url()`).

## Steps

### 1. The editor offers the type
- **Action**: {admin} adds a User Dashboard block > Content panel.
- **Expect**: a "Listing Type" dropdown ("All types" + every type by name) with help text explaining it limits My Listings; the same shared control as the other blocks.
- **Read-back**: saved block comment carries `"listingType":"<A>"`.

### 2. Rows are scoped
- **Action**: open the pinned page `?tab=listings` as {owner}.
- **Expect**: only type-A rows; type-B rows appear on an unpinned dashboard and on one pinned to B.
- **Read-back**: row count equals the type-A count from Setup.

### 3. The tile agrees with the tab
- **Action**: open Overview on the same pinned page.
- **Expect**: "Active" counts type A only; a tile of 12 above a list of 3 is the failure.
- **Read-back**: `SELECT COUNT(*)` of published type-A rows by {owner} equals the tile; the `Dashboard_Data::stats()` cache key carries the type (`"stats:user:{id}:type:{slug}"`, `class-dashboard-data.php:46`), so two pinned pages never serve each other's numbers.

### 4. Renewal filters stay inside the type
- **Action**: with renewal on, use Expiry (Any / Active / Expiring soon / Expired) and Status on a pinned dashboard.
- **Expect**: every filter and its pager count stays within the pinned type, including "Active = published minus expiring" whose expiring-IDs probe is a separate query.
- **Read-back**: summed pager totals per filter never exceed the type-A count.

### 5. REST returns the same set
- **Action**: `GET /listora/v1/dashboard/listings?listing_type=<A>` as {owner} (cookie + `X-WP-Nonce`), then without the param.
- **Expect**: `total` = type-A count, `pages` = ceil(total / per_page), `listing_type` per row = A's name; without the param `total` = `N_ALL`. Same rows and `total` as the rendered tab.
- **Read-back**: control: with vs without the param differ by the non-A rows, proving the filter removes something.

### 6. Cursor pagination stays inside the type
- **Action**: same route with `per_page=1&cursor=0`, follow `next_cursor` until `has_more` is false.
- **Expect**: every type-A listing is reached once, newest first, none of type B; `has_more: true` until the last empty page.
- **Read-back**: collected ids equal step 5's ids. On fail: type must be inside the SELECT, not applied to its result (`includes/rest/class-dashboard-controller.php:445` `fetch_listing_ids_after_cursor`).

### 7. An unknown slug shows nothing, not everything
- **Action**: pin the block to a non-type slug, or `?listing_type=not-a-type`.
- **Expect**: `total: 0, pages: 0`, `listings: []`; the dashboard shows an empty list.
- **Read-back**: REST body as above. Silently widening to every listing is the failure.

### 8. Existing sites are untouched
- **Action**: a dashboard block with no `listingType`.
- **Expect**: identical to before: all types, all tabs.
- **Read-back**: tile equals `N_ALL` published + pending.

### 9. Scale
- **Action**: as {admin} (owner of the large seed) pin to a type with thousands of rows.
- **Expect**: 20 rows per page, "Page 1 of N (X items)"; page past the end clamps to the last page.
- **Read-back**: `X` equals the REST `total`; request answers well under a second. Seed via the fixtures script if the site has < 2,000 rows.

### 10. 390 and dark
- **Action**: repeat steps 2-3 at 390 and in dark mode.
- **Expect**: no horizontal scroll, controls >= 40px, values unchanged.
- **Read-back**: same queries. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-10 |
| {other} | the pinned dashboard shows only their own type-A listings |
| {guest} | login prompt; `GET /dashboard/listings` returns 401 |
| {admin} | sees their own listings only, never site totals; paginates at scale |

## Pass criteria
1. Rows, Overview tile and REST agree for a pinned type, with and without the param.
2. An unknown type yields nothing; an unpinned dashboard is unchanged.
3. Cursor paging reaches every row of the type and none of another.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Editor lacks the control | attribute missing | `src/blocks/user-dashboard/index.js:41-42`, `blocks/user-dashboard/block.json` |
| Wrong rows | `$dashboard_type` | `blocks/user-dashboard/render.php:104`, `:222`, `:272` |
| Tile disagrees | cache key lacks type | `includes/core/class-dashboard-data.php:46` |
| Cursor strands rows | type applied after SELECT | `includes/rest/class-dashboard-controller.php:445` |

## Not verified
Steps 1-4 on a pinned page need a published page with the pinned block and were not run; steps 5-7 (REST) and the unpinned tile were read live. Steps 9-10 added.

## Cleanup
Delete the `QA-J1-` listing and the pinned test page.
