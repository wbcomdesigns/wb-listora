# 11 — Maps

## Scope

| | Free | Pro |
|---|---|---|
| OpenStreetMap / Leaflet | Yes | Yes |
| Google Maps | — | Yes |
| Map markers with popups | Yes | Yes |
| Marker clustering | Yes (basic) | Yes (advanced) |
| "Near me" geolocation | Yes | Yes |
| Bounding box search (map drag) | Yes | Yes |
| Custom marker icons per type | Yes | Yes |
| Draggable pin (submission form) | Yes | Yes |
| Street View | — | Yes |
| Custom map styles | — | Yes |
| Heatmaps | — | Yes |
| Places autocomplete | — | Yes |
| Route/directions link | Yes | Yes |

---

## Architecture: Map Provider Abstraction

```php
// Abstract interface
interface Map_Provider {
    public function get_name(): string;
    public function get_scripts(): array;        // JS files to enqueue
    public function get_styles(): array;         // CSS files to enqueue
    public function get_config(): array;         // Client-side config
    public function get_tile_url(): string;      // Tile layer URL
    public function geocode(string $address): ?array;  // Returns [lat, lng]
    public function reverse_geocode(float $lat, float $lng): ?array;
}

// Implementations
class OpenStreetMap_Provider implements Map_Provider { ... }  // Free
class Google_Maps_Provider implements Map_Provider { ... }    // Pro
```

### Provider Selection
- Default: OpenStreetMap (zero configuration)
- Set in Settings → Maps or during setup wizard
- Switched via `apply_filters('wb_listora_map_provider', $provider)`
- Pro hooks in to offer Google Maps when activated

---

## OpenStreetMap / Leaflet (Free)

### Libraries
- **Leaflet** 1.9.x (~40KB gzipped) — loaded from local assets (not CDN)
- **Leaflet.markercluster** — for marker clustering
- **Tile provider:** OpenStreetMap default tiles (free, no API key)

### Tile URL
```
https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png
```
Attribution: `© OpenStreetMap contributors` (required, auto-added)

### Geocoding
- **Nominatim** (OpenStreetMap's geocoding service)
- Rate limit: 1 request/second (enforced server-side)
- For bulk import: queue geocoding requests, process via cron
- Cache geocoding results in transient (30 days)

### Limitations
- Tile quality varies by region
- No Street View
- No Places autocomplete (use location taxonomy for suggestions)
- Nominatim rate limits make bulk geocoding slow

---

## Google Maps (Pro)

### Libraries
- Google Maps JavaScript API (loaded from Google CDN)
- Google Maps Marker Clusterer library

### Required API Keys
- Maps JavaScript API
- Geocoding API
- Places API (for autocomplete)
- Stored in: `wb_listora_settings['google_maps_api_key']`
- Public key exposed via REST: `GET /listora/v1/settings/maps`

### Pro-Only Features
- **Street View** — embedded in listing detail
- **Custom styles** — JSON-based map styling (snazzy maps compatible)
- **Places autocomplete** — on address inputs (submission form, search location)
- **Heatmaps** — visualize listing density
- **Advanced clustering** — Google's clustering with custom icons + counts

---

## Map Block: `listora/listing-map`

### Default Display
```
┌────────────────────────────────────────────────────┐
│                                                    │
│         ┌──────┐                                  │
│    📍   │Popup │  📍                              │
│         │Card  │       📍                          │
│    📍   └──────┘                 📍               │
│                    📍                              │
│         📍              📍                        │
│              ⑫                                    │
│         (cluster)            📍                   │
│                                                    │
│   [−][+]         [📍 Near Me]      [⛶ Fullscreen]│
└────────────────────────────────────────────────────┘
```

### Attributes
```json
{
  "attributes": {
    "listingType": { "type": "string", "default": "" },
    "height": { "type": "string", "default": "400px" },
    "defaultZoom": { "type": "number", "default": 12 },
    "centerLat": { "type": "number", "default": 0 },
    "centerLng": { "type": "number", "default": 0 },
    "showClustering": { "type": "boolean", "default": true },
    "showNearMe": { "type": "boolean", "default": true },
    "showFullscreen": { "type": "boolean", "default": true },
    "searchOnDrag": { "type": "boolean", "default": true },
    "maxMarkers": { "type": "number", "default": 500 }
  }
}
```

### Server Rendering
- Renders a `<div>` with `data-wp-interactive` attributes
- Initial markers loaded as JSON in a `data-wp-context` attribute (first page of results)
- Map initializes client-side via Interactivity API `view.js`

---

## Marker Design

### Per-Type Custom Markers
Each listing type has `_listora_color` and `_listora_icon`. Map markers use these:

```
     ╱╲
    ╱  ╲
   │ 🍕 │    ← Type icon
   │    │    ← Type color background
    ╲  ╱
     ╲╱
      │
```

SVG markers generated with CSS custom properties:
```css
.listora-marker {
  --marker-color: var(--listora-type-color, #0073aa);
}
.listora-marker svg path {
  fill: var(--marker-color);
}
```

### Marker States
| State | Visual |
|-------|--------|
| Default | Type-colored pin with icon |
| Hovered (from card) | Bouncing animation + larger size |
| Active (clicked) | Popup open, pin highlighted |
| Featured | Gold ring around marker |
| Clustered | Circle with count number |

---

## Marker Popups

### Compact Card Popup
When a marker is clicked:
```
┌─────────────────────────┐
│ [Image]                 │
│ Restaurant Name    ★4.5 │
│ 📍 Manhattan · $$$     │
│ [View Details →]        │
└─────────────────────────┘
```

- Uses compact card layout
- Loads listing data from shared store (no extra API call if already in results)
- "View Details" links to listing page

### Cluster Popup
When a cluster is clicked:
- Zoom in to show individual markers
- If at max zoom: show list of listings in popup

---

## Map + Search Integration

### Shared State (Interactivity API)
```javascript
state: {
  mapBounds: null,          // Current viewport bounds
  mapZoom: 12,              // Current zoom level
  mapCenter: [lat, lng],    // Current center
  activeMarker: null,       // Highlighted marker listing ID
  markers: [],              // Current marker data
  isMapReady: false,
}
```

### Behavior: Search Drives Map
```
1. User searches "Italian" → results returned
2. Map markers update to show only matching results
3. Map auto-fits to show all result markers
4. If results are in a small area → zoom in
5. If results are spread wide → zoom out
```

### Behavior: Map Drives Search (viewport search)
```
1. User pans/zooms the map
2. After 500ms debounce → map bounds captured
3. Search triggered with bounds filter
4. Grid results update to show only listings in viewport
5. "Search this area" button appears if map moved significantly
```

**"Search this area" pattern:**
```
┌────────────────────────────────────────┐
│        [🔍 Search this area]           │   ← Button overlay at top of map
│                                        │
│    📍    📍         📍                │
│              📍           📍          │
│                                        │
└────────────────────────────────────────┘
```

### Behavior: Card Hover ↔ Marker Highlight
- Hover on card → marker bounces
- Hover on marker → card gets highlight border
- Click marker → scroll to card (or show popup)

---

## Map in Listing Detail Page

Single listing detail shows a small map with:
- One marker at listing location
- Zoom level 15 (street level)
- "Get Directions" link → opens Google Maps/Apple Maps

```
┌─────────────────────────────────────────┐
│                                         │
│              📍                         │
│     Listing Location                    │
│                                         │
│                                         │
│ [Get Directions]                        │
└─────────────────────────────────────────┘
```

---

## Map in Submission Form

Address field includes map picker:
```
┌─────────────────────────────────────────┐
│ Address: [ 123 Main St, NYC         ]   │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │                                     │ │
│ │         📍 (draggable)              │ │
│ │                                     │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ Lat: [40.7128]  Lng: [-74.0060]         │
└─────────────────────────────────────────┘
```

- Type address → geocode → place pin
- Drag pin → reverse geocode → update address
- Click on map → place pin → reverse geocode

---

## Performance

### Marker Limits
- Default max: 500 markers loaded at once
- Beyond 500: use clustering aggressively, load markers by viewport
- 100K+ listings: only load markers for current viewport (AJAX on map move)

### Lazy Loading
- Map JS/CSS loaded only when `listora/listing-map` block is on page
- Map initializes on scroll-into-view (Intersection Observer)
- Tile images lazy-loaded by the map library automatically

### Static Map Fallback (optional)
For pages where map is below the fold:
- Initial render: static map image (screenshot)
- On scroll into view: replace with interactive map
- Reduces initial page weight

---

## Accessibility

| Element | A11y Feature |
|---------|-------------|
| Map container | `role="application"`, `aria-label="Map showing listing locations"` |
| Markers | `role="button"`, `aria-label="Listing Name"` |
| Marker popup | Focus trapped when open, Escape to close |
| Zoom controls | `aria-label="Zoom in"`, `aria-label="Zoom out"` |
| Near Me button | `aria-label="Find listings near your location"` |
| Skip link | "Skip to listing results" before map (keyboard users can bypass) |
| Screen reader | Result count announced: "23 listings shown on map" |

---

## Theme Adaptive Styling

Map itself is not theme-styled (it's a tile layer). But surrounding UI inherits theme:

```css
.listora-map {
  border-radius: var(--wp--custom--border-radius, 8px);
  overflow: hidden;
  border: 1px solid var(--wp--preset--color--contrast-3, #eee);
}

.listora-map__controls button {
  background: var(--wp--preset--color--base, #fff);
  color: var(--wp--preset--color--contrast, #333);
  border: 1px solid var(--wp--preset--color--contrast-3, #ddd);
}

.listora-map__popup {
  font-family: inherit;
  font-size: var(--wp--preset--font-size--small, 0.9rem);
}
```

### Dark Mode
- Leaflet: use dark tile provider (CartoDB Dark Matter)
- Google Maps: use dark style JSON
- Detect via `prefers-color-scheme: dark` or theme class

---

## Mobile Behavior

### Touch Interactions
- Pinch to zoom
- Two-finger drag to pan (single finger scrolls page)
- Tap marker to show popup
- Tap outside popup to close

### Mobile Layout
In split view on mobile: full-width tabs
```
[Grid] [Map]

(Only one shows at a time, swipe or tap to switch)
```

Map height on mobile: `60vh` (enough to see but not overwhelming)
