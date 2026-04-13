# WB Listora — Architecture Foundation (5-Year Solid)

## Philosophy

> Build the foundation so well that for the next 5 years you only ADD to it, never REWRITE it.

With AI, any feature is 1-2 days of work. What takes years to fix is a bad foundation. This document defines the architectural decisions that must be right from day one.

---

## Core Principles

1. **WordPress is the backend layer, REST API is the product**
2. **One listing = one physical address** — no multi-location hacks
3. **Every feature is API-first** — UI is just one consumer
4. **Stateless** — no server sessions, all state from API responses
5. **Extensible** — hooks, filters, template overrides on everything
6. **Performance** — custom tables, denormalized indexes, zero WP_Query abuse for search

---

## Data Architecture (Must Not Change)

### Custom Tables

| Table | Purpose | Why Custom Table |
|-------|---------|-----------------|
| `listora_geo` | lat/lng, address, city, state, country, geohash | Haversine queries, spatial indexing |
| `listora_search_index` | denormalized listing data for fulltext + faceted search | Performance — single-table search, no JOINs |
| `listora_field_index` | type-specific field values | Dynamic schemas per listing type |
| `listora_reviews` | ratings, criteria scores, helpful counts | Aggregate queries, sort by rating |
| `listora_review_votes` | per-user helpful votes | Prevent duplicate votes |
| `listora_favorites` | user bookmarks | Simple lookup, no post meta bloat |
| `listora_claims` | ownership claims with proof | Workflow state machine |
| `listora_hours` | structured business hours | JSON-in-meta is unqueryable |
| `listora_analytics` | views, clicks, impressions | High-volume writes, prunable |
| `listora_payments` | transaction records | Financial audit trail (managed by wbcom-credits-sdk) |
| `listora_services` | service catalogue per listing | Schema.org OfferCatalog |

**Rule:** Post meta is for per-listing config (expiration date, featured flag). Custom tables are for anything queried across listings or high-volume.

### Taxonomies

| Taxonomy | Hierarchical | Purpose |
|----------|-------------|---------|
| `listora_listing_type` | No | Directory vertical (restaurant, hotel, etc.) |
| `listora_listing_cat` | Yes | Business categories within a type |
| `listora_listing_location` | Yes | Country > State > City hierarchy |
| `listora_listing_feature` | No | Amenities/features (WiFi, Parking, etc.) |
| `listora_listing_tag` | No | Free-form tags |
| `listora_service_cat` | No | Service categories |

**Location data flow (auto-sync on every save):**
```
Listing saved → address geocoded → wp_listora_geo row upserted
                                 → listora_listing_location terms auto-created & assigned
                                 → search_index updated
```

### Meta Conventions

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_listora_{field_key}` | varies | Type-specific field values |
| `_listora_is_featured` | bool | Featured flag |
| `_listora_is_verified` | bool | Verification badge |
| `_listora_is_claimed` | bool | Claimed by owner |
| `_listora_expiration_date` | datetime | Auto-expire |
| `_listora_demo_content` | bool | Deletable demo data |

---

## REST API Contract (Must Not Break)

### Namespace: `listora/v1`

Every endpoint follows:
- `GET` reads, `POST` creates, `PATCH` updates, `DELETE` deletes
- `permission_callback` returns `WP_Error` with proper status code (401/403)
- Response shape is filterable via `wb_listora_rest_prepare_{resource}`
- Pagination: `page`, `per_page`, response includes `X-WP-Total`, `X-WP-TotalPages`
- `has_more = (offset + count) < total`
- Error responses: `{ code, message, data: { status } }`

### Endpoint Registry

```
/listora/v1/
  # Listings
  listings/                    GET (list), POST (create)
  listings/{id}                GET, PATCH, DELETE
  listings/{id}/media          GET, POST, DELETE

  # Search
  search/                      GET (faceted + geo + fulltext)
  search/suggestions           GET (autocomplete: listings + locations)

  # Reviews
  reviews/                     GET (for a listing), POST (create)
  reviews/{id}                 GET, PATCH, DELETE
  reviews/{id}/helpful         POST
  reviews/{id}/reply           POST
  reviews/{id}/report          POST

  # Favorites
  favorites/                   GET (user's), POST (add), DELETE (remove)

  # Claims
  claims/                      GET, POST
  claims/{id}                  GET, PATCH (approve/reject)

  # Submission
  submission/                  POST (frontend 5-step form)

  # User Dashboard
  dashboard/                   GET (stats)
  dashboard/listings           GET (user's listings)
  dashboard/reviews            GET (reviews on user's listings)
  dashboard/favorites          GET (user's favorites)

  # Listing Types
  listing-types/               GET
  listing-types/{slug}         GET (includes field definitions)

  # Settings (admin)
  settings/                    GET, PUT, DELETE (reset)
  settings/export              GET
  settings/import              POST

  # Import
  export/csv                   GET
  import/csv                   POST
  migrations/{slug}/start      POST
```

### Pro Endpoints: `listora-pro/v1`

```
  credits/balance              GET
  credits/history              GET
  credits/purchase             POST
  credits/add                  POST (admin)
  credits/packs                GET
  webhook/                     POST (payment callback)
  analytics/{id}               GET (listing stats)
  analytics/overview           GET (directory-wide)
  needs/                       GET, POST
  needs/{id}                   GET, PATCH, DELETE
  saved-searches/              GET, POST, DELETE
  messages/                    GET, POST (future)
  messages/{id}                GET
  messages/{id}/reply          POST (future)
```

### Authentication Methods

| Method | When | Consumer |
|--------|------|----------|
| Cookie/Nonce | WordPress admin + frontend | Browser |
| Application Password | REST API external access | Mobile app, integrations |
| JWT | Token-based auth | Mobile app, SPA |
| OAuth 2.0 | Social login | Google, Facebook, Apple |
| API Key | Third-party read access | Zapier, Make, n8n |

---

## Hook Architecture (Must Not Change)

### Write Operations — `before_` + `after_` Pattern

Every write operation fires:
- `wb_listora_before_{action}` — `apply_filters`, return `WP_Error` to abort
- `wb_listora_after_{action}` — `do_action`, for side effects

```
before/after_create_listing
before/after_update_listing
before/after_delete_listing
before/after_create_review
before/after_update_review
before/after_delete_review
before/after_add_favorite
before/after_remove_favorite
before/after_submit_claim
before/after_update_claim
before/after_create_service
before/after_update_service
before/after_delete_service
```

### REST Response Filters

Every REST response is filterable:
```
wb_listora_rest_prepare_listing
wb_listora_rest_prepare_review
wb_listora_rest_prepare_favorite
wb_listora_rest_prepare_claim
wb_listora_rest_prepare_search_result
wb_listora_rest_prepare_dashboard_stats
wb_listora_rest_prepare_listing_type
wb_listora_rest_prepare_service
```

### Block Render Hooks

```
wb_listora_before_listing_grid / wb_listora_after_listing_grid
wb_listora_before_featured_listings / wb_listora_after_featured_listings
wb_listora_before_categories_grid / wb_listora_after_categories_grid
wb_listora_before_calendar / wb_listora_after_calendar
wb_listora_before_map / wb_listora_after_map
wb_listora_before_reviews / wb_listora_after_reviews
```

### Query Filters

```
wb_listora_grid_query_args
wb_listora_featured_query_args
wb_listora_search_args
wb_listora_review_criteria
wb_listora_map_config
wb_listora_settings_tabs
wb_listora_settings_nav_groups
wb_listora_settings_skip_form_tabs
```

---

## Template Override System (Must Not Change)

```
Plugin path:  templates/blocks/{block-name}/{template}.php
Theme path:   {theme}/wb-listora/blocks/{block-name}/{template}.php
Email path:   templates/emails/{template}.php
```

Functions:
- `wb_listora_get_template( $name, $args )` — include with extract
- `wb_listora_get_template_html( $name, $args )` — return HTML string
- `wb_listora_locate_template( $name )` — theme-first lookup

All 14 blocks (11 free + 3 pro) are wrapped. Templates receive `$view_data` array, do zero DB queries.

---

## Frontend Architecture (Must Not Change)

### CSS Token System

All values via `--listora-*` custom properties:
- Typography: `--listora-text-xs` through `--listora-text-3xl`
- Colors: `--listora-text`, `--listora-primary`, `--listora-success`, etc.
- Spacing: `--listora-gap-xs` through `--listora-gap-3xl`
- Elevation: `--listora-shadow-xs` through `--listora-shadow-xl`
- Radius: `--listora-radius-sm` through `--listora-radius-full`
- Animation: `--listora-transition-fast`, `--listora-transition-base`, `--listora-transition-slow`

Themes override by setting `--listora-*` in theme.json or CSS. Plugin never uses hardcoded values.

### Interactivity API

- Single namespace: `listora/directory`
- Shared store: `src/interactivity/store.js`
- Per-block view scripts import the shared store
- Server state via `wp_interactivity_state()` — never define client defaults for server keys
- All actions in the shared store, not in individual view.js files

### Block Standard

Every block has:
- `apiVersion: 3`
- 20 standard attributes (uniqueId, responsive padding/margin, border radius, box shadow, device visibility)
- InspectorControls: Content, Display, Layout, Style, Advanced panels
- Per-instance CSS scoping via `Block_CSS::render()`
- Template-wrapped frontend output

---

## Credits & Payments (wbcom-credits-sdk)

**Listora does NOT handle payments directly.** All credit/payment operations go through the shared `wbcom-credits-sdk` — the same SDK used by WP Career Board and future Wbcom products.

### SDK Responsibilities (not Listora's concern)

- User credit balance (read/write)
- Credit purchase (Stripe, PayPal, WooCommerce — SDK handles all gateways)
- Transaction log with audit trail
- Webhook receiver for payment callbacks
- Credit packs / pricing tiers
- Idempotency on all transactions
- Admin credit management

### Listora's Responsibility (consumer of SDK)

- Check if user has enough credits before submission/plan activation
- Deduct credits via SDK API when listing is submitted or plan activated
- Display credit balance in dashboard
- Show credit purchase UI (SDK provides the block/widget)
- Define pricing plans (CPT) with credit costs

### Integration Pattern

```php
// Check balance
$balance = \Wbcom\Credits\SDK::get_balance( $user_id );

// Deduct for listing submission
\Wbcom\Credits\SDK::deduct( $user_id, $cost, 'listing_submission', $listing_id );

// SDK handles everything else: purchase, gateways, webhooks, logs
```

### Current State

The credit system is currently embedded in `wb-listora-pro/includes/features/class-credit-system.php`. Before v1.0 launch, this needs to be extracted into `wbcom-credits-sdk` as a Composer package or WordPress library plugin, so both Listora and Career Board consume the same code.

**Reference:** https://github.com/vapvarun/wbcom-credits-sdk.git

---

## Extensibility Points for Future Features

Any future feature (messaging, booking, AI, marketplace) hooks into existing architecture:

| Future Feature | Hooks Into |
|----------------|-----------|
| **Messaging** | New REST controller, new custom table, new dashboard tab via `wb_listora_dashboard_tabs` filter |
| **Booking** | New field type in field registry, new REST endpoint, hooks into `wb_listora_after_listing_fields` |
| **AI descriptions** | Filter on `wb_listora_before_create_listing`, intercept content field, call LLM API |
| **AI categorization** | Filter on `wb_listora_before_create_listing`, suggest terms before save |
| **AI smart search** | Filter on `wb_listora_search_args`, add semantic query parameter |
| **New payment gateway** | Handled by wbcom-credits-sdk — Listora never touches payments directly |
| **Social Login** | New REST endpoint `/auth/{provider}`, WP user creation |
| **Multi-language** | All strings already `__()` wrapped, taxonomies translatable |
| **GraphQL** | WPGraphQL type registration for listora_listing CPT + custom tables |
| **Mobile push** | Hook into `wb_listora_after_*` actions, queue FCM notification |

---

## v1.0 Pre-Launch Checklist

### P0 — Must ship

- [ ] REST API 100% parity audit (every UI action has endpoint)
- [ ] Location taxonomy auto-sync (geo → terms on save)
- [ ] Social Login (Google, Facebook, Apple via OAuth 2.0)
- [ ] JWT authentication for mobile readiness
- [ ] SEO: Schema.org LocalBusiness + Review + Event markup
- [ ] Demo seeder: all 10 listing types with geo + location terms
- [ ] Outgoing webhooks: complete delivery + retry mechanism

### P1 — Ship within 2 weeks of launch

- [ ] PHPUnit acceptance tests for all REST endpoints
- [ ] Playwright E2E visual regression tests
- [ ] Developer documentation site (hook reference, REST API docs)
- [ ] User messaging system (inbox, threads)

### P2 — Ship within 1 month

- [ ] Booking/appointment addon
- [ ] Extract credit system into wbcom-credits-sdk (shared with WP Career Board)
- [ ] Mobile app MVP (React Native, iOS + Android)
