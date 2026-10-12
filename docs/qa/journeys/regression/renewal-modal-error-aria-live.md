---
journey: renewal-modal-error-aria-live
plugin: wb-listora
priority: normal
roles: [subscriber, anonymous]
covers: [renewal-modal, a11y-live-region, renewal-error-announcement, dashboard-listings-tab]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who owns a renewable listing approaching expiry or expired (capture OWNER_LOGIN + LISTING_ID)"
estimated_runtime_minutes: 4
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Renewal-modal error is a pre-existing assertive live region (screen-reader announced)

The failure message paragraph must already exist in the DOM so its text is announced when `hidden` is removed.
Template: `templates/blocks/user-dashboard/tab-listings.php:769`
`<p class="listora-dashboard__renew-modal-error" data-listora-renew-error aria-live="assertive" aria-atomic="true" hidden>`,
server-rendered inside the shared modal `[data-listora-renew-modal]` (`:744`, rendered once per dashboard Listings tab, even with no
renewable listing). Script: `src/blocks/user-dashboard/view.js:321` (`errEl`), toggled at `:369-499`.

Dashboard URL: `wb_listora_get_page_url('dashboard')` (`$SITE_URL/my-listings/` on this site, tab `#listings`; not `/dashboard/`).

Role ladder: the owner sees the Renew button (`.listora-dashboard__renew-btn[data-listora-renew-listing]`, text "Renew Now") only for
an expired/expiring listing with Renewal enabled; another member cannot open it (403 on the REST quote); logged out is sent to
login; admin is the control (the modal markup is identical).

## Steps

### 1. The live region is server-rendered, hidden (verified on the admin dashboard)
- **Action**: `GET $SITE_URL/my-listings/?autologin=<OWNER_LOGIN>` and inspect the initial response (before any JS).
- **Expect**: `<p ... data-listora-renew-error aria-live="assertive" aria-atomic="true" hidden></p>` present.
  ```js
  const e = document.querySelector('[data-listora-renew-error]');
  [e !== null, e.getAttribute('aria-live'), e.getAttribute('aria-atomic'), e.hasAttribute('hidden')] // [true,"assertive","true",true]
  ```

### 2. A failed renewal reveals the message in place
- **Action**: click **Renew Now** on LISTING_ID, force a failure (balance below cost, or block `POST .../renew`), confirm.
- **Expect**: the SAME element loses `hidden` and gets text (e.g. "You need X credits to renew (you have Y)." for a short balance,
  "Listing not ready to renew yet." / the server `reason`, or the server message on error); `aria-live` stays "assertive". The node
  is reused, never replaced.

### 3. Success hides it again
- **Action**: renew with enough credits.
- **Expect**: element back to `hidden` with empty text; no stale error.

### 4. Mobile (390)
- **Expect**: modal fits, Confirm/Cancel >= 40px, no horizontal page scroll.

## Notes
An AT only announces mutations in a region present when it built the accessibility tree.

## Fail diagnostics
`templates/blocks/user-dashboard/tab-listings.php:769` (attributes), `src/blocks/user-dashboard/view.js:369-499` (reuse).

## Not verified
Steps 2-4 (modal interaction, announcement, 390px) need a browser session with a renewable listing; only step 1 was checked in the DOM.
