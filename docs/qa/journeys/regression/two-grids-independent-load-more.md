---
journey: two-grids-independent-load-more
plugin: wb-listora
priority: high
roles: [anonymous, member]
covers: [listing-grid, pro-load-more, pro-infinite-scroll, iapi-context]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Pro active, Settings > Pagination style = Load more button (option key `pagination_type` = `load_more`; this site currently has `pagination`, so set it for the run and restore)"
  - "At least 2 listing types with more listings than one page each (restaurant 19, hotel 20 here)"
estimated_runtime_minutes: 9
covers_card: 10314572173
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Two grids on one page paginate independently

Each grid used to share the single `listora/directory` Interactivity state, so two grids on one page overwrote each other and the last rendered won (the first grid's Load More fetched the second's type). Free's grid render now emits a `data-wp-context` per block (`blocks/listing-grid/render.php:160-169`) carrying `gridType`, `gridPerPage`, `gridTotalPages`, `gridTotalItems`, `gridCurrentPage`, `gridLoadedPages`, `gridPageFrom`, `gridPageTo`, `gridViewMode`, `gridLoadingMore`. Pro's getters and load-more action read that context (`wb-listora-pro/assets/js/infinite-scroll.js`, `loadMoreForGrid()` at `:40`, `getContext()` callers from `:199`) and append into the grid that owns the clicked button.

## Setup
Publish a page `QA-J1 two grids` with `<!-- wp:listora/listing-grid {"listingType":"restaurant","perPage":3} /-->` followed by `<!-- wp:listora/listing-grid {"listingType":"hotel","perPage":6} /-->`. Set Pagination style to **Load more button** (Pro settings). Restore both afterwards.

## Steps

### 1. Each grid renders its own context
- **Action**: `curl` the page and read each grid's `data-wp-context` JSON.
- **Expect** (2026-10-12): `{"gridType":"restaurant","gridPerPage":3,"gridTotalPages":7,"gridTotalItems":19,...,"gridPageTo":3}` and `{"gridType":"hotel","gridPerPage":6,"gridTotalPages":4,"gridTotalItems":20,...,"gridPageTo":6}`; different page counts.

### 2. Each counter counts its own grid
- **Expect**: `.listora-grid__count` reads "Showing 1-3 of 19 listings" above the first and "Showing 1-6 of 20 listings" above the second (it is `role="status"`; the numbers are `state.gridCountFrom/To/Total`).

### 3. The FIRST grid's Load More extends the FIRST grid
- **Action**: click the first grid's `button.listora-loadmore__btn` ("Load More").
- **Expect**: the first grid grows 3 -> 6 and the new cards are restaurants; the second grid is still 6. Pro's `.listora-loadmore__remaining` shows the remaining count.
- **On fail**: `loadMoreForGrid()` in `wb-listora-pro/assets/js/infinite-scroll.js:40` and the `data-wp-context` in `blocks/listing-grid/render.php`.

### 4. The SECOND grid's Load More extends the SECOND grid
- **Expect**: second grid 6 -> 12, new cards are hotels, first grid still 6.

### 5. No console errors
- **Expect**: zero. The getters call `getContext()`, valid only inside directive evaluation; "cannot read properties of undefined" means a call outside it.

### 6. Infinite scroll mode too
- **Action**: set Pagination style = Infinite scroll, reload, scroll each grid's sentinel into view.
- **Expect**: each grid loads its own next page (the IntersectionObserver fires outside directive evaluation, so the sentinel's init captures its context once).

### 7. A single grid is unchanged (positive control)
- **Action**: a page with one grid; click Load More.
- **Expect**: cards append, the counter grows, and `?listora_page=2` is pushed to the address bar (`infinite-scroll.js:144-145`).

### 8. Two grids do NOT touch the address bar
- **Expect**: no `?listora_page` after clicking either button (`:140` comment: it cannot describe two grids and would apply to both server renders on reload).

### 9. Large data
- **Action**: a grid with no `listingType` (all 2809 listings) and Load More repeatedly.
- **Expect**: each click adds `perPage` cards; the counter total is the count from `GET /listora/v1/search` `X-WP-Total`; no request carries a whole-table payload.

### 10. Role ladder / mobile
Anonymous and member see the same behaviour (public reads; anonymous is rate-limited at 120/min per IP). Admin: control. At 390 the Load More button is full width, >= 40px, no horizontal scroll.

## Automated coverage
`tests/integration/GridPerBlockContextTest.php` locks the server half (context exists, describes its own grid, differs per grid). Steps 3-8 are browser behaviour.

## Not verified
Only steps 1 and 2 (server HTML for context and counter markup) were run, on 2026-10-12, via curl against a temporary page (deleted). The Load More clicks (3, 4, 6-8), console check and the mobile checks were not run; the pagination setting was not changed.

Verified via: curl/DOM.
