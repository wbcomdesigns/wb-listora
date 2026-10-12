---
journey: service-details-toggle
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [services-tab, iapi-toggle-action, css-clamp]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing with a service whose description is longer than 120 characters (NONE of the demo services qualifies: the longest is 119). Create one as `QA-J1-service` in wp-admin Services box or via the dashboard and delete it afterwards."
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Service description toggle sentinel

On the listing page Services tab, "Details" on a service card toggles `listora-detail__service-desc--collapsed` on that card's description and rotates the chevron. The button only exists when the description exceeds 120 characters after tag stripping (`templates/blocks/listing-detail/tabs.php:420-432`); shorter ones render no toggle.

Verified via: source read and a DB check of demo data (no service > 119 chars); the click was NOT run in a browser here. Role ladder: public surface; owner/other member/anonymous/admin all see the same markup (owner additionally sees the dashboard Services panel, not part of this journey).

## Steps

### 1. Open the Services tab
- **Action**: open `$SITE_URL/listing/<slug>/`, click the tab `#tab-services` (`role=tab`, text "Services"); panel is `#panel-services`.
- **Expect**: service cards `.listora-detail__service-card` render with `.listora-detail__service-desc`.

### 2. Collapsed by default
- **Action**: `document.querySelectorAll('.listora-detail__service-desc--collapsed').length`
- **Expect**: >= 1 for the QA fixture (2-line clamp, `blocks/listing-detail/style.css:2025-2040`). Positive control: a service under 121 chars has no `--collapsed` class and no `.listora-detail__service-toggle`.

### 3. Click Details
- **Action**: click `.listora-detail__service-toggle` (button text "Details") in that card.
- **Expect**: `--collapsed` removed from that card's `.listora-detail__service-desc`; chevron svg rotates (`style.css:2057-2058`).
- **On fail**: `src/interactivity/store.js:3062-3076` (`toggleServiceDesc`).

### 4. Click again
- **Expect**: `--collapsed` re-applied, chevron back.

### 5. Per-card
- With two long services, toggling one leaves the other collapsed.

### 6. Mobile
- 390px: the toggle is >= 40px tall (`.listora-btn--text`) and the card does not overflow horizontally.

## Fail diagnostics
| Symptom | File |
|---|---|
| Click does nothing | `src/interactivity/store.js:3062-3076`; also `build/` bundle |
| All cards toggle | selector not card-scoped (`:3072` uses the wrap root) |
| No chevron rotation | `blocks/listing-detail/style.css:2057-2058` |
| No toggle for a 100-char description | by design (threshold 120, `tabs.php:426`) |
