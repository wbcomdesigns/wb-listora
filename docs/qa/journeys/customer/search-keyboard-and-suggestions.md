---
journey: search-keyboard-and-suggestions
plugin: wb-listora
priority: critical
roles: [guest, owner, admin]
covers: [search-enter-key, listing-search, interactivity-store, F-05, search-suggest, iapi-state, REST /search/suggest, search-clear-button, "#9962442616", search-on-enter, card-10360339355]
prerequisites:
  - "Directory page with listing-search + listing-grid at $SITE_URL{directory}"
  - "$KEYWORD: matches exactly one listing title and does not start with a stop word; $NOMATCH: a string that matches nothing"
  - "At least 3 published listings with searchable titles"
  - "Chromium/WebKit browser (the native search-cancel control is a WebKit/Blink feature)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/search-enter-key-filters.md
  - ora:regression/search-suggest-envelope-unwrap.md
  - ora:regression/search-single-clear-icon.md
  - ora:regression/directory-search-and-small-fixes.md
fails_on: unrecorded
site_profile: required
---

# Typing only suggests; Enter, the Search button and the clear icon each do one clear thing

## Why
Typing a keyword and pressing Enter is how most people search; it once did nothing until `handleSuggestionKeydown()` called `searchImmediate()`. The suggest endpoint wraps its rows in `{suggestions: [...]}` and the dropdown must unwrap it. WebKit painted a second native clear (x) beside Listora's own.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `BASELINE` = text of `.listora-grid__count` on the Directory with no filters; `TOTAL` = `X-WP-Total` of `GET $SITE_URL/wp-json/listora/v1/search`.
- Run keyboard steps with a real `page.keyboard.press('Enter')`, never `form.submit()`. After editing `src/`, rebuild: `build/interactivity/store.js` and `build/blocks/*/view.js` are what ships.

## Steps

### 1. Suggest REST envelope
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/search/suggest?q=${PREFIX}"` where `${PREFIX}` is 3 letters of a listing title.
- **Expect**: an object with a `suggestions` array whose items have `type, id, text, meta, url`; an empty query returns `{"suggestions":[]}` (`includes/rest/class-search-controller.php:871,967`).
- **Read-back**: REST total for the same prefix via `/search?keyword=${PREFIX}` is not smaller than the suggestion count.

### 2. Typing only suggests
- **Action**: open the Directory, focus `#listora-keyword` (role combobox), type `${PREFIX}` at ~150 ms per key, wait 2 s.
- **Expect**: exactly one `GET /search/suggest?q=${PREFIX}&type=` request (debounced, not per key); `#listora-suggestions` (`role=listbox`) is visible, input `aria-expanded="true"`, 1+ `.listora-search__suggestion-item` each with the title; URL, count and grid unchanged (typing only runs `setSearchQuery`).
- **Read-back**: in DevTools `state.suggestions` is an Array, not `{suggestions: [...]}` (`store.js:1336-1337`).

### 3. Zero matches keeps the dropdown closed
- **Action**: clear, type `$NOMATCH`.
- **Expect**: 200 `{"suggestions":[]}`; `aria-expanded` stays `false`; no empty listbox is visible.

### 4. Enter filters the grid
- **Action**: baseline, fill `#listora-keyword` with `$KEYWORD`, press Enter.
- **Expect**: `.listora-grid__count` becomes "Showing 1-1 of 1 listings"; URL gains `listora_keyword=`; `#listora-suggestions` closes; exactly one `.listora-card` (class is `.listora-card`; `.listora-listing-card` does not exist) and it is the matching listing; the Filters badge reads `1`.
- **Read-back**: the count equals `GET /search?keyword=$KEYWORD` `total`; server render of `?listora_keyword=$KEYWORD` carries `#listora-keyword` with `value`.

### 5. Search button and Enter agree
- **Action**: reload, set `$KEYWORD`, click `.listora-search__submit` (text "Search").
- **Expect**: identical count text and card set as step 4.

### 6. No results shows the empty state
- **Action**: reload, type `$NOMATCH`, Enter.
- **Expect**: count text "0 results" and `.listora-grid__empty` computed-visible (`getComputedStyle(el).display !== 'none'`; the node is always in the DOM and carries `is-hidden` when there are results, `templates/blocks/listing-grid/grid.php:130`).
- **Positive control**: with `$KEYWORD` the same node has `is-hidden`.

### 7. Highlighted suggestion wins over a raw search; Escape closes without searching
- **Action**: type 2+ chars, ArrowDown to highlight one, Enter.
- **Expect**: navigates to that suggestion's `url` (a listing permalink), not a keyword search.
- **Action**: reopen suggestions, press Escape.
- **Expect**: list closes, no search fires, URL unchanged.

### 8. Location field
- **Action**: fill `#listora-location` with `$CITY`, Enter.
- **Expect**: grid filters and the URL gains `listora_location=`, same as the Search button.

### 9. Exactly one clear icon
- **Action**: type text in `#listora-keyword`, keep focus; inspect `.listora-search__field--keyword` at 1440 and 390.
- **Expect**: exactly one clear control (`.listora-search__clear`, `aria-label="Clear search"`); no native x inside the input edge (`blocks/listing-search/style.css:76-77`, `style-rtl.css:76-77` suppress `::-webkit-search-cancel-button` and `::-webkit-search-decoration`).
- **Positive control**: disable the `::-webkit-search-cancel-button` rule in DevTools: a second x appears, proving the check can fail.

### 10. Clear works
- **Action**: click `.listora-search__clear`.
- **Expect**: input empties, `#listora-suggestions` `aria-expanded="false"`, the grid re-runs the unfiltered search (`clearSearchQuery()` calls `searchImmediate()`, `store.js:1313-1319`); URL loses `listora_keyword`.

### 11. A keyword that starts with a stop word still finds its listing
- **Action**: search the full title of a listing that begins with "The " (for example `?listora_keyword=The $LISTING_TITLE`).
- **Expect**: the listing is returned (1+ result). `expected-fail: leading stop word returns 0 results (InnoDB stop word with a + prefix in build_boolean_keyword, includes/search/class-search-engine.php:316-346)`.

### 12. 390px and RTL
- **Expect**: no horizontal scroll (`document.documentElement.scrollWidth === innerWidth`); Search button >= 40px tall; single clear icon in an RTL locale (`style-rtl.css:76`). Not verified live.

## Role ladder
| Actor | Steps | Expect |
|---|---|---|
| {guest} | 1-12 | search is public |
| {owner} (member) | 2, 4-6 | identical results |
| {admin} (control) | 4 | same count (drafts never in the public directory) |

## Pass criteria
1. Typing never changes URL, count or grid; it only fills suggestions (once per pause).
2. Enter, Search button and location Enter filter identically; counts agree with REST.
3. No-match shows "0 results" and a visible empty card.
4. Highlighted suggestion + Enter selects it; Escape closes without a search.
5. One clear icon at 1440 and 390; Clear resets the grid.
6. Step 11 is the only expected-fail.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| URL gains `?listora_keyword=` but grid unchanged | Enter branch | `src/interactivity/store.js:1345-1389` (calls `actions.searchImmediate()`); button `templates/blocks/listing-search/search-bar.php:125` |
| Source right, browser wrong | stale bundle | `build/interactivity/store.js`, `build/blocks/*/view.js` |
| Highlighted suggestion ignored | early return after `highlighted.click()` | `store.js:1368-1372` |
| Typing changes the grid | `setSearchQuery` runs a search | `store.js:952` |
| Listbox opens, zero items / flashes empty | envelope assigned / flag unconditional | `store.js:1336-1337` |
| Bare array from REST | controller shape | `class-search-controller.php:967` |
| JS error with 200 | return shape | `src/utils/abortable-fetch.js` |
| Empty state never visible | asserted existence, not computed visibility | `templates/blocks/listing-grid/grid.php:130` |
| Two x icons | suppress rule lost | `blocks/listing-search/style.css:76` |

## Not verified
Steps 2-12 in a real browser were not run in the source journeys (curl and source reads only). Steps 11 and 12 were added in consolidation.

## Cleanup
None (read-only).
