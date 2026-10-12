---
journey: submission-map-picker-stacking
plugin: wb-listora
priority: normal
roles: [member, logged-out, admin]
covers: ["#9976402618", "submission map picker stacking context", "Leaflet vs fixed theme header"]
prerequisites:
  - "A theme with a fixed/sticky header whose stacking context is below 1000 (Reign reproduces; BuddyX Pro on this site has a sticky header too)"
  - "Member session ?autologin=QA-J1-member"
estimated_runtime_minutes: 3
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Submission map picker confines Leaflet below fixed theme headers

Leaflet's `.leaflet-top` controls (z-index 1000, root stacking context) painted above the theme's fixed header while scrolling. `.listora-submission__map-picker` now creates its own stacking context with `isolation: isolate` (`blocks/listing-submission/style.css:1756`, RTL twin `style-rtl.css:1756`), confining panes and controls inside the picker box, the same fix `listing-map/style.css` ships. `position: relative` on the same element keeps the map inside its container at init.

## Steps

### 1. Stacking context applied
- **Action**: as a member walk Add Listing (Restaurant) to the Details step where `.listora-submission__map-picker` renders; read `getComputedStyle( picker ).isolation`.
- **Expect**: `isolate` (verified 2026-10-12 at 1440 light on BuddyX Pro). Repeat on an RTL site: same.
- **Positive control**: `getComputedStyle( document.querySelector( '.listora-submission__fieldset' ) ).isolation` is `auto`, so the value is specific to the picker.

### 2. Scroll paint order
- **Action**: enter an address so Leaflet mounts (`.leaflet-container` inside the picker), scroll so the map crosses under the fixed header.
- **Expect**: the header paints above the map's zoom controls and tiles; no Leaflet chrome over the header. Use `document.elementFromPoint()` at a header pixel that overlaps the map: it must be a header element, not `.leaflet-*`.

### 3. Map still usable
- **Action**: click `.leaflet-control-zoom-in`.
- **Expect**: zoom changes; the map does not escape the 250px box (`picker.getBoundingClientRect().height` stays about 250).

### 4. Role ladder / mobile
Member: steps 1-3. Logged out: sign-in prompt, no picker. Admin: same picker (control). At 390 the picker is full-width, no horizontal scroll, zoom buttons >= 40px (or the theme's tap floor).

## Fail diagnostics
| Symptom | File |
|---|---|
| Leaflet over header | `blocks/listing-submission/style.css:1756` (`isolation: isolate`), `style-rtl.css:1756` |
| Map escapes its box | same rule block: `position: relative` removed |
| Single listing map regresses | `blocks/listing-map/style.css` |

## Not verified
Only step 1 was run (computed `isolation: isolate` on the Details step, Restaurant, 1440, BuddyX Pro light). Steps 2-4, a fixed-header theme with a low stacking context (Reign), RTL and dark were not run.

Verified via: Playwright MCP (Chromium).
