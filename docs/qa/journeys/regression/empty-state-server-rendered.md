---
journey: empty-state-server-rendered
plugin: wb-listora
priority: high
roles: [anonymous, member, admin]
covers: [listing-grid-empty-state, iapi-show-empty-state-getter, server-render-hydration, term-pages]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "A listing type with zero published listings: Event (0 on this site) and a query that matches nothing"
estimated_runtime_minutes: 3
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A 0-result directory shows its empty state on first paint and after a live filter

The grid's empty card is `.listora-card.listora-card--empty.listora-grid__empty[role="status"]` (`templates/blocks/listing-grid/grid.php:120-142`). It carries `is-hidden` only when results exist. Hydration must not hide it: `state.showEmptyState` (store getter) must be true when `totalResults === 0` regardless of `hasSearched`.

2.0.0 note: category, location, tag, feature and type pages now show the Directory filtered to the term (title + breadcrumb) and the plain `/listing/` archive 301s to `/listings/`; there is no `/business/` archive.

Verified via: curl + DOM (first paint) on 2026-10-12; the live-filter and getter steps need a browser and were not run.

## Steps

### 1. First paint of a 0-result URL
- **Action**: GET `/listing-type/event/` (term page, 0 listings) and `/listings/?listora_keyword=xyzzy12345`.
- **Expect**: interactivity state `totalResults` 0; the empty card is present WITHOUT `is-hidden`; title "No listings found", description "Try adjusting your filters, or be the first to add a listing.", button "Clear All Filters" (`actions.clearAllFilters`). `/listing-type/event/` has `<h1>Event</h1>` and a breadcrumb.

### 2. POSITIVE CONTROL: results present hides it
- **Action**: GET `/listing-type/hotel/` (20 results).
- **Expect**: the same card is in the DOM WITH `is-hidden`. The hidden/visible switch is the only difference.

### 3. Getter after hydration (browser)
- **Action**: `getComputedStyle(document.querySelector('.listora-grid__empty')).display` and the store's `showEmptyState`.
- **Expect**: `display !== 'none'`, `true`. Not run.

### 4. Clear All Filters
- **Action**: from `/listings/?listora_keyword=xyzzy12345` click "Clear All Filters".
- **Expect**: keyword param stripped, 104 results, card hidden again. Not run.

### 5. Live filter to 0
- **Action**: on `/listings/` type `xyzzy12345` in `#listora-keyword`, press Enter.
- **Expect**: count drops to 0 and the card appears. Not run.

### 6. Type-pinned grids use different copy
- See grid-pinned-type-empty-state.

### 7. Role ladder
- The state is public and identical for anonymous, member, owner, editor and admin; the "Add a listing" button only appears when `wb_listora_can_offer_submission()` is true for the viewer. Admin is only the control.

### 8. 390px
- **Expect**: card centred, no horizontal scroll. Not run.

## Fail diagnostics

| Symptom | File |
|---|---|
| Hidden on first paint | `templates/blocks/listing-grid/grid.php:130` class `is-hidden` only when `$total > 0` |
| Visible server-side, hidden after hydration | `src/interactivity/store.js` `showEmptyState` getter |
| Term page shows blog loop | `/listing/` redirect and term templates (see readme 2.0.0 "Category, location, tag and type pages") |
