# 12 — Listing Detail Page

## Scope

| | Free | Pro |
|---|---|---|
| Tabbed layout | Yes | Yes |
| Sidebar layout | Yes | Yes (enhanced) |
| Full-width layout | — | Yes |
| Photo gallery with lightbox | Yes | Yes |
| Field groups as tabs/sections | Yes | Yes |
| Business hours display | Yes | Yes |
| Map embed | Yes | Yes |
| Social links | Yes | Yes |
| Contact info (phone, email, web) | Yes | Yes + click tracking |
| Share buttons | Yes | Yes |
| Claim button | Yes | Yes |
| Reviews section | Yes | Yes |
| Related listings | Yes | Yes |
| Breadcrumbs | Yes | Yes |
| Schema.org JSON-LD | Yes | Yes |
| Photo gallery (user photos) | — | Yes |
| Street View embed | — | Yes |
| Lead form (contact owner) | — | Yes |
| Listing comparison add | — | Yes |
| Analytics tracking | — | Yes |
| Verified badge | — | Yes |
| Virtual tour embed | Yes | Yes |

---

## Overview

The listing detail page is where conversions happen — phone calls, website visits, reviews, favorites. It must be comprehensive but not overwhelming. Information architecture matters more than feature count.

---

## Layout Options

### Tabbed Layout (Default)
```
┌──────────────────────────────────────────────────────────┐
│ Breadcrumb: Home > Restaurants > Italian > Pizza Palace  │
├──────────────────────────────────────────────────────────┤
│ ┌──────────────────────────────────────────────────────┐ │
│ │ ┌────┐┌────┐┌────┐┌────┐                            │ │
│ │ │    ││    ││    ││    │  ← Gallery (4+ images)     │ │
│ │ │Main││ 2  ││ 3  ││ 4  │                            │ │
│ │ │    ││    ││    ││    │                            │ │
│ │ └────┘└────┘└────┘└────┘                            │ │
│ └──────────────────────────────────────────────────────┘ │
│                                                          │
│ ┌──────────────────────────────────────────────────────┐ │
│ │                                                      │ │
│ │  Restaurant · Italian · $$$                          │ │
│ │  ★★★★½ 4.5 (23 reviews)                            │ │
│ │                                                      │ │
│ │  Pizza Palace                                        │ │
│ │  📍 123 Main St, Manhattan, NY 10001                │ │
│ │                                                      │ │
│ │  [♡ Save] [↗ Share] [📍 Directions] [🏷 Claim]     │ │
│ │                                                      │ │
│ └──────────────────────────────────────────────────────┘ │
│                                                          │
│ [Overview] [Details] [Hours] [Reviews] [Map]             │
│ ─────────────────────────────────────────────            │
│                                                          │
│ ┌──────────────────────────────────────────────────────┐ │
│ │ Overview tab content:                                │ │
│ │                                                      │ │
│ │ Description text from the post content...            │ │
│ │                                                      │ │
│ │ Quick Info:                                          │ │
│ │ ┌────────────┬────────────┬────────────┐            │ │
│ │ │ Cuisine    │ Price      │ Delivery   │            │ │
│ │ │ Italian    │ $$$        │ ✓ Yes      │            │ │
│ │ └────────────┴────────────┴────────────┘            │ │
│ │                                                      │ │
│ │ Features:                                            │ │
│ │ [WiFi] [Parking] [Outdoor Seating] [Reservations]   │ │
│ │                                                      │ │
│ └──────────────────────────────────────────────────────┘ │
│                                                          │
│ ┌──────────────────────────────────────────────────────┐ │
│ │ Contact Sidebar (sticky on scroll):                  │ │
│ │                                                      │ │
│ │ 📞 (212) 555-0123         [Call]                    │ │
│ │ 🌐 www.pizzapalace.com    [Visit]                   │ │
│ │ ✉️ info@pizzapalace.com   [Email]                   │ │
│ │                                                      │ │
│ │ [Facebook] [Instagram] [Twitter]                     │ │
│ └──────────────────────────────────────────────────────┘ │
│                                                          │
│ ┌──────────────────────────────────────────────────────┐ │
│ │ Related Listings (same type, same category/area)     │ │
│ │ ┌────┐ ┌────┐ ┌────┐                               │ │
│ │ │Card│ │Card│ │Card│                               │ │
│ │ └────┘ └────┘ └────┘                               │ │
│ └──────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────┘
```

### Sidebar Layout
```
┌────────────────────────────────┬─────────────────────┐
│                                │ Contact Info        │
│ Gallery                        │ 📞 (212) 555-0123  │
│ Title + Meta                   │ 🌐 Website         │
│                                │ ✉️ Email            │
│ Tab Content                    │                     │
│ (Overview, Details, etc.)     │ Map                  │
│                                │ [small map embed]  │
│                                │                     │
│                                │ Business Hours      │
│                                │ Mon: 9AM-9PM       │
│                                │ Tue: 9AM-9PM       │
│                                │ ...                 │
│ Reviews Section                │ Open Now ✓         │
│                                │                     │
│                                │ Social Links        │
│                                │ [f] [ig] [tw]      │
│                                │                     │
│ Related Listings               │ [♡ Save]           │
│                                │ [↗ Share]          │
│                                │ [🏷 Claim]         │
└────────────────────────────────┴─────────────────────┘
```
Content: 65%, Sidebar: 35%. Sidebar sticky on scroll.

---

## Components

### Gallery
```
Main image (large) + thumbnail strip below
Click → Lightbox (full-screen, swipeable)
```
- Server-rendered: first image visible, thumbnails below
- Interactivity API: lightbox, navigation, swipe gestures
- Lazy load non-visible thumbnails
- Video thumbnails play inline on click (YouTube/Vimeo oembed)
- Max images in gallery: configurable (default 20)
- `srcset` for responsive images

### Header Section
```html
<header class="listora-detail__header">
  <div class="listora-detail__type-badge">Restaurant</div>
  <div class="listora-detail__rating">★★★★½ 4.5 (23 reviews)</div>
  <h1 class="listora-detail__title" itemprop="name">Pizza Palace</h1>
  <address class="listora-detail__address" itemprop="address">
    📍 123 Main St, Manhattan, NY 10001
  </address>
  <div class="listora-detail__actions">
    <button data-wp-on--click="actions.toggleFavorite">♡ Save</button>
    <button data-wp-on--click="actions.shareDialog">↗ Share</button>
    <a href="https://maps.google.com/...">📍 Directions</a>
    <button data-wp-on--click="actions.showClaimModal">🏷 Claim</button>
  </div>
</header>
```

### Tab Navigation
Tabs correspond to field groups + fixed tabs (Overview, Reviews, Map):

| Tab | Source | Always Visible |
|-----|--------|:-:|
| Overview | Post content + quick info fields | Yes |
| Details | Type-specific field groups | If fields exist |
| Hours | Business hours + open now | If hours data exists |
| Reviews | Review list + form | Yes |
| Map | Embedded map + directions | If location exists |
| Gallery | Full photo gallery | If > 4 images |

Tabs are anchor-linked (`#reviews`) for direct linking and SEO.

### Contact Section
```html
<aside class="listora-detail__contact" itemscope itemtype="https://schema.org/ContactPoint">
  <a href="tel:+12125550123" class="listora-detail__phone" itemprop="telephone">
    📞 (212) 555-0123
  </a>
  <a href="https://pizzapalace.com" class="listora-detail__website" itemprop="url"
     target="_blank" rel="noopener">
    🌐 www.pizzapalace.com
  </a>
  <a href="mailto:info@pizzapalace.com" class="listora-detail__email" itemprop="email">
    ✉️ info@pizzapalace.com
  </a>
</aside>
```

**Pro:** Each click tracked for analytics (phone_click, website_click, email_click).

### Business Hours Display
```
┌─────────────────────────────┐
│ Business Hours    🟢 Open Now│
│                              │
│ Monday      9:00 AM – 9:00 PM│
│ Tuesday     9:00 AM – 9:00 PM│
│ Wednesday   9:00 AM – 9:00 PM│
│ Thursday    9:00 AM – 9:00 PM│
│ Friday      9:00 AM – 11:00 PM│ ← today highlighted
│ Saturday    10:00 AM – 11:00 PM│
│ Sunday      Closed            │
└─────────────────────────────┘
```

- Current day highlighted
- "Open Now" / "Closed" badge computed from listing's timezone
- Hours in 12h or 24h format based on site locale

### Share Dialog
```
┌─────────────────────────────┐
│ Share this listing          │
│                             │
│ [📋 Copy Link]             │
│ [Facebook] [Twitter]       │
│ [WhatsApp] [Email]         │
│ [LinkedIn] [Pinterest]      │
└─────────────────────────────┘
```

Uses Web Share API on mobile (native share sheet), custom dialog on desktop.

### Related Listings
- Same listing type
- Same category or nearby location
- Exclude current listing
- Show 3-4 cards in a row
- Uses REST: `GET /listora/v1/listings/{id}/related`

---

## Block: `listora/listing-detail`

### Usage
This block is meant for use in the single listing template:
- Block theme: Place in `templates/single-listora_listing.html`
- Classic theme: Rendered automatically via `single-listora_listing.php` template

### Attributes
```json
{
  "attributes": {
    "layout": { "type": "string", "default": "tabbed" },
    "showGallery": { "type": "boolean", "default": true },
    "showMap": { "type": "boolean", "default": true },
    "showReviews": { "type": "boolean", "default": true },
    "showRelated": { "type": "boolean", "default": true },
    "showShare": { "type": "boolean", "default": true },
    "showClaim": { "type": "boolean", "default": true },
    "relatedCount": { "type": "number", "default": 3 }
  }
}
```

---

## Template Hierarchy

### Block Theme
```
templates/single-listora_listing.html
```
Contains:
```html
<!-- wp:template-part {"slug":"header"} /-->
<!-- wp:group {"layout":{"type":"constrained"}} -->
  <!-- wp:listora/listing-detail /-->
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer"} /-->
```

### Classic Theme
Plugin provides `templates/single-listora_listing.php` as a fallback.
Themes can override by creating `single-listora_listing.php` in their directory.

---

## Mobile Layout

### Stacked (No Sidebar)
On mobile, sidebar layout converts to stacked:
1. Gallery (full-width, swipeable)
2. Title + meta
3. Action buttons (horizontal scroll)
4. Contact info (cards)
5. Tabs (horizontal scroll)
6. Tab content
7. Reviews
8. Map (full-width)
9. Related listings (horizontal scroll)

### Sticky Contact Bar
On mobile, a sticky bottom bar appears:
```
┌─────────────────────────────────────┐
│ [📞 Call]  [🌐 Visit]  [♡ Save]   │
└─────────────────────────────────────┘
```

---

## Theme Adaptive CSS

```css
.listora-detail {
  --listora-detail-max-width: var(--wp--style--global--content-size, 1200px);
  --listora-detail-gap: var(--wp--preset--spacing--30, 1.5rem);
  --listora-detail-bg: var(--wp--preset--color--base, #fff);
  --listora-detail-text: var(--wp--preset--color--contrast, #333);
  --listora-detail-border: var(--wp--preset--color--contrast-3, #eee);
  --listora-detail-tab-active: var(--wp--preset--color--primary, #0073aa);
}

.listora-detail__title {
  font-family: var(--wp--preset--font-family--heading, inherit);
  font-size: var(--wp--preset--font-size--x-large, 2rem);
  color: var(--wp--preset--color--contrast, #1a1a1a);
}

.listora-detail__tabs [role="tab"][aria-selected="true"] {
  border-block-end-color: var(--listora-detail-tab-active);
  color: var(--listora-detail-tab-active);
}
```

---

## Accessibility

| Element | A11y Feature |
|---------|-------------|
| Gallery | `role="img"` group, alt text, keyboard lightbox nav |
| Tabs | `role="tablist"`, `role="tab"`, `role="tabpanel"`, arrow key nav |
| Contact links | Descriptive text, not just icons |
| Share dialog | Focus trap, Escape to close |
| Map | Skip link past map, `role="application"` |
| Business hours | `<table>` with proper headers, today marked `aria-current="date"` |
| Breadcrumbs | `nav` with `aria-label="Breadcrumb"` |
| Phone link | `href="tel:"` for assistive tech |
| Rating | Text alternative: "Rated 4.5 out of 5 based on 23 reviews" |

---

## SEO

### Schema.org (JSON-LD in `<head>`)
See `23-seo-schema.md` for full schema per listing type.

### Open Graph
```html
<meta property="og:title" content="Pizza Palace — Italian Restaurant">
<meta property="og:description" content="Best pizza in Manhattan...">
<meta property="og:image" content="featured-image-url.jpg">
<meta property="og:type" content="place">
<meta property="og:url" content="https://site.com/listing/pizza-palace/">
<meta property="place:location:latitude" content="40.7128">
<meta property="place:location:longitude" content="-74.0060">
```

### Breadcrumb JSON-LD
```json
{
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"position": 1, "name": "Home", "item": "https://site.com"},
    {"position": 2, "name": "Restaurants", "item": "https://site.com/restaurants/"},
    {"position": 3, "name": "Italian", "item": "https://site.com/listing-category/italian/"},
    {"position": 4, "name": "Pizza Palace"}
  ]
}
```
