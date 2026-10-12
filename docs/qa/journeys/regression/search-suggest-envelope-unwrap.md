---
journey: search-suggest-envelope-unwrap
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [F-05, search-suggest, iapi-state, REST /search/suggest]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 3 published listings with searchable titles (demo content)"
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Search suggest dropdown renders the inner array

`GET /listora/v1/search/suggest?q=<x>` returns `{ "suggestions": [ {type,id,text,meta,url}, ... ] }` (verified for `q=gre`: items of type `listing` and `location`). The Interactivity action `fetchSuggestions()` (`src/interactivity/store.js:1321-1342`) must unwrap it: `state.suggestions = Array.isArray( response?.suggestions ) ? response.suggestions : []` (`:1336`) and `state.showSuggestions = state.suggestions.length > 0` (`:1337`). Suggestions start at 2 typed characters (`state.searchQuery.length < 2` returns).

Verified via: curl of the endpoint and a source read; the dropdown render and click were NOT run in a browser here (see Not verified). Public surface, same for every role.

## Steps

### 1. REST envelope
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/search/suggest?q=gre"`.
- **Expect**: object with a `suggestions` array; items have `type, id, text, meta, url` (observed "The Greenwich Hotel", "Union Square Greenmarket", ...). Controller: `includes/rest/class-search-controller.php:967` (and `:871` for the empty-query case, returns `{"suggestions":[]}`).

### 2. Dropdown renders items (positive control)
- **Action**: open `$SITE_URL/listings/`, focus `#listora-keyword`, type `gre` slowly (about 150ms per char).
- **Expect**: network `GET /wp-json/listora/v1/search/suggest?q=gre&type=<selected type>` 200; `#listora-suggestions` (`role=listbox`) visible, the input has `aria-expanded="true"`; 1+ `.listora-search__suggestion-item` each with the title text.

### 3. Zero matches keeps it closed
- **Action**: clear, type `xyzqqq`.
- **Expect**: 200 with `{"suggestions":[]}`; `aria-expanded` stays `false`; no empty listbox visible.

### 4. Click a suggestion
- **Expect**: navigates to the suggestion's `url` (a listing permalink).

### 5. State shape (DevTools)
- `state.suggestions` is an Array, not `{suggestions: [...]}`.

## Fail diagnostics
| Symptom | File |
|---|---|
| Listbox opens, zero items | `store.js:1336` assigns the envelope |
| Empty listbox flashes | `store.js:1337` flag set unconditionally |
| Bare array from REST | `class-search-controller.php:967` |
| JS error with 200 | `src/utils/abortable-fetch.js` return shape |

## Not verified
Steps 2-5 in a browser.
