# WB Listora — CLAUDE.md

## Overview
Complete WordPress directory plugin. Create any type of listing directory — business, restaurant, hotel, real estate, jobs, events, and more.

## Tech Stack
- **PHP:** 7.4+ (WordPress plugin)
- **JS:** @wordpress/scripts, @wordpress/interactivity API
- **Build:** `npm run build` (wp-scripts)
- **CSS:** PostCSS via wp-scripts
- **Database:** 10 custom tables (listora_ prefix)

## Architecture

### Plugin Entry
- `wb-listora.php` — Main file, constants, autoloader, fires `wb_listora_loaded`

### Core (`includes/core/`)
- `class-post-types.php` — `listora_listing` CPT
- `class-taxonomies.php` — `listora_listing_cat`, `listora_listing_type`, `listora_listing_location`, `listora_listing_feature`, `listora_service_cat`
- `class-listing-type-registry.php` — Dynamic listing types (restaurant, hotel, etc.)
- `class-listing-type.php` / `class-listing-type-defaults.php` — Type config + defaults
- `class-field-registry.php` / `class-field.php` / `class-field-group.php` — Custom field system
- `class-meta-handler.php` — Meta storage/retrieval
- `class-capabilities.php` — Custom caps
- `class-services.php` — Services CRUD (listora_services table)

### Admin (`includes/admin/`)
- `class-admin.php` — Admin init, menu
- `class-settings-page.php` — Settings UI with tabs
- `class-listing-columns.php` — Admin columns
- `class-setup-wizard.php` — First-run wizard

### Search (`includes/search/`)
- `class-search-engine.php` — Main search with facets, geo, fulltext
- `class-search-indexer.php` — Builds denormalized search_index
- `class-facets.php` — Faceted search
- `class-geo-query.php` — Haversine distance queries

### REST API (`includes/rest/`)
- `class-listings-controller.php` — CRUD for listings
- `class-reviews-controller.php` — Reviews, helpful votes, replies, reports
- `class-search-controller.php` — Search endpoint
- `class-submission-controller.php` — Frontend submission
- `class-claims-controller.php` — Business claims
- `class-favorites-controller.php` — User favorites
- `class-dashboard-controller.php` — User dashboard data
- `class-listing-types-controller.php` — Type definitions
- `class-settings-controller.php` — Admin settings
- `class-services-controller.php` — Service CRUD endpoints

### Blocks (`blocks/`)
11 blocks using Interactivity API:
- `listing-grid`, `listing-card`, `listing-search`, `listing-map`
- `listing-detail`, `listing-reviews`, `listing-submission`
- `listing-categories`, `listing-featured`, `listing-calendar`
- `user-dashboard`

### Shared Block Infrastructure (`src/shared/`)
- `components/` — 7 editor controls: ResponsiveControl, SpacingControl, TypographyControl, BoxShadowControl, BorderRadiusControl, ColorHoverControl, DeviceVisibility
- `hooks/` — useUniqueId (auto-generate block instance ID), useResponsiveValue (device-aware values)
- `utils/attributes.js` — Standard attribute schemas (spacing, typography, shadow, border, visibility)
- `utils/css.js` — Per-instance CSS generator (responsive media queries)
- `base.css` — Block reset, device visibility classes, reduced motion
- `theme-isolation.css` — Neutralizes aggressive theme styles (BuddyX, Reign, Astra)

### Block Quality Standard
Every block has:
- 20 standard attributes (uniqueId, responsive padding/margin, border radius, box shadow, device visibility)
- apiVersion 3
- InspectorControls with panels: Content, Display, Layout, Style, Advanced
- Per-instance CSS scoping via `Block_CSS::render()`
- All view.js files import shared store for proper dependency chain

### PHP Utilities
- `includes/class-block-css.php` — `WBListora\Block_CSS` — generates per-instance scoped CSS, visibility classes, wrapper classes
- `includes/core/class-lucide-icons.php` — `WBListora\Core\Lucide_Icons` — inline SVG rendering for 21 Lucide icons

### Template Override System
Themes can override templates WooCommerce-style:
- Path: `{theme}/wb-listora/blocks/listing-card/card.php` etc.
- Functions: `wb_listora_get_template()`, `wb_listora_locate_template()`, `wb_listora_get_template_html()`
- Currently used for: email templates, block templates (listing-card, listing-detail, user-dashboard)

### Database Tables (prefix: `listora_`)
`geo`, `search_index`, `field_index`, `reviews`, `review_votes`, `favorites`, `claims`, `hours`, `analytics`, `payments`, `services`

## Key Constants
```php
WB_LISTORA_VERSION        // '1.0.0'
WB_LISTORA_TABLE_PREFIX   // 'listora_'
WB_LISTORA_REST_NAMESPACE // 'listora/v1'
WB_LISTORA_META_PREFIX    // '_listora_'
```

## Key Hooks (for Pro extensibility)
- `wb_listora_loaded` — Plugin fully loaded
- `wb_listora_rest_api_init` — REST routes registered
- `wb_listora_review_criteria` — Filter review criteria fields
- `wb_listora_after_listing_fields` — Action after listing detail fields
- `wb_listora_map_config` — Filter map configuration
- `wb_listora_settings_tabs` / `wb_listora_settings_tab_content` — Settings extensibility
- `wb_listora_listing_submitted` — After frontend submission
- `wb_listora_review_submitted` — After review posted
- `wb_listora_search_args` — Filter search parameters

### Block Render Hooks
- listing-grid: `wb_listora_before_listing_grid`, `wb_listora_grid_query_args`, `wb_listora_grid_after_card`, `wb_listora_after_listing_grid`
- listing-featured: `wb_listora_before_featured_listings`, `wb_listora_featured_query_args`, `wb_listora_after_featured_listings`
- listing-categories: `wb_listora_before_categories_grid`, `wb_listora_category_card_data`, `wb_listora_after_categories_grid`
- listing-calendar: `wb_listora_before_calendar`, `wb_listora_calendar_events`, `wb_listora_after_calendar`
- listing-map: `wb_listora_before_map`, `wb_listora_after_map`

### Write-Operation Hooks (before_ / after_)
All write operations fire a `before_` filter (return WP_Error to abort) and `after_` action:
- `wb_listora_before_create_listing` / `wb_listora_after_create_listing`
- `wb_listora_before_update_listing` / `wb_listora_after_update_listing`
- `wb_listora_before_delete_listing` / `wb_listora_after_delete_listing`
- `wb_listora_before_create_review` / `wb_listora_after_create_review`
- `wb_listora_before_update_review` / `wb_listora_after_update_review`
- `wb_listora_before_delete_review` / `wb_listora_after_delete_review`
- `wb_listora_before_add_favorite` / `wb_listora_after_add_favorite`
- `wb_listora_before_remove_favorite` / `wb_listora_after_remove_favorite`
- `wb_listora_before_submit_claim` / `wb_listora_after_submit_claim`
- `wb_listora_before_update_claim` / `wb_listora_after_update_claim`
- `wb_listora_before_create_service` / `wb_listora_after_create_service`
- `wb_listora_before_update_service` / `wb_listora_after_update_service`
- `wb_listora_before_delete_service` / `wb_listora_after_delete_service`

### REST Response Filters
Every REST response is filterable for Pro/extensions to add fields:
- `wb_listora_rest_prepare_listing` — single listing detail + submission/update response
- `wb_listora_rest_prepare_review` — each review in list + create/update response
- `wb_listora_rest_prepare_favorite` — each favorite in list + add/remove response
- `wb_listora_rest_prepare_claim` — each claim in list + submit/update response
- `wb_listora_rest_prepare_search_result` — search results array
- `wb_listora_rest_prepare_dashboard_stats` — dashboard stats
- `wb_listora_rest_prepare_listing_type` — listing type response
- `wb_listora_rest_prepare_service` — each service in list + create/update response

## Interactivity API
- Single namespace: `listora/directory`
- ALL actions in `src/interactivity/store.js` (NOT in individual view.js files)
- Server state via `wp_interactivity_state()` — do NOT define client defaults for server-provided keys
- View.js files import the shared store to ensure proper load order

## Recent Changes (2026-04-13)

| Area | Change |
|------|--------|
| Blocks | Shared infrastructure: 7 editor controls, 2 hooks, 2 utils, CSS reset |
| Blocks | All 11 blocks: InspectorControls with 5 panels (Content, Display, Layout, Style, Advanced) |
| Blocks | All 11 block.json: 20 standard attributes, apiVersion 3 |
| Blocks | Per-instance CSS scoping via Block_CSS class |
| Icons | Lucide_Icons SVG helper (21 icons), replaced broken dashicons in 5 render.php |
| CSS | Breakpoints standardized (1024px/767px), card tokens unified, icon button token |
| Hooks | 15 new hooks across 5 blocks (grid, featured, categories, calendar, map) |
| Interactivity | Detail view actions merged into main store, server state fix |
| Templates | WooCommerce-style overrides for listing-card, listing-detail, user-dashboard |

## Recent Changes (2026-04-05)

| Area | Change |
|------|--------|
| Services | Listing Services system: listora_services table, Services CRUD class, REST controller |
| Services | listora_service_cat taxonomy for categorizing services |
| Services | Services tab on listing detail page with card grid |
| Services | Manage Services in user dashboard per listing |
| Services | Service text indexed in search_index for full-text search |
| Services | Schema.org OfferCatalog markup for services |
| REST | before_/after_ hooks on all write operations (create/update/delete) |
| REST | REST response filters on all endpoints (wb_listora_rest_prepare_*) |
| REST | Permission callbacks return WP_Error instead of false (401/403) |
| Build | viewScript → viewScriptModule (ES modules for Interactivity API) |
| Build | Dual webpack config (classic IIFE + ESM modules) |
| WP Req | Bumped to WordPress 6.9 |
| CI | GitHub Actions: PHP Lint, WPCS, PHPStan L5, PHPUnit, PCP |
| Import | JSON + GeoJSON importers, 4 competitor migration tools |
| Events | Recurring events, date filters, calendar virtual occurrences |
| Email | All 14 notification templates + draft reminder cron |
| Spam | reCAPTCHA v3 + Cloudflare Turnstile + rate limiting |
| Submission | Guest registration, conditional fields, draggable map pin |
| Demo | 5 type-specific demo packs in setup wizard |
| Admin | Lucide icon picker, onboarding checklist |

## Recent Changes (2026-04-06)

| Area | Change |
|------|--------|
| Tokens | Hardcoded hex → `--listora-*` tokens in card, detail, toast, dashboard |
| Tokens | Added `--listora-warning` + `--listora-premium` to shared.css |
| Architecture | New `Listing_Data` helper class — extracts DB queries from render.php |
| Performance | Dashboard stats cached in 60s transient with cache-busting hooks |
| UX | Categories empty state, review form inline validation on blur |
| UX | Settings Import/Export tab fix (duplicate section ID) |
| Responsive | 480px detail breakpoint, 390px calendar breakpoint |
| Responsive | Featured carousel `min(260px, 80vw)`, dashboard tab scroll hint |
| Admin | Button text visibility fix (scoped selector) |

## Commands
```bash
npm install && npm run build   # Build JS/CSS
```

## Environment
- **Local URL:** http://wb-listora.local
- **WP Root:** /Users/varundubey/Local Sites/wb-listora/app/public/
- **Repository:** wbcomdesigns/wb-listora
- **Basecamp:** https://3.basecamp.com/5798509/buckets/46767283/card_tables/9752604461

## Glossary
- **Listing** -- A single directory entry (business, restaurant, hotel, etc.)
- **Directory** -- The collection of all listings
- **Listing Type** -- A category template (Restaurant, Hotel, Real Estate, etc.) that determines which fields appear
- **Category** -- Taxonomy for organizing listings within a type (e.g., Italian, French under Restaurant)
- **Location** -- Hierarchical geographic taxonomy (Country > State > City)
- **Feature** -- Amenities or attributes (WiFi, Parking, Pet Friendly)
- **Claim** -- A request from a business owner to take ownership of their listing
- **Review** -- A user rating (1-5 stars) with text feedback for a listing
- **Submission** -- The process of a frontend user creating a new listing
- **Dashboard** -- The frontend user panel for managing listings, reviews, and favorites
