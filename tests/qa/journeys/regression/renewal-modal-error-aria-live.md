---
journey: renewal-modal-error-aria-live
plugin: wb-listora
priority: normal
roles: [subscriber]
covers: [renewal-modal, a11y-live-region, renewal-error-announcement, dashboard-listings-tab]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who owns a renewable listing approaching expiry (capture OWNER_LOGIN + LISTING_ID)"
estimated_runtime_minutes: 4
covers_card: null
covers_commit: 1535f00
---

# Renewal-modal error is a pre-existing assertive live region (screen-reader announced)

Regression sentinel for UX-renewal-modal-error-aria-live (`1535f00`). When a
renewal attempt fails (insufficient credits / network error), the error
paragraph must be a live region that already exists in the DOM so the failure
message is announced the moment its `hidden` attribute is removed. A live region
created at the same time as its content is NOT reliably announced — it must
pre-exist. The error `<p>` lives in
`templates/blocks/user-dashboard/tab-listings.php:558`:
`[data-listora-renew-error]` with `aria-live="assertive"` + `aria-atomic="true"`,
server-rendered with `hidden`.

## Setup

- Site: `$SITE_URL`; owner = `OWNER_LOGIN` with a renewable `LISTING_ID`.

## Steps

### 1. The live region is server-rendered (present even while hidden)
- **Action**:
  ```
  grep -n "data-listora-renew-error" templates/blocks/user-dashboard/tab-listings.php
  ```
  Then `GET $SITE_URL/dashboard/?autologin=<OWNER_LOGIN>#listings` and inspect the renewal modal markup in the initial server response.
- **Expect**: a `<p ... data-listora-renew-error aria-live="assertive" aria-atomic="true" hidden>` element is present in the DOM on initial render (before any JS runs). Both ARIA attributes are on the element while it is still `hidden`.
- **Verify**:
  ```js
  const e = document.querySelector('[data-listora-renew-error]');
  e !== null;                              // expect true (pre-exists)
  e.getAttribute('aria-live');             // expect "assertive"
  e.getAttribute('aria-atomic');           // expect "true"
  e.hasAttribute('hidden');                // expect true initially
  ```
- **On fail**: `1535f00` — the element missing, or the ARIA attributes added dynamically rather than server-rendered.

### 2. A failed renewal reveals the message in-place
- **Action**: open the renewal modal for `LISTING_ID` and trigger a failure (e.g. force insufficient credits, or block the renewal REST request to simulate a network error). Submit.
- **Expect**: the SAME `[data-listora-renew-error]` element has its `hidden` attribute removed and its text set to the error message — the element is reused, not replaced. Because it pre-existed as an `aria-live="assertive"` region, the inserted text is announced.
- **Verify**:
  ```js
  const e = document.querySelector('[data-listora-renew-error]');
  e.hasAttribute('hidden');   // expect false after failure
  e.textContent.trim().length // expect > 0
  e.getAttribute('aria-live') // still "assertive"
  ```
- **On fail**: JS replaces the node (losing the live-region history) or toggles a different element.

### 3. Success path hides it again
- **Action**: perform a successful renewal (sufficient credits).
- **Expect**: `[data-listora-renew-error]` returns to `hidden` with empty text; no stale error announced.

## Notes
- The contract is specifically "the live region pre-exists in the DOM" — a screen reader only announces mutations to a region that was present when the AT built its accessibility tree. Creating the region and its content together is the classic a11y bug this sentinel guards against.
