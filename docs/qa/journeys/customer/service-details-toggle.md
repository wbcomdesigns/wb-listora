---
journey: service-details-toggle
plugin: wb-listora
priority: normal
site_profile: required
roles: [guest, owner, other, admin]
covers: [services-tab, iapi-toggle-action, css-clamp, card-9872013428]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published {listing} with a service whose description is longer than 120 characters after tag stripping. Create one as 'QA-service-<time>' in the wp-admin Services box or the dashboard and delete it afterwards (check demo data first: `SELECT MAX(CHAR_LENGTH(description)) FROM ${PREFIX}listora_services`)."
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged default theme]
supersedes: []
fails_on: "pre-fix: toggleServiceDesc() not card-scoped (all cards toggled) - sentinel for card 9872013428"
---

# Service description toggle sentinel

## Why

On the listing page Services tab, "Details" on a service card toggles `listora-detail__service-desc--collapsed` on that card's description and rotates the chevron. The button exists only when the description exceeds 120 characters after tag stripping (`templates/blocks/listing-detail/tabs.php:420-432`). A dead or all-cards toggle hides long service text from every visitor.

## Setup

- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `$SLUG` = the fixture {listing} slug; add a second long service so step 5 can run, and one short (< 121 chars) service as the control.

## Steps

### 1. Open the Services tab
- **Action**: open `$SITE_URL/listing/$SLUG/` as {guest}, click `#tab-services` (`role=tab`, text "Services"); the panel is `#panel-services`.
- **Expect**: service cards `.listora-detail__service-card` render with `.listora-detail__service-desc`.

### 2. Collapsed by default
- **Action**: `document.querySelectorAll('.listora-detail__service-desc--collapsed').length`
- **Expect**: equals the number of long services (2-line clamp, `blocks/listing-detail/style.css:2025-2040`). Positive control: the short service has no `--collapsed` class and no `.listora-detail__service-toggle`.
- **Read-back**: count of `.listora-detail__service-toggle` equals the count of long services.

### 3. Click Details
- **Action**: click `.listora-detail__service-toggle` (button text "Details") in one card.
- **Expect**: `--collapsed` removed from that card's description only; the chevron svg rotates (`style.css:2057-2058`).

### 4. Click again
- **Expect**: `--collapsed` re-applied, chevron back.

### 5. Per-card
- **Action**: toggle the first long service.
- **Expect**: the second long service stays collapsed.

### 6. Boundary: description length
- **Action**: services with exactly 120 and 121 characters, one containing markup, one with an emoji.
- **Expect**: 120 shows no toggle, 121 shows one; markup is stripped before counting; emoji do not break the clamp. Not verified live (added by consolidation).

### 7. Mobile and dark
- **Action**: repeat steps 3-4 at 390px, light and dark.
- **Expect**: the toggle is >= 40px tall (`.listora-btn--text`), the card does not overflow horizontally, the chevron and text keep contrast.

## Role ladder

Public surface; every actor sees the same markup.

| Actor | Expect |
|---|---|
| {guest} | steps 1-7 |
| {other} (another member) | identical markup |
| {owner} | identical markup (plus the dashboard Services panel, not part of this journey) |
| {admin} | identical markup (control) |

## Pass criteria

1. Long descriptions are collapsed with a "Details" toggle; short ones render no toggle.
2. A click expands only its own card and a second click collapses it again.
3. The chevron follows the state; 390px has no overflow.

## Fail diagnostics

| Symptom | File |
|---|---|
| Click does nothing | `src/interactivity/store.js:3062-3076`; also the `build/` bundle |
| All cards toggle | selector not card-scoped (`:3072` uses the wrap root) |
| No chevron rotation | `blocks/listing-detail/style.css:2057-2058` |
| No toggle for a 100-char description | by design (threshold 120, `tabs.php:426`) |

## Not verified

Source read plus a DB check that no demo service exceeds 119 characters; the click itself was NOT run in a browser. Steps 6-7 not run.

## Cleanup

Delete the `QA-service-*` services created for the fixture.
