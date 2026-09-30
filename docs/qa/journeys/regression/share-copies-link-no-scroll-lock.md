---
journey: share-copies-link-no-scroll-lock
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-detail, interactivity]
prerequisites:
  - "Combo, any published listing"
estimated_runtime_minutes: 3
---

# Share works once and never locks the page

## Background

Found in the 1.9.0 overlay audit. The Share button was handled twice (the Interactivity store and `listing-detail-fallback.js`), so `navigator.share()` ran twice and the second call threw. On a browser without Web Share the store opened a `share` modal that has no markup: nothing appeared, and `body` was left with scroll locked.

## Steps

### 1. Browser with Web Share
Stub `navigator.share` to count calls, click Share.
- **Expect**: exactly 1 call, no console error.

### 2. Browser without Web Share
Delete `navigator.share`, click Share.
- **Expect**: one "Link copied!" toast, the listing URL on the clipboard, `body` overflow is not `hidden`, the page still scrolls.

## Fail diagnostics
- Two calls -> the `e.defaultPrevented` guard in `assets/js/listing-detail-fallback.js` is gone.
- Page cannot scroll -> `shareDialog` in `src/interactivity/store.js` is calling `openModal( 'share' )` again.
