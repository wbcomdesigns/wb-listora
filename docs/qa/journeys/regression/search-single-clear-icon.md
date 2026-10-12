---
journey: search-single-clear-icon
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: ["#9962442616", "search clear button", "native search-cancel suppression"]
prerequisites:
  - "Chromium/WebKit browser (the native cancel control is a WebKit/Blink feature)"
estimated_runtime_minutes: 1
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Search keyword input shows exactly ONE clear (x) icon

The keyword field `#listora-keyword` is `<input type="search">`, so WebKit/Blink painted its native `::-webkit-search-cancel-button` where the block renders its own `.listora-search__clear` (button, `aria-label="Clear search"`, `data-wp-on--click="actions.clearSearchQuery"`). `blocks/listing-search/style.css:76-77` (+ `style-rtl.css:76-77`) suppress `::-webkit-search-cancel-button` and `::-webkit-search-decoration` on `.listora-search__input`.

Verified via: source read and server-rendered markup only; the visual check needs a Chromium/WebKit browser and was not run here (see Not verified). Anonymous-only surface; no role-dependent markup.

## Steps

### 1. One x at desktop
- **Action**: Directory (`$SITE_URL/listings/`), type text in `#listora-keyword`, keep focus; screenshot `.listora-search__field--keyword`.
- **Expect**: exactly one clear control (the `.listora-search__clear` button); no native x inside the input edge. Positive control: in DevTools disable the `::-webkit-search-cancel-button` rule from `style.css:76` and confirm a second x appears, proving the check can fail.

### 2. One x at 390
- **Expect**: same, no overlap.

### 3. Clear works
- **Action**: click `.listora-search__clear`.
- **Expect**: input empties, suggestions close (`#listora-suggestions` `aria-expanded="false"`), the grid re-runs the unfiltered search (`clearSearchQuery()` calls `searchImmediate()`, `src/interactivity/store.js:1313-1319`); URL loses `listora_keyword`.

### 4. RTL
- With an RTL locale the same rule applies from `style-rtl.css:76`.

## Not verified
Steps 1-3 visual/behavioural (no browser run in this pass).
