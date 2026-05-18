# 41 — Free vs Pro — Definitive Reference

## Philosophy

```
Free = Complete, working directory. No crippled features.
Pro  = Extends Free. Adds power features ON TOP. Never replaces.
```

Pro is a SEPARATE PLUGIN that hooks into Free via actions/filters. Both run simultaneously. Free never checks "is Pro active?" to hide core functionality — it checks to EXTEND functionality.

---

## Conflict Resolutions

| # | Feature | Decision | Reasoning |
|---|---------|----------|-----------|
| 1 | Favorite Collections | **Pro** | Basic save/unsave is Free. Named collections ("Date Night", "Weekend") is a power feature. |
| 2 | Featured Badge Display | **Free** (admin-set). **Pro** (payment-driven) | Admin can manually mark any listing featured for free. Pro adds payment-driven featuring via plans. |
| 3 | Conditional Fields | **Free** | Core usability — Real Estate NEEDS "show rent fields for rent, sale fields for sale" to work properly. |
| 4 | Recurring Events | **Free** (basic). **Pro** (series linking) | Weekly/monthly recurrence is core for event directories. Series linking (concert tour) is Pro. |
| 5 | Calendar View Block | **Free** | Core block for event directories. Without it, event type is incomplete. |
| 6 | Coming Soon Mode | **Pro** | Convenience feature for agencies. Not needed for basic directory. |
| 7 | Report Review | **Free** | Safety/moderation feature. Must be available to all. |
| 8 | Duplicate Detection | **Free** | Data quality feature. Benefits all directories. |
| 9 | Booking Hooks | Hook point **Free**, implementation **Pro** | Free fires `do_action('wb_listora_appointment_button')`. Pro/third-party provides the actual booking UI. |

---

## Complete Feature Matrix

### CORE & SETUP

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| Single CPT (`listora_listing`) | Yes | — |
| All 5 taxonomies | Yes | — |
| 10 default listing types with full fields | Yes | — |
| Create unlimited custom listing types | Yes | — |
| Unlimited listings | Yes | — |
| Setup wizard (type, location, pages, demo) | Yes | + license step, Pro config |
| Demo content importer | Yes | — |
| Auto-create pages per type | Yes | — |
| Onboarding checklist | Yes | — |
| Composer PSR-4 autoloader | Yes | — |
| WP-CLI commands (reindex, import, export, stats) | Yes | — |
| WCAG 2.1 AA accessibility | Yes | — |
| Full translation ready (.pot) | Yes | — |
| RTL support (CSS logical properties) | Yes | — |
| WPML/Polylang integration | Yes | — |

### CUSTOM FIELDS

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| All field types (text, select, gallery, map, hours, social, price, etc.) | Yes | — |
| Field groups with drag-drop ordering | Yes | — |
| Per-type field configuration | Yes | — |
| Searchable/filterable flags per field | Yes | — |
| Conditional field logic (show/hide based on other field) | Yes | — |
| REST API exposure per field | Yes | — |
| Repeater field type (dynamic sub-groups) | — | Yes |

### SEARCH & FILTERING

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| Keyword search (FULLTEXT, two-phase architecture) | Yes | — |
| Category filter | Yes | — |
| Location filter (text + geocoding) | Yes | + Places autocomplete |
| Listing type filter (tabs/dropdown) | Yes | — |
| Custom field filters (per-type, dynamic) | Yes | — |
| "Open Now" filter | Yes | — |
| Faceted counts (dynamic per filter) | Yes | — |
| All sort options (rating, price, distance, newest, relevance) | Yes | — |
| Autocomplete suggestions (titles, categories, locations) | Yes | + Google Places |
| "Near Me" (browser geolocation) | Yes | — |
| Min/Max number inputs for price, bedrooms, etc. | Yes | — |
| Date filters for events (today, weekend, range) | Yes | — |
| "Happening Now" filter for events | Yes | — |
| Radius slider for distance filtering | — | Yes |
| Range sliders (price, area, salary) with histogram | — | Yes |
| Saved searches with email alerts | — | Yes |
| Multi-field range filters | — | Yes |

### MAPS

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| OpenStreetMap / Leaflet (zero API keys) | Yes | — |
| Map markers with popups | Yes | — |
| Basic marker clustering | Yes | + advanced clustering |
| Custom marker icons per listing type | Yes | — |
| "Near Me" geolocation | Yes | — |
| Bounding box search (search on map drag) | Yes | — |
| Draggable pin on submission form | Yes | — |
| Route/directions link | Yes | — |
| Google Maps | — | Yes |
| Street View | — | Yes |
| Custom map styles (Snazzy Maps) | — | Yes |
| Heatmaps | — | Yes |
| Places autocomplete on address fields | — | Yes |

### BLOCKS

| Block | Free | Pro Extends |
|-------|:----:|:-----------:|
| `listora/listing-search` | Yes | + radius slider, range sliders |
| `listora/listing-grid` | Yes | + infinite scroll |
| `listora/listing-card` | Yes | + overlay layout, quick view popup |
| `listora/listing-map` | Yes | + Google Maps, heatmaps |
| `listora/listing-detail` | Yes | + full-width layout, analytics, lead form |
| `listora/listing-reviews` | Yes | + multi-criteria, photo reviews |
| `listora/listing-submission` | Yes | + plan selection step, payment step |
| `listora/user-dashboard` | Yes | + analytics tab, payments tab, saved searches |
| `listora/listing-categories` | Yes | — |
| `listora/listing-featured` | Yes (shows listings, no payment gating) | + payment-driven featuring, badges |
| `listora/listing-calendar` | Yes (for event types) | — |
| `listora/listing-comparison` | — | Yes |
| `listora/analytics-dashboard` | — | Yes |

### CARD LAYOUTS

| Layout | Free | Pro Extends |
|--------|:----:|:-----------:|
| Standard (vertical image + content) | Yes | — |
| Horizontal (image left, content right) | Yes (basic) | + enhanced with more fields |
| Compact (minimal, one-line) | Yes | — |
| Overlay (image background + gradient text) | — | Yes |

### DETAIL PAGE LAYOUTS

| Layout | Free | Pro Extends |
|--------|:----:|:-----------:|
| Tabbed (tabs for field groups, reviews, map) | Yes | — |
| Sidebar (content left, contact/map right) | Yes | + enhanced sidebar |
| Full-width (no sidebar, wide content) | — | Yes |

### LISTING LIFECYCLE

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| All statuses (draft, pending, published, rejected, expired, deactivated) | Yes | + pending payment |
| Status transitions | Yes | — |
| Time-based expiration | Yes | — |
| Date-based expiration (events end date, job deadline) | Yes | — |
| Expiration cron (twice daily) | Yes | — |
| Expiration warning emails (7 days, 1 day) | Yes | — |
| Listing renewal (re-publish expired) | Yes | + payment-to-renew |
| Owner deactivation (voluntary unpublish) | Yes | — |
| Moderation queue (approve/reject pending) | Yes | + moderator assignment |
| Rejection with reason | Yes | — |
| Duplicate detection on submission | Yes | — |
| Coming soon / pre-launch mode | — | Yes |
| Moderator role with workload balancing | — | Yes |
| Notification digests (daily summary instead of individual) | — | Yes |
| Audit log (who changed what, when) | — | Yes |

### REVIEWS & RATINGS

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| Single overall rating (1-5 stars) | Yes | — |
| Text review with title | Yes | — |
| Min character count requirement | Yes | — |
| One review per user per listing | Yes | — |
| Owner reply to review | Yes | — |
| "Helpful" votes (with duplicate prevention) | Yes | — |
| Review moderation (approve/spam/delete) | Yes | — |
| Report review (flag inappropriate) | Yes | — |
| Rating summary (bar chart breakdown) | Yes | — |
| Average rating on cards + detail | Yes | — |
| Review count display | Yes | — |
| Schema.org aggregateRating | Yes | — |
| Multi-criteria ratings (food, service, ambiance) | — | Yes |
| Photo reviews | — | Yes |
| Verified visit badges | — | Yes |
| AI review summary | — | Yes |
| Review analytics | — | Yes |

### FAVORITES

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| Save/unsave listings (heart button) | Yes | — |
| View saved listings in dashboard | Yes | — |
| Favorite count per listing | Yes | — |
| Named collections ("Date Night", "Weekend Spots") | — | Yes |
| Share collections | — | Yes |

### CLAIM LISTING

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| Claim button on unclaimed listings | Yes | — |
| Proof submission (text + file upload) | Yes | — |
| Admin review queue (approve/reject) | Yes | — |
| Ownership transfer on approval | Yes | — |
| "Claimed" badge on listing | Yes | — |
| Email notifications (submitted, approved, rejected) | Yes | — |
| Paid claim fee (charge to claim) | — | Yes |
| Auto-verification methods | — | Yes |
| Claim + upgrade to paid plan | — | Yes |

### FRONTEND SUBMISSION

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| Multi-step submission form | Yes | — |
| Type selection drives field rendering | Yes | — |
| Media upload (featured image + gallery) | Yes | — |
| Map/address picker with draggable pin | Yes | + Places autocomplete |
| Preview before submit | Yes | — |
| Edit own listing from frontend | Yes | — |
| Draft save and resume later | Yes | — |
| Spam prevention (honeypot, rate limit) | Yes | — |
| User registration form | Yes | + social login |
| Package/plan selection step | — | Yes |
| Payment step (Stripe/PayPal) | — | Yes |
| Social login (Google/Facebook) | — | Yes |

### USER DASHBOARD

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| My Listings tab (with status indicators) | Yes | — |
| My Reviews tab (written + received) | Yes | — |
| My Favorites tab | Yes | — |
| Profile settings | Yes | — |
| Edit/delete own listings | Yes | — |
| Listing status overview (active, pending, expired) | Yes | — |
| Renew expired listings | Yes | + payment-to-renew |
| Notification preferences | Yes | — |
| My Analytics tab (views, clicks, CTR) | — | Yes |
| My Payments tab (history, subscriptions) | — | Yes |
| Saved searches tab | — | Yes |

### PAYMENTS & MONETIZATION (Credit System)

| Feature | Free | Pro |
|---------|:----:|:---:|
| Free listings (no payment required) | Yes | Yes |
| Credit system (user balances) | — | Yes |
| Universal webhook receiver (works with ANY payment system) | — | Yes |
| Pricing plans with credit costs | — | Yes |
| Credit packs with external buy URLs | — | Yes |
| Admin manual credit management | — | Yes |
| Credit transaction log | — | Yes |
| Coupon codes (credit discounts) | — | Yes |
| Featured listing via plan perks | — | Yes |
| Listing packages (image limits, duration, badges) | — | Yes |
| Works with: Stripe, PayPal, WooCommerce, Razorpay, EDD, LemonSqueezy, bank transfer, ANY webhook-capable system | — | Yes |

**Note:** The plugin NEVER handles money directly. Credits are added via webhooks from external payment systems. This means zero PCI compliance burden, zero gateway maintenance, and compatibility with every payment system in the world.

### EMAIL NOTIFICATIONS

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| All 14 notification events | Yes | — |
| HTML email templates | Yes | — |
| Toggle per notification | Yes | — |
| Template variables ({listing_title}, {author_name}, etc.) | Yes | — |
| Theme template override | Yes | — |
| Template customization hooks | Yes | — |
| Visual email template editor | — | Yes |
| Notification digests (daily summary) | — | Yes |
| Webhook notifications | — | Yes |

### SEO & SCHEMA

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| Schema.org JSON-LD for all 10 types | Yes | — |
| Breadcrumbs (JSON-LD + optional visual block) | Yes | — |
| Open Graph meta tags | Yes | — |
| Twitter Card meta tags | Yes | — |
| WordPress sitemap integration | Yes | — |
| Aggregate ratings in schema | Yes | — |
| SEO plugin compatibility (Yoast, Rank Math) | Yes | — |
| Canonical URLs for filtered views | Yes | — |
| Custom meta for taxonomy pages | Yes | — |
| Noindex for thin/filtered pages | Yes | — |
| Internal linking (related, breadcrumbs, category links) | Yes | — |
| Expired listing URL strategy (200 + noindex, not 404) | Yes | — |
| FAQ schema (auto-generated from fields) | — | Yes |
| Programmatic SEO pages (type-in-location combos) | — | Yes |

### IMPORT / EXPORT

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| CSV import with column-to-field mapping | Yes | — |
| CSV export (filtered) | Yes | — |
| JSON/GeoJSON import | Yes | — |
| WP-CLI import/export commands | Yes | — |
| Visual field mapping UI (drag-drop) | — | Yes |
| Competitor migration tools (GeoDirectory, Directorist, etc.) | — | Yes |
| Scheduled imports (cron-based feeds) | — | Yes |

### NICHE FEATURES (Events, Jobs, Healthcare)

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| Event: recurring events (weekly/monthly) | Yes | — |
| Event: calendar view block | Yes | — |
| Event: date-based expiration | Yes | — |
| Event: "Happening Now/Today/Weekend" filters | Yes | — |
| Event: event series linking (concert tour) | — | Yes |
| Job: salary range display + filtering | Yes | — |
| Job: date-based expiration (deadline) | Yes | — |
| Job: "Position Filled" close action | Yes | — |
| Job: company profiles (taxonomy + profile page) | — | Yes |
| Job: application tracking + Easy Apply | — | Yes |
| Healthcare: insurance autocomplete filter | Yes | — |
| Healthcare: appointment booking hooks | Hook point Free | Implementation Pro |
| Conditional fields (show/hide based on value) | Yes | — |
| User types (owner vs visitor, via user meta) | — | Yes |

### ANALYTICS (Pro Only)

| Feature | Free | Pro |
|---------|:----:|:---:|
| View tracking (server-side, privacy-safe) | — | Yes |
| Click tracking (phone, website, email, directions) | — | Yes |
| Search impression tracking | — | Yes |
| Owner analytics dashboard | — | Yes |
| Admin site-wide analytics | — | Yes |
| REST API for analytics data | — | Yes |

### PRO-ONLY FEATURES (No Free Equivalent)

| Feature | Pro |
|---------|:---:|
| Google Maps (all features) | Yes |
| Lead form / contact owner with tracking | Yes |
| Listing comparison table (side-by-side) | Yes |
| Verification badges | Yes |
| White-label (remove branding, custom name) | Yes |
| Multi-currency display | Yes |
| Webhooks (outgoing to Zapier/Make.com) | Yes |
| Infinite scroll pagination | Yes |
| Quick view popup on cards | Yes |

### REST API

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| All public listing/search/type/review endpoints | Yes | — |
| All authenticated CRUD endpoints | Yes | — |
| Dashboard endpoints | Yes | + analytics |
| Settings endpoints | Yes | — |
| Abilities API declarations | Yes | + Pro abilities |
| Payment endpoints | — | Yes |
| Webhook endpoints | — | Yes |
| Application endpoints (jobs) | — | Yes |
| Saved searches endpoints | — | Yes |
| Analytics endpoints | — | Yes |

### INTERACTIVITY API

| Feature | Free | Pro Extends |
|---------|:----:|:-----------:|
| Shared `listora/directory` store namespace | Yes | Pro adds to same namespace |
| Search state + actions | Yes | + saved searches |
| Map state + actions | Yes | + Google Maps actions |
| Card interactions (hover sync, favorite) | Yes | + quick view, comparison add |
| Review form + submission | Yes | + multi-criteria, photo upload |
| Dashboard state | Yes | + analytics, payments tabs |
| Error handling + loading states | Yes | — |
| URL state management (pushState) | Yes | — |

---

## The Golden Rule

```
If a site owner installs ONLY the free plugin, they get a COMPLETE,
PROFESSIONAL, FULLY FUNCTIONAL directory that they can launch and
operate indefinitely. Nothing is broken, missing, or "coming soon."

Pro makes it BETTER — more maps, more money, more data, more power.
Pro never makes Free WORK.
```

---

## How Pro Hooks In (Technical)

```php
// Free plugin provides extension points at EVERY feature:
do_action('wb_listora_after_listing_fields', $id, $type);
apply_filters('wb_listora_field_types', $types);
apply_filters('wb_listora_search_args', $args);
apply_filters('wb_listora_card_template', $template, $type);
apply_filters('wb_listora_map_provider', $provider);
apply_filters('wb_listora_review_criteria', $criteria, $type);
apply_filters('wb_listora_payment_gateways', $gateways);

// Pro hooks in via standard WordPress hooks:
add_filter('wb_listora_field_types', function($types) {
    $types['repeater'] = Repeater_Field::class;
    return $types;
});

add_filter('wb_listora_map_provider', function($provider) {
    if (wb_listora_get_setting('map_provider') === 'google') {
        return new Google_Maps_Provider();
    }
    return $provider;
});

// Free checks Pro status for UI hints (NOT for functionality):
if (wb_listora_is_pro_active()) {
    // Show Pro-enhanced UI
} else {
    // Show upgrade hint (never block functionality)
}
```

---

## Upgrade Hints (How Free Promotes Pro)

Free shows subtle, non-intrusive upgrade hints at decision points:

```
┌─────────────────────────────────────────┐
│ Map Provider                            │
│ (•) OpenStreetMap (Free — works now)    │
│ ( ) Google Maps   ⭐ Pro               │
│     [Learn more →]                      │
└─────────────────────────────────────────┘
```

**Rules for upgrade hints:**
1. Never block a workflow
2. Never show popups or modals
3. Small inline text or badge only
4. Always show the Free option as default/selected
5. Never more than 1 hint per admin page
6. Zero hints on the frontend (visitors never see "Pro" messaging)
