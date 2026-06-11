---
journey: submission-map-picker-stacking
plugin: wb-listora
priority: normal
roles: [member]
covers: ["#9976402618", "submission map picker stacking context", "Leaflet vs fixed theme header"]
prerequisites:
  - "Theme with a fixed/sticky header (Reign reproduces; any fixed header whose stacking context < 1000 is affected)"
estimated_runtime_minutes: 2
---

# Submission map picker confines Leaflet below fixed theme headers

Card #9976402618: on Add Listing → Details with the Reign theme, Leaflet's
`.leaflet-top` controls (z-index 1000, ROOT stacking context) painted ABOVE
the theme's fixed header while scrolling, because Reign's
`.reign-fallback-header.fixed-top` (z-index 1030) is trapped inside
`#masthead`'s stacking context at z-index 100.

Fix: `.listora-submission__map-picker` now creates its own stacking context
via `isolation: isolate` (in `blocks/listing-submission/style.css` AND the
`style-rtl.css` twin), confining all Leaflet panes/controls inside the 250px
box — the same canonical fix `listing-map/style.css` ships for #9895489254.

Related: #9952543239 added `position: relative` to the same element (map
escaping its container at init); this card is the scroll-time stacking issue.

## Steps

### 1. Stacking context applied
- **Action**: as a logged-in member, walk Add Listing to the step rendering
  `.listora-submission__map-picker`; read
  `getComputedStyle(picker).isolation`.
- **Expect**: `isolate` (LTR site). Repeat on an RTL site — same.

### 2. Scroll paint order (Reign / fixed-header theme)
- **Action**: with the map initialized (enter an address so Leaflet mounts),
  scroll so the map crosses under the fixed header.
- **Expect**: header paints ABOVE the map's zoom controls and tiles — no
  Leaflet chrome bleeding over the header.
