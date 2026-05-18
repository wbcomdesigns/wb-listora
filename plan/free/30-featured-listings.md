# 30 — Featured Listings (Pro)

## Scope: Pro Only

---

## Overview

Featured listings appear first in search results and get visual prominence (badge, card highlight). This is a core monetization feature — listing owners pay to be featured.

---

## How It Works

### Becoming Featured
1. **Admin-set:** Admin marks any listing as featured
2. **Payment-driven:** Listing owner selects a plan that includes "featured" perk
3. **Time-limited:** Featured status expires with the plan duration

### Visual Treatment

**Card badge:**
```
┌─────────────────────────┐
│ ⭐ FEATURED             │  ← Gold badge top-left
│ ┌─────────────────────┐ │
│ │   Featured Image    │ │
│ │                     │ │
│ └─────────────────────┘ │
│ Restaurant Name         │
│ ...                     │
└─────────────────────────┘
```

**Card highlight:**
```css
.listora-card--featured {
  border-color: var(--wp--preset--color--luminous-vivid-amber, #fcb900);
  box-shadow: 0 0 0 2px rgba(252, 185, 0, 0.2);
}
```

### Search Ordering
Featured listings always appear first in results (before non-featured), then sorted by the user's chosen sort order within each group.

```sql
ORDER BY is_featured DESC, avg_rating DESC
```

---

## Featured Carousel Block: `listora/listing-featured`

Displays featured listings in a horizontal carousel on homepage or any page:

```
┌──────────────────────────────────────────────────────┐
│ Featured Listings                          [→]      │
│                                                     │
│ ◀ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ▶          │
│   │ ⭐   │ │ ⭐   │ │ ⭐   │ │ ⭐   │             │
│   │Card 1│ │Card 2│ │Card 3│ │Card 4│             │
│   │      │ │      │ │      │ │      │             │
│   └──────┘ └──────┘ └──────┘ └──────┘             │
│                                                     │
│              ● ● ○ ○                                │
└──────────────────────────────────────────────────────┘
```

### Attributes
```json
{
  "count": 8,
  "listingType": "",
  "autoplay": false,
  "showDots": true,
  "columns": 4
}
```

### Accessibility
- No autoplay by default
- Keyboard navigable (arrow keys)
- `aria-roledescription="carousel"`, `aria-label="Featured listings"`
- Pause on hover/focus
