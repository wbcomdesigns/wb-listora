---
journey: search-single-clear-icon
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: ["#9962442616", "search clear button", "native search-cancel suppression"]
prerequisites:
  - "Chromium/WebKit browser (the native cancel control is a WebKit/Blink feature)"
estimated_runtime_minutes: 1
---

# Search keyword input shows exactly ONE clear (×) icon

Card #9962442616: the keyword field is `<input type="search">`, so
WebKit/Blink painted its NATIVE `::-webkit-search-cancel-button` at the
input's right edge — the same spot where the block renders its own
`.listora-search__clear` button. With text entered, two × icons stacked.

Fix: `blocks/listing-search/style.css` (+ RTL twin) suppress
`::-webkit-search-cancel-button` / `::-webkit-search-decoration` on
`.listora-search__input`; the block's own clear button is the single
affordance.

## Steps

### 1. One × at desktop
- **Action**: on the Directory page, type text into the keyword search input
  (keep focus). Screenshot `.listora-search__field--keyword`.
- **Expect**: exactly one × control (the circular `.listora-search__clear`
  button); no smaller native × inside the input edge.

### 2. One × at 390px
- **Action**: repeat at 390px viewport.
- **Expect**: same — single clear control, no overlap.

### 3. Clear still works
- **Action**: click the clear button.
- **Expect**: input empties and `state.searchQuery` resets (suggestions
  close).
