---
journey: dashboard-listing-type
plugin: wb-listora
priority: normal
roles: [administrator, subscriber]
covers: [user-dashboard-block, dashboard-listings-rest, listing-type-control]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member owning listings of at least TWO different listing types"
estimated_runtime_minutes: 8
covers_card: 10213596281
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A dashboard pinned to a listing type manages only that type

The User Dashboard block has a `listingType` attribute, so sites running Jobs, Classifieds and Real Estate on separate pages can give each page's dashboard only that page's listings. The surfaces that must agree: the server-rendered rows, the Overview stat tile that links to them, and `GET /listora/v1/dashboard/listings` for the app. They are scoped through two shared helpers, `wb_listora_listing_type_query_args()` (`includes/helpers.php:262`) and `wb_listora_count_user_listings()` (`includes/helpers.php:296`), plus `Dashboard_Data::stats( $user_id, $type )`, so a future change cannot move one and not the others.

Scope is deliberate: **listings only**. Reviews, favourites and claims are not per-type surfaces and stay member-wide.

Roles: the member who owns listings of two types (actor); an administrator configures the block (editor sidebar) and is the control; another member and logged out see only their own / nothing.

## Setup

- A member owning listings of at least TWO types. QA site: `qa_vendor_01` owns six `restaurant` listings (7379-7384) and one listing with no type (845); no second typed type exists, so create one `QA-J1-` listing of another type (e.g. `hotel`) or use 845's empty type as the "other" case, and delete it afterwards.
- A page with the User Dashboard block pinned to type A (`<!-- wp:listora/user-dashboard {"listingType":"restaurant"} /-->`); an unpinned dashboard page (QA: `/my-listings/`).

## Steps

### 1. The editor offers the type
- **Action**: admin > add a User Dashboard block > Content panel.
- **Expect**: a "Listing Type" dropdown ("All types" + every type by name) with help text explaining it limits My Listings; the same shared control as the other blocks.
- **On fail**: `src/blocks/user-dashboard/index.js:41-42`, `blocks/user-dashboard/block.json` (`listingType`).

### 2. Rows are scoped
- **Action**: open the pinned page (`?tab=listings`) as the member.
- **Expect**: only type-A rows; type-B rows appear on an unpinned dashboard and on one pinned to B.
- **On fail**: `blocks/user-dashboard/render.php:104` (`$dashboard_type`) and `:222` / `:272`.

### 3. The tile agrees with the tab
- **Action**: open the Overview tab on the same pinned page.
- **Expect**: "Active" counts type A only (unpinned QA member: 2 Active = 7379 and 845; pinned to `restaurant`: 1). A tile of 12 above a list of 3 is the failure this step exists for.
- **On fail**: `Dashboard_Data::stats()` cache key must carry the type (`"stats:user:{id}:type:{slug}"`, `class-dashboard-data.php:46`), or two pinned pages serve each other's numbers.

### 4. Renewal filters stay inside the type
- **Action**: with renewal on, use the Expiry (Any / Active / Expiring soon / Expired) and Status selects on a pinned dashboard.
- **Expect**: every filter and its pager count stays within the pinned type, including the "Active = published minus expiring" path whose expiring-IDs probe is a separate query.

### 5. REST returns the same set
- **Action**: `GET /listora/v1/dashboard/listings?listing_type=restaurant` as the member (cookie + `X-WP-Nonce`).
- **Expect** (verified): `total: 6, pages: 1`, ids 7379-7384, `listing_type` per row "Restaurant"; without the param `total: 7` (845 has no type). Same rows and `total` as the rendered tab.

### 6. Cursor pagination stays inside the type
- **Action**: same route with `per_page=1&cursor=0`, then follow `next_cursor` until `has_more` is false.
- **Expect** (verified): six pages, `[7384]`, `[7383]`, ... `[7379]`, newest first, each with `has_more: true` until the last empty page; all six reached, none of 845. A short page that read `has_more` false early would strand listings.
- **On fail**: `includes/rest/class-dashboard-controller.php:445` (`fetch_listing_ids_after_cursor`): the type must be in that SELECT, not applied to its result.

### 7. An unknown slug shows nothing, not everything
- **Action**: pin the block to a slug that is not a type, or `?listing_type=not-a-type`.
- **Expect** (verified): `total: 0, pages: 0`, `listings: []`; the dashboard shows an empty list. Silently widening to every listing is the failure mode.

### 8. Existing sites are untouched
- **Action**: a dashboard block with no `listingType`.
- **Expect**: identical to before: all types, all tabs.

### 9. Positive control and role ladder
- **Control**: step 5 with and without the param (6 vs 7) proves the filter removes something.
- **Another member**: the pinned dashboard shows only their own type-A listings.
- **Logged out**: login prompt; `GET /dashboard/listings` returns 401.
- **Admin**: sees their own listings only (2,632 unpinned on the QA site); pinning to a type with thousands of rows paginates (20 per page, "Page 1 of N (X items)").

## Automated coverage

`tests/integration/DashboardListingTypeTest.php` covers steps 2, 3, 5, 6, 7 and 8 headlessly.

## Not verified

Steps 5, 6 and 7 (REST) and the unpinned Overview tile ("2 Active") were read live as `qa_vendor_01`. Steps 1, 2, 3 and 4 on a pinned page need a published page with the pinned block and were NOT run; no second type exists for that member on the QA site.
