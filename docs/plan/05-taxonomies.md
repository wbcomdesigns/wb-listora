# 05 — Taxonomies (Categories, Tags, Locations, Features)

## Scope

| | Free | Pro |
|---|---|---|
| Categories (hierarchical, per-type) | Yes | Yes |
| Tags (free-form) | Yes | Yes |
| Locations (hierarchical) | Yes | Yes |
| Features/Amenities | Yes | Yes |
| Taxonomy icons/images | Yes | Yes |
| Category counts in search (facets) | Yes | Yes + advanced facets |

---

## Taxonomy Overview

| Taxonomy | Slug | Hierarchical | Purpose | Example |
|----------|------|:---:|---------|---------|
| `listora_listing_type` | `listing-type` | No | Defines listing type | Restaurant, Hotel, Job |
| `listora_listing_cat` | `listing-category` | Yes | Content categories (per-type) | Italian, Chinese (Restaurant); Apartment, Villa (Real Estate) |
| `listora_listing_tag` | `listing-tag` | No | Free-form tags | "pet-friendly", "rooftop", "family" |
| `listora_listing_location` | `listing-location` | Yes | Geographic hierarchy | USA > New York > Manhattan |
| `listora_listing_feature` | `listing-feature` | No | Amenities/features | WiFi, Parking, Pool, AC, Wheelchair |

---

## Category System

### Architecture: Scoped Categories

Categories exist in a single taxonomy (`listora_listing_cat`) but are **scoped to listing types**. This is the critical design decision.

**How scoping works:**
- Each listing type stores `_listora_allowed_categories` (array of term IDs)
- When creating/editing a listing of type "Restaurant", only restaurant categories appear
- In search filters, only relevant categories show
- On type-specific pages, only those categories are browsable

**Why single taxonomy (not one per type):**
- Simpler database (one term table)
- Cross-type categories possible if needed (e.g., "Luxury" across Hotel + Real Estate)
- Standard WP REST API works without custom endpoints
- Easier migration/import

### Default Categories Per Type

Created on activation. See `03-listing-types.md` for full list per type.

### Category Term Meta

```
_listora_icon        → string: dashicon name or SVG identifier
_listora_image       → int: attachment ID for category image
_listora_color       → string: hex color for category badge
_listora_description → string: category description (for SEO)
_listora_order       → int: display order within parent
```

### Admin UI

**Within Listing Type Editor (Tab 3: Categories):**
```
┌─────────────────────────────────────────────┐
│ Categories for: Restaurant                  │
│                                             │
│ ┌───────────────────────────────────────┐   │
│ │ ☑ Italian        [🍕] 12 listings    │   │
│ │   ☑ Pizza                            │   │
│ │   ☑ Pasta                            │   │
│ │ ☑ Chinese        [🥡] 8 listings     │   │
│ │ ☑ Japanese       [🍣] 15 listings    │   │
│ │   ☑ Sushi                            │   │
│ │   ☑ Ramen                            │   │
│ │ ☑ Mexican        [🌮] 6 listings     │   │
│ │ ☐ Indian (not assigned to this type) │   │
│ └───────────────────────────────────────┘   │
│                                             │
│ Quick Add: [ Category Name ] [Icon ▾] [Add] │
│                                             │
│ Note: Categories can be shared across       │
│ listing types if needed.                    │
└─────────────────────────────────────────────┘
```

**Standalone Category Admin (Listora → Categories):**
Standard WP taxonomy admin with added columns:
| Column | Content |
|--------|---------|
| Icon | Category icon |
| Image | Category thumbnail |
| Listing Types | Which types use this category |
| Count | Listing count |

---

## Location Taxonomy

### Hierarchy
```
Country
└── State/Province
    └── City
        └── Neighborhood (optional)
```

### Term Meta
```
_listora_lat          → decimal: center latitude
_listora_lng          → decimal: center longitude
_listora_bounds       → JSON: bounding box for map viewport
_listora_country_code → string: ISO 3166-1 alpha-2 (e.g., "US")
_listora_timezone     → string: IANA timezone (e.g., "America/New_York")
```

### Auto-Population
When a listing is saved with a `map_location` field:
1. Geocoding response includes city, state, country
2. Check if location terms exist → create if not
3. Assign listing to the full location chain (Country + State + City)
4. This happens automatically — listing owners don't pick locations manually (unless map_location is empty)

### Admin UI
```
┌─────────────────────────────────────────────┐
│ Locations                                   │
│                                             │
│ ▾ United States (245 listings)              │
│   ▾ New York (89 listings)                  │
│     ▸ Manhattan (45)                        │
│     ▸ Brooklyn (28)                         │
│     ▸ Queens (16)                           │
│   ▸ California (78 listings)                │
│   ▸ Texas (42 listings)                     │
│ ▸ United Kingdom (56 listings)              │
│ ▸ Canada (23 listings)                      │
│                                             │
│ [+ Add Location Manually]                   │
└─────────────────────────────────────────────┘
```

### Frontend Browse
```
┌─────────────────────────────────────────────┐
│ Browse by Location                          │
│                                             │
│ ┌────────┐ ┌────────┐ ┌────────┐          │
│ │ 🏙️     │ │ 🌴     │ │ 🌆     │          │
│ │New York│ │ LA     │ │Chicago │          │
│ │245 list│ │ 189    │ │ 134    │          │
│ └────────┘ └────────┘ └────────┘          │
│ ┌────────┐ ┌────────┐ ┌────────┐          │
│ │Houston │ │Phoenix │ │ More   │          │
│ │ 98     │ │ 76     │ │  →     │          │
│ └────────┘ └────────┘ └────────┘          │
└─────────────────────────────────────────────┘
```

---

## Feature/Amenity Taxonomy

### Purpose
Tags for amenities and features that can be shared across listing types:
- WiFi, Parking, Pool, Gym, Pet-Friendly, Wheelchair Accessible
- AC, Elevator, 24-Hour, Outdoor Seating, Live Music

### Term Meta
```
_listora_icon    → string: icon identifier
_listora_group   → string: grouping label ("Internet", "Accessibility", "Facilities")
```

### Frontend Display
Features show as icon+label pills/badges on listing cards and detail pages:
```
[📶 WiFi] [🅿️ Parking] [♿ Wheelchair] [🐕 Pet-Friendly]
```

### Filtering
Features appear as multi-checkbox filters in search:
```
Amenities:
☑ WiFi          ☐ Pool
☑ Parking       ☐ Gym
☐ Pet-Friendly  ☐ AC
```

---

## Tags Taxonomy

### Purpose
Free-form tags that listing owners can add. Not scoped to types.

### Behavior
- Displayed on listing detail page
- Searchable (included in search index)
- Tag cloud block available
- No admin pre-configuration needed

---

## REST API

| Endpoint | Method | Response |
|----------|--------|----------|
| `listora/v1/listing-types` | GET | All listing types |
| `listora/v1/listing-types/{slug}/categories` | GET | Categories scoped to type |
| `listora/v1/categories` | GET | All categories with type info |
| `listora/v1/locations` | GET | Location hierarchy |
| `listora/v1/locations/{id}/children` | GET | Child locations |
| `listora/v1/features` | GET | All features with icons |
| `listora/v1/tags` | GET | All tags |

Standard WP REST taxonomy endpoints also available via `show_in_rest`.

---

## Theme Adaptive Display

### Category Cards
```html
<a class="listora-category-card" href="/listing-category/italian/">
  <span class="listora-category-card__icon" aria-hidden="true">
    <svg>...</svg>
  </span>
  <span class="listora-category-card__name">Italian</span>
  <span class="listora-category-card__count">12 listings</span>
</a>
```

### CSS
```css
.listora-category-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wp--preset--spacing--10, 0.5rem);
  padding: var(--wp--preset--spacing--20, 1rem);
  border: 1px solid var(--wp--preset--color--contrast-3, #ddd);
  border-radius: var(--wp--custom--border-radius, 8px);
  background: var(--wp--preset--color--base, #fff);
  color: var(--wp--preset--color--contrast, #333);
  text-decoration: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.listora-category-card:hover {
  border-color: var(--wp--preset--color--primary, #0073aa);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
```

All colors, spacing, and radii from `theme.json` tokens. Works with any block theme.

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Category used by multiple types | Show in all assigned types' search filters |
| Category with 0 listings | Show in browse, show "(0)" count, optionally hide via setting |
| Location not found during geocoding | Create "Unknown" location, flag for admin review |
| 500+ locations | Paginate location browse, use searchable dropdown in forms |
| Feature shared across incompatible types | Features are universal — display regardless of type |
| Deleting a category with listings | Listings become uncategorized (not deleted) |
| RTL category display | CSS logical properties handle this automatically |
| Category image missing | Show icon fallback, then first-letter fallback |
