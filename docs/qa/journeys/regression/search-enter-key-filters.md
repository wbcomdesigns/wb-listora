---
journey: search-enter-key-filters
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber]
covers: [search-enter-key, listing-search, interactivity-store]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Directory page with listing-search + listing-grid (this site: $SITE_URL/listings/, page 'Directory')"
  - "A KEYWORD that matches exactly one listing title and does NOT start with a stop word (verified: KEYWORD = 'Greenwich Hotel' -> 1 result), and NOMATCH = 'xyzqqq'"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Enter in the directory search box must filter the grid

Typing a keyword and pressing Enter is how most people search. It did nothing until `handleSuggestionKeydown()` called `searchImmediate()` (the same action the Search button runs). 2.0.0 also makes Enter work in the Location field (same handler, `templates/blocks/listing-search/search-bar.php:48,105`).

Verified via: curl of the server-rendered states (`?listora_keyword=`) plus a source read. The real key press in a browser was NOT executed in this pass (shared browser unavailable): run steps 2-6 with Playwright `page.keyboard.press('Enter')`, not `form.submit()`.

## Setup
- Directory: `$SITE_URL/listings/` (page ID 8). Public rows: 84 published here (the 2,709 other listings are drafts), 20 per page, 5 pages.
- **Do not use a keyword that starts with "The"**: `?listora_keyword=The Greenwich Hotel` returns 0 results although the title matches (InnoDB stop word with a `+` prefix, reported as a product bug). `Greenwich Hotel` returns 1.

## Steps

### 1. Baseline
- **Action**: open `$SITE_URL/listings/`.
- **Capture**: `.listora-grid__count` text: "Showing 1–20 of 84 listings" (`BASELINE`).

### 2. Type a keyword and press Enter
- **Action**: fill `#listora-keyword` with `KEYWORD`, press a real Enter.
- **Expect**: `.listora-grid__count` becomes "Showing 1–1 of 1 listings"; URL gains `listora_keyword=Greenwich+Hotel`; `#listora-suggestions` closes.
- **On fail**: `src/interactivity/store.js:1345-1389` (`handleSuggestionKeydown`, Enter branch calls `actions.searchImmediate()`). Check the bundles too: `build/interactivity/store.js` and `build/blocks/*/view.js`; a `src/` edit alone ships nothing.

### 3. The matching card is the one shown
- **Expect**: the single `.listora-card` is "The Greenwich Hotel". Card class is `.listora-card` (`.listora-listing-card` does not exist; it silently returns zero).

### 4. Enter and the Search button agree
- **Action**: reload `/listings/`, set `KEYWORD`, click `.listora-search__submit` (button text "Search").
- **Expect**: identical count text and card set as step 2.

### 5. No results shows the empty state
- **Action**: reload, type `NOMATCH`, Enter.
- **Expect**: count text "0 results" and `.listora-grid__empty` computed-visible (`getComputedStyle(el).display !== 'none'`; the node is always in the DOM and carries `is-hidden` when there are results, `templates/blocks/listing-grid/grid.php:130`). Positive control: with `KEYWORD` the same node has `is-hidden`.

### 6. Suggestions still work
- **Action**: type `gre` (2+ chars triggers the fetch), ArrowDown to highlight one, Enter.
- **Expect**: the highlighted suggestion's click path runs (navigates to its listing), not a raw keyword search.
- **Action**: reopen suggestions, press Escape. **Expect**: list closes, no search fires.

### 7. Location field
- **Action**: fill `#listora-location` with a city (e.g. "Manhattan"), Enter. **Expect**: the grid filters (URL gains `listora_location=`), same as the Search button.

### 8. Mobile + large set
- 390x844: repeat step 2; no horizontal scroll (`document.documentElement.scrollWidth === innerWidth`), Search button >= 40px tall.
- Directory past the last page: `?listora_page=99` shows the last page ("Showing 81–84 of 84 listings"), not an inverted range.

### 9. Role ladder
anonymous and subscriber (`subscriber2`): identical results for steps 2-5 (search is public); admin as control: same counts (admin does not see drafts in the public directory).

## Pass criteria
1. Enter filters like the button. 2. Counts agree between Enter and button. 3. No-match: "0 results" and visible empty state. 4. Highlighted suggestion + Enter selects it; Escape closes without search. 5. Works at 390 with no overflow.

## Fail diagnostics
| Symptom | File |
|---|---|
| URL gains `?listora_keyword=` but grid unchanged | `src/interactivity/store.js:1366-1388` |
| Source right, browser wrong | rebuild `build/interactivity/store.js`, `build/blocks/*/view.js` |
| Highlighted suggestion ignored | early `return` after `highlighted.click()` at `store.js:1368-1372` |
| Empty state never visible | asserting existence instead of computed visibility |
| Full title with leading "The" returns 0 | `includes/search/class-search-engine.php:316-346` (`build_boolean_keyword`) |
