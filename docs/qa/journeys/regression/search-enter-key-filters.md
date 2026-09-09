---
journey: search-enter-key-filters
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber]
covers: [search-enter-key, listing-search, interactivity-store]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Directory page with a listing-search + listing-grid block (default: /listings/)"
  - "At least one listing whose title matches a distinctive keyword"
estimated_runtime_minutes: 4
---

# Enter in the directory search box must filter the grid

Typing a keyword and pressing Enter is how most people search. It did nothing.

`handleSuggestionKeydown()` only acted on Enter when an autocomplete suggestion was
**highlighted**, and highlighting requires arrow-keying into the list. In the ordinary case —
type, press Enter — the handler fell through with no `preventDefault()` and no search call. The
URL picked up `?keyword=...`, which made it look like something had happened, while the grid kept
rendering the previous unfiltered results. The keyword had to be re-submitted with the Search
button to take effect.

The two paths now share one action: `searchImmediate()`, which is what the Search button has
always run.

This bug shipped in 1.7.0 and earlier. It was found by the 1.8.0 release smoke, not by a customer
report, which is the reason this journey exists — the surface is the most-visited in the product
and had no keyboard-path coverage at all.

## Setup

- Site: `$SITE_URL`
- Directory: `$SITE_URL/listings/`
- Pick a `KEYWORD` that matches exactly one listing title, and a `NOMATCH` string that matches none.

```bash
wp post list --post_type=listora_listing --post_status=publish --field=post_title | head -20
```

## Steps

### 1. Baseline the unfiltered grid
- **Action**: `playwright_navigate $SITE_URL/listings/`
- **Action**: `document.querySelector('[class*="listora-grid"]').innerText.slice(0,40)`
- **Capture**: `BASELINE_COUNT` <- the "Showing X of N" figure
- **Expect**: the full result set

### 2. Type a keyword and press Enter — the whole point of this journey
- **Action**: fill `#listora-keyword` with `KEYWORD`, then send a real Enter key press.
  Use an actual key event (`page.keyboard.press('Enter')`), NOT `form.submit()` or a
  synthetic click — the bug lived in the keydown handler and only a real key event exercises it.
- **Action**:
  ```js
  ({ url: location.href,
     grid: document.querySelector('[class*="listora-grid"]')?.innerText.trim().slice(0,60) })
  ```
- **Expect**: the grid reports the FILTERED count, not `BASELINE_COUNT`
- **On fail**: regression. `src/interactivity/store.js::handleSuggestionKeydown()` — the Enter
  branch must call `actions.searchImmediate()` when no suggestion is highlighted.
  **And check the build**: the store is bundled into `build/interactivity/store.js` and into
  several `build/blocks/*/view.js`. A fix in `src/` alone ships nothing — `npm run build` and
  commit the bundles.

### 3. The matching card is the one shown
- **Action**: read the card titles in the grid
- **Expect**: the listing matching `KEYWORD`, and nothing unrelated
- **Note**: the card class is `.listora-card` — `.listora-listing-card` does not exist and
  silently returns zero, which reads as a failure when the grid is actually correct.

### 4. Enter and the Search button must agree
- **Action**: reload `/listings/`, set the same `KEYWORD`, click the Search button instead
- **Expect**: byte-identical grid summary to step 2. Two code paths that disagree is the defect
  class this journey guards; they now share `searchImmediate()`.

### 5. No results shows the empty state
- **Action**: reload, type `NOMATCH`, press Enter
- **Expect**: the grid reports `0 results` AND `.listora-grid__empty` is
  computed-visible (`getComputedStyle(el).display !== 'none'`)
- **On fail**: the empty state is present in the DOM on every render and hidden with CSS, so
  asserting mere existence proves nothing — it is always there. Assert computed visibility.

### 6. Escape and arrow keys still behave
- **Action**: type a partial keyword to open suggestions; press ArrowDown to highlight one, then Enter
- **Expect**: the highlighted suggestion is chosen (its click path), NOT a raw keyword search —
  the pre-existing behaviour this fix had to preserve
- **Action**: reopen suggestions and press Escape
- **Expect**: the suggestion list closes and no search fires

### 7. Mobile
- **Action**: 390x844, repeat step 2
- **Expect**: same filtered result, no horizontal overflow

## Pass criteria

ALL of the following hold:
1. Enter filters the grid to the keyword.
2. Enter and the Search button produce the same result.
3. A no-match keyword yields `0 results` with a computed-visible empty state.
4. A highlighted suggestion + Enter still selects that suggestion.
5. Escape still closes the suggestion list without searching.
6. Works at 390px.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| URL gains `?keyword=` but the grid does not change | Enter branch does not call `searchImmediate()` | `src/interactivity/store.js::handleSuggestionKeydown()` |
| Source looks correct but the browser still misbehaves | built bundles not regenerated | `build/interactivity/store.js`, `build/blocks/*/view.js` — run `npm run build` |
| Enter now ignores a highlighted suggestion | the early `return` after `highlighted.click()` was dropped | same handler |
| Empty state never appears | asserting existence instead of computed visibility | `.listora-grid__empty` is always in the DOM |
