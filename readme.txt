=== WB Listora - Directory & Listing Plugin ===
Contributors: wbcomdesigns
Tags: directory, listings, business directory, classifieds, maps
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The complete WordPress directory plugin. Create any type of listing directory — business, restaurant, hotel, real estate, jobs, events, and more.

== Description ==

WB Listora is a modern, block-based WordPress directory plugin that lets you create any type of listing directory. Built with the WordPress Interactivity API for lightning-fast search and filtering without page reloads.

= Key Features =

* **10 Pre-built Listing Types** — Restaurant, Hotel, Real Estate, Business, Job, Event, Healthcare, Education, Place, Classified
* **Two-Phase Search Engine** — FULLTEXT search with custom field filtering, geo queries, and faceted counts
* **11 Gutenberg Blocks** — Search, Grid, Card, Detail, Map, Reviews, Submission, Dashboard, Categories, Featured, Calendar
* **Interactive Map** — OpenStreetMap with marker clustering, near-me, and drag search
* **Frontend Submission** — Multi-step wizard with type selection, field validation, and media upload
* **User Dashboard** — Manage listings, reviews, favorites, and profile from the frontend
* **Reviews & Ratings** — Star ratings, helpful votes, owner replies, and review moderation
* **Business Hours** — Weekly schedule with open now filtering
* **Claim Listings** — Let business owners claim and manage their listings
* **Favorites** — Save listings with collections support
* **CSV Import/Export** — Bulk import listings with column mapping
* **WP-CLI** — Stats, reindex, repair, import, export, demo commands
* **Schema.org** — Automatic JSON-LD structured data for SEO
* **Theme Adaptive** — Uses CSS custom properties that inherit from theme.json
* **Accessible** — ARIA roles, keyboard navigation, screen reader support
* **Developer Friendly** — 30+ hooks, REST API with 41 endpoints, extensible architecture

= Pro Features =

Upgrade to [WB Listora Pro](https://wbcomdesigns.com/downloads/wb-listora-pro/) for:

* Google Maps integration
* Payment & credit system
* Pricing plans with plan selection in submission
* Analytics dashboard (views, clicks, CTR)
* Lead contact form
* Side-by-side comparison
* Multi-criteria reviews
* Photo reviews
* Saved search alerts
* Verification badges
* Notification digest emails

== Installation ==

1. Upload `wb-listora` to `/wp-content/plugins/`
2. Activate the plugin
3. Follow the setup wizard to configure listing types, location, and create pages
4. Add listings via the admin or use the demo content generator

== Frequently Asked Questions ==

= What listing types are supported? =

WB Listora includes 10 pre-built types: Restaurant, Hotel, Real Estate, Business, Job, Event, Healthcare, Education, Place, and Classified. Each type has its own custom fields, categories, and card layout.

= Does it work with any theme? =

Yes. WB Listora uses CSS custom properties that automatically adapt to your theme's colors, typography, and spacing via theme.json. It works with both block themes and classic themes.

= Is coding required? =

No. Everything is configured through the block editor and admin settings. Just place the blocks on your pages and customize the attributes.

= How does the search engine work? =

WB Listora uses a two-phase search architecture. Phase 1 queries a denormalized search index with FULLTEXT support for fast keyword matching. Phase 2 filters by custom field values. Geo queries use bounding box pre-filtering with Haversine distance calculation.

== Screenshots ==

1. Directory listing page with 3-column grid and search bar
2. Single listing detail page with tabs, contact sidebar, and business hours
3. Frontend submission form with multi-step wizard
4. User dashboard with listing management
5. Search with type tabs and advanced filters

== Changelog ==

= 1.0.0 =
* Initial release
* 10 listing types with custom fields
* Two-phase search engine with FULLTEXT and geo queries
* 11 Gutenberg blocks
* Frontend submission and user dashboard
* Reviews, favorites, and claims system
* CSV import/export and WP-CLI commands
* Schema.org structured data

== Upgrade Notice ==

= 1.0.0 =
Initial release of WB Listora directory plugin.
