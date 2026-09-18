---
journey: two-grids-independent-load-more
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-grid, pro-load-more, pro-infinite-scroll, iapi-context]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Pro active, Pagination type set to Load More"
  - "At least 2 listing types with more listings than one page each"
estimated_runtime_minutes: 8
covers_card: 10314572173
---

# Two grids on one page paginate independently

Regression sentinel for per-grid Interactivity state.

## Background

Every grid seeded its type, page size, page count and view mode into the single shared `listora/directory` Interactivity state, and Pro's Load More built its request from that state. Two grids on one page overwrote each other and the last one rendered won: clicking the **first** grid's Load More fetched `?type=hotel&per_page=6` and the restaurants section filled up with hotels. Pro then appended the response into `document.querySelector( '.wp-block-listora-listing-grid .listora-grid__results' )` — the first grid on the page, whichever button was clicked — so the two bugs partly hid each other.

Free's grid render now emits a `data-wp-context` per block carrying `gridType`, `gridPerPage`, `gridTotalPages`, `gridTotalItems`, `gridLoadedPages`, `gridPageTo` and `gridLoadingMore`. Pro's getters and load-more action read that context, and append into the grid that owns the clicked button.

The shared state keys stay: the Search block reads and writes them, and on a single-grid page the two agree.

## Steps

### 1. Each grid renders its own context
- **Action**: publish a page with `listing-grid {"listingType":"restaurant","perPage":3}` followed by `listing-grid {"listingType":"hotel","perPage":6}`. View source.
- **Expect**: two `data-wp-context` attributes carrying `gridType` — one `restaurant`/3, one `hotel`/6, with **different** page counts.

### 2. Each counter counts its own grid
- **Expect**: "3 of N listings shown" above the first, "6 of M" above the second, with different N and M.

### 3. The FIRST grid's Load More extends the FIRST grid
- **Action**: click the first grid's Load More.
- **Expect**: the first grid grows 3 → 6 and the new cards are **restaurants**. The second grid is untouched at 6.
- **On fail**: this is the reported bug. Check `loadMoreForGrid()` in `wb-listora-pro/assets/js/infinite-scroll.js` and the `data-wp-context` in `blocks/listing-grid/render.php`.

### 4. The SECOND grid's Load More extends the SECOND grid
- **Action**: click the second grid's Load More.
- **Expect**: second grid 6 → 12, new cards are **hotels**, first grid still at 6.

### 5. No console errors
- **Expect**: zero. The getters call `getContext()`, which resolves only inside directive evaluation — a "cannot read properties of undefined" here means something is calling them outside it.

### 6. Infinite scroll mode too
- **Action**: switch Pagination type to Infinite Scroll, reload, scroll each grid's sentinel into view.
- **Expect**: each grid loads its own next page. The IntersectionObserver fires **outside** directive evaluation, so the sentinel's init callback captures its context once and passes it in; if that regresses, infinite scroll breaks while Load More still works.

### 7. A single grid is unchanged
- **Action**: a page with one grid. Click Load More.
- **Expect**: cards append, the counter grows, and `?listora_page=2` is pushed to the address bar.

### 8. Two grids do NOT touch the address bar
- **Expect**: no `?listora_page` after clicking either button. It cannot describe two grids, and on reload it would be applied to both server renders.

## Automated coverage

`tests/integration/GridPerBlockContextTest.php` (5 tests) locks the server half — that the context exists, describes its own grid, and that the two differ. Steps 3-8 are browser behaviour and are verified here.
