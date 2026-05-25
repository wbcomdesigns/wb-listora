# Feature Matrix - Free vs Pro

Every WB Listora capability at a glance. Use this page to decide whether Free covers your launch needs or you need Pro from day one.

> **Rule of thumb:** Free gives you a complete, public, searchable directory with reviews, claims, frontend submission, and full taxonomy / map / spam-protection support. Pro adds the **business model layer** - credit-based plans, lead capture, advanced analytics, moderator team, comparison, verification, white-label, BuddyPress sync, and the reverse "Needs marketplace."

![Feature Catalog - complete capability grid](images/feature-catalog.png)

## Setup & content infrastructure

| Capability | Free | Pro |
|---|:---:|:---:|
| **Setup wizard** (6-step onboarding: type → location → maps → pages → demo → done) | Yes | Yes + Pro overlay (license, credit packs, plans, Google Maps key) |
| **Multiple listing types** (Restaurant / Hotel / Real Estate / Job Board / Place / Classified / …) | Yes 9 demo packs | Yes |
| **Custom field framework** per listing type | Yes | Yes + Pro fields (badges, criteria, services) |
| **Categories taxonomy** (`listora_listing_cat`) | Yes | Yes |
| **Locations taxonomy** (hierarchical, Country/State/City) | Yes | Yes |
| **Amenities / Features taxonomy** (flat tags) | Yes | Yes |
| **Listing Tags** | Yes | Yes |
| **Listing Type Editor** (icon, fields, schema mapping) | Yes | Yes |
| **Schema.org JSON-LD** (LocalBusiness, Restaurant, Hotel…) | Yes 10 schema types | Yes |
| **OpenGraph + Twitter cards** | Yes | Yes |
| **Breadcrumbs** | Yes | Yes |
| **Sitemap integration** (WP core sitemap) | Yes | Yes |
| **Demo content packs** (9 verticals - restaurant, hotel, real-estate, job-board, general, classified, education, healthcare, place - 128+ seeded listings) | Yes via `wp listora demo seed --pack=all` | Yes Pro overlay (`wp listora-pro demo seed`) |
| **Setup wizard auto-creates** Add Listing / My Listings / Directory pages | Yes | Yes + Compare / Buy Credits / Needs |

## Search & discovery

| Capability | Free | Pro |
|---|:---:|:---:|
| **Full-text search** (denormalized index table) | Yes | Yes |
| **Faceted filters** (category, location, feature, type) | Yes | Yes |
| **Geo radius search** (Near Me, distance) | Yes Haversine | Yes |
| **"Search this area"** (drag-to-update bounds) | Yes | Yes |
| **Sort by** date, rating, name, distance, featured | Yes | Yes |
| **Saved searches** with alerts | - | Yes |
| **Advanced search builder** (custom field filters) | - | Yes |
| **Infinite scroll** on listing grid | - | Yes |
| **Quick view modal** on cards | - | Yes |
| **SEO landing pages** (auto-generated `/type-in-location/` pages) | - | Yes |
| **Compare listings** (2-4 side by side) | - | Yes |

## Frontend blocks (Gutenberg)

| Block | Free | Pro |
|---|:---:|:---:|
| `listora/listing-grid` | Yes | Yes |
| `listora/listing-card` | Yes | Yes |
| `listora/listing-search` | Yes | Yes |
| `listora/listing-map` | Yes Leaflet + OSM | Yes + Google Maps + clustering |
| `listora/listing-detail` | Yes | Yes |
| `listora/listing-reviews` | Yes | Yes + multi-criteria + photos |
| `listora/listing-submission` (wizard) | Yes | Yes + duplicate-check + plan picker |
| `listora/listing-categories` | Yes | Yes |
| `listora/listing-featured` (carousel) | Yes | Yes + paid rotation |
| `listora/listing-calendar` (events) | Yes | Yes |
| `listora/user-dashboard` | Yes | Yes + Saved Searches + Needs tabs |
| `listora-pro/comparison` | - | Yes |
| `listora-pro/needs-grid` | - | Yes |
| `listora-pro/post-need` | - | Yes |
| `listora-pro/moderator-queue` | - | Yes |
| `listora-pro/credit-purchase` | - | Yes |

## Submission & lifecycle

| Capability | Free | Pro |
|---|:---:|:---:|
| **Frontend submission wizard** (multi-step, draft-saving) | Yes | Yes |
| **Guest submission** (with email-verification gate) | Yes | Yes |
| **Conditional fields** | Yes | Yes |
| **Draggable map pin** for address | Yes | Yes |
| **Image gallery upload** | Yes | Yes |
| **Business hours** with timezone | Yes | Yes |
| **Social links** (7 platforms) | Yes | Yes |
| **Email verification** for guest submissions | Yes | Yes |
| **Duplicate detection** at submit | - | Yes |
| **Listing renewal** (extend expiration) | Yes | Yes + credit-gated pricing |
| **Self-service deactivate / reactivate** | Yes | Yes |
| **Pricing plans** (free / paid / featured) | - | Yes |
| **Credit system** (Hold-and-Commit activation) | - | Yes |
| **Coupons** (discount codes for plans) | - | Yes |

## Reviews

| Capability | Free | Pro |
|---|:---:|:---:|
| **5-star rating + written reviews** | Yes | Yes |
| **Owner replies** to reviews | Yes | Yes |
| **Helpful-vote** + milestone notifications | Yes | Yes |
| **Report-a-review** workflow | Yes | Yes |
| **Auto-approve / require moderation** toggle | Yes | Yes |
| **Multi-criteria reviews** (per-aspect stars: Food / Service / Value …) | - | Yes |
| **Photo reviews** (reviewers attach photos) | - | Yes |

## Trust & moderation

| Capability | Free | Pro |
|---|:---:|:---:|
| **Business claims** ("Is this your business?" flow) | Yes | Yes |
| **Moderation queue** (pending listings / reviews / claims) | Yes | Yes |
| **Bulk-moderate** (REST + admin) | Yes | Yes |
| **Approve / Reject row actions** on Listings list | Yes | Yes |
| **Verification badges** (verified, top-rated, choice-of-…) | - | Yes |
| **Moderators team** (non-admin users with moderate caps) | - | Yes |
| **Audit log** (every transition recorded) | - | Yes |

## Owner / vendor tools

| Capability | Free | Pro |
|---|:---:|:---:|
| **Frontend dashboard** (My Listings overview) | Yes | Yes |
| **Edit listing** from dashboard | Yes | Yes |
| **Favorites** with collections | Yes | Yes |
| **Contact form** on listing detail | Yes Free contact form | Yes replaced by Lead Form |
| **Lead form** (analytics, custom fields, integrations) | - | Yes |
| **Services per listing** (sub-products with prices) | Yes | Yes + cross-listing service search |
| **Featured listing** (rotation entitlement) | Yes admin-set | Yes self-serve via credits |
| **Service-level booking CTA** | - | Yes |

## Marketing / Analytics

| Capability | Free | Pro |
|---|:---:|:---:|
| **Per-listing analytics** (views, clicks, contact submits) | - | Yes |
| **Directory-wide analytics** (top listings, top categories) | - | Yes |
| **Outgoing webhooks** (listing.created, review.created, …) | - | Yes |
| **Inbound payment webhooks** (Stripe / PayPal + WooCommerce / WooSubscriptions / MemberPress / PMPro / WooMemberships bridges) | - | Yes |
| **Notification digest** (daily / weekly bundle email) | - | Yes |
| **White-label** (custom brand color + logo across admin) | - | Yes |

## Anti-spam & abuse controls

| Capability | Free | Pro |
|---|:---:|:---:|
| **Honeypot** on every form | Yes | Yes |
| **Per-IP sliding-window rate limits** | Yes | Yes |
| **CAPTCHA** (reCAPTCHA v3 + Cloudflare Turnstile) | Yes | Yes |
| **Akismet integration** for reviews + claims | Yes | Yes |
| **Keyword blacklist** | Yes | Yes |
| **URL-density cap** per event | Yes | Yes |
| **Coming Soon mode** (gate the directory) | - | Yes |

## Migration & import

| Capability | Free | Pro |
|---|:---:|:---:|
| **CSV import / export** (per listing type) | Yes | Yes |
| **JSON import** | Yes | Yes |
| **GeoJSON import** (FeatureCollection) | Yes | Yes |
| **Settings JSON export / import** | Yes | Yes |
| **Competitor migrators** (Directorist / GeoDirectory / WPBDP / ListingPro) | Yes CLI + admin | Yes + Visual Importer (mapping UI) |
| **Google Places import** (single + bulk) | - | Yes |
| **Visual bulk importer** (auto-detect fields + preview) | - | Yes |

## Integrations

| Capability | Free | Pro |
|---|:---:|:---:|
| **WooCommerce** (credits via WC product) | - | Yes |
| **WooCommerce Subscriptions** | - | Yes |
| **MemberPress** | - | Yes |
| **Paid Memberships Pro** | - | Yes |
| **WooMemberships** | - | Yes |
| **BuddyPress activity sync** (listing actions → activity stream) | - | Yes |
| **Stripe / PayPal** (direct via the bundled SDK gateways) | - | Yes |
| **License / auto-updates** (Pro license server at wblistora.com) | - | Yes |
| **Akismet** | Yes | Yes |
| **Google Maps API** | - | Yes |

## Reverse marketplace ("Needs")

| Capability | Free | Pro |
|---|:---:|:---:|
| **Buyer posts a need** ("Looking for caterer in Brooklyn") | - | Yes |
| **Needs grid + filters** (type, urgency, budget) | - | Yes |
| **Business responds with a quote** | - | Yes |
| **Needs dashboard tab** for buyers + responders | - | Yes |
| **Auto-match by type + location** | - | Yes |

## Developer surface

| Capability | Free | Pro |
|---|:---:|:---:|
| **REST API** | Yes 55 routes | Yes +65 endpoints (62 unique routes) |
| **Action / filter hooks** | Yes 226 fired hooks (120 actions + 106 filters) | Yes adds extension points |
| **WP-CLI commands** | Yes 8: `stats`, `reindex`, `listing-types`, `import`, `export`, `repair`, `migrate`, `demo` | Yes Pro QA seeder (`wp listora-pro demo seed/remove`) |
| **Template overrides** (WooCommerce-style) | Yes | Yes |
| **Custom capabilities** | Yes 15 stored caps + 1 virtual (`view_listora_dashboard`) granted at runtime | Yes + 3 Pro caps (`wb_listora_pro_view_analytics`, `manage_listora_moderators`, reverse-listings caps) |
| **Interactivity API** (single store) | Yes | Yes extends |
| **Block development kit** (shared editor controls, hooks, utils) | Yes | Yes |
| **Action Scheduler** (bundled - bullet-proof background jobs) | Yes 3.9.3 vendored | Yes consumes Free's copy |

## Compatibility

| Requirement | Free + Pro |
|---|---|
| **PHP** | 7.4+ |
| **WordPress** | 6.9+ |
| **MySQL** / MariaDB | 5.7+ / 10.3+ |
| **Translation-ready** (`wb-listora` text domain) | Yes |
| **RTL** stylesheets | Yes |
| **Multisite** | Yes |
| **Headless** (Next.js / Astro / mobile via REST) | Yes |

## What this means for you

| You're building… | What you need |
|---|---|
| Open directory of free listings | **Free** only |
| Niche directory (yoga studios, food trucks, …) with submission gate | **Free** only |
| Job board for a single industry | **Free** is enough |
| Paid business directory (vendors pay to list) | **Free + Pro** |
| Premium listings with featured rotation | **Free + Pro** |
| B2B services marketplace with lead capture | **Free + Pro** (Lead Forms) |
| Reverse marketplace where buyers post needs | **Free + Pro** (Needs) |
| Multi-city / multi-vertical directory with SEO landing pages | **Free + Pro** (SEO Pages) |
| Membership-gated directory | **Free + Pro** (with MemberPress / PMP / WooMemberships) |
| Community + directory hybrid | **Free + Pro** (+ BuddyPress) |
| White-label / agency reseller | **Free + Pro** (White Label feature) |

## Related

- [Plugin comparison](comparison.md) - WB Listora vs Directorist / GeoDirectory / WPBDP / ListingPro.
- [Why WB Listora?](why-wb-listora.md) - the architectural reasoning behind these capabilities.
- [Activating Pro](getting-started/activating-pro.md) - how to add Pro on top of an existing Free install.
- [Pricing Plans (Pro)](features/pricing-plans.md) - the credit-and-plan business model.
- [User Journeys](user-journeys/site-owner.md) - what a directory operator can do, end-to-end.
