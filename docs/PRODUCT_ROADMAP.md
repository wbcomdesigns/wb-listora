# WB Listora — 5-Year Product Roadmap

## Vision

**WordPress is the backend layer. The REST API is the product.**

WB Listora is a SaaS-grade directory platform where WordPress serves as the application server. Every feature is API-first — the block-based frontend is just one consumer. Mobile apps, third-party integrations, and headless frontends all consume the same API.

## Differentiators (non-negotiable)

1. **Performance** — custom tables, denormalized search index, zero WP_Query abuse
2. **Developer Experience** — hooks on every write operation, template overrides, clean REST API
3. **Type Flexibility** — 10 listing types out of the box, any vertical in minutes
4. **Modern Stack** — Interactivity API, block editor native, ES modules, no jQuery
5. **AI-Ready** — hooks for LLM descriptions, auto-categorization, smart search, embeddings

## Architecture Principles

- **API-first**: every feature must have a REST endpoint before it gets a UI
- **One listing = one address**: clean data model, no multi-location hacks
- **Location taxonomy**: hierarchical (Country > State > City), full names, auto-assigned from geo data
- **Stateless frontend**: all state from API responses, no server session dependency
- **Mobile-ready**: JWT/App Password auth, paginated responses, image CDN support

---

## Year 1 — v1.0 Launch (2026)

### Theme: "Ship Complete"

#### v1.0.0 — Initial Release

**Free Plugin (Core)**
- [x] 10 listing types with custom fields
- [x] 11 Gutenberg blocks (search, grid, map, detail, submission, reviews, dashboard, categories, featured, calendar, card)
- [x] Interactivity API frontend (no jQuery)
- [x] OpenStreetMap default (Leaflet), Google Maps optional via Pro
- [x] Search: fulltext + faceted + geo (Haversine) + sort
- [x] Reviews: star ratings, helpful votes, owner reply, reporting
- [x] Claims: proof upload, admin approval, ownership transfer
- [x] Favorites: per-user, API-driven
- [x] Frontend submission: 5-step wizard
- [x] User dashboard: listings, reviews, favorites, profile, saved searches
- [x] WooCommerce-style template overrides (all 11 blocks)
- [x] Email system: 14 templates, all triggered
- [x] Import/Export: CSV, JSON, GeoJSON + 4 competitor migrators
- [x] Settings: tabbed admin UI with grouped navigation
- [x] Demo seeder: 5 vertical packs with real geo data
- [x] CI: WPCS + PHPStan L5 + PHP Lint + PCP
- [x] Toast notification system (no browser alerts)
- [x] Global focus-visible accessibility
- [x] Breadcrumbs: Directory > Type > Category > Listing

**Pro Plugin (Premium)**
- [x] License system with weekly validation
- [x] Credit system with transaction log
- [x] Pricing plans CPT with coupon support
- [x] Google Maps provider (swap from OSM)
- [x] Multi-criteria reviews
- [x] Photo reviews
- [x] Lead form (contact listing owner)
- [x] Listing comparison (side-by-side)
- [x] Verification badges
- [x] Analytics (views, clicks, CTR)
- [x] Advanced search with saved searches + email alerts
- [x] Notification digest (instant/daily/urgent)
- [x] White label
- [x] Audit log with retention
- [x] Badges system (automatic + plan-based)
- [x] Coupons CRUD
- [x] Moderator roles
- [x] Needs/RFQ system (reverse listings)
- [x] Admin menu consolidated (24 → 14 items)
- [x] Tools page (Visual Import + Google Import)

**v1.0 Gaps to Close Before Launch:**

| # | Gap | Priority | Effort |
|---|-----|----------|--------|
| 1 | **REST API audit** — ensure 100% parity (every UI action has an API endpoint) | P0 | 2 weeks |
| 2 | **Location taxonomy auto-sync** — geo data → auto-create/assign location terms on save | P0 | 3 days |
| 3 | **Social Login** — Google, Facebook, Apple (OAuth 2.0 via REST) | P0 | 1 week |
| 4 | **JWT Authentication** — App Password + JWT for mobile app auth | P0 | 1 week |
| 5 | **Outgoing Webhooks** — complete delivery mechanism, retry queue | P1 | 3 days |
| 6 | **SEO** — Schema.org (LocalBusiness, Event, Review), sitemap provider, meta tags | P1 | 1 week |
| 7 | **PHPUnit tests** — acceptance tests for all REST endpoints + submission flow | P1 | 2 weeks |
| 8 | **Playwright E2E** — automated visual regression tests | P1 | 1 week |
| 9 | **Documentation site** — developer docs, hook reference, REST API docs | P1 | 1 week |
| 10 | **Demo seeder: all 10 types** — currently 5 packs, need Event, Job, Healthcare, Education, Classified | P2 | 3 days |
| 11 | **Messaging between users** — direct messages via REST, inbox in dashboard | P2 | 2 weeks |

#### v1.1.0 — Post-Launch Polish (Q3 2026)

- User messaging system (inbox, threads, notifications)
- Booking/appointment system (Pro)
- Stripe Connect for marketplace payments
- PayPal integration
- Listing packages (free = 1 listing, basic = 5, pro = unlimited)
- Admin dashboard widgets (new listings, reviews, revenue)
- Bulk actions (approve, reject, feature, expire)
- Activity feed in admin

#### v1.2.0 — Mobile App Foundation (Q4 2026)

- React Native app (iOS + Android) consuming REST API
- Push notifications via Firebase
- Deep linking (listing URLs → app)
- Offline favorites + drafts
- Image upload from camera
- Location-aware search (GPS)

---

## Year 2 — v2.0 Platform (2027)

### Theme: "From Plugin to Platform"

#### v2.0.0 — AI & Marketplace (Q1 2027)

- **AI Description Generator** — auto-generate listing descriptions from fields + images
- **AI Auto-Categorization** — suggest categories/features from description
- **AI Smart Search** — semantic search using embeddings (pgvector or custom table)
- **AI Review Moderation** — flag spam/fake reviews automatically
- **Marketplace Commission** — take % of transactions (Stripe Connect)
- **Booking Calendar** — availability, time slots, instant booking

#### v2.1.0 — Multi-Language (Q2 2027)

- WPML/Polylang compatibility
- RTL layout (already CSS-ready)
- Translatable listing types + field labels
- Multi-currency support

#### v2.2.0 — Advanced Analytics (Q3 2027)

- Heatmap of listing views
- Conversion funnel (view → contact → booking)
- Competitor benchmarking (vs similar listings)
- Revenue dashboard for directory owners
- Export reports (PDF, CSV)

#### v2.3.0 — Developer Ecosystem (Q4 2027)

- Extension marketplace (third-party addons)
- CLI tools (`wp listora create-type`, `wp listora seed`)
- GraphQL endpoint (via WPGraphQL integration)
- Webhooks v2 (real-time events via WebSockets)
- SDK for external integrations (npm package)

---

## Year 3 — v3.0 SaaS (2028)

### Theme: "Multi-Tenant SaaS"

#### v3.0.0 — Hosted Platform (Q1 2028)

- **Multi-tenant architecture** — one WordPress multisite, each site = one directory
- **Onboarding wizard** — pick vertical, customize design, go live in 5 minutes
- **Hosted dashboard** — billing, usage, support tickets
- **CDN integration** — image optimization, global edge caching
- **Uptime monitoring** — auto-restart, health checks

#### v3.1.0 — White-Label SaaS (Q2 2028)

- Custom domains per directory
- Theme customizer (colors, fonts, logo)
- Email domain verification (SPF/DKIM)
- Branded mobile app (via React Native config)

#### v3.2.0 — Enterprise Features (Q3 2028)

- SSO (SAML/OIDC)
- Role-based access control (granular permissions)
- Audit trail (compliance-grade logging)
- Data export (GDPR)
- SLA dashboard

---

## Year 4 — v4.0 Ecosystem (2029)

### Theme: "Marketplace of Marketplaces"

- **Theme marketplace** — directory-specific themes
- **Extension marketplace** — paid addons by third-party developers
- **Affiliate program** — revenue share for referrals
- **Partner API** — allow SaaS integrations (Zapier, Make, n8n native)
- **Franchise system** — one brand, multiple directories, shared listings
- **Data marketplace** — anonymized directory data for market research

---

## Year 5 — v5.0 Intelligence (2030)

### Theme: "AI-Native Directory"

- **AI-powered search** — "find me a pet-friendly Italian restaurant near Central Park open now" → natural language
- **Automated listing creation** — scrape public data, create listings, verify with owners
- **Predictive analytics** — "your listing views are down 20% this month, here's why"
- **Dynamic pricing** — auto-adjust listing plan prices based on demand
- **Voice search** — Siri/Alexa integration via API
- **AR preview** — "see this restaurant from the street" via mobile app

---

## Technical Architecture (SaaS-Grade)

### Database Layer

```
wp_listora_geo          — lat/lng, address, city, state, country, geohash
wp_listora_search_index — denormalized search (fulltext + faceted)
wp_listora_field_index  — type-specific field values for filtering
wp_listora_reviews      — ratings, criteria, helpful votes
wp_listora_review_votes — per-user helpful votes
wp_listora_favorites    — user bookmarks
wp_listora_claims       — business claim requests
wp_listora_hours        — business hours (structured)
wp_listora_analytics    — view/click/CTR tracking
wp_listora_payments     — transaction records
wp_listora_services     — service catalogue per listing

Pro tables:
wp_listora_credit_log   — credit transactions
wp_listora_audit_log    — admin action log
wp_listora_saved_searches — user saved search criteria
wp_listora_coupon_usage — coupon redemption tracking
wp_listora_messages     — (v1.1) user messaging
```

### REST API Namespace

```
/listora/v1/
  listings/           GET, POST
  listings/{id}       GET, PATCH, DELETE
  listings/{id}/media GET, POST, DELETE
  search/             GET (faceted, geo, fulltext)
  search/suggestions  GET (autocomplete)
  reviews/            GET, POST
  reviews/{id}        GET, PATCH, DELETE
  reviews/{id}/helpful POST
  reviews/{id}/reply   POST
  reviews/{id}/report  POST
  favorites/          GET, POST, DELETE
  claims/             GET, POST
  claims/{id}         GET, PATCH
  submission/         POST (frontend form)
  dashboard/          GET (user stats)
  dashboard/listings  GET (user's listings)
  listing-types/      GET
  listing-types/{slug} GET
  settings/           GET, PUT, DELETE (admin)
  export/csv          GET (admin)
  import/csv          POST (admin)
  migrations/{slug}/start POST (admin)

Pro endpoints:
/listora-pro/v1/
  credits/balance     GET
  credits/history     GET
  credits/purchase    POST
  credits/add         POST (admin)
  credits/packs       GET
  webhook/            POST (payment callback)
  analytics/{id}      GET
  needs/              GET, POST
  needs/{id}          GET, PATCH, DELETE
  saved-searches/     GET, POST, DELETE
  messages/           GET, POST (v1.1)
  messages/{id}       GET
  messages/{id}/reply POST (v1.1)
```

### Location Data Flow (v1.0)

```
Listing saved (frontend or admin)
  ↓
Address geocoded → wp_listora_geo (lat, lng, city, state, country)
  ↓
Location terms auto-created/assigned → wp_term_relationships
  Country: "United States" (top level)
  State: "New York" (child of country)
  City: "Manhattan" (child of state)
  ↓
Search index updated → wp_listora_search_index
  ↓
Map markers cached → Interactivity API state
```

### Authentication (Mobile-Ready)

```
WordPress Application Passwords  → v1.0 (built-in)
JWT tokens                       → v1.0 (via plugin or custom)
OAuth 2.0 (Social Login)         → v1.0 (Google, Facebook, Apple)
API Keys (third-party)           → v2.0
SSO (SAML/OIDC)                  → v3.0 (enterprise)
```

---

## Release Cadence

| Version | Frequency | Focus |
|---------|-----------|-------|
| x.0.0 | Annual (major) | New capabilities, breaking changes |
| x.x.0 | Quarterly (minor) | New features, non-breaking |
| x.x.x | As needed (patch) | Bug fixes, security |

## Success Metrics

| Metric | Year 1 | Year 3 | Year 5 |
|--------|--------|--------|--------|
| Active installs | 1,000 | 10,000 | 50,000 |
| Pro licenses | 100 | 2,000 | 15,000 |
| REST API calls/day | 10K | 1M | 50M |
| Mobile app users | 0 | 5,000 | 100,000 |
| Third-party extensions | 0 | 20 | 100 |
| Revenue (MRR) | $5K | $50K | $500K |
