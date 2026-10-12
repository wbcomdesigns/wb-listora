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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Dashboard "Services" panel opens as a modal overlay, not below the list

Regression sentinel for the Services panel presentation. `templates/blocks/user-dashboard/tab-listings.php` renders every `.listora-dashboard__services-panel` (id `services-panel-{ID}`) in a sibling loop AFTER all listing rows, so simply removing `hidden` on the distant panel once dropped the owner thousands of pixels below the row they clicked. The panel presents as a fixed modal overlay: the wrapper carries `role="dialog"` + `aria-modal="true"` + `aria-labelledby` + a backdrop + a 40px close button, and `toggleDashServices` / `closeDashServices` in `src/interactivity/store.js` (`:2949-2993`) own open/close, Esc, single-open and focus-return.

**Scope:** PRESENTATION only. The CRUD inside the panel is `dashboard-service-crud.md`; step 4 here deliberately does not try to cover it (a save on an empty form returns at the title guard before any request).

Roles: the listing owner (a member) is the actor; a member with no listings sees no rows or panels; logged out gets the login prompt; admin as control.

## Setup

- Site `$SITE_URL`; owner `OWNER_LOGIN` owning at least 2 listings of a type with services (QA: `qa_vendor_01`, listings 7379 and 7380...). The button only renders when `Services::enabled_for_listing()` is true for the listing's type.
- The trigger is now a labelled button, **Services** (`button.listora-dashboard__services-toggle`, wrench icon, `data-wp-context='{"servicesListingId":<ID>}'`); the old gear with `aria-label="Manage services"` no longer exists.
- Dashboard `$SITE_URL/my-listings/?tab=listings`.

## Steps

### 1. The Services button opens the panel as an in-viewport overlay
- **Action**: desktop viewport, click **Services** on the `LISTING_ID` row.
- **Expect**: `#services-panel-<LISTING_ID>` loses `hidden`, is `position: fixed`, and its `.listora-dashboard__services-dialog` is fully inside the viewport (the page does NOT scroll to it).
- **Verify**:
  ```js
  const p = document.getElementById('services-panel-<LISTING_ID>');
  const r = p.querySelector('.listora-dashboard__services-dialog').getBoundingClientRect();
  ({ open: !p.hidden, pos: getComputedStyle(p).position, inView: r.top >= 0 && r.bottom <= innerHeight,
     role: p.getAttribute('role'), modal: p.getAttribute('aria-modal'),
     labelled: !!document.getElementById(p.getAttribute('aria-labelledby')) })
  // expect { open: true, pos: "fixed", inView: true, role: "dialog", modal: "true", labelled: true }
  ```
- The dialog title reads `Services for "<listing title>"`.
- **On fail**: overlay CSS in `blocks/user-dashboard/style.css` (and `style-rtl.css`), modal markup in `templates/blocks/user-dashboard/tab-listings.php:553-575`.

### 2. All three close affordances work and focus returns
- **Action**: with the modal open press `Escape`; reopen and click `.listora-dashboard__services-close` (`aria-label="Close"`); reopen and click `.listora-dashboard__services-backdrop`.
- **Expect**: each closes the modal (`hidden` restored). After the Esc close `document.activeElement` is the **Services** button that opened it.
- **On fail**: `closeDashServices` / `listoraCloseServicesModal` in `src/interactivity/store.js`, directives on backdrop / X in `tab-listings.php`.

### 3. Only one panel open at a time
- **Action**: open the panel for `LISTING_ID`, then click **Services** on a DIFFERENT row (use Tab / Esc if the backdrop covers it).
- **Expect**: the first panel re-hides; `document.querySelectorAll('.listora-dashboard__services-panel:not([hidden])').length === 1`.

### 4. Inner form toggles; no save request on an empty form
- **Action**: with the modal open click **Add Service** (the inline form `.listora-dashboard__service-form` appears), then **Save Service** with an empty title.
- **Expect**: the Service Name field gets `is-invalid` and focus; NO toast and NO request; no console errors.
- **Note**: that no request fires here is the point (see Scope).

### 5. 390px viewport
- **Action**: resize 390x844, reload, open the modal, open the Add Service form.
- **Expect**: dialog fits (`document.documentElement.scrollWidth <= innerWidth`), the form grid is one column, the close button measures >= 40x40.

### 6. Positive control and role ladder
- **Control**: a listing whose type has services OFF shows no **Services** button; one with services ON does (verified: all of `qa_vendor_01`'s restaurant rows have it). A page where no row has the button would fail step 1 for the wrong reason.
- **Another member**: their own panels only; ids `services-panel-<ID>` of other members' listings are not in the DOM.
- **Logged out**: login prompt; no panels.

## Pass criteria

1. **Services** opens the panel as a fixed, in-viewport dialog (never below the list).
2. Esc, X and backdrop all close; focus returns to the triggering button.
3. Single-open invariant holds across rows.
4. Add Service toggles the form; an empty title is flagged with no toast and no request.
5. 390px: fits, one-column form, 40px close target, zero console errors.

## Not verified

The button, panel ids, `role`/`aria-*` attributes and the services form fields were read from the served dashboard HTML (as `qa_vendor_01`) and source. Computed position, viewport containment, Esc / backdrop / focus-return, single-open and 390 behaviour were NOT run in a browser.
