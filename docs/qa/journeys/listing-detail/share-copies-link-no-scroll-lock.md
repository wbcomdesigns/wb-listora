---
journey: share-copies-link-no-scroll-lock
group: listing-detail
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [guest, other, admin]
covers: [listing-detail, interactivity, share]
prerequisites:
  - "Any published {listing}"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "Share handled by both the store and listing-detail-fallback.js (navigator.share ran twice)"
---

# Share works once and never locks the page

## Why
The Share button (`data-wp-on--click="actions.shareDialog" data-listora-track="share"`, `blocks/listing-detail/render.php`) was handled by the Interactivity store AND `assets/js/listing-detail-fallback.js`, so `navigator.share()` ran twice and the second call threw; without Web Share the store opened a `share` modal that has no markup and left `body` scroll-locked, so the member could not scroll again.

Now the fallback skips when `e.defaultPrevented` and the store copies the link (`src/interactivity/store.js`, toast text `listoraI18n.linkCopied` = "Link copied!").

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Public control: run as {guest}, and once as {other} to prove login state does not matter.

## Steps

### 1. Browser with Web Share
- **Action**: stub `navigator.share = (d) => { window.__n = (window.__n||0)+1; return Promise.resolve(); }`, click Share.
- **Expect**: `window.__n === 1`, no console error.

### 2. Browser without Web Share (copy path)
- **Action**: set `navigator.share` to `undefined`, click Share.
- **Expect**: one "Link copied!" toast, the listing URL on the clipboard (`navigator.clipboard.readText()`), `getComputedStyle(document.body).overflow !== 'hidden'`, the page still scrolls, no `[role=dialog]` opened.

### 3. Share rejected or clipboard denied
- **Action**: make `navigator.share` reject (user cancel) and, separately, deny clipboard permission.
- **Expect**: no error toast storm, page not locked, button usable again. Not verified live.

### 4. Retry
- **Action**: click Share twice quickly.
- **Expect**: one call per click; no stacked toasts beyond the documented one.

### 5. 390px
- **Expect**: Share button >= 40px tall; toast not clipped; no horizontal overflow.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | steps 1-5 |
| {other} | identical |
| {admin} | identical |

## Pass criteria
1. `navigator.share` runs exactly once per click.
2. Without Web Share the link is copied with a toast and the page stays scrollable.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Two share calls | `e.defaultPrevented` guard gone | `assets/js/listing-detail-fallback.js` (~:86) |
| Page cannot scroll | `shareDialog` calls `openModal( 'share' )` again | `src/interactivity/store.js` (~:1804-1835) |
| Change has no effect | built bundle stale | rebuild `build/interactivity/store.js` after any `src/` edit |

## Not verified
Steps 1-5 were not run in a real browser (source read and button markup only).

## Cleanup
None.
