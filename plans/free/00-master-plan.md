# WB Listora — Master Plan

## Product Vision

The only WordPress directory plugin that scales to 100K+ listings AND ships everything in one free plugin. Modern WP stack (REST API, Interactivity API, block editor). No addon bloat. No jQuery spaghetti. App-ready from day one.

---

## Distribution Model

| | Free (wb-listora) | Pro (wb-listora-pro) |
|---|---|---|
| **Distribution** | WordPress.org | wblistora.com |
| **Price** | Free forever | TBD (annual + lifetime options) |
| **Philosophy** | Complete working directory | Power features for serious directories |
| **Target** | Solo operators, first-timers, small directories | Agencies, large directories, monetized sites |

---

## Free vs Pro Feature Split

### FREE — Everything needed to run a real directory

| Category | Features |
|----------|----------|
| **Core** | Single CPT, all taxonomies, 10 listing types with full fields, unlimited listings |
| **Setup** | Setup wizard, demo content import, auto-page creation |
| **Search** | Keyword + category + location + type + custom field filters, faceted counts, "open now", date filters for events |
| **Maps** | OpenStreetMap/Leaflet (zero API keys), markers, basic clustering, "near me" |
| **Blocks** | All 10+ blocks including featured carousel (featured carousel shows ALL listings in free; Pro adds badge styling and payment-driven featuring) |
| **Submission** | Multi-step frontend form, type-driven fields, media upload |
| **Dashboard** | Listing owner dashboard (my listings, my reviews, my favorites) |
| **Reviews** | Single overall rating (1-5 stars), text review, owner reply |
| **Favorites** | Save/unsave listings |
| **Claim** | Basic claim flow (submit proof → admin approves) |
| **SEO** | Schema.org for all 10 types, breadcrumbs, sitemap integration |
| **Notifications** | All email notifications (submission, approval, rejection, expiration, review, claim) |
| **Import/Export** | CSV import/export with field mapping |
| **REST API** | Full public + authenticated API for all features |
| **Admin** | WP Settings API, listing type manager, moderation queue |
| **WP-CLI** | reindex, import, export, stats, listing-types |
| **Accessibility** | WCAG 2.1 AA compliant |
| **i18n** | Full translation ready, RTL support |
| **Performance** | Custom database tables, object cache, transient caching |
| **Fields** | Conditional field logic (show/hide based on other field values) |
| **Events** | Recurring events, calendar view block, date filters, "happening now" |
| **Safety** | Report review flow, duplicate listing detection |
| **Hooks** | Booking/appointment hook point (`do_action` — third-party implements) |

**See `41-free-vs-pro-definitive.md` for the complete feature-by-feature matrix.**

### PRO — Advanced features for monetized/large directories

| Category | Features |
|----------|----------|
| **Maps** | Google Maps (clustering, custom styles, Street View, Places autocomplete, heatmaps) |
| **Search** | Radius slider, saved searches with email alerts, price range slider, multi-field range filters |
| **Reviews** | Multi-criteria ratings, photo reviews, verified visit badges, AI review summary |
| **Payments** | Credit system + universal webhook (works with ANY payment: Stripe, PayPal, WooCommerce, Razorpay, bank transfer, etc.) |
| **Monetization** | Featured/promoted listings with badges, listing packages, claim fees |
| **Analytics** | Owner dashboard (views, clicks, CTR, leads), admin analytics |
| **Lead Form** | Contact owner form with tracking, click-to-call/WhatsApp tracking |
| **Comparison** | Side-by-side listing comparison table |
| **Layouts** | Overlay card layout, full-width detail layout, quick view popup |
| **Fields** | Repeater field type (dynamic sub-groups) |
| **Import** | Visual field mapping UI, competitor migration tools, scheduled imports |
| **White-label** | Remove branding, custom plugin name |
| **Verification** | Verified badges for listing owners |
| **Multi-currency** | Price display in multiple currencies |
| **Social login** | Login via Google/Facebook for submission/reviews |
| **Webhooks** | Outgoing webhooks for Zapier/Make.com integration |
| **Pricing Extras** | Coupon codes, promotional pricing, VAT/tax handling, refund management, invoices |
| **Moderation** | Notification digests, moderator role & assignment, audit log |
| **Jobs** | Company profiles, application tracking, Easy Apply |
| **Events** | Event series linking (concert tours across cities) |
| **Favorites** | Named collections, share collections |
| **SEO** | Programmatic SEO pages, FAQ schema |
| **Launch** | Coming soon / pre-launch mode |
| **User Types** | User type distinction (owner vs visitor/seeker) |
| **Infinite Scroll** | Infinite scroll pagination on grid |

---

## Implementation Phases (Usability-First Order)

### Phase 1: First Impression (Weeks 1-3)
*Goal: Site owner goes from install to credible directory in 2 hours*

| Priority | Feature | Doc |
|----------|---------|-----|
| 1 | Plugin bootstrap, autoloader, activation | `01-plugin-foundation.md` |
| 2 | Setup wizard | `02-setup-wizard.md` |
| 3 | Listing types system (3 default: Business, Restaurant, Real Estate) | `03-listing-types.md` |
| 4 | Custom fields system | `04-custom-fields.md` |
| 5 | Categories & locations (scoped to types) | `05-taxonomies.md` |
| 6 | Custom database tables | `06-database.md` |
| 7 | Listing card block | `08-listing-cards.md` |
| 8 | Search + filter block | `09-search-filtering.md` |
| 9 | Listing grid block | `10-listing-grid.md` |
| 10 | Maps block (OSM/Leaflet) | `11-maps.md` |
| 11 | Listing detail block | `12-listing-detail.md` |
| 12 | Demo content importer | `13-demo-content.md` |
| 13 | Admin settings (basic) | `18-admin-settings.md` |

### Phase 2: User Features (Weeks 4-6)
*Goal: Listing owners can submit, visitors can interact*

| Priority | Feature | Doc |
|----------|---------|-----|
| 14 | Frontend submission form | `14-frontend-submission.md` |
| 15 | User dashboard | `15-user-dashboard.md` |
| 16 | Email notifications | `16-email-notifications.md` |
| 17 | Reviews (single rating) | `17-reviews.md` |
| 18 | Favorites/bookmarks | `19-favorites.md` |
| 19 | Claim listing | `20-claim-listing.md` |

### Phase 3: Revenue & Growth (Weeks 7-9)
*Goal: Site owner starts earning, SEO kicks in*

| Priority | Feature | Doc |
|----------|---------|-----|
| 20 | Payment system (Pro: Stripe/PayPal) | `21-payments.md` |
| 21 | Pricing plans (Pro) | `22-pricing-plans.md` |
| 22 | SEO & Schema.org | `23-seo-schema.md` |
| 23 | CSV import/export | `24-import-export.md` |
| 24 | REST API (full) | `25-rest-api.md` |
| 25 | WP-CLI commands | `26-wp-cli.md` |
| 26 | Remaining 7 listing types | `03-listing-types.md` |

### Phase 4: Pro Power Features (Weeks 10-14)
*Goal: Pro plugin ships alongside free*

| Priority | Feature | Doc |
|----------|---------|-----|
| 27 | Google Maps (Pro) | `11-maps.md` |
| 28 | Advanced search (Pro) | `09-search-filtering.md` |
| 29 | Multi-criteria reviews (Pro) | `17-reviews.md` |
| 30 | Analytics dashboard (Pro) | `27-analytics.md` |
| 31 | Lead form (Pro) | `28-lead-form.md` |
| 32 | Comparison table (Pro) | `29-comparison.md` |
| 33 | Featured listings (Pro) | `30-featured-listings.md` |
| 34 | Verification badges (Pro) | `31-verification.md` |
| 35 | Webhooks (Pro) | `32-webhooks.md` |

### Phase 5: Polish & Scale (Weeks 15-16)
| Priority | Feature | Doc |
|----------|---------|-----|
| 36 | Performance optimization | `07-performance.md` |
| 37 | Competitor migration tools | `33-migration.md` |
| 38 | Accessibility audit | `34-accessibility.md` |
| 39 | Theme compatibility testing | `35-theme-compatibility.md` |
| 40 | Documentation | External |

---

## Plugin Identity

| Key | Value |
|-----|-------|
| Name | WB Listora |
| Slug (Free) | `wb-listora` |
| Slug (Pro) | `wb-listora-pro` |
| Namespace | `WBListora` / `WBListoraPro` |
| Text Domain | `wb-listora` |
| REST Namespace | `listora/v1` |
| Interactivity Namespace | `listora/directory` |
| Table Prefix | `listora_` |
| Meta Prefix | `_listora_` |
| Hook Prefix | `wb_listora_` |
| Domain | wblistora.com |
| Min PHP | 7.4 |
| Min WP | 6.4 |
| Tested Up To | 6.9 |

---

## Theme Adaptive Design Principles

See `35-theme-compatibility.md` for full details. Core principles:

1. **Inherit, don't impose** — Use `theme.json` CSS custom properties for colors, fonts, spacing
2. **CSS logical properties** — `margin-inline-start` not `margin-left` (RTL support)
3. **Block-based output** — All frontend output uses WP block markup patterns
4. **No fixed dimensions** — Fluid/responsive by default, container-query aware
5. **Minimal opinionated CSS** — Only structural styles; visual styles from theme
6. **Semantic HTML** — `<article>`, `<address>`, `<time>`, proper heading hierarchy
7. **CSS custom properties API** — `--listora-card-gap`, `--listora-card-columns` etc. for theme overrides
8. **Dark mode ready** — Respect `prefers-color-scheme` and theme dark mode tokens

---

## Three User Journeys

### Site Owner Journey
Install → Wizard → Configure types → Seed content → Design pages → Set up payments → Launch → Moderate → Grow

### Listing Owner Journey
Discover site → Register → Submit listing → Upload media → Pay (if required) → Manage dashboard → Respond to reviews → Renew

### Visitor Journey
Land on site → Search/browse → View listing → Read reviews → Save favorite → Contact owner → Leave review

Each feature doc addresses all three journeys where relevant.

---

## Document Index

| # | Document | Status |
|---|----------|--------|
| 00 | Master Plan (this file) | Complete |
| 01 | Plugin Foundation | Planned |
| 02 | Setup Wizard | Planned |
| 03 | Listing Types | Planned |
| 04 | Custom Fields | Planned |
| 05 | Taxonomies | Planned |
| 06 | Database | Planned |
| 07 | Performance | Planned |
| 08 | Listing Cards | Planned |
| 09 | Search & Filtering | Planned |
| 10 | Listing Grid | Planned |
| 11 | Maps | Planned |
| 12 | Listing Detail | Planned |
| 13 | Demo Content | Planned |
| 14 | Frontend Submission | Planned |
| 15 | User Dashboard | Planned |
| 16 | Email Notifications | Planned |
| 17 | Reviews | Planned |
| 18 | Admin Settings | Planned |
| 19 | Favorites | Planned |
| 20 | Claim Listing | Planned |
| 21 | Payments (Pro) | Planned |
| 22 | Pricing Plans (Pro) | Planned |
| 23 | SEO & Schema | Planned |
| 24 | Import/Export | Planned |
| 25 | REST API | Planned |
| 26 | WP-CLI | Planned |
| 27 | Analytics (Pro) | Planned |
| 28 | Lead Form (Pro) | Planned |
| 29 | Comparison (Pro) | Planned |
| 30 | Featured Listings (Pro) | Planned |
| 31 | Verification (Pro) | Planned |
| 32 | Webhooks (Pro) | Planned |
| 33 | Migration | Planned |
| 34 | Accessibility | Planned |
| 35 | Theme Compatibility | Planned |
| 36 | Status Lifecycle & Moderation | Complete |
| 37 | Multilingual & RTL | Complete |
| 38 | Advanced SEO | Complete |
| 39 | Niche Features (Events, Jobs) | Complete |
| 40 | API, Abilities & Interactivity Architecture | Complete |
| 41 | Free vs Pro — Definitive Reference | Complete |
| 42 | Pro Business Plan (Pricing, Revenue, Launch) | Complete |
