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
---

# A dashboard pinned to a listing type manages only that type

Regression sentinel for the per-type dashboard page.

## Background

Sites running Jobs, Classifieds and Real Estate on separate pages want each page's dashboard to manage that page's listings. The block had no `listingType` attribute at all, so every dashboard showed everything the member owned (support ticket 233992000087306088).

The trap this journey exists to catch is disagreement between surfaces: the server-rendered rows, the overview stat tile that links to those rows, and `GET /dashboard/listings` for the app all have to return the same set. They are scoped through two shared helpers - `wb_listora_listing_type_query_args()` and `wb_listora_count_user_listings()` in `includes/helpers.php` - precisely so a future change cannot move one and not the others.

Scope is deliberate: **listings only**. Reviews, favourites and claims are not per-type surfaces and stay member-wide, and so does `/dashboard/stats`.

## Steps

### 1. The editor offers the type
- **Action**: admin → add a User Dashboard block → Content panel.
- **Expect**: a "Listing Type" dropdown ("All types" + every type by name), with help text explaining it limits My Listings. Same shared control as the other seven blocks.
- **On fail**: `src/blocks/user-dashboard/index.js`, `blocks/user-dashboard/block.json` (`listingType` attribute).

### 2. Rows are scoped
- **Action**: publish a page with the dashboard pinned to type A; log in as a member owning listings of A and B; open the Listings tab.
- **Expect**: only type-A listings. Type-B listings appear on an unpinned dashboard and on one pinned to B.
- **On fail**: `$dashboard_type_args` merges in `blocks/user-dashboard/render.php`.

### 3. The tile agrees with the tab
- **Action**: open the Overview tab on the same pinned page.
- **Expect**: "Active listings" counts type A only. A tile of 12 above a list of 3 is the cross-cutting check 8 failure this step exists for.
- **On fail**: the `$listing_counts` branch in `render.php`, or the transient cache key (it must carry the type - two pinned pages otherwise serve each other's numbers for 60 seconds).

### 4. Renewal filters stay inside the type
- **Action**: with the renewal feature on, use the All / Active / Expiring / Expired filter on a pinned dashboard.
- **Expect**: every filter stays within the pinned type, counts included - including the "Active = published minus expiring" path, whose expiring-IDs probe is a separate query.

### 5. REST returns the same set
- **Action**: `GET /listora/v1/dashboard/listings?listing_type=<A>` as that member (cookie + nonce).
- **Expect**: same listings and same `total` as the rendered tab. Omitting the param returns everything, unchanged for existing clients.
- **On fail**: `Dashboard_Controller::get_listings`.

### 6. Cursor pagination stays inside the type
- **Action**: same route with `per_page=1&cursor=0`, then follow `next_cursor` to the end.
- **Expect**: every page holds only type-A listings, and the walk reaches ALL of them. A short page would make `has_more` read false early and strand listings the member owns.
- **On fail**: `fetch_listing_ids_after_cursor()` - the type has to be in that SELECT, not applied to its result.

### 7. An unknown slug shows nothing, not everything
- **Action**: pin the block to a slug that is not a type on the site (or pass `listing_type=not-a-type`).
- **Expect**: an empty dashboard / `total: 0`. Silently widening back to every listing is the failure mode the `status` enum on this same route was tightened for.

### 8. Existing sites are untouched
- **Action**: a dashboard block with no `listingType` (every site before this release).
- **Expect**: identical to before - all types, all tabs.

## Automated coverage

`tests/integration/DashboardListingTypeTest.php` covers steps 2, 3, 5, 6, 7 and 8 headlessly.
