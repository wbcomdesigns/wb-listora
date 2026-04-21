# Why WB Listora?

## For site owners

### Modern block-based architecture
WB Listora is built entirely with Gutenberg blocks and the WordPress Interactivity API. Directorist and GeoDirectory still rely heavily on shortcodes. WB Listora's blocks work in the block editor's visual canvas — no memorizing shortcode parameters.

### Built-in claims workflow
Every listing has a claim system out of the box. With Directorist, Business Directory Plugin, and HivePress, claims require a paid add-on or a custom implementation. In WB Listora, claims, status tracking, email notifications, and the owner dashboard are all included in the free plugin.

### Full REST API, no add-on required
39 REST endpoints covering listings, search, reviews, favorites, claims, services, and the user dashboard are available in the free plugin. GeoDirectory's REST support is limited; Directorist's API requires the Pro version. WB Listora's API is ready for mobile apps and headless integrations from day one.

### Overridable templates for theme authors
WB Listora uses WooCommerce-style template overrides. Copy any template from `wb-listora/templates/` to `{theme}/wb-listora/` and edit it. No child theme gymnastics, no fighting with shortcode output.

### Fast at scale
Search uses a dedicated `listora_search_index` table — not WordPress `meta_query`. Geographic queries use a Haversine calculation against a `listora_geo` table. The plugin handles large listing counts without degrading search performance.

### Genuine multisite awareness
Capability and role checks use WordPress's standard `current_user_can()` system. The plugin does not hard-code `is_admin()` checks in frontend logic, making it compatible with multisite and membership setups.

---

## For directory users

- **Save listings** to favorites and return to them from your dashboard — no bookmarks needed.
- **Claim your business** without contacting the site owner — the claim form is self-service and status is visible in real time on your dashboard.
- **Write richer reviews** — star ratings, helpful votes, report abuse, and get an owner reply all from one page.
- **Find listings near you** — the "Near Me" geolocation button works on mobile without any extra steps.
- **Manage your listing yourself** — edit details, update hours, add services, upload photos all from the frontend dashboard, not the WordPress admin.
- **Works with any theme** — the blocks include per-instance style controls (spacing, shadow, border radius), so they adapt to your theme's design.
- **Fully mobile-optimized** — every block has responsive controls and breakpoints tested at 1024px, 767px, 480px, and 390px.

---

## Free vs Pro comparison

| Feature | Free | Pro | Notes |
|---------|------|-----|-------|
| Listing blocks (11 blocks) | Yes | Yes | — |
| REST API (39 endpoints) | Yes | Yes | — |
| Frontend submission | Yes | Yes | — |
| Business claims | Yes | Yes | — |
| Reviews with owner reply | Yes | Yes | Free: single overall star rating |
| Multi-criteria reviews | — | Yes | [Multi-Criteria Reviews](features/multi-criteria-reviews.md) — define criteria per listing type (e.g., Food, Service, Ambiance) |
| Photo reviews | — | Yes | [Photo Reviews](features/photo-reviews.md) — reviewers upload images with their review |
| Favorites | Yes | Yes | — |
| Saved searches + email alerts | — | Yes | [Saved Searches](features/saved-searches.md) — daily alerts for new matching listings |
| Services per listing | Yes | Yes | — |
| User dashboard (6 tabs) | Yes | Yes | Credits tab shows an upgrade prompt in Free |
| Setup wizard | Yes | Yes | — |
| Import / Export | Yes | Yes | — |
| OpenStreetMap (no API key) | Yes | Yes | — |
| Google Maps + Places autocomplete | — | Yes | [Google Maps](features/google-maps.md) — requires a Google Cloud API key |
| Credit-based payment system | — | Yes | [Credits and Plans](features/credits-and-plans.md) — webhook-based, works with Stripe/PayPal/Paddle |
| Pricing plans | — | Yes | [Credits and Plans](features/credits-and-plans.md) — listing tiers with duration, perks, and featured placement |
| Coupons | — | Yes | [Coupons](features/coupons.md) — discount codes for plan credit costs |
| Per-listing analytics | — | Yes | [Analytics](features/analytics.md) — cookie-free views and click tracking |
| Lead forms | — | Yes | [Lead Forms](features/lead-forms.md) — Contact Owner form emailed to listing owner |
| Verification badges | — | Yes | [Verification Badges](features/verification-badges.md) — manual admin award, visible on listing cards |
| Needs marketplace | — | Yes | [Needs Marketplace](features/needs-marketplace.md) — reverse directory for buyer requests |
| Moderator role | — | Yes | [Moderators](features/moderators.md) — scoped role for content moderation without full admin access |
| Digest notifications | — | Yes | [Digest Notifications](features/digest-notifications.md) — batch emails into a daily summary |
| White label | — | Yes | [White Label](features/white-label.md) — rename admin menu and Plugins list for client handoff |
| Coming Soon / Private mode | — | Yes | [Coming Soon Mode](features/coming-soon.md) — hide directory during setup or require login |
| Auto-updates via license | — | Yes | [License Management](getting-started/pro-license.md) — updates appear in Dashboard → Updates |

---

## Get Pro

Install the add-on and activate your license: [Installing WB Listora Pro](getting-started/activating-pro.md)

---

## Related

- [Installation](getting-started/installation.md)
- [Setup Wizard](getting-started/setup-wizard.md)
- [Blocks Overview](features/blocks-overview.md)
- [Feature Catalog](feature-catalog.md)
