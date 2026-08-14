---
journey: dashboard-services-modal
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [dashboard-listings-tab, services-panel-modal, manage-services-gear, modal-close-affordances, mobile-390]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who owns at least 2 listings (capture OWNER_LOGIN + LISTING_ID of one of them)"
estimated_runtime_minutes: 4
covers_card: 9976599203
---

# Dashboard "Manage services" panel opens as a modal overlay, not below the list

Regression sentinel for BC #9976599203. `tab-listings.php` renders every
`.listora-dashboard__services-panel` (id `services-panel-{ID}`) in a sibling
foreach AFTER all listing rows, so the old behaviour — just removing `hidden`
on the distant panel — dropped the owner thousands of pixels below the row
whose gear they clicked (panel N sat below row 20). The panel now presents as
a fixed modal overlay: the wrapper carries `role="dialog"` + `aria-modal` +
backdrop + a 40px close button, and `toggleDashServices` /
`closeDashServices` in `src/interactivity/store.js` own open/close, Esc,
single-open, and focus-return. The inner panel functionality (Add Service
form toggle; the service CRUD stubs that toast "coming in a future update")
is intentionally unchanged.

## Setup

- Site: `$SITE_URL`; owner = `OWNER_LOGIN` owning `LISTING_ID`.

## Steps

### 1. Gear click opens the panel as an in-viewport overlay
- **Action**: `playwright_navigate $SITE_URL/my-listings/?autologin=<OWNER_LOGIN>` (desktop viewport), then click the gear button (`aria-label="Manage services"`) on the `LISTING_ID` row.
- **Expect**: `#services-panel-<LISTING_ID>` loses `hidden`, is `position: fixed`, and its `.listora-dashboard__services-dialog` is fully inside the viewport — the page does NOT need to scroll to see it.
- **Verify**:
  ```js
  const p = document.getElementById('services-panel-<LISTING_ID>');
  !p.hidden;                                              // expect true
  getComputedStyle(p).position;                           // expect "fixed"
  const r = p.querySelector('.listora-dashboard__services-dialog').getBoundingClientRect();
  r.top >= 0 && r.bottom <= window.innerHeight;           // expect true
  p.getAttribute('role');                                 // expect "dialog"
  p.getAttribute('aria-modal');                           // expect "true"
  document.getElementById(p.getAttribute('aria-labelledby')) !== null; // expect true
  ```
- **On fail**: panel reverted to inline reveal — check `.listora-dashboard__services-panel` overlay CSS in `blocks/user-dashboard/style.css` (and `-rtl` twin) and the modal markup wrapper in `templates/blocks/user-dashboard/tab-listings.php`.

### 2. All three close affordances work + focus returns to the gear
- **Action**: with the modal open, press `Escape`. Reopen via the gear, click the `.listora-dashboard__services-close` X button. Reopen, click the `.listora-dashboard__services-backdrop`.
- **Expect**: each of the three closes the modal (`hidden` restored). After the Esc close, `document.activeElement` is the gear button that opened it.
- **On fail**: `closeDashServices` / `listoraCloseServicesModal` wiring in `src/interactivity/store.js`, or the `data-wp-on--click` directives on backdrop/X in `tab-listings.php`.

### 3. Only one panel open at a time
- **Action**: open the services modal for `LISTING_ID`, then click the gear on a DIFFERENT listing row.
- **Expect**: the first panel re-hides; only the second is visible (`document.querySelectorAll('.listora-dashboard__services-panel:not([hidden])').length === 1`).

### 4. Inner panel functionality unchanged inside the modal
- **Action**: with the modal open, click "Add Service" — the inline form appears. Click "Save Service".
- **Expect**: the form toggles open/closed exactly as before, and **Save Service creates the service** — the handlers were stubs firing a "coming in a future update" toast until 1.6.0, and now call the `Services_Controller` routes (BC 10199116630). Saving with an empty title marks the field invalid rather than firing a toast. No console errors.
- **On fail**: scope creep or breakage in `toggleServiceForm` / the CRUD handlers. A "coming in a future update" toast reappearing is a regression — the docs describe this as working, and now it does.

### 5. 390px viewport — usable, no horizontal overflow
- **Action**: `playwright_resize 390 844`, reload, open the modal, open the Add Service form.
- **Expect**: dialog fits the viewport (no horizontal scrollbar — `document.documentElement.scrollWidth <= window.innerWidth`), the form grid collapses to one column, and the close button measures ≥40×40px.

## Pass criteria

1. Gear opens the panel as a fixed, centered, in-viewport dialog (never below the list)
2. Esc + X + backdrop all close; focus returns to the triggering gear
3. Single-open invariant holds across rows
4. Add Service form toggle + stub toasts behave exactly as pre-fix
5. 390px: fits, single-column form, 40px close target, zero console errors
