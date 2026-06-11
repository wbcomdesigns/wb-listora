---
journey: search-suggest-envelope-unwrap
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [F-05, search-suggest, iapi-state, REST /search/suggest]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 3 published listings with searchable titles (e.g. demo content seeded)"
estimated_runtime_minutes: 2
---

# Search suggest dropdown renders inner array (F-05 sentinel)

The REST endpoint `GET /listora/v1/search/suggest?q=<x>` returns an envelope
`{ suggestions: [...] }`. The IAPI store action `fetchSuggestions()` must
unwrap the inner array before assigning to `state.suggestions` — assigning
the whole envelope makes IAPI's `<ul data-wp-each>` iterate over the
envelope's KEYS instead, rendering nothing in the dropdown.

Originally smoke F-05 (2026-05-18 BLOCKED): listbox opened (HTTP 200,
`aria-expanded=true`, hidden flag cleared) but rendered zero `<li>`.
Fix: commit 773a89a — `src/interactivity/store.js` action now does
`Array.isArray( response?.suggestions ) ? response.suggestions : []`
and only flips `state.showSuggestions = true` when the array is non-empty.

## Setup

- Site: `$SITE_URL`
- Anonymous browser session

## Steps

### 1. Confirm REST envelope shape
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/search/suggest?q=gre"`
- **Expect**: response is JSON with shape `{ "suggestions": [ {type,id,text,meta,url}, ... ] }`
- **On fail**: REST controller drift — `includes/rest/class-search-controller.php` ~line 694 should still wrap in envelope. If the shape changed to a bare array, the JS unwrap will silently break.

### 2. Open the listings page
- **Action**: `playwright_navigate $SITE_URL/listings/`
- **Expect**: search bar `#listora-keyword` (or equivalent) renders

### 3. Type 3+ chars matching a listing
- **Action**: focus `#listora-keyword`, type `gre` slowly (one char per ~150ms to trigger debounced fetch)
- **Wait**: 2s for debounce + fetch
- **Expect**:
  - Network: GET `/wp-json/listora/v1/search/suggest?q=gre&type=*` returns 200
  - `#listora-suggestions` listbox flips visible (`aria-expanded=true`, `hidden` removed)
  - Listbox contains 1+ `<li>` entries with matching listing titles
  - Each `<li>` has text content + a URL link to the listing

### 4. Type a query with zero matches
- **Action**: clear input, type `xyzqqq` slowly
- **Wait**: 2s
- **Expect**:
  - Network: GET `/search/suggest?q=xyzqqq` returns 200 with `{ "suggestions": [] }`
  - Listbox does NOT open (`state.showSuggestions` stays false when array is empty — the secondary part of the F-05 fix)
  - No empty listbox visible to the user

### 5. Click a suggestion item
- **Action**: type `gre` again, wait for dropdown, click first `<li>`
- **Expect**: page navigates to the listing detail URL from the suggestion `url` field

### 6. Verify in DevTools (manual)
- **Action**: open DevTools → Application → on the suggestion fetch:
  - Read `window.wp.interactivity.getNamespace('listora/directory').state.suggestions`
  - **Expect**: array of objects (NOT an object with a `suggestions` key)
- **On fail**: `state.suggestions` is `{ suggestions: [...] }` → the unwrap is broken. Check `src/interactivity/store.js` ~line 754.

## Pass criteria

1. Endpoint returns `{ suggestions: [...] }` envelope (unchanged contract)
2. Dropdown renders one `<li>` per inner suggestion when array non-empty
3. Dropdown stays closed when array is empty (no empty listbox flashed)
4. Clicking a suggestion navigates to its `url`
5. `state.suggestions` is an Array, never an Object envelope

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Listbox opens but zero `<li>` rendered | regression of F-05 — JS assigning whole envelope to state | `src/interactivity/store.js` ~line 754: must be `Array.isArray( response?.suggestions ) ? response.suggestions : []` |
| Listbox flashes empty on every keystroke | secondary regression — `showSuggestions` flipped to true unconditionally | same file ~line 755: `state.showSuggestions = state.suggestions.length > 0` |
| Endpoint returns bare array | REST contract drift | `includes/rest/class-search-controller.php` ~line 694 — must still `return new WP_REST_Response( array( 'suggestions' => $suggestions ), 200 )` |
| Network 200 but JS error in console | abortableApiFetch wrapping changed | `src/utils/abortable-fetch.js` — return shape must be the parsed JSON body |
