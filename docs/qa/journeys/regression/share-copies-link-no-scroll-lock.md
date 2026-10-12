---
journey: share-copies-link-no-scroll-lock
plugin: wb-listora
priority: high
roles: [anonymous, member, administrator]
covers: [listing-detail, interactivity]
prerequisites:
  - "Any published listing (e.g. $SITE_URL/listing/union-square-greenmarket/)"
estimated_runtime_minutes: 3
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Share works once and never locks the page

The Share button (`<button ... data-wp-on--click="actions.shareDialog" data-listora-track="share">`, `blocks/listing-detail/render.php:540`) was handled by the Interactivity store AND `assets/js/listing-detail-fallback.js`, so `navigator.share()` ran twice and the second call threw; without Web Share the store opened a `share` modal that has no markup and left `body` scroll-locked. Now the fallback skips when `e.defaultPrevented` (`listing-detail-fallback.js:86`) and the store copies the link (`src/interactivity/store.js:1804-1835`, toast text `listoraI18n.linkCopied` = "Link copied!", `includes/class-assets.php:300`).

Verified via: source read and the button markup on the live page. The click behaviour was NOT run in a browser here (see Not verified). Public control, same for every role; run as anonymous and once logged in to prove no login state matters.

## Steps

### 1. Browser with Web Share
- **Action**: stub `navigator.share = (d) => { window.__n = (window.__n||0)+1; return Promise.resolve(); }`, click the Share button.
- **Expect**: `window.__n === 1`, no console error.

### 2. Browser without Web Share (positive control that the copy path works)
- **Action**: `delete navigator.share` (set to `undefined`), click Share.
- **Expect**: one "Link copied!" toast, the listing URL on the clipboard (`navigator.clipboard.readText()`), `getComputedStyle(document.body).overflow !== 'hidden'`, the page still scrolls, no `[role=dialog]` opened.

### 3. 390px
- Share button >= 40px tall; toast not clipped; no horizontal overflow.

## Fail diagnostics
- Two share calls: the `e.defaultPrevented` guard in `assets/js/listing-detail-fallback.js:86` is gone.
- Page cannot scroll: `shareDialog` in `src/interactivity/store.js:1804` calls `openModal( 'share' )` again.
- Rebuild `build/interactivity/store.js` after any `src/` edit.

## Not verified
Steps 1-3 in a real browser.
