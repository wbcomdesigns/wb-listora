# 18 — Admin Settings & Management

## Scope

| | Free | Pro |
|---|---|---|
| Settings page (tabbed) | Yes | Yes + Pro tabs |
| Listing type manager | Yes | Yes |
| Review moderation | Yes | Yes |
| Claim management | Yes | Yes |
| Import/export page | Yes | Yes + advanced |
| Admin columns & filters | Yes | Yes |
| Dashboard widget | Yes | Yes |

---

## Admin Menu Structure

```
Listora
├── Dashboard          → Overview stats, quick actions
├── Listings           → Standard WP post list (CPT)
│   ├── All Listings
│   ├── Add New
│   └── Categories     → listora_listing_cat taxonomy
├── Listing Types      → Custom admin page (type manager)
├── Reviews            → Custom admin page (moderation queue)
├── Claims             → Custom admin page
├── Locations           → listora_listing_location taxonomy
├── Features           → listora_listing_feature taxonomy
├── Import / Export    → Custom admin page
└── Settings           → Tabbed settings page
```

---

## Settings Page (WP Settings API)

### Tab: General
```
┌─────────────────────────────────────────────────────┐
│ [General] [Maps] [Submissions] [Notifications]      │
│ [SEO] [Import/Export] [Advanced]                    │
├─────────────────────────────────────────────────────┤
│                                                     │
│ General Settings                                    │
│                                                     │
│ Listings per page:  [ 20 ]                          │
│ Default listing type: [ Business ▾ ]                │
│ Default sort order: [ Featured ▾ ]                  │
│                                                     │
│ Listing URL slug:   [ listing ]                     │
│ Category URL slug:  [ listing-category ]            │
│                                                     │
│ Permalink Structure:                                │
│ Single listing:  /listing/{slug}/                   │
│ Type archive:    /{type-slug}/  (auto-created pages)│
│ Category:        /listing-category/{slug}/          │
│ Location:        /listing-location/{slug}/          │
│ Tag:             /listing-tag/{slug}/               │
│                                                     │
│ Note: Single listing URL uses the "Listing URL slug"│
│ setting above. Type archives use auto-created pages │
│ with the type's slug. Taxonomy URLs use their       │
│ registered rewrite slugs.                           │
│                                                     │
│ Currency:           [ USD - US Dollar ▾ ]           │
│ Distance unit:      (•) Kilometers  ( ) Miles       │
│                                                     │
│ ☑ Enable listing expiration                        │
│   Default expiration: [ 365 ] days                  │
│                                                     │
│ ☑ Enable listing claiming                          │
│ ☐ Enable guest listing viewing (no login required) │
│                                                     │
│                                      [Save Changes] │
└─────────────────────────────────────────────────────┘
```

### Tab: Maps
```
┌─────────────────────────────────────────────────────┐
│ Map Provider                                        │
│ (•) OpenStreetMap (free, no API key)                │
│ ( ) Google Maps (Pro — requires API key)            │
│                                                     │
│ Default Location                                    │
│ Latitude:   [ 40.7128 ]                             │
│ Longitude:  [ -74.0060 ]                            │
│ Zoom Level: [ 12 ]                                  │
│                                                     │
│ [Preview Map]                                       │
│ ┌─────────────────────────────────────────────┐     │
│ │              [Map Preview]                  │     │
│ └─────────────────────────────────────────────┘     │
│                                                     │
│ Marker Clustering: ☑ Enabled                       │
│ Search on map drag: ☑ Enabled                      │
│ Max markers per page: [ 500 ]                       │
│                                                     │
│ Pro: Google Maps API Key                            │
│ [ AIza... ] [Verify Key]                            │
│                                                     │
│                                      [Save Changes] │
└─────────────────────────────────────────────────────┘
```

### Tab: Submissions
```
┌─────────────────────────────────────────────────────┐
│ Frontend Submissions                                │
│                                                     │
│ ☑ Enable frontend listing submission                │
│                                                     │
│ Moderation:                                         │
│ (•) Require admin approval                          │
│ ( ) Auto-approve all submissions                    │
│ ( ) Auto-approve for verified users                 │
│                                                     │
│ Required fields for all types:                      │
│ ☑ Title  ☑ Description  ☑ Featured Image          │
│ ☑ Address  ☐ Phone  ☐ Email                       │
│                                                     │
│ Media uploads:                                      │
│ Max file size:     [ 5 ] MB                         │
│ Max gallery images: [ 20 ]                          │
│ Allowed file types: ☑ JPG ☑ PNG ☑ WebP ☐ GIF    │
│                                                     │
│ Submission page:   [ Add Listing ▾ ]                │
│ Dashboard page:    [ My Dashboard ▾ ]               │
│                                                     │
│ After submission:                                   │
│ (•) Redirect to dashboard                           │
│ ( ) Show confirmation message                       │
│ ( ) Redirect to listing (if auto-approved)          │
│                                                     │
│                                      [Save Changes] │
└─────────────────────────────────────────────────────┘
```

### Tab: Notifications
See `16-email-notifications.md` for full settings.

### Tab: SEO
```
┌─────────────────────────────────────────────────────┐
│ SEO Settings                                        │
│                                                     │
│ ☑ Enable Schema.org structured data                │
│ ☑ Enable breadcrumbs (JSON-LD)                     │
│ ☑ Add listings to WordPress sitemap                │
│                                                     │
│ Schema output:                                      │
│ (•) JSON-LD in <head> (recommended)                 │
│ ( ) Microdata in HTML                               │
│                                                     │
│ Open Graph:                                         │
│ ☑ Add Open Graph meta tags to listings             │
│ ☑ Add Twitter Card meta tags                       │
│                                                     │
│                                      [Save Changes] │
└─────────────────────────────────────────────────────┘
```

### Tab: Advanced
```
┌─────────────────────────────────────────────────────┐
│ Advanced Settings                                   │
│                                                     │
│ Data Management:                                    │
│ ☐ Delete all data on plugin uninstall              │
│   ⚠ This will remove all listings, reviews,        │
│     settings, and custom tables permanently.        │
│                                                     │
│ Performance:                                        │
│ Search cache TTL: [ 15 ] minutes                    │
│ Facet cache TTL:  [ 30 ] minutes                    │
│                                                     │
│ Maintenance:                                        │
│ [Rebuild Search Index]  Last run: Mar 15, 2026     │
│ [Clear All Caches]                                  │
│ [Remove Demo Content]                               │
│                                                     │
│ Debug:                                              │
│ ☐ Enable debug logging                             │
│                                                     │
│ Flush Permalinks: [Flush Now]                       │
│ (Run this after changing URL slugs)                 │
│                                                     │
│ [Run Setup Wizard Again]                            │
│                                                     │
│                                      [Save Changes] │
└─────────────────────────────────────────────────────┘
```

---

## Settings Storage

Single option: `wb_listora_settings`

```php
$defaults = [
    'per_page'            => 20,
    'default_type'        => 'business',
    'default_sort'        => 'featured',
    'listing_slug'        => 'listing',
    'category_slug'       => 'listing-category',
    'currency'            => 'USD',
    'distance_unit'       => 'km',
    'enable_expiration'   => true,
    'default_expiration'  => 365,
    'enable_claiming'     => true,
    'map_provider'        => 'osm',
    'map_default_lat'     => 40.7128,
    'map_default_lng'     => -74.0060,
    'map_default_zoom'    => 12,
    'map_clustering'      => true,
    'map_search_on_drag'  => true,
    'map_max_markers'     => 500,
    'google_maps_key'     => '',
    'enable_submission'   => true,
    'moderation'          => 'manual',
    'max_upload_size'     => 5,
    'max_gallery_images'  => 20,
    'submission_page'     => 0,
    'dashboard_page'      => 0,
    'enable_schema'       => true,
    'enable_breadcrumbs'  => true,
    'enable_sitemap'      => true,
    'enable_opengraph'    => true,
    'delete_on_uninstall' => false,
    'search_cache_ttl'    => 15,
    'facet_cache_ttl'     => 30,
    'debug_logging'       => false,
    'setup_complete'      => false,
];
```

Accessed via helper: `wb_listora_get_setting('map_provider', 'osm')`

---

## Admin Dashboard Widget

```
┌─────────────────────────────────────────┐
│ WB Listora Overview                     │
│                                         │
│ Listings:  245 published, 12 pending    │
│ Reviews:   89 total, 3 pending          │
│ Claims:    2 pending                    │
│ Favorites: 456 total                    │
│                                         │
│ Index: ✅ Synced (245/245)              │
│ Last reindex: 2 hours ago              │
│                                         │
│ [View All] [Add Listing] [Settings]     │
└─────────────────────────────────────────┘
```

---

## Admin Columns for Listings

| Column | Content |
|--------|---------|
| Featured Image | Thumbnail |
| Title | Post title |
| Listing Type | Type badge |
| Category | Category name(s) |
| Location | City, State |
| Rating | Stars + count |
| Status | Published / Pending / Expired |
| Author | User |
| Date | Publish date |

### Admin Filters
Dropdown filters above the list table:
- By listing type
- By category
- By status
- By date range

---

## Listing Edit Screen (Admin)

Standard WP editor with custom metaboxes:

```
┌─────────────────────────────────────────────────────┐
│ Title: [ Pizza Palace ]                             │
│                                                     │
│ ┌───────────────────────────────────────────────┐   │
│ │ Content editor (block editor)                 │   │
│ │                                               │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ ┌─ Listing Type ─────────────────────────────────┐  │
│ │ [ Restaurant ▾ ] ← changing this refreshes    │  │
│ │                    fields via AJAX             │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ ┌─ Restaurant Fields ────────────────────────────┐  │
│ │ Phone: [ (212) 555-0123 ]                      │  │
│ │ Cuisine: ☑ Italian ☐ Chinese                  │  │
│ │ Price: [$$$]                                   │  │
│ │ ...                                            │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ ┌─ Location ─────────────────────────────────────┐  │
│ │ Address: [123 Main St]                         │  │
│ │ [Map]                                          │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ ┌─ Categories (scoped to Restaurant) ────────────┐  │
│ │ ☑ Italian  ☐ Chinese  ☐ Japanese              │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ ┌─ Features ─────────────────────────────────────┐  │
│ │ ☑ WiFi  ☑ Parking  ☐ Pool                    │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ Sidebar: Publish, Featured Image, Tags, Status     │
└─────────────────────────────────────────────────────┘
```

**Dynamic type switching:** When admin changes listing type dropdown, metabox fields refresh via AJAX to show the new type's fields. Existing field values preserved if field key exists in both types.
