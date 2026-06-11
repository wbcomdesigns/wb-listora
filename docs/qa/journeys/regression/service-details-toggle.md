---
journey: service-details-toggle
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [services-tab, iapi-toggle-action, css-clamp]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 1 published listing with services that have descriptions"
estimated_runtime_minutes: 2
---

# Service description toggle regression sentinel

On the listing detail Services tab, clicking "Details" on a service card must expand the description (toggle the `--collapsed` modifier) AND rotate the chevron. Pre-fix #9872013428 (commit `c382a86`): the click did nothing. Sentinel.

## Setup

- Site: `$SITE_URL`
- Fixture: listing with at least 1 service that has a description >120 chars (so it's clamp-collapsed by default)

## Steps

### 1. Visit listing detail Services tab
- **Action**: `playwright_navigate $SITE_URL/listing/<slug>#services` (or click Services tab after page load)
- **Expect**: services tab visible, service cards render with collapsed descriptions

### 2. Find a collapsed description
- **Action**: `browser_evaluate "Array.from(document.querySelectorAll('.listora-detail__service-desc')).filter(el => el.classList.contains('listora-detail__service-desc--collapsed')).length"`
- **Expect**: ≥1
- **On fail**: CSS clamp class not applied — `blocks/listing-detail/style.css` clamp rule

### 3. Click the Details toggle
- **Action**: click the "Details" button on a service card with collapsed description
- **Expect**:
  - The corresponding `.listora-detail__service-desc` element loses the `--collapsed` class (description fully visible)
  - The chevron icon rotates (CSS transform)
- **On fail**: regression of #9872013428. See `src/interactivity/store.js` `toggleServiceDesc` action — selectors must target the right service card, not the parent.

### 4. Click again to re-collapse
- **Action**: click Details again on the same card
- **Expect**:
  - `--collapsed` class re-applied
  - Chevron rotates back

### 5. Verify other cards unaffected
- **Action**: ensure clicking on one card's Details does NOT toggle a different card

## Pass criteria

1. Services with long descriptions render collapsed by default (CSS clamp visible)
2. Clicking Details toggles the `--collapsed` class on the SAME card
3. Chevron rotates with each toggle
4. Toggle is per-card, not global

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Click does nothing | regression of #9872013428 | `src/interactivity/store.js` `toggleServiceDesc` action — selector logic |
| All cards toggle together | scope leak | toggle action must use card-scoped context, not global state |
| Chevron doesn't rotate | CSS missing | `blocks/listing-detail/style.css` chevron rotation rule |
