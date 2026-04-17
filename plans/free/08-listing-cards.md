# 08 — Listing Cards

## Scope

| | Free | Pro |
|---|---|---|
| Standard card layout | Yes | Yes |
| Horizontal card layout | Yes (basic) | Yes (enhanced) |
| Compact card layout | Yes | Yes |
| Overlay card layout | — | Yes |
| Card in grid/list toggle | Yes | Yes |
| Favorite button on card | Yes | Yes |
| Quick view popup | — | Yes |
| Custom card field selection | Yes | Yes |
| Featured badge | Yes | Yes + custom badges |
| Verified badge | — | Yes |

---

## Overview

The listing card is the most-seen component in any directory. It appears in search results, grids, carousels, related listings, and favorites. It must:

1. Look professional with ZERO CSS customization (theme-adaptive)
2. Load fast (no unnecessary assets)
3. Show the right info for each listing type
4. Work in grid AND list layouts
5. Be accessible (keyboard nav, screen readers)

---

## Card Layouts

### Standard Card (Default)
```
┌─────────────────────────┐
│ ┌─────────────────────┐ │
│ │                     │ │
│ │    Featured Image   │ │  ← 16:10 aspect ratio
│ │                     │ │
│ │  [♡]          ★4.5  │ │  ← Favorite + rating overlay
│ └─────────────────────┘ │
│                         │
│ Listing Type Badge      │  ← Small pill: "Restaurant"
│ Listing Title           │  ← <h3>, truncate at 2 lines
│ 📍 Location             │  ← City, State
│                         │
│ ┌─────┐ ┌─────┐ ┌────┐│
│ │$$$  │ │🍕   │ │Open││  ← Type-specific meta pills
│ └─────┘ └─────┘ └────┘│
│                         │
│ [WiFi] [Parking] [♿]  │  ← Feature badges (max 3)
└─────────────────────────┘
```

**Dimensions:** Fluid width (container-responsive), min 250px, max 400px

### Horizontal Card (List View)
```
┌────────────────────────────────────────────────────┐
│ ┌──────────┐                                       │
│ │          │  Listing Title           [♡]  ★4.5   │
│ │  Image   │  📍 Manhattan, NY                     │
│ │          │  $$$ · Italian · Open Now              │
│ │          │  Short excerpt text goes here...       │
│ └──────────┘  [WiFi] [Parking]                     │
└────────────────────────────────────────────────────┘
```

**Use case:** List view toggle, search results in sidebar

### Compact Card
```
┌────────────────────────────┐
│ 🍕 Restaurant Name    ★4.5│
│    Manhattan · $$$         │
└────────────────────────────┘
```

**Use case:** Sidebar widgets, map popups, related listings

### Overlay Card (Pro)
```
┌─────────────────────────┐
│                         │
│    Featured Image       │
│    (full card bg)       │
│                         │
│ ┌─────────────────────┐ │
│ │ Listing Title       │ │  ← Gradient overlay at bottom
│ │ Location · $$$      │ │
│ │ ★4.5 (23 reviews)  │ │
│ └─────────────────────┘ │
└─────────────────────────┘
```

**Use case:** Hero/featured sections, visual-heavy directories

---

## Card Components

### Featured Image
- Uses `post_thumbnail` (standard WP)
- Lazy loaded (`loading="lazy"`)
- `srcset` for responsive images
- Fallback: listing type icon on solid color background (type's brand color)
- Aspect ratio: `16:10` default (configurable via CSS custom property)

### Favorite Button
- Heart icon, toggle on click
- Interactivity API action → REST API call
- Visual feedback: filled heart when favorited
- Requires authentication — unauthenticated users see login prompt
- `aria-label="Save to favorites"` / `aria-label="Remove from favorites"`

### Rating Display
- Filled/empty star icons
- Numeric value + review count: "★ 4.5 (23)"
- Hidden if no reviews
- `<span aria-label="Rating: 4.5 out of 5 stars">`

### Type Badge
- Small colored pill with type name
- Color from listing type's `_listora_color`
- Only shown in "All Types" view (hidden on type-specific pages)

### Meta Fields
- Configurable per listing type (`_listora_card_fields`)
- Max 3-4 fields shown as pills/icons
- Icon + short value format
- Truncated if too long

### Feature Badges
- First 3 features as small icon badges
- "+N more" indicator if > 3
- Icon-only on small cards

### Status Badges
- "Featured" — star badge (golden accent)
- "Verified" — checkmark badge (Pro)
- "New" — shown for first 7 days
- "Open Now" — green dot/badge (if hours data exists)
- "Claimed" — small indicator

---

## Block: `listora/listing-card`

### `block.json`
```json
{
  "apiVersion": 3,
  "name": "listora/listing-card",
  "title": "Listing Card",
  "category": "listora",
  "parent": ["listora/listing-grid"],
  "description": "Displays a single listing as a card.",
  "supports": {
    "html": false,
    "align": false
  },
  "attributes": {
    "listingId": { "type": "number" },
    "layout": { "type": "string", "default": "standard" },
    "showRating": { "type": "boolean", "default": true },
    "showFavorite": { "type": "boolean", "default": true },
    "showType": { "type": "boolean", "default": true },
    "showFeatures": { "type": "boolean", "default": true },
    "maxMetaFields": { "type": "number", "default": 3 }
  },
  "usesContext": ["listora/viewMode", "listora/listingType"],
  "style": "file:./style.css",
  "viewScriptModule": "file:./view.js",
  "render": "file:./render.php"
}
```

### Server Rendering (`render.php`)
- Card is server-rendered for SEO
- Interactivity API adds: favorite toggle, quick view (Pro)
- `data-wp-interactive="listora/directory"` on card root
- `data-wp-on--click` for card click navigation
- `data-wp-on--mouseenter` for map marker highlight sync

### HTML Structure
```html
<article
  class="listora-card listora-card--standard"
  data-wp-interactive="listora/directory"
  data-wp-context='{"listingId": 123}'
  data-wp-on--mouseenter="actions.highlightMarker"
  data-wp-on--mouseleave="actions.unhighlightMarker"
  itemscope
  itemtype="https://schema.org/Restaurant"
>
  <div class="listora-card__media">
    <a href="/listing/pizza-palace/" class="listora-card__image-link">
      <img
        class="listora-card__image"
        src="..."
        srcset="..."
        sizes="(max-width: 600px) 100vw, 300px"
        alt="Pizza Palace"
        loading="lazy"
        itemprop="image"
      />
    </a>
    <button
      class="listora-card__favorite"
      data-wp-on--click="actions.toggleFavorite"
      data-wp-class--is-favorited="state.isFavorited"
      aria-label="Save to favorites"
    >
      <svg><!-- heart icon --></svg>
    </button>
    <span class="listora-card__rating" aria-label="Rating: 4.5 out of 5">
      <svg><!-- star --></svg> 4.5
    </span>
  </div>

  <div class="listora-card__body">
    <span class="listora-card__type-badge" style="--badge-color: #E74C3C">
      Restaurant
    </span>
    <h3 class="listora-card__title" itemprop="name">
      <a href="/listing/pizza-palace/">Pizza Palace</a>
    </h3>
    <address class="listora-card__location" itemprop="address">
      <svg><!-- pin icon --></svg> Manhattan, NY
    </address>

    <div class="listora-card__meta">
      <span class="listora-card__meta-item">$$$ </span>
      <span class="listora-card__meta-item">Italian</span>
      <span class="listora-card__meta-item listora-card__meta-item--open">
        Open Now
      </span>
    </div>

    <div class="listora-card__features">
      <span class="listora-feature-badge" title="WiFi">
        <svg><!-- wifi icon --></svg>
      </span>
      <span class="listora-feature-badge" title="Parking">
        <svg><!-- parking icon --></svg>
      </span>
    </div>
  </div>
</article>
```

---

## Theme Adaptive CSS

### Custom Properties (Overridable by Theme)
```css
:root {
  /* Card structure */
  --listora-card-radius: var(--wp--custom--border-radius, 8px);
  --listora-card-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  --listora-card-shadow-hover: 0 4px 12px rgba(0, 0, 0, 0.12);
  --listora-card-bg: var(--wp--preset--color--base, #fff);
  --listora-card-border: 1px solid var(--wp--preset--color--contrast-3, #eee);
  --listora-card-padding: var(--wp--preset--spacing--20, 1rem);
  --listora-card-gap: var(--wp--preset--spacing--10, 0.5rem);

  /* Card image */
  --listora-card-image-ratio: 16 / 10;

  /* Card typography */
  --listora-card-title-size: var(--wp--preset--font-size--medium, 1.1rem);
  --listora-card-title-font: var(--wp--preset--font-family--heading, inherit);
  --listora-card-meta-size: var(--wp--preset--font-size--small, 0.85rem);
  --listora-card-location-color: var(--wp--preset--color--contrast-2, #666);

  /* Card colors */
  --listora-card-title-color: var(--wp--preset--color--contrast, #1a1a1a);
  --listora-card-text-color: var(--wp--preset--color--contrast-2, #555);
  --listora-card-link-color: var(--wp--preset--color--contrast, #1a1a1a);

  /* Interactive */
  --listora-card-favorite-color: var(--wp--preset--color--vivid-red, #cf2e2e);
  --listora-card-rating-color: var(--wp--preset--color--luminous-vivid-amber, #fcb900);
  --listora-card-open-color: #16a34a;
}
```

### How Themes Override
In `theme.json`:
```json
{
  "settings": {
    "custom": {
      "listora": {
        "card-radius": "12px",
        "card-shadow": "none",
        "card-image-ratio": "4 / 3"
      }
    }
  }
}
```

Or in theme CSS:
```css
.listora-card {
  --listora-card-radius: 12px;
  --listora-card-shadow: none;
}
```

### Dark Mode Support
```css
@media (prefers-color-scheme: dark) {
  .listora-card {
    --listora-card-bg: var(--wp--preset--color--base, #1a1a1a);
    --listora-card-border: 1px solid var(--wp--preset--color--contrast-3, #333);
    --listora-card-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
  }
}
```

Only activates if theme supports dark mode (via `theme.json` or `prefers-color-scheme` styles).

---

## Responsive Behavior

```css
/* Cards in grid inherit column count from grid block */
/* No fixed breakpoints — uses container queries */
@container listora-grid (max-width: 500px) {
  .listora-card--standard {
    /* Stack to single column */
  }
  .listora-card__meta {
    /* Reduce to 2 visible fields */
  }
}

@container listora-grid (max-width: 350px) {
  /* Compact mode: reduce image height, smaller text */
}
```

---

## Accessibility

| Element | Accessible Behavior |
|---------|---------------------|
| Card | `<article>` landmark with `itemscope` |
| Title link | Focus visible, full card click area (via `::after` pseudo) |
| Favorite button | `aria-label`, `aria-pressed` states |
| Rating | `aria-label="Rating: 4.5 out of 5 stars"` |
| Image | Meaningful `alt` text (listing title) |
| Meta items | Text content readable by screen readers |
| Keyboard nav | Tab to card → Enter to visit, Tab to favorite → Enter to toggle |
| Feature badges | `title` attribute for full text, `aria-hidden` on decorative icons |
| Open/Closed status | `aria-label` on status badge |

---

## Map Interaction

Cards and map markers are synchronized via shared Interactivity API state:

- **Hover on card** → `actions.highlightMarker` → corresponding map marker bounces/highlights
- **Hover on map marker** → `actions.highlightCard` → corresponding card gets `--is-highlighted` class (subtle border/shadow change)
- **Click map marker** → scrolls to card OR shows popup with compact card

This requires the card and map blocks to share the `listora/directory` namespace.

---

## No Image Fallback

When a listing has no featured image:

```
┌─────────────────────────┐
│ ┌─────────────────────┐ │
│ │                     │ │
│ │    [ Type Icon ]    │ │  ← Large type icon (e.g., fork/knife for restaurant)
│ │    on solid color   │ │  ← Background = type's brand color (20% opacity)
│ │                     │ │
│ │  [♡]          ★4.5  │ │
│ └─────────────────────┘ │
│ ...                     │
└─────────────────────────┘
```

The fallback is visually designed — not a broken image placeholder.
