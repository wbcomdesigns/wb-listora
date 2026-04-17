# P2-03 — Quick View Popup

## Scope: Pro Only

---

## Overview

A rich preview modal that appears when users click an eye icon on a listing card. Instead of navigating to the full detail page, users get an instant preview with gallery slider, key info, mini map, and action buttons — all loaded via REST API and rendered with the Interactivity API. On mobile, the modal renders as a full-screen bottom sheet.

### Why It Matters

- Reduces bounce rate on search/grid pages — users browse more listings without navigating away
- Critical for high-volume directories (real estate, restaurants) where users compare many listings
- Matches user expectations from Airbnb, Zillow, Google Maps — inline previews are standard
- Faster than full page loads — only fetches the data needed for preview
- Keeps search context (filters, scroll position) intact while previewing

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Visitor | Preview a listing without leaving search results | I can quickly browse multiple listings without losing my filters |
| 2 | Visitor | Swipe through gallery photos in the popup | I can see the business without opening the full page |
| 3 | Visitor | See key contact info (phone, hours) in the popup | I can call or visit without extra clicks |
| 4 | Visitor | Close the popup with Esc or clicking outside | The interaction feels natural and fast |
| 5 | Mobile user | See the popup as a swipe-up bottom sheet | The experience is optimized for touch and small screens |
| 6 | Visitor | Click "View Full Details" from the popup | I can easily transition to the complete listing page |

---

## Technical Design

### REST Endpoint

```
GET /listora/v1/listings/{id}?context=quick-view
```

Returns a trimmed payload optimized for the popup — no full content, no comments, just what the modal needs:

```json
{
  "id": 123,
  "title": "Pizza Palace",
  "type": "restaurant",
  "type_label": "Restaurant",
  "url": "/listing/pizza-palace/",
  "status": "publish",
  "featured_image": {
    "url": "https://site.com/wp-content/uploads/pizza-hero.jpg",
    "alt": "Pizza Palace interior"
  },
  "gallery": [
    { "url": "...", "alt": "..." },
    { "url": "...", "alt": "..." }
  ],
  "rating": {
    "average": 4.5,
    "count": 23
  },
  "badges": ["featured", "verified"],
  "meta": {
    "phone": "+1-555-0123",
    "email": "info@pizzapalace.com",
    "website": "https://pizzapalace.com",
    "address": "123 Main St, Manhattan, NY",
    "price_range": "$$$",
    "hours_today": "11:00 AM - 10:00 PM",
    "is_open_now": true
  },
  "location": {
    "lat": 40.7128,
    "lng": -74.0060
  },
  "excerpt": "Authentic Neapolitan pizza in the heart of Manhattan..."
}
```

The `context=quick-view` parameter triggers a filter that limits the response to only these fields. No full `content`, no reviews list, no field groups — keeping the response under 2KB.

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `blocks/listing-card/quick-view-modal.php` | Modal template (server-rendered shell) |
| `blocks/listing-card/quick-view.js` | Interactivity API store extension for modal state |
| `blocks/listing-card/quick-view.css` | Modal + bottom sheet styles |

### Files to Modify (wb-listora-pro)

| File | Change |
|------|--------|
| `blocks/listing-card/render.php` (Pro filter) | Add eye icon button to card markup |
| `blocks/listing-card/view.js` (Pro extension) | Add quick-view actions to `listora/directory` store |

### Files to Modify (wb-listora free)

| File | Change |
|------|--------|
| `includes/rest/class-listings-controller.php` | Support `context=quick-view` parameter (return trimmed response) |

### Interactivity API Store Extension

```js
// Pro extends the shared listora/directory store
const { state, actions } = store('listora/directory', {
    state: {
        quickView: {
            isOpen: false,
            isLoading: false,
            listingId: null,
            data: null,
            galleryIndex: 0,
        },
    },
    actions: {
        openQuickView: async (event) => {
            const id = event.target.closest('[data-listing-id]').dataset.listingId;
            state.quickView.isOpen = true;
            state.quickView.isLoading = true;
            state.quickView.listingId = id;
            state.quickView.galleryIndex = 0;

            const response = await fetch(`/wp-json/listora/v1/listings/${id}?context=quick-view`);
            state.quickView.data = await response.json();
            state.quickView.isLoading = false;
        },
        closeQuickView: () => {
            state.quickView.isOpen = false;
            state.quickView.data = null;
        },
        nextGallerySlide: () => {
            const max = state.quickView.data?.gallery?.length || 1;
            state.quickView.galleryIndex = (state.quickView.galleryIndex + 1) % max;
        },
        prevGallerySlide: () => {
            const max = state.quickView.data?.gallery?.length || 1;
            state.quickView.galleryIndex = (state.quickView.galleryIndex - 1 + max) % max;
        },
    },
});
```

### Keyboard Navigation

| Key | Action |
|-----|--------|
| `Esc` | Close modal |
| `ArrowLeft` | Previous gallery image |
| `ArrowRight` | Next gallery image |
| `Tab` | Cycle through action buttons |

### Focus Trap

When modal is open, Tab cycling is restricted to elements within the modal. Focus returns to the eye icon that triggered the modal on close.

---

## UI Mockup

### Desktop: Centered Modal

```
┌─────────────────── Overlay (dark backdrop) ───────────────────┐
│                                                               │
│   ┌───────────────────────────────────────────────────────┐   │
│   │ [X]                                                   │   │
│   │                                                       │   │
│   │ ┌───────────────────────────────────────────────────┐ │   │
│   │ │                                                   │ │   │
│   │ │              [Gallery Image]                      │ │   │
│   │ │                                                   │ │   │
│   │ │  [<]                                       [>]    │ │   │
│   │ │                                                   │ │   │
│   │ │           ● ○ ○ ○ (dots)                         │ │   │
│   │ └───────────────────────────────────────────────────┘ │   │
│   │                                                       │   │
│   │ Restaurant                                            │   │
│   │ Pizza Palace              ★★★★½ 4.5 (23 reviews)    │   │
│   │ [Featured] [Verified]                                 │   │
│   │                                                       │   │
│   │ Authentic Neapolitan pizza in the heart of            │   │
│   │ Manhattan...                                          │   │
│   │                                                       │   │
│   │ ─────────────────────────────────────────────────     │   │
│   │                                                       │   │
│   │ 📞 +1-555-0123      ✉ info@pizzapalace.com          │   │
│   │ 🕐 Open now (11 AM - 10 PM)    💰 $$$               │   │
│   │ 📍 123 Main St, Manhattan, NY                        │   │
│   │                                                       │   │
│   │ ┌─────────────────┐                                   │   │
│   │ │   [Mini Map]    │                                   │   │
│   │ │    (static)     │                                   │   │
│   │ └─────────────────┘                                   │   │
│   │                                                       │   │
│   │ [View Full Details]  [Get Directions]  [♡] [Share]   │   │
│   └───────────────────────────────────────────────────────┘   │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

### Mobile: Full-Screen Bottom Sheet

```
┌───────────────────────────┐
│ Search Results (dimmed)   │
│                           │
│                           │
├───────────────────────────┤ <-- Swipe handle
│ ━━━━━━                    │
│                           │
│ ┌───────────────────────┐ │
│ │   [Gallery Image]     │ │
│ │  [<]            [>]   │ │
│ │      ● ○ ○ ○          │ │
│ └───────────────────────┘ │
│                           │
│ Restaurant                │
│ Pizza Palace              │
│ ★★★★½ 4.5 (23)          │
│ [Featured] [Verified]     │
│                           │
│ 📞 +1-555-0123           │
│ 🕐 Open (11 AM - 10 PM) │
│ 📍 123 Main St           │
│                           │
│ [View Full Details]       │
│ [Directions]  [♡] [Share] │
│                    [X]    │
└───────────────────────────┘
```

### Eye Icon on Card

```
┌─────────────────────────────┐
│ ┌─────────────────────────┐ │
│ │                         │ │
│ │    [Listing Image]      │ │
│ │                    [👁] │ │  <-- Eye icon (top-right of image)
│ │                         │ │
│ └─────────────────────────┘ │
│ Pizza Palace                │
│ ★★★★½  ·  Restaurant       │
│ 123 Main St, Manhattan      │
└─────────────────────────────┘
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Add `context=quick-view` support to listings REST controller | 2 |
| 2 | Add eye icon button to card template (Pro filter) | 1 |
| 3 | Build modal shell template (`quick-view-modal.php`) | 3 |
| 4 | Interactivity API store — open/close/loading state | 3 |
| 5 | REST fetch on click + data binding to modal | 2 |
| 6 | Gallery slider with swipe (touch) + arrow key navigation | 4 |
| 7 | Mini map rendering (static Leaflet image or small interactive) | 2 |
| 8 | Action buttons (View Details, Directions, Save, Share) | 2 |
| 9 | Focus trap + Esc close + keyboard navigation | 2 |
| 10 | Mobile bottom sheet layout + swipe-to-dismiss | 3 |
| 11 | CSS — modal styles, backdrop, responsive breakpoints | 3 |
| 12 | Loading skeleton while data fetches | 1 |
| 13 | Lazy loading — no scripts/styles until first click | 1 |
| 14 | Accessibility — ARIA roles, live region, screen reader | 2 |
| 15 | Automated tests + documentation | 2 |
| **Total** | | **33 hours** |

---

## Performance Considerations

- **Lazy-loaded:** Zero JS/CSS loaded until first eye icon click. Modal module is dynamically imported.
- **Small payload:** `context=quick-view` returns ~2KB JSON (vs ~15KB for full listing).
- **Image optimization:** Gallery images use `srcset` with appropriate sizes for modal width.
- **No map tile load:** Mini map uses a static image tile (OpenStreetMap static API) unless user interacts, avoiding tile server calls.
- **Cached responses:** REST response cached in Interactivity API state — re-opening same listing is instant.

---

## Competitive Context

| Competitor | Quick View? | Our Advantage |
|-----------|------------|---------------|
| GeoDirectory | Basic popup (limited) | Full gallery slider, mini map, action buttons |
| Directorist | No quick view | Rich preview with swipeable gallery |
| HivePress | No | Lazy-loaded, keyboard accessible |
| ListingPro | Basic lightbox | Full Interactivity API integration, mobile bottom sheet |
| MyListing | Quick view addon | Included in Pro, modern stack (no jQuery) |
| Airbnb | Yes (inspiration) | Similar quality, optimized for WP |

**Our edge:** The Interactivity API modal feels like a native app — no full page reload, instant transitions, swipeable gallery, keyboard navigation, and proper focus management. The mobile bottom sheet (instead of a centered modal that's unusable on phones) matches modern mobile UX patterns. REST API context parameter means the payload is tiny and fast.

---

## Effort Estimate

**Total: ~33 hours (4-5 dev days)**

- REST API changes: 2h
- Card icon + template: 4h
- Interactivity API state: 5h
- Gallery slider: 4h
- Map + actions: 4h
- Keyboard + a11y: 4h
- Mobile bottom sheet: 3h
- CSS + responsive: 3h
- Performance + lazy load: 2h
- Tests + docs: 2h
