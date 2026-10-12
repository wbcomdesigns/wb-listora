---
journey: dashboard-services-modal
plugin: wb-listora
priority: high
roles: [owner, other, admin, guest]
covers: [dashboard-listings-tab, services-panel-modal, manage-services-button, modal-close-affordances, mobile-390, covers_card 9976599203]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "{owner} owns at least 2 listings of a type with Services enabled (`Services::enabled_for_listing()` true)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/dashboard-services-modal.md
---

# The dashboard Services panel opens as a modal overlay, not below the list

## Why
`templates/blocks/user-dashboard/tab-listings.php` renders every `.listora-dashboard__services-panel` (`services-panel-{ID}`) in a loop AFTER all rows, so removing `hidden` on the distant panel once dropped the owner thousands of pixels below the row they clicked. Scope is PRESENTATION only; the CRUD inside is `dashboard-service-crud`.

The panel is a fixed modal: `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, a backdrop and a 40px close button; `toggleDashServices` / `closeDashServices` in `src/interactivity/store.js` (`:2949-2993`) own open/close, Esc, single-open and focus-return.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `LISTING_ID` and a second row's id = two of {owner}'s listings of a Services-enabled type. Dashboard `$DASH/?tab=listings`.
- The trigger is a labelled button **Services** (`button.listora-dashboard__services-toggle`, wrench icon, `data-wp-context='{"servicesListingId":<ID>}'`); the old gear "Manage services" no longer exists.

## Steps

### 1. Services opens an in-viewport overlay
- **Action**: desktop viewport, click **Services** on the `LISTING_ID` row.
- **Expect**: `#services-panel-<LISTING_ID>` loses `hidden`, is `position: fixed`, its `.listora-dashboard__services-dialog` is fully inside the viewport (page does NOT scroll). Title reads `Services for "<listing title>"`.
- **Read-back**:
  ```js
  const p = document.getElementById('services-panel-<LISTING_ID>');
  const r = p.querySelector('.listora-dashboard__services-dialog').getBoundingClientRect();
  ({ open: !p.hidden, pos: getComputedStyle(p).position, inView: r.top >= 0 && r.bottom <= innerHeight,
     role: p.getAttribute('role'), modal: p.getAttribute('aria-modal'),
     labelled: !!document.getElementById(p.getAttribute('aria-labelledby')) })
  // { open: true, pos: "fixed", inView: true, role: "dialog", modal: "true", labelled: true }
  ```

### 2. All three close affordances work and focus returns
- **Action**: with the modal open press Esc; reopen and click `.listora-dashboard__services-close` (`aria-label="Close"`); reopen and click `.listora-dashboard__services-backdrop`.
- **Expect**: each restores `hidden`. After the Esc close `document.activeElement` is the **Services** button that opened it. Focus is trapped inside while open.
- **Read-back**: `document.activeElement.matches('.listora-dashboard__services-toggle')`.

### 3. Only one panel open at a time
- **Action**: open the panel for `LISTING_ID`, then click **Services** on a DIFFERENT row (Tab / Esc if the backdrop covers it).
- **Expect**: the first re-hides.
- **Read-back**: `document.querySelectorAll('.listora-dashboard__services-panel:not([hidden])').length === 1`.

### 4. Inner form toggles; no request on an empty form
- **Action**: click **Add Service** (inline `.listora-dashboard__service-form` appears), then **Save Service** with an empty title.
- **Expect**: Service Name gets `is-invalid` and focus; NO toast and NO request; no console errors (that no request fires is the point; a save on an empty form returns at the title guard).
- **Read-back**: network log shows zero `/services` requests.

### 5. 390px
- **Action**: resize 390x844, reload, open the modal and the Add Service form.
- **Expect**: dialog fits, form grid one column, close button >= 40x40.
- **Read-back**: `document.documentElement.scrollWidth <= innerWidth`.

### 6. Dark and an unbridged theme
- **Action**: repeat step 1 in dark and on the unbridged theme.
- **Expect**: backdrop, dialog and close button readable; same geometry.
- **Read-back**: step 1 query. Not verified live.

### 7. Positive control
- **Action**: compare a listing whose type has services OFF with one that has them ON.
- **Expect**: OFF shows no **Services** button; ON shows it. A page where no row has the button would fail step 1 for the wrong reason.
- **Read-back**: `querySelectorAll('.listora-dashboard__services-toggle').length` per row.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-7 |
| {other} | their own panels only; `services-panel-<ID>` of {owner}'s listings not in the DOM |
| {guest} | login prompt; no panels |
| {admin} | control (their own listings) |

## Pass criteria
1. **Services** opens a fixed, in-viewport dialog, never below the list.
2. Esc, X and backdrop close; focus returns to the trigger.
3. Single-open invariant holds; empty title flagged with no toast or request.
4. 390px: fits, one-column form, 40px close target, zero console errors.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Panel appears below the list | overlay CSS missing | `blocks/user-dashboard/style.css` (and `style-rtl.css`) |
| Missing dialog attributes | modal markup | `templates/blocks/user-dashboard/tab-listings.php:553-575` |
| Focus not returned / two open | store actions | `src/interactivity/store.js:2949-2993` (`closeDashServices`, `listoraCloseServicesModal`) |

## Not verified
Button, panel ids and aria attributes were read from served HTML; computed position, containment, Esc/backdrop/focus-return, single-open, 390 and step 6 were not run in a browser.

## Cleanup
None (read-only).
