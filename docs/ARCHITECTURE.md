# WB Listora Architecture

## Overview

WB Listora is a complete WordPress directory plugin that enables any type of listing directory -- business, restaurant, hotel, real estate, jobs, events, and more. It uses a custom post type with 10 custom database tables, 11 Gutenberg blocks powered by the Interactivity API, and a REST-first architecture for all data operations.

- **Version:** 1.0.0
- **Requires:** WordPress 6.9+, PHP 7.4+
- **Namespace:** `WBListora`
- **Text Domain:** `wb-listora`

## Directory Structure

```
wb-listora/
├── wb-listora.php              # Entry point: constants, autoloader, requirement checks
├── uninstall.php               # Clean removal of all plugin data
├── CLAUDE.md                   # AI-assistant context file
│
├── includes/
│   ├── class-plugin.php        # Main orchestrator: hooks, init, REST registration
│   ├── class-activator.php     # Activation: table creation, defaults, capabilities
│   ├── class-deactivator.php   # Deactivation cleanup
│   ├── class-assets.php        # Enqueue scripts and styles
│   ├── class-captcha.php       # reCAPTCHA v3 + Cloudflare Turnstile
│   ├── class-cli-commands.php  # WP-CLI commands
│   ├── class-template-helpers.php  # Template loading utilities
│   │
│   ├── core/                   # Domain model
│   │   ├── class-post-types.php           # listora_listing CPT
│   │   ├── class-taxonomies.php           # 4 taxonomies
│   │   ├── class-listing-type-registry.php # Dynamic listing types
│   │   ├── class-listing-type.php         # Type config object
│   │   ├── class-listing-type-defaults.php # Built-in type definitions
│   │   ├── class-field-registry.php       # Custom field type system
│   │   ├── class-field.php                # Field definition
│   │   ├── class-field-group.php          # Field grouping
│   │   ├── class-meta-handler.php         # Meta storage/retrieval
│   │   ├── class-capabilities.php         # Custom capabilities
│   │   └── class-recurrence.php           # Recurring events logic
│   │
│   ├── search/                 # Search engine
│   │   ├── class-search-engine.php   # Main search: fulltext, facets, geo
│   │   ├── class-search-indexer.php  # Denormalized index builder
│   │   ├── class-facets.php          # Faceted search computation
│   │   └── class-geo-query.php       # Haversine distance queries
│   │
│   ├── rest/                   # REST API controllers (9 controllers, 36 endpoints)
│   │   ├── class-listings-controller.php      # CRUD + detail + related
│   │   ├── class-search-controller.php        # Search + autocomplete
│   │   ├── class-reviews-controller.php       # Reviews, votes, replies, reports
│   │   ├── class-submission-controller.php    # Frontend submission (create + edit)
│   │   ├── class-claims-controller.php        # Business claim workflow
│   │   ├── class-favorites-controller.php     # User bookmarks
│   │   ├── class-dashboard-controller.php     # User dashboard data
│   │   ├── class-listing-types-controller.php # Type definitions CRUD
│   │   ├── class-settings-controller.php      # Admin settings
│   │   └── class-import-export-controller.php # CSV/JSON/GeoJSON import/export
│   │
│   ├── admin/                  # Admin UI
│   │   ├── class-admin.php            # Admin init, menu registration
│   │   ├── class-settings-page.php    # Settings page with tabs
│   │   ├── class-listing-columns.php  # Custom admin list columns
│   │   ├── class-setup-wizard.php     # First-run setup wizard
│   │   ├── class-taxonomy-fields.php  # Custom taxonomy fields
│   │   └── class-type-editor.php      # Listing type editor
│   │
│   ├── workflow/               # Background processes
│   │   ├── class-expiration-cron.php  # Expiry checks + draft reminders + analytics pruning
│   │   ├── class-notifications.php    # 14 email notification templates
│   │   └── class-status-manager.php   # Listing status transitions
│   │
│   ├── schema/                 # Structured data
│   │   └── class-schema-generator.php # JSON-LD schema output
│   │
│   ├── db/                     # Database migrations
│   │   └── class-migrator.php         # Schema version management
│   │
│   └── import-export/          # Data import/export
│       ├── class-migration-base.php       # Abstract migrator base
│       ├── class-csv-exporter.php         # CSV export
│       ├── class-csv-importer.php         # CSV import
│       ├── class-json-importer.php        # JSON import
│       ├── class-geojson-importer.php     # GeoJSON import
│       ├── class-directorist-migrator.php # Directorist migration
│       ├── class-geodirectory-migrator.php # GeoDirectory migration
│       ├── class-bdp-migrator.php         # Business Directory Plugin migration
│       └── class-listingpro-migrator.php  # ListingPro migration
│
├── blocks/                     # 11 Gutenberg blocks (Interactivity API)
│   ├── listing-grid/           # Grid/list display
│   ├── listing-card/           # Individual card component
│   ├── listing-search/         # Search form with filters
│   ├── listing-map/            # Interactive map
│   ├── listing-detail/         # Single listing view
│   ├── listing-reviews/        # Reviews display + form
│   ├── listing-submission/     # Frontend submission form
│   ├── listing-categories/     # Category browsing
│   ├── listing-featured/       # Featured listings carousel
│   ├── listing-calendar/       # Event calendar view
│   └── user-dashboard/         # User dashboard panel
│
├── src/                        # JS/CSS source (compiled by wp-scripts)
├── build/                      # Compiled output
├── templates/                  # PHP templates
├── assets/                     # Static assets
├── demo/                       # Demo content packs (5 type-specific)
├── languages/                  # Translation files
├── tests/                      # PHPUnit tests
├── vendor/                     # Composer dependencies
└── docs/                       # Documentation
```

## Request Lifecycle

### Search Request Flow

```
User interacts with listing-search block
    │
    ▼
Interactivity API store dispatches action
    │
    ▼
wp.apiFetch → GET /wp-json/listora/v1/search?keyword=...&type=...&lat=...
    │
    ▼
Search_Controller::search()
    ├── Extracts and validates args from WP_REST_Request
    ├── Applies wb_listora_search_args filter
    │
    ▼
Search_Engine::search($args)
    ├── Builds SQL from denormalized search_index table
    ├── Adds FULLTEXT matching for keyword search
    ├── Joins geo table for distance queries (Haversine formula)
    ├── Joins field_index for custom field facets
    ├── Applies sorting (featured, rating, distance, price, etc.)
    ├── Computes facet counts if requested
    │
    ▼
Returns { listing_ids[], total, pages, facets, distances }
    │
    ▼
Search_Controller::hydrate_listings()
    ├── Batch-loads posts via get_posts(post__in)
    ├── Primes meta cache (update_meta_cache)
    ├── Primes term cache (update_object_term_cache)
    ├── Batch-loads ratings from search_index
    ├── Applies wb_listora_rest_listing_response filter per listing
    │
    ▼
Returns JSON response with listings[], total, pages, has_more, facets
    │
    ▼
Interactivity API store updates → blocks re-render
```

### Submission Request Flow

```
User fills listing-submission block form
    │
    ▼
wp.apiFetch → POST /wp-json/listora/v1/submit
    │
    ▼
Submission_Controller::submit_listing()
    ├── Honeypot check
    ├── Nonce verification (if present)
    ├── Rate limiting (3/user/hr, 5/IP/hr)
    ├── CAPTCHA verification (reCAPTCHA v3 or Turnstile)
    ├── Guest registration (if enabled)
    ├── Duplicate detection (title similarity + geo proximity)
    ├── wb_listora_before_create_listing filter
    │
    ▼
Database Transaction (START TRANSACTION)
    ├── wp_insert_post()
    ├── wp_set_object_terms() — type, category, tags
    ├── set_post_thumbnail()
    ├── Meta_Handler::set_value() — gallery, video, custom fields
    ├── save_meta_fields() — type-specific fields
    └── COMMIT (or ROLLBACK on failure)
    │
    ▼
wb_listora_listing_submitted action
    │
    ▼
Returns { id, status, url, message }
```

## Data Layer

### Custom Tables (prefix: `{wp_prefix}listora_`)

All tables use `ENGINE=InnoDB` for transaction support.

| Table | Purpose | Primary Key |
|---|---|---|
| `geo` | Geolocation data (lat, lng, address components, geohash) | `listing_id` |
| `search_index` | Denormalized search index (title, content, rating, geo, flags) | `listing_id` |
| `field_index` | Custom field values for faceted filtering | `(listing_id, field_key, field_value)` |
| `reviews` | User reviews with ratings and moderation status | `id` (auto) |
| `review_votes` | Helpful vote tracking | `(user_id, review_id)` |
| `favorites` | User bookmarks with collections | `(user_id, listing_id)` |
| `claims` | Business ownership claim requests | `id` (auto) |
| `hours` | Business operating hours per day | `(listing_id, day_of_week)` |
| `analytics` | View/click/impression events by date (Pro) | `id` (auto) |
| `payments` | Payment transactions and subscriptions (Pro) | `id` (auto) |

### Key Indexes

- `search_index.idx_search` -- FULLTEXT on `(title, content_text, meta_text)`
- `geo.idx_lat_lng` -- Composite for bounding box queries
- `geo.idx_geohash` -- Geohash prefix queries
- `search_index.idx_featured_rating` -- Featured listings sorted by rating
- `field_index.idx_type_field` -- Type-scoped field value lookups

### Custom Post Type

- **Name:** `listora_listing`
- **Taxonomies:** `listora_listing_cat`, `listora_listing_type`, `listora_listing_location`, `listora_listing_feature`, `listora_listing_tag`

### Meta Prefix

All listing meta keys use the `_listora_` prefix, managed via `Meta_Handler`.

## REST API Map

All endpoints live under the `listora/v1` namespace.

### Listings (extends WP_REST_Posts_Controller)

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/listings` | Public | List listings (inherited CRUD) |
| POST | `/listings` | Admin | Create listing |
| GET | `/listings/{id}` | Public | Get single listing |
| PUT | `/listings/{id}` | Admin | Update listing |
| DELETE | `/listings/{id}` | Owner/Admin | Soft-delete (trash) |
| GET | `/listings/{id}/detail` | Public | Enriched single for apps |
| GET | `/listings/{id}/related` | Public | Related listings |

### Search

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/search` | Public | Full search with facets, geo, filters |
| GET | `/search/suggest` | Public | Autocomplete suggestions |

### Reviews

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/listings/{id}/reviews` | Public | Paginated reviews for listing |
| POST | `/listings/{id}/reviews` | Logged in | Create review |
| PUT | `/reviews/{id}` | Owner/Mod | Update review |
| DELETE | `/reviews/{id}` | Owner/Mod | Delete review |
| POST | `/reviews/{id}/helpful` | Logged in | Vote helpful |
| POST | `/reviews/{id}/reply` | Listing owner | Owner reply |
| POST | `/reviews/{id}/report` | Logged in | Report review |

### Submission

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/submit` | User/Guest | Create listing from frontend |
| POST | `/submit/check-duplicate` | Logged in | Check for duplicate listings |
| PUT | `/submit/{id}` | Owner | Edit own listing |

### Claims

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/claims` | Admin | List all claims |
| POST | `/claims` | Logged in | Submit a claim |
| PUT | `/claims/{id}` | Admin | Approve/reject claim |

### Favorites

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/favorites` | Logged in | User's favorites |
| POST | `/favorites` | Logged in | Add favorite |
| DELETE | `/favorites/{id}` | Logged in | Remove favorite |

### Dashboard

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/dashboard/stats` | Logged in | Summary counts |
| GET | `/dashboard/listings` | Logged in | User's listings |
| GET | `/dashboard/reviews` | Logged in | Written + received reviews |
| GET | `/dashboard/claims` | Logged in | User's claim submissions |
| GET | `/dashboard/profile` | Logged in | User profile data |
| PUT | `/dashboard/profile` | Logged in | Update profile |
| GET | `/dashboard/notifications` | Logged in | Notification feed |
| PUT | `/dashboard/notifications/read` | Logged in | Mark notifications read |

### Listing Types

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/listing-types` | Public | All types |
| POST | `/listing-types` | Admin | Create type |
| GET | `/listing-types/{slug}` | Public | Single type |
| PUT | `/listing-types/{slug}` | Admin | Update type |
| DELETE | `/listing-types/{slug}` | Admin | Delete type |
| GET | `/listing-types/{slug}/fields` | Public | Fields for type |
| GET | `/listing-types/{slug}/categories` | Public | Categories for type |

### Settings

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/settings` | Admin | All settings |
| PUT | `/settings` | Admin | Update settings |
| DELETE | `/settings` | Admin | Reset to defaults |
| GET | `/settings/maps` | Public | Map provider config |
| GET | `/settings/export` | Admin | Export settings JSON |
| POST | `/settings/import` | Admin | Import settings JSON |

### Import/Export

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/export/csv` | Admin | Export listings as CSV |
| POST | `/import/csv` | Admin | Import from CSV |
| POST | `/import/json` | Admin | Import from JSON |
| POST | `/import/geojson` | Admin | Import from GeoJSON |

## Hook Reference

### Actions

| Hook | File | Description |
|---|---|---|
| `wb_listora_loaded` | `class-plugin.php` | Plugin fully loaded |
| `wb_listora_rest_api_init` | `class-plugin.php` | REST routes registered |
| `wb_listora_listing_submitted` | `class-submission-controller.php` | After frontend submission |
| `wb_listora_after_create_listing` | `class-submission-controller.php` | After listing created via form |
| `wb_listora_listing_updated` | `class-submission-controller.php` | After listing edited via frontend |
| `wb_listora_listing_trashed` | `class-listings-controller.php` | After listing soft-deleted |
| `wb_listora_listing_indexed` | `class-search-indexer.php` | After search index updated |
| `wb_listora_listing_status_changed` | `class-search-indexer.php` | Post status transition |
| `wb_listora_listing_{$status}` | `class-status-manager.php` | Dynamic status change |
| `wb_listora_listing_expiring` | `class-expiration-cron.php` | Listing expiring soon (7d or 1d) |
| `wb_listora_listing_expired` | `class-expiration-cron.php` | Listing has expired |
| `wb_listora_draft_reminder` | `class-expiration-cron.php` | Draft listing abandoned 48h+ |
| `wb_listora_review_submitted` | `class-reviews-controller.php` | After review posted |
| `wb_listora_review_helpful_milestone` | `class-reviews-controller.php` | Review hits vote milestone |
| `wb_listora_review_reply` | `class-reviews-controller.php` | Owner replied to review |
| `wb_listora_claim_submitted` | `class-claims-controller.php` | After claim filed |
| `wb_listora_claim_approved` | `class-claims-controller.php` | Claim approved |
| `wb_listora_claim_rejected` | `class-claims-controller.php` | Claim rejected |
| `wb_listora_favorite_added` | `class-favorites-controller.php` | Listing favorited |
| `wb_listora_favorite_removed` | `class-favorites-controller.php` | Listing unfavorited |
| `wb_listora_submission_captcha` | `class-captcha.php` | CAPTCHA field rendered |
| `wb_listora_register_field_types` | `class-field-registry.php` | Register custom field types |
| `wb_listora_register_listing_types` | `class-listing-type-registry.php` | Register listing types |
| `wb_listora_settings_tab_content` | `class-settings-page.php` | Render custom settings tab |
| `wb_listora_before_template` | `class-template-helpers.php` | Before template render |
| `wb_listora_after_template` | `class-template-helpers.php` | After template render |

### Filters

| Filter | File | Description |
|---|---|---|
| `wb_listora_search_args` | `class-search-controller.php` | Modify search parameters |
| `wb_listora_search_results` | `class-search-controller.php` | Modify search response |
| `wb_listora_rest_listing_response` | `class-search-controller.php` | Modify individual listing data |
| `wb_listora_before_create_listing` | `class-submission-controller.php` | Gate listing creation |
| `wb_listora_before_update_listing` | `class-submission-controller.php` | Gate listing update |
| `wb_listora_rest_prepare_listing` | `class-submission-controller.php` | Filter submission response |
| `wb_listora_before_add_favorite` | `class-favorites-controller.php` | Gate favorite creation |
| `wb_listora_before_submit_claim` | `class-claims-controller.php` | Gate claim submission |
| `wb_listora_expired_listing_notice` | `class-plugin.php` | Modify expired listing notice |
| `wb_listora_settings_nav_groups` | `class-settings-page.php` | Modify settings navigation |
| `wb_listora_settings_tabs` | `class-settings-page.php` | Add/modify settings tabs |
| `wb_listora_review_criteria` | documented in CLAUDE.md | Filter review criteria fields |
| `wb_listora_map_config` | documented in CLAUDE.md | Filter map configuration |
| `wb_listora_send_notification` | `class-notifications.php` | Gate email notifications |
| `wb_listora_email_subject` | `class-notifications.php` | Filter email subject |
| `wb_listora_email_content` | `class-notifications.php` | Filter email body |
| `wb_listora_notification_recipients` | `class-notifications.php` | Filter email recipients |
| `wb_listora_email_headers` | `class-notifications.php` | Filter email headers |
| `wb_listora_schema_data` | `class-schema-generator.php` | Modify JSON-LD schema |
| `wb_listora_locate_template` | `class-template-helpers.php` | Override template path |
| `wb_listora_template_args` | `class-template-helpers.php` | Modify template variables |
| `wb_listora_placeholder_url` | `class-template-helpers.php` | Modify placeholder image URL |
| `wb_listora_field_types` | `class-field-registry.php` | Register field types |
| `wb_listora_field_sanitize_callbacks` | `class-field.php` | Add sanitize callbacks |
| `wb_listora_analytics_retention_days` | `class-expiration-cron.php` | Analytics pruning period (default 90) |

## Block Architecture

All 11 blocks use the WordPress Interactivity API with `viewScriptModule` (ES modules). They share a common store namespace: `listora/directory`.

| Block | Slug | Purpose |
|---|---|---|
| Listing Grid | `listing-grid` | Grid/list display of search results |
| Listing Card | `listing-card` | Individual listing card component |
| Listing Search | `listing-search` | Search form with dynamic filters |
| Listing Map | `listing-map` | Interactive map with markers |
| Listing Detail | `listing-detail` | Single listing full view |
| Listing Reviews | `listing-reviews` | Reviews display and submission form |
| Listing Submission | `listing-submission` | Frontend listing creation form |
| Listing Categories | `listing-categories` | Category browsing grid |
| Listing Featured | `listing-featured` | Featured listings carousel |
| Listing Calendar | `listing-calendar` | Event calendar with date filters |
| User Dashboard | `user-dashboard` | User panel for managing listings |

### Build System

- **Tool:** `@wordpress/scripts` via webpack
- **Config:** Dual webpack config -- classic IIFE bundles + ESM modules for Interactivity API
- **Build:** `npm run build`
- **Dev:** `npm run start`

## Cron Events

| Event Hook | Schedule | Handler |
|---|---|---|
| `wb_listora_check_expirations` | Twice daily | Warn expiring (7d, 1d) + expire listings |
| `wb_listora_draft_reminder_cron` | Twice daily | Email draft listing reminders (48h+) |
| `wb_listora_daily_cleanup` | Daily | Prune analytics records older than 90 days |

## Migration System

Supports importing from 4 competitor plugins via the abstract `Migration_Base` class:

- **Directorist** -- `Directorist_Migrator`
- **GeoDirectory** -- `Geodirectory_Migrator`
- **Business Directory Plugin** -- `BDP_Migrator`
- **ListingPro** -- `Listingpro_Migrator`

Also supports bulk import from CSV, JSON, and GeoJSON files.

Migrations process in batches of 50 with transaction support -- each batch is committed atomically. Failed batches are rolled back to prevent partial data.
