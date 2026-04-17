# 10 — Listing Grid

## Scope

| | Free | Pro |
|---|---|---|
| Grid view (columns) | Yes | Yes |
| List view (rows) | Yes | Yes |
| View mode toggle (grid/list) | Yes | Yes |
| Split view (grid + map) | Yes | Yes |
| Map-only view | Yes | Yes |
| Pagination | Yes | Yes |
| "Load More" button | Yes | Yes |
| Infinite scroll | — | Yes |
| Result count display | Yes | Yes |
| Sort dropdown | Yes | Yes |
| Empty state | Yes | Yes |
| Loading skeleton | Yes | Yes |

---

## Overview

The grid block displays search results as a responsive grid or list of listing cards. It's tightly coupled with the search block and map block via shared Interactivity API state.

---

## View Modes

### Grid View (Default)
```
┌────────────────────────────────────────────────────┐
│ 156 results  |  Grid ▪ List ▪ Map  | Sort: Rating ▾│
├────────────────────────────────────────────────────┤
│ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│ │          │ │          │ │          │           │
│ │  Card 1  │ │  Card 2  │ │  Card 3  │           │
│ │          │ │          │ │          │           │
│ └──────────┘ └──────────┘ └──────────┘           │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│ │          │ │          │ │          │           │
│ │  Card 4  │ │  Card 5  │ │  Card 6  │           │
│ │          │ │          │ │          │           │
│ └──────────┘ └──────────┘ └──────────┘           │
│                                                    │
│              [1] [2] [3] ... [8] [→]               │
└────────────────────────────────────────────────────┘
```

### List View
```
┌────────────────────────────────────────────────────┐
│ ┌──────────────────────────────────────────────┐   │
│ │ [Image]  Title          ★4.5  [♡]           │   │
│ │          📍 Location · $$$                   │   │
│ │          Description excerpt...              │   │
│ └──────────────────────────────────────────────┘   │
│ ┌──────────────────────────────────────────────┐   │
│ │ [Image]  Title          ★4.2  [♡]           │   │
│ │          📍 Location · $$                    │   │
│ │          Description excerpt...              │   │
│ └──────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────┘
```

### Split View (Grid + Map)
```
┌─────────────────────────┬──────────────────────────┐
│ ┌─────┐ ┌─────┐       │                           │
│ │Card │ │Card │       │                           │
│ │  1  │ │  2  │       │        Map                │
│ └─────┘ └─────┘       │        with               │
│ ┌─────┐ ┌─────┐       │        markers            │
│ │Card │ │Card │       │                           │
│ │  3  │ │  4  │       │                           │
│ └─────┘ └─────┘       │                           │
│                         │                           │
│ [1] [2] [3]            │                           │
└─────────────────────────┴──────────────────────────┘
```

Grid takes 60%, map takes 40%. On mobile: tabs between grid and map.

---

## Block: `listora/listing-grid`

### Attributes
```json
{
  "attributes": {
    "listingType": { "type": "string", "default": "" },
    "columns": { "type": "number", "default": 3 },
    "perPage": { "type": "number", "default": 20 },
    "defaultView": { "type": "string", "default": "grid" },
    "showViewToggle": { "type": "boolean", "default": true },
    "showResultCount": { "type": "boolean", "default": true },
    "showSort": { "type": "boolean", "default": true },
    "showPagination": { "type": "boolean", "default": true },
    "paginationType": { "type": "string", "default": "numbered" },
    "cardLayout": { "type": "string", "default": "" }
  }
}
```

### Server Rendering (`render.php`)
1. Check if connected to a search block (via shared namespace)
2. If standalone (no search block): query listings by attributes (type, sort, per_page)
3. If connected: render placeholder, Interactivity API populates from search results
4. Initial render always server-side (SEO)

---

## Grid Responsive Behavior

```css
.listora-grid {
  display: grid;
  grid-template-columns: repeat(var(--listora-grid-columns, 3), 1fr);
  gap: var(--wp--preset--spacing--30, 1.5rem);
  container-type: inline-size;
  container-name: listora-grid;
}

@container listora-grid (max-width: 900px) {
  .listora-grid { --listora-grid-columns: 2; }
}

@container listora-grid (max-width: 500px) {
  .listora-grid { --listora-grid-columns: 1; }
}
```

Uses **container queries** instead of media queries — grid adapts to its container width, not viewport width. This means it works correctly in:
- Full-width layouts
- Sidebar layouts (60/40 split)
- Column blocks
- Site editor template areas

---

## Loading States

### Initial Load (Server-Rendered)
Full HTML content rendered by PHP — no loading state needed for first paint.

### AJAX Search Loading
```
┌────────────────────────────────────────────────────┐
│ Searching...                                       │
├────────────────────────────────────────────────────┤
│ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│ │ ░░░░░░░░ │ │ ░░░░░░░░ │ │ ░░░░░░░░ │           │
│ │ ░░░░░░░░ │ │ ░░░░░░░░ │ │ ░░░░░░░░ │           │
│ │ ░░░░░    │ │ ░░░░░    │ │ ░░░░░    │           │
│ │ ░░░      │ │ ░░░      │ │ ░░░      │           │
│ └──────────┘ └──────────┘ └──────────┘           │
└────────────────────────────────────────────────────┘
```

Skeleton cards with CSS animation (pulsing gray blocks). Same dimensions as real cards to prevent layout shift.

### Empty State
```
┌────────────────────────────────────────────────────┐
│                                                    │
│           🔍                                      │
│                                                    │
│     No listings found                              │
│                                                    │
│     Try adjusting your filters or                  │
│     search in a different area.                    │
│                                                    │
│     [Clear All Filters]                            │
│                                                    │
└────────────────────────────────────────────────────┘
```

---

## Pagination

### Numbered (Default)
```
[← Prev] [1] [2] [3] ... [8] [Next →]
```

### Load More Button
```
Showing 20 of 156 results
[Load More]
```
Appends next page results below existing ones.

### Infinite Scroll (Pro)
Automatically loads next page when user scrolls near bottom. Shows loading spinner. Stops at last page.

---

## Toolbar

```html
<div class="listora-grid__toolbar">
  <span class="listora-grid__count" aria-live="polite">
    156 results
  </span>

  <div class="listora-grid__view-toggle" role="radiogroup" aria-label="View mode">
    <button role="radio" aria-checked="true" aria-label="Grid view">▦</button>
    <button role="radio" aria-checked="false" aria-label="List view">≡</button>
    <button role="radio" aria-checked="false" aria-label="Map view">🗺</button>
  </div>

  <select class="listora-grid__sort" aria-label="Sort by">
    <option value="featured">Featured</option>
    <option value="rating">Highest Rated</option>
    <option value="newest">Newest</option>
    <option value="distance">Nearest</option>
    <option value="price_asc">Price: Low to High</option>
    <option value="price_desc">Price: High to Low</option>
  </select>
</div>
```

---

## Theme Adaptive CSS

```css
.listora-grid__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--wp--preset--spacing--10, 0.5rem) 0;
  border-block-end: 1px solid var(--wp--preset--color--contrast-3, #eee);
  margin-block-end: var(--wp--preset--spacing--20, 1rem);
  font-size: var(--wp--preset--font-size--small, 0.9rem);
  color: var(--wp--preset--color--contrast-2, #666);
}

.listora-grid__view-toggle button {
  background: var(--wp--preset--color--base, #fff);
  border: 1px solid var(--wp--preset--color--contrast-3, #ddd);
  color: var(--wp--preset--color--contrast-2, #666);
}

.listora-grid__view-toggle button[aria-checked="true"] {
  background: var(--wp--preset--color--primary, #0073aa);
  color: var(--wp--preset--color--base, #fff);
  border-color: var(--wp--preset--color--primary, #0073aa);
}
```

---

## Accessibility

| Element | A11y Feature |
|---------|-------------|
| Result count | `aria-live="polite"` — announced on change |
| View toggle | `role="radiogroup"` with `aria-checked` |
| Sort dropdown | `<select>` with `<label>` |
| Pagination | `nav` element with `aria-label="Pagination"` |
| Loading state | `aria-busy="true"` on grid container |
| Empty state | Descriptive text, action button |
| Card grid | `<ul role="list">` with `<li>` per card |
