# P2-10 — Multi-Directory

## Scope: Pro Only (NEW -- Competitor Gap)

---

## Overview

Run multiple independent directories on a single WordPress site. Each directory has its own listing types, settings overrides, pages, and URL prefix. Implementation uses a custom taxonomy `listora_directory` where each term represents a directory. All existing queries filter by active directory context. This enables "one site, many directories" — a restaurant directory, a job board, and a property listing site all on one WordPress install.

### Why It Matters

- Agencies managing multi-niche sites need independent directories without running multiple WP installs
- Cities/municipalities often need separate directories: businesses, events, services, parks
- Platforms like Craigslist, Yelp, and Google Maps run multiple directory types on one domain
- Reduces infrastructure cost — one WP install, one database, one admin panel
- This is a **major competitor gap** — no free or affordable WordPress directory plugin offers true multi-directory

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Agency owner | Run /restaurants, /jobs, and /real-estate as separate directories | My client has one site with multiple verticals |
| 2 | City admin | Have a business directory and an events calendar as separate directories | Citizens can browse each independently |
| 3 | Admin | Configure different settings per directory (map style, submission settings) | Each directory works optimally for its niche |
| 4 | Visitor | Browse the restaurant directory without seeing job listings | Each directory feels purpose-built |
| 5 | Admin | Switch between directory contexts in the admin panel | I can manage each directory's content separately |
| 6 | Developer | Filter queries by directory context programmatically | Custom integrations can target specific directories |

---

## Technical Design

### Taxonomy-Based Architecture

```php
// Register directory taxonomy
register_taxonomy('listora_directory', 'listora_listing', [
    'labels'       => ['name' => 'Directories', 'singular_name' => 'Directory'],
    'public'       => false,
    'hierarchical' => false,
    'rewrite'      => false,
    'show_in_rest' => true,
    'meta_box_cb'  => false, // Managed via admin page, not post editor
]);
```

Each directory is a term:
```
Term: "Restaurant Directory"
  Slug: restaurants
  Meta:
    _listora_dir_url_prefix      -> "restaurants"      (URL path prefix)
    _listora_dir_listing_types   -> ["restaurant","cafe","bar"]
    _listora_dir_settings        -> JSON: { ...overrides... }
    _listora_dir_page_search     -> 42  (page ID for search page)
    _listora_dir_page_submit     -> 43  (page ID for submission page)
    _listora_dir_page_dashboard  -> 44  (page ID for dashboard page)
    _listora_dir_icon            -> "utensils" (Lucide icon)
    _listora_dir_description     -> "Find the best restaurants, cafes, and bars."
    _listora_dir_sort_order      -> 1
```

### Directory Context

```php
class Directory_Context {
    private static ?int $current_directory_id = null;

    /**
     * Set the active directory (from URL, admin panel, or explicit call).
     */
    public static function set( int $term_id ): void {
        self::$current_directory_id = $term_id;
    }

    /**
     * Get the active directory ID (null = all directories / default).
     */
    public static function get(): ?int {
        return self::$current_directory_id;
    }

    /**
     * Auto-detect directory from current URL.
     */
    public static function detect_from_url(): void {
        $path   = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $prefix = explode('/', $path)[0] ?? '';

        $directories = get_terms([
            'taxonomy'   => 'listora_directory',
            'hide_empty' => false,
            'meta_key'   => '_listora_dir_url_prefix',
            'meta_value' => $prefix,
        ]);

        if (!empty($directories)) {
            self::set($directories[0]->term_id);
        }
    }

    /**
     * Get settings for current directory (with fallback to global).
     */
    public static function get_setting( string $key, $default = null ) {
        if (self::$current_directory_id) {
            $overrides = get_term_meta(self::$current_directory_id, '_listora_dir_settings', true);
            if (is_array($overrides) && isset($overrides[$key])) {
                return $overrides[$key];
            }
        }
        // Fall back to global setting
        return get_option("listora_{$key}", $default);
    }
}
```

### Query Filtering

All existing search/listing queries automatically filter by directory context:

```php
// Hook into search engine
add_filter('wb_listora_search_args', function(array $args): array {
    $dir_id = Directory_Context::get();
    if ($dir_id) {
        $args['tax_query'][] = [
            'taxonomy' => 'listora_directory',
            'field'    => 'term_id',
            'terms'    => $dir_id,
        ];
    }
    return $args;
});

// Hook into listing type registry
add_filter('wb_listora_available_listing_types', function(array $types): array {
    $dir_id = Directory_Context::get();
    if ($dir_id) {
        $allowed = get_term_meta($dir_id, '_listora_dir_listing_types', true);
        if (!empty($allowed)) {
            return array_filter($types, fn($t) => in_array($t->slug, $allowed, true));
        }
    }
    return $types;
});

// Hook into admin listing table
add_filter('parse_query', function(\WP_Query $query): void {
    if (!is_admin() || $query->get('post_type') !== 'listora_listing') return;

    $dir_id = $_GET['listora_directory'] ?? null;
    if ($dir_id) {
        $query->set('tax_query', [[
            'taxonomy' => 'listora_directory',
            'field'    => 'term_id',
            'terms'    => (int) $dir_id,
        ]]);
    }
});
```

### Per-Directory Settings Override

Settings that can be overridden per directory:

| Setting | Global Default | Per-Directory Override |
|---------|---------------|----------------------|
| `map_provider` | `osm` | Each directory can use different map |
| `map_default_zoom` | `12` | City directory might use 10, neighborhood uses 14 |
| `submission_requires_approval` | `true` | Trusted directories might auto-publish |
| `reviews_enabled` | `true` | Job directory might disable reviews |
| `pagination_type` | `pagination` | Restaurant directory might use infinite scroll |
| `default_sort` | `newest` | Real estate might sort by price |
| `cards_per_page` | `12` | Job board might show 20 per page |
| `card_layout` | `standard` | Each directory can have different card style |

### URL Structure

```
Default (no multi-directory):
  /listings/
  /listing/pizza-palace/

With multi-directory:
  /restaurants/                    (restaurant directory search page)
  /restaurants/listing/pizza-palace/  (listing within restaurant directory)
  /jobs/                           (job directory search page)
  /jobs/listing/web-developer/     (listing within job directory)
  /real-estate/                    (property directory search page)
```

### Directory Assignment

When a listing is created, it's assigned to a directory based on its listing type:

```php
add_action('save_post_listora_listing', function(int $post_id): void {
    $listing_type = get_post_meta($post_id, '_listora_listing_type', true);

    // Find which directory owns this listing type
    $directories = get_terms([
        'taxonomy'   => 'listora_directory',
        'hide_empty' => false,
    ]);

    foreach ($directories as $dir) {
        $types = get_term_meta($dir->term_id, '_listora_dir_listing_types', true);
        if (is_array($types) && in_array($listing_type, $types, true)) {
            wp_set_object_terms($post_id, $dir->term_id, 'listora_directory');
            break;
        }
    }
});
```

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/multi-directory/class-directory-context.php` | Context detection, setting resolution |
| `includes/multi-directory/class-directory-manager.php` | CRUD for directories, type assignment |
| `includes/multi-directory/class-directory-rewrite.php` | URL prefix rewrite rules |
| `includes/multi-directory/class-directory-query-filter.php` | Search/query filtering by context |
| `includes/rest/class-directories-controller.php` | REST endpoints |
| `includes/admin/class-directories-page.php` | Admin directory manager |
| `includes/admin/class-directory-settings.php` | Per-directory settings override UI |

### Files to Modify (wb-listora free)

| File | Change |
|------|--------|
| `includes/core/class-taxonomies.php` | Add `listora_directory` taxonomy registration hook |
| `includes/search/class-search-engine.php` | Add `wb_listora_search_args` filter (if not already present) |
| `includes/core/class-listing-type-registry.php` | Add `wb_listora_available_listing_types` filter |

### API Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `GET` | `/listora/v1/directories` | Public | List all directories |
| `POST` | `/listora/v1/directories` | Admin | Create directory |
| `GET` | `/listora/v1/directories/{id}` | Public | Get directory details |
| `PUT` | `/listora/v1/directories/{id}` | Admin | Update directory |
| `DELETE` | `/listora/v1/directories/{id}` | Admin | Delete directory |
| `GET` | `/listora/v1/directories/{id}/settings` | Admin | Get per-directory settings |
| `PUT` | `/listora/v1/directories/{id}/settings` | Admin | Update per-directory settings |

---

## UI Mockup

### Admin: Directory Manager (Listora > Directories)

```
┌─────────────────────────────────────────────────────────────┐
│ Directories                              [+ Add Directory]  │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 🍽 Restaurant Directory                                │ │
│ │   URL: /restaurants/                                    │ │
│ │   Types: Restaurant, Cafe, Bar                          │ │
│ │   Listings: 234  ·  Active                              │ │
│ │                               [Settings] [Edit] [X]    │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ 💼 Job Board                                           │ │
│ │   URL: /jobs/                                           │ │
│ │   Types: Job                                            │ │
│ │   Listings: 89  ·  Active                               │ │
│ │                               [Settings] [Edit] [X]    │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ 🏠 Real Estate                                         │ │
│ │   URL: /real-estate/                                    │ │
│ │   Types: Real Estate                                    │ │
│ │   Listings: 156  ·  Active                              │ │
│ │                               [Settings] [Edit] [X]    │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ 3 directories · 479 total listings                          │
└─────────────────────────────────────────────────────────────┘
```

### Admin: Create/Edit Directory

```
┌─────────────────────────────────────────────────────────────┐
│ Edit Directory                                              │
│                                                             │
│ Name *                                                      │
│ [ Restaurant Directory                              ]       │
│                                                             │
│ URL Prefix *                                                │
│ [ restaurants   ]  → site.com/restaurants/                  │
│                                                             │
│ Description                                                 │
│ [ Find the best restaurants, cafes, and bars.        ]      │
│                                                             │
│ Icon (Lucide)                                               │
│ [ utensils  ▾ ]                                             │
│                                                             │
│ ── Listing Types ────────────────────────────────────────── │
│                                                             │
│ Which listing types belong to this directory?               │
│ ☑ Restaurant                                               │
│ ☑ Cafe                                                     │
│ ☑ Bar                                                      │
│ ☐ Hotel                                                    │
│ ☐ Real Estate                                              │
│ ☐ Job                                                      │
│ ☐ Event                                                    │
│ ☐ Doctor                                                   │
│ ☐ Salon                                                    │
│ ☐ Gym                                                      │
│                                                             │
│ ── Pages ────────────────────────────────────────────────── │
│                                                             │
│ Search Page:     [ Restaurant Directory ▾ ] [Create New]    │
│ Submission Page: [ Add Restaurant      ▾ ] [Create New]    │
│ Dashboard Page:  [ My Dashboard        ▾ ] (shared)        │
│                                                             │
│                                        [Cancel]  [Save]     │
└─────────────────────────────────────────────────────────────┘
```

### Admin: Per-Directory Settings

```
┌─────────────────────────────────────────────────────────────┐
│ Settings — Restaurant Directory                             │
│                                                             │
│ These settings override the global defaults for this        │
│ directory only. Unchecked settings use global values.       │
│                                                             │
│ ☑ Map Provider                                             │
│   (●) OpenStreetMap  ( ) Google Maps                        │
│                                                             │
│ ☐ Default Zoom (using global: 12)                          │
│                                                             │
│ ☑ Submission Requires Approval                             │
│   (●) Yes  ( ) No (auto-publish)                            │
│                                                             │
│ ☑ Reviews Enabled                                          │
│   (●) Yes  ( ) No                                           │
│                                                             │
│ ☐ Pagination Type (using global: pagination)               │
│                                                             │
│ ☑ Cards Per Page                                           │
│   [ 16 ]                                                    │
│                                                             │
│ ☐ Default Sort (using global: newest)                      │
│                                                             │
│                                        [Cancel]  [Save]     │
└─────────────────────────────────────────────────────────────┘
```

### Frontend: Directory Switcher (Optional)

```
┌─────────────────────────────────────────────────────────────┐
│ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐     │
│ │ 🍽 Restaurants │ │ 💼 Jobs       │ │ 🏠 Real Estate│     │
│ │    234         │ │    89         │ │    156         │     │
│ │  listings      │ │  listings     │ │  listings      │     │
│ └───────────────┘ └───────────────┘ └───────────────┘     │
│                                                             │
│ Click a directory to browse                                 │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Register `listora_directory` taxonomy + term meta | 2 |
| 2 | Build `Directory_Context` class — detection, setting resolution | 4 |
| 3 | URL prefix rewrite rules + flush on directory create/edit | 3 |
| 4 | Query filtering — search, listings, admin, REST | 5 |
| 5 | Auto-assign directory to listings based on type | 2 |
| 6 | Per-directory settings override system | 4 |
| 7 | Admin directory manager page (Pattern B) | 4 |
| 8 | Admin create/edit directory form | 3 |
| 9 | Per-directory settings UI (checkbox to override + value) | 3 |
| 10 | Page auto-creation per directory (search, submit, dashboard) | 2 |
| 11 | Admin listing table — directory filter dropdown | 1 |
| 12 | Frontend directory switcher block (optional) | 3 |
| 13 | REST endpoints for directory CRUD | 3 |
| 14 | Listing type exclusivity validation (type can't be in 2 directories) | 1 |
| 15 | Migration: assign existing listings to "Default" directory | 2 |
| 16 | Automated tests + documentation | 4 |
| **Total** | | **46 hours** |

---

## Migration Strategy

When Pro activates multi-directory on an existing site:

1. Create a "Default" directory with all existing listing types
2. Assign all existing listings to the "Default" directory
3. Existing pages continue working under the default directory
4. Admin can then split types into new directories at their own pace

This ensures zero disruption to existing sites.

---

## Competitive Context

| Competitor | Multi-Directory? | Our Advantage |
|-----------|-----------------|---------------|
| GeoDirectory | No | First affordable multi-directory solution |
| Directorist | "Multi Directory" addon ($49) | Included in Pro, per-directory settings |
| HivePress | No | Full isolation with independent settings |
| ListingPro | No | Taxonomy-based (lightweight, WP-native) |
| MyListing | Limited (theme-level) | Plugin-based, works with any theme |
| JetEngine | Custom taxonomies (manual setup) | Purpose-built, zero-config for common patterns |

**Our edge:** True per-directory setting overrides is unique — most competitors that offer multi-directory treat it as a simple taxonomy filter. Our implementation lets each directory have its own map provider, submission flow, pagination style, and even card layout. The taxonomy-based approach is lightweight (no extra tables, no duplicated data) and leverages WordPress's built-in term system. The auto-assignment of listings to directories based on listing type means zero manual work for common setups.

---

## Effort Estimate

**Total: ~46 hours (6 dev days)**

- Taxonomy + context: 6h
- Rewrite rules: 3h
- Query filtering: 5h
- Settings override: 7h
- Admin UI: 10h
- REST API: 3h
- Frontend switcher: 3h
- Migration: 2h
- Validation: 1h
- Tests + docs: 4h
- QA: 2h
