---
journey: map-notice-only-when-capped
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [map-marker-cap-notice, bc-10350135718]
prerequisites:
  - "More than 50 mappable published listings (demo: 79) and a category with fewer (demo: /listing-category/casual/, 6)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# "Zoom in to see more" appears only when markers are held back

Card 10393313839. Notice element `.listora-map__notice`, written by `updateMapNotice()` (`src/blocks/listing-map/view.js:330`, called at `:394`), markup in `templates/blocks/listing-map/map.php`.

Verified via: Playwright at 1440 with `map_max_markers` flipped by `wp eval` and restored to 500.

## Steps

### 1. Nothing held back (negative control)
- **Action**: logged out, open `/listing-category/casual/` (grid: "Showing 1-6 of 6 listings").
- **Expect**: `.listora-map__notice` has `hidden === true` and `textContent.trim() === ''`: no "Showing N of N" text anywhere in the DOM.

### 2. Cap bites (positive control)
- **Action**: Settings > Maps > Max markers = 50 (minimum allowed is 50), save; open `/listings/`.
- **Expect**: notice visible with the exact text "Showing 50 of 79 on the map, zoom in to see more" (79 = mappable published; the grid says 83). Count is mappable listings, not grid total.
- **Restore**: Max markers back to 500.

### 3. Under the cap with a filter (negative control)
- **Action**: Max markers 50, open `/listings/?listora_type=hotel` (20 mappable).
- **Expect**: notice `hidden` and empty. Restore Max markers to 500 afterwards.

### 4. Role ladder
Public surface, no role dependence; a logged-in member sees the same text. Admin (control) is the only one who can change the cap.

### 5. 390px: the capped caption is whole and inside the map (card 10350135718)
- **Action**: Max markers 50, viewport 390x844, logged out, open `/listings/` (notice text "Showing 50 of 79 on the map, zoom in to see more").
- **Expect**: `n = document.querySelector('.listora-map__notice'); r = n.getBoundingClientRect(); m = n.closest('.listora-map').getBoundingClientRect()` gives `r.left >= m.left`, `r.right <= m.right`, `n.scrollWidth <= n.clientWidth` (text wraps to a second line, none clipped), `r.height` fits at least two lines, and the last word "more" lies inside `r`. No horizontal page scroll. Restore Max markers to 500.
- **Read-back**: `document.documentElement.scrollWidth <= 390`. Negative control: at 1440 the same notice is one line.
- **fails_on**: 46ba223e (parent of 66994034, the wrap fix). Not verified live.

## Fail diagnostics
`src/blocks/listing-map/view.js:330` `updateMapNotice`; `templates/blocks/listing-map/map.php`; `includes/admin/class-settings-page.php:138` (bounds 50-5000).
