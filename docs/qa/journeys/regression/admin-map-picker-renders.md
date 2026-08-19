---
journey: admin-map-picker-renders
plugin: wb-listora
roles: [admin]
priority: normal
covers: [map_location, listing-fields-metabox, initMapPickers, leaflet-admin-enqueue, BC-10198832114]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active"
  - "A listing whose type includes a `map_location` field"
estimated_runtime_minutes: 4
---

# The map picker works in wp-admin, using the same picker as the wizard

The Listing Fields metabox reuses the **frontend** submission field renderer, so a `map_location`
field prints the same `.listora-submission__map-picker` div the Add Listing wizard uses. On the
frontend that div becomes a Leaflet map because the block enqueues Leaflet and the wizard's view
module calls `initMapPickers()`. In wp-admin **neither ever loaded**, so editors got an empty box:
no map, no marker, nothing to drag.

Saving still worked — the hidden lat/lng inputs and `Field::sanitize_map_location()` are untouched —
which is why this read as a UX gap rather than data loss, and why it survived so long.

> **Correction to the original report.** It also blamed missing sizing CSS. That part is not true:
> the block editor enqueues every registered block stylesheet, so
> `blocks/listing-submission/style.css` and its `height: 250px` rule already applied. Only Leaflet
> and the initialiser were missing. The 0×0 box that suggested otherwise was the metabox container
> being `display: none` at the moment it was measured.

## Steps

### 1 — The assets are there, in the right order

Open `wp-admin/post.php?post=<id>&action=edit` for a listing whose type has a `map_location` field.

- `assets/vendor/leaflet.css` present
- `assets/vendor/leaflet.js` present
- `build/admin/listing-map-picker.js` present, and **after** leaflet.js

### 2 — It is actually a map, not a div

Presence of the script proves nothing on its own. Assert in the page:

```js
typeof window.L !== 'undefined'                                       // Leaflet booted
document.querySelectorAll( '.leaflet-container' ).length > 0          // a map mounted
document.querySelector( '.listora-submission__map-picker' ).children.length > 0
```

An empty `.listora-submission__map-picker` with zero children is the original bug.

> Scroll the metabox into view first. The block editor mounts metaboxes after the initial paint, so
> a measurement taken too early reports a 0×0 box on a picker that is fine.

### 3 — The marker drives the fields

Drag the marker (or click the map).

- **Expect** the hidden `[name$="[lat]"]` / `[name$="[lng]"]` inputs update, and the address field
  reverse-geocodes.
- Type an address → the marker moves. Save → reload → the marker is where it was left.

### 4 — There is exactly one picker implementation

```bash
grep -rn "L.map(" wb-listora/src/ | grep -v "utils/map-picker.js"
```

Empty is the pass. `initMapPickers()` lives in `src/utils/map-picker.js` and is imported by both the
wizard's `view.js` and `src/admin/listing-map-picker.js`. A second map implementation appearing
anywhere — however small — is the regression this step exists to catch.

### 5 — Frontend is unchanged

Add Listing → Location step still shows the working OSM picker with the same behaviour. The
extraction moved the code; it must not have changed it.

## Cleanup

None — this journey only reads, apart from the optional save in step 3. Restore the listing's
original coordinates if you saved.
