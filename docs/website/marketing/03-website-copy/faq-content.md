# WB Listora — Frequently Asked Questions (25 Q&A)

Organized by topic: Product, Setup, Features, Payments, Technical, Support.

---

## Product FAQ

### 1. What is WB Listora?

WB Listora is a WordPress plugin that turns any website into a business directory. Free gives you a complete public directory — search, filters, reviews, business claims, frontend submission, maps, spam protection, and Schema.org markup. Pro adds the business model layer: credit-based pricing plans, lead capture with analytics, verification badges, side-by-side comparison, a moderators team, and a reverse marketplace where buyers post needs and businesses respond with quotes.

### 2. Is WB Listora really free? What's the catch?

Free is genuinely free — not a trial, not a time-limited demo. It includes 11 Gutenberg blocks, a full search engine with geo and faceted filters, reviews, business claims, frontend submission, 6-layer anti-spam, 9 demo packs, 55 REST endpoints, 8 WP-CLI commands, and 28+ email templates (15 in Free). You can run a real, production directory on Free alone and never need Pro. Pro is the upgrade for when you want to charge vendors or need the trust/analytics infrastructure.

### 3. Where can I download WB Listora?

WB Listora is a private plugin distributed by Wbcom Designs. It is not published to the WordPress.org plugin repository. Download and license information is at wblistora.com.

### 4. Does Pro work without Free installed?

No. WB Listora Pro requires the Free plugin to be active. Pro is a pure extension — it consumes Free's hooks, REST endpoints, blocks, and database tables. If Free is deactivated, Pro deactivates itself automatically. This is by design: you never lose the public directory when Pro's license expires.

### 5. What kinds of directories can I build?

Any type of listing-based directory. The 9 built-in demo packs cover restaurants, hotels, real estate, job boards, general business directories, classified ads, education, healthcare, and places. You can also create custom listing types with their own field sets, icons, and Schema.org types.

---

## Setup FAQ

### 6. How long does setup take?

The 6-step setup wizard takes about 30 minutes from a fresh WordPress install to a working directory with demo content. The wizard auto-creates the Add Listing, My Listings, and Directory pages — already wired with the correct blocks. You don't need to add any shortcodes or build pages from scratch.

### 7. Does WB Listora work with my theme?

Yes. WB Listora is a block-first plugin — any modern block theme works. It also supports classic themes through WooCommerce-style template overrides. The plugin ships a theme bridge for BuddyX Free. We test against BuddyX Pro, Astra, Kadence, and GeneratePress.

### 8. What are the minimum requirements?

WordPress 6.9+, PHP 7.4+, MySQL 5.7+ or MariaDB 10.3+. A modern block theme is recommended but not required.

### 9. Can I try it with demo data before adding real listings?

Yes. Use `wp listora demo seed --pack=all` to load all 9 demo packs (128+ listings across verticals). You can also seed individual packs: `--pack=restaurant`, `--pack=hotel`, etc. Remove demo data at any time with `wp listora demo remove`.

### 10. How do I migrate from my current directory plugin?

Built-in migrators handle Directorist, GeoDirectory, WPBDP (Business Directory Plugin), and ListingPro. Run `wp listora migrate --from=directorist --dry-run` to preview before importing. Pro adds a Visual Importer with field auto-detection and a preview step. For other sources, use CSV, JSON, or GeoJSON import — all included in Free.

---

## Features FAQ

### 11. What search capabilities does the free version include?

Full-text search on a denormalized index table (not a slow `LIKE` query on post content), faceted filters (category, location, feature, listing type), geo radius search with Near Me, "Search this area" drag-to-update map bounds, and sort by date, rating, name, distance, or featured. URL state is preserved so visitors can share exact filter combinations.

### 12. Can visitors compare listings?

Yes — with Pro. The comparison feature lets visitors save up to 4 listings and view them side by side in a configurable table (core info, pricing, features, ratings, services, hours). A floating comparison bar tracks selections across the whole site. Comparison state persists via localStorage and is shareable via URL.

### 13. How do business claims work?

A "Claim this business" button appears on every listing that hasn't been claimed. The business owner clicks it, uploads proof of ownership, and the site admin reviews the claim. On approval, the listing's author field transfers to the claimant — they can then edit the listing, reply to reviews, and manage services from their dashboard. The original listing is never duplicated.

### 14. What review features are included in Free?

Free includes 5-star ratings with written reviews, owner public replies, helpful-vote tracking with milestone notifications, a report-a-review workflow, and a moderation toggle (auto-approve or queue for review). Pro adds multi-criteria ratings (rate individual aspects like Food, Service, Value, Ambiance separately) and photo reviews (reviewers can attach photos).

### 15. Does the plugin send emails automatically?

Yes. Free ships 15 email templates covering new submission, approval/rejection, expiration, renewal, claimed listing, review received, helpful-vote milestones, contact form, email verification, draft reminder, and admin notifications. Pro adds 13 more templates including lead form notification, plan activated, paused listing, listing resumed, and saved-search alerts. All templates are overrideable from your theme.

---

## Payments FAQ

### 16. Which payment gateways are supported?

Pro supports 7 payment paths through the Wbcom Credits SDK: WooCommerce, WooCommerce Subscriptions, MemberPress, Paid Memberships Pro, WooMemberships (5 SDK adapters), plus Stripe direct and PayPal direct via the inbound webhook receiver. Every top-up from any gateway — regardless of source — flows through the same credit ledger and automatically resumes paused listings.

### 17. How does the credit system work?

Site owners define credit packs (e.g. 10 credits for $40) and pricing plans (e.g. Basic plan costs 5 credits/month). Vendors buy credits and use them to activate listing plans. The Hold-and-Commit model means credits are held when a plan is selected and only committed when the listing activates — if activation fails for any reason, the hold is canceled and the credits are returned.

### 18. Can I offer discounts?

Yes. Pro includes a coupon manager. Coupons can be percentage-based or flat discount, single-use or multi-use, with optional expiration dates. Vendors enter the coupon code during plan checkout.

### 19. What happens when a listing expires?

When a listing's expiration date passes, it transitions to Expired status and is hidden from the public directory. A 7-day expiry reminder email fires automatically before expiration. Renewal is manual — the vendor clicks Renew in their dashboard, which calls `GET /listings/{id}/renewal-quote` for a price preview, then `POST /listings/{id}/renew` to extend. There is no automatic renewal.

### 20. Can I run a free directory where vendors don't pay?

Yes. Pro's payment features are opt-in. If you don't create any paid plans, the credit and payment systems stay dormant. You can add paid plans at any point in the future without rebuilding anything.

---

## Technical FAQ

### 21. Does WB Listora support headless WordPress?

Yes. The REST API (55 endpoints in Free, 65 in Pro) exposes every directory surface. Listing browsing, filtering, submission, reviews, favorites, claims, and user dashboard data all have REST endpoints. The plugin ships with a single Interactivity API store for the built-in blocks, but external frontends built in Next.js, Astro, or a mobile app framework can consume the REST API directly.

### 22. How do I extend WB Listora with custom code?

The plugin fires 226 documented hooks (120 actions + 106 filters) — all with argument signatures listed in `audit/manifest.json`. Every block template is overrideable WooCommerce-style (copy to `{theme}/wb-listora/blocks/{block-name}/`). REST responses are filterable via `wb_listora_rest_prepare_*` filters. WP-CLI commands cover stats, reindexing, listing-type management, import/export, repair, migration, and demo data.

### 23. Is it compatible with WordPress Multisite?

Yes. WB Listora supports Multisite. Each site in the network runs its own independent directory with its own tables, settings, and listing types.

### 24. How does the plugin handle performance at scale?

The search engine uses a dedicated `listora_search_index` table with full-text indexing — not `WP_Query` on custom fields. Geographic queries use an optimized Haversine calculation against a separate `listora_geo` table. Action Scheduler (bundled in Free at version 3.9.3) handles all background jobs — expiration checks, email sends, index rebuilds — so they don't block page loads. The REST API prefetches post meta and term caches before prepare-item loops to avoid N+1 queries.

---

## Support FAQ

### 25. Where do I get support?

Support for Free and Pro is provided by Wbcom Designs at support@wbcomdesigns.com. Pro license holders get priority support response. Documentation is at wblistora.com. For developer questions, the REST API reference, hook index, and WP-CLI command documentation are included in the plugin at `docs/`.
