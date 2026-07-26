# WB Listora - Objection Handling Guide

For sales team use. Each entry follows: Acknowledge - Address - Confirm.

---

## 1. "Why not Directorist? It's more established."

**Acknowledge:** Directorist has been around longer and has a larger install base. That's a fair point to raise.

**Address:** WB Listora was built after studying what directory operators actually outgrow with Directorist - specifically, the payment and monetization layer. Directorist requires third-party add-ons to handle paid plans, and those add-ons don't share a common credit ledger, so vendors who top up via WooCommerce can't use those credits on a MemberPress-gated plan. WB Listora ships with a unified credit system: all 7 payment integrations (Stripe, PayPal, WooCommerce, WooSubscriptions, MemberPress, Paid Memberships Pro, WooMemberships) feed one ledger. There's also a built-in Directorist migrator - you can import your existing listings with field mapping preserved, not just a raw CSV dump.

**Confirm:** If you're running a free-listing directory with no plans to charge vendors, Directorist is fine. If you're building a paid directory - or plan to be - WB Listora gives you the monetization layer in the core product, not as a patchwork of add-ons.

---

## 2. "Is Pro actually worth it? What can't I do with Free?"

**Acknowledge:** Free is genuinely functional on its own, and we don't cripple it to force upgrades.

**Address:** The dividing line is the business model. Free gives you a complete, public, searchable directory - reviews, claims, search, geo, blocks, REST API, anti-spam, import/export, competitor migrators, 8 WP-CLI commands. Pro adds the revenue layer: credit-based pricing plans, 7 payment integrations, lead forms (replacing the basic contact form with analytics and custom fields), analytics dashboard, multi-criteria reviews, photo reviews, verification badges, moderator team with an audit log, saved search alerts, advanced search builder, SEO landing pages, BuddyPress sync, white-label, and the reverse Needs marketplace. If you're building a directory where vendors pay to list - or where you need to track who did what and when - Pro is the layer that makes that viable.

**Confirm:** Run Free first. You'll see the "Pro" placeholders in the UI (upsell prompts on plan setup, analytics, lead forms). If any of those are features you plan to build or pay someone to build, Pro is faster and cheaper.

---

## 3. "Will it scale to 50,000 listings? 100,000?"

**Acknowledge:** Scale concerns are legitimate - many directory plugins hit a wall at a few thousand listings because they rely on WP_Query meta loops.

**Address:** WB Listora maintains a denormalized `listora_search_index` table that search queries hit instead of looping post meta. Geo queries use a Haversine formula over a dedicated `listora_geo` table. REST list endpoints use cursor pagination (not OFFSET, which degrades at scale) and call `update_post_meta_cache()` and `update_object_term_cache()` before the prepare-item loop to avoid N+1 queries. Background jobs run on Action Scheduler (bundled in Free) rather than WP-Cron, which drops jobs under load. The plugin has been tested with 100K+ listings and targets under 800ms TTFB for anonymous directory pages. If you're building at extreme scale, you'll also want a caching layer (Redis, WP Super Cache) on top - WB Listora doesn't fight those.

**Confirm:** For directories up to 100K listings on reasonable hosting, WB Listora is architected to handle the load. Above that, standard WordPress scaling practices apply (object cache, read replicas) - the plugin doesn't block them.

---

## 4. "Can I migrate from Directorist / GeoDirectory / WPBDP / ListingPro?"

**Acknowledge:** Migration is one of the most common things people get wrong - they import data but lose custom fields, taxonomy mappings, or owner relationships.

**Address:** WB Listora ships four built-in competitor migrators in the Free plugin - for Directorist, GeoDirectory, WPBDP (Business Directory Plugin), and ListingPro. Each migrator knows the source plugin's database schema and maps fields to WB Listora's equivalents automatically. You can run a dry-run first (`wp listora migrate --from=directorist --dry-run`) to see what will import and what won't match before touching live data. Pro adds a Visual Importer - a UI for custom field mapping if the defaults don't cover your edge cases, plus a Google Places import for populating from scratch. If you're migrating from something not in that list, the CSV / JSON / GeoJSON importers handle structured exports from most other tools.

**Confirm:** For migrations from the four supported competitors, the migrators cover most cases out of the box. For other sources, the CSV importer gets you there with a bit of field mapping.

---

## 5. "Will it work with my theme (BuddyX, Astra, Divi, etc.)?"

**Acknowledge:** Theme compatibility is a real concern with any plugin that renders public-facing HTML - themes can override link colors, button heights, grid widths, and spacing in ways that break a plugin's design.

**Address:** WB Listora ships a theme isolation layer (`assets/css/themes/`) that neutralizes aggressive theme styles for BuddyX, Reign, and Astra. All block CSS uses scoped BEM selectors rather than global resets. Design tokens (colors, spacing, typography, radius, shadow, motion) are defined in CSS custom properties, so themes that use the same property names naturally inherit your palette, while themes that don't are blocked by specificity. The plugin is also verified at 390px (mobile), 768px (tablet), and 1280px/1440px (desktop) at every release. If your theme has a known conflict, the template override system (WooCommerce-style) lets you copy any template to `{theme}/wb-listora/` and edit it without touching the plugin.

**Confirm:** Most themes work without any configuration. If you're on BuddyX, Reign, or Astra, there are specific compatibility bridges already in the plugin. For anything else, the template override system is the escape hatch.

---

## 6. "What about updates? Will it break my site?"

**Acknowledge:** The fear of updates breaking a live site is completely valid - especially with directory plugins that touch custom database tables and custom post types.

**Address:** WB Listora follows a strict deprecation policy: public symbols (hooks, REST routes, functions, meta keys, capabilities) are never removed in the release they are deprecated. There's a minimum of two minor versions between a deprecation notice and the deletion. Patch releases (1.0.x) are bug fixes only - no behavior changes, no new features. Minor releases (1.y.0) are additive. Database schema changes require a DB_VERSION bump and a minor release minimum. Pro delivers updates via a license server at wblistora.com - updates appear in the standard WordPress dashboard update screen.

**Confirm:** There's a policy governing this, not just a promise. If you're customizing the plugin via hooks or child templates (both recommended patterns), you won't hit a breaking change without advance warning.

---

## 7. "Is it GDPR-compliant?"

**Acknowledge:** GDPR compliance for a directory plugin has several moving parts - form data, user accounts, payment records, email logs, analytics.

**Address:** WB Listora doesn't make GDPR compliance claims that belong to your site operator setup (that's your privacy policy, cookie consent, data processing agreements with payment providers). What the plugin does: it doesn't send data to any Wbcom servers beyond the Pro license check. Cloudflare Turnstile is offered as a GDPR-friendlier CAPTCHA alternative to reCAPTCHA v3 (Turnstile does not drop third-party cookies). Submissions require a logged-in WordPress account, so the data on file is standard WordPress user data. The audit log stores action timestamps and user IDs - standard WordPress user data. Payment credentials (Stripe keys, PayPal credentials) are stored in your own WordPress database, not transmitted to any third party by the plugin. For data erasure, the standard WordPress user data export and erasure hooks apply.

**Confirm:** The plugin is built to not introduce GDPR surface area beyond what's inherent in running a WordPress directory. You'll still need a compliant cookie banner, privacy policy, and DPA with any payment processors you enable.

---

## 8. "Can I get a refund if it's not right for my use case?"

**Acknowledge:** Buying a plugin without a trial is a risk, and a refund policy matters.

**Address:** WB Listora Pro is a premium plugin - the exact refund terms are published at wblistora.com/refund-policy ({{REFUND_POLICY_PLACEHOLDER}}). Before purchasing, you can: run WB Listora Free on your staging site to verify the core directory behavior, download the feature matrix from the docs to compare capabilities, or email varun@wbcomdesigns.com with a specific use-case question to get a direct answer before you buy.

**Confirm:** We'd rather help you decide correctly before purchase than handle a refund after. Ask the question before you buy.

---

## 9. "Will it slow my site down?"

**Acknowledge:** Any plugin that adds database tables, REST endpoints, and JavaScript stores is worth scrutinizing for performance.

**Address:** WB Listora uses a denormalized search index table so search queries don't loop meta rows. REST endpoints use cursor pagination and bulk cache prefetches to avoid N+1 queries. Action Scheduler handles all background jobs, removing the timing pressure from web requests. JavaScript is built on the WordPress Interactivity API (server-rendered HTML, hydrated by a single shared store) rather than a full client-side framework - blocks ship only the JS they need, with a register-only pattern for scripts that aren't rendered on every page. The performance targets are under 800ms TTFB for anonymous directory pages and Lighthouse 90+ mobile. Those are targets, not guarantees - they depend on your hosting, theme, and other active plugins. Running WP Rocket or a similar cache layer on top is compatible and recommended.

**Confirm:** The plugin is architected for performance, but "will it slow your site" depends on your whole stack. If you're on managed WordPress hosting (WP Engine, Kinsta, Flywheel) with a CDN, the overhead is minimal. On shared hosting without caching, any complex plugin will show.

---

## 10. "How long does setup take?"

**Acknowledge:** Setup time questions usually mean "will I be wrestling with documentation for a week?"

**Address:** The 6-step setup wizard (listing type, location defaults, maps provider, page creation, demo content, done) gets you to a browsable directory in under 30 minutes on a fresh WordPress install. That includes auto-creating the Add Listing, My Listings, and Directory pages with the correct blocks already placed. Seeding a demo pack (`wp listora demo seed --pack=restaurant`) adds ~20 realistic listings so you can browse a populated site immediately rather than an empty grid. Building out real taxonomy (categories, locations, amenities) and configuring notifications takes another hour or two. Pro setup (credit packs, pricing plans, payment integration) adds another 20-30 minutes if you know which payment integration you're using. End-to-end, most operators are live - with real taxonomy, anti-spam configured, and at least one payment integration working - within a day.

**Confirm:** The wizard does the scaffolding work. The time you spend is on your business decisions (how many categories, what credit pack pricing, which payment method) not on plugin configuration.

---

## 11. "What support do you offer?"

**Acknowledge:** Support quality is often more important than feature lists for long-term plugin relationships.

**Address:** WB Listora is a product from Wbcom Designs, a WordPress agency with a track record of maintained plugins. Pro license holders get priority email support at the team. Response time and SLA details are published at {{SUPPORT_POLICY_PLACEHOLDER}}. The documentation at docs.wblistora.com covers every feature with step-by-step guides, REST API reference, and developer hooks. The plugin ships with a QA smoke runbook covering every major customer flow - so issues are caught before releases reach you. For developers, the 259 hooks + 58 REST endpoints have inline PHPDoc with args signatures and example usage.

**Confirm:** There is no forum-only support tier for Pro. If you have a license, you have a direct line to the team.

---

## 12. "What if I outgrow it? Can I export my data?"

**Acknowledge:** Lock-in anxiety is reasonable - directory data (hundreds or thousands of listings, reviews, owner relationships) is hard to recreate.

**Address:** WB Listora stores all listings as WordPress posts (`listora_listing` CPT) with standard WordPress meta. There is no proprietary data silo. Export is built into the Free plugin: `wp listora export --type=restaurant --format=csv` or `--format=json`. The REST API (58 Free endpoints, 73 Pro endpoints) exposes all listing, review, favorite, and claim data in JSON - any headless client or export script can pull it. The settings export/import (`Settings → Advanced → Export Settings`) covers your configuration. If you ever need to move to a different platform, your data is accessible in standard formats without contacting Wbcom.

**Confirm:** Your data is yours. Every piece of it is readable and exportable without a special migration service.

---

## 13. "Can I customize it for my specific use case?"

**Acknowledge:** Every directory has quirks - a real estate directory needs things a restaurant directory doesn't. Generic plugins often force a lowest-common-denominator experience.

**Address:** WB Listora has three customization layers. First, the no-code layer: the Listing Type Editor lets you define custom field groups per type, control which fields appear at submission, and set the Schema.org mapping - so a real estate listing has bedrooms and square footage while a job listing has salary and employment type, with no shared compromise. Second, the template layer: any PHP template can be overridden WooCommerce-style by placing a copy in `{theme}/wb-listora/` - no child plugin needed. Third, the developer layer: 259 fired hooks (133 actions, 126 filters) cover every write operation with before-/after- pairs. The REST API is filterable at every response. Full API documentation is at docs.wblistora.com.

**Confirm:** You're not locked into the default output. The three layers cover everything from a field label rename to a fully custom listing card template to a headless React frontend.

---

## 14. "Why a credit system instead of per-listing subscriptions?"

**Acknowledge:** Subscriptions are familiar - Airbnb, Amazon Seller Central, most SaaS products work that way. Credits feel less intuitive at first.

**Address:** Credits work better for directory operators for a few reasons. Vendors don't all renew at the same time, so subscription churn is lumpy and hard to predict. Credits decouple the payment event from the activation event - a vendor can buy credits in January and use them in March when they're ready to list. The Hold-and-Commit pattern (credits are held when a plan is selected, committed only after the listing activates) means vendors never pay for a listing that doesn't go live. Credits also let operators run promotions (bonus credits on purchase, coupon codes for discounts) that would be complicated to implement on a per-subscription basis. All 7 payment integrations feed the same credit ledger, so vendors can top up via WooCommerce checkout, a membership level, or a direct Stripe payment and the credits appear in the same balance.

**Confirm:** If your use case genuinely requires recurring subscriptions (vendor pays monthly, listing stays live as long as they're subscribed), the WooCommerce Subscriptions adapter supports that model - subscription events credit the account, and credits govern plan renewal. The credit system is the rails; subscriptions can run on those rails.

---

## 15. "How do you compare to GeoDirectory?"

**Acknowledge:** GeoDirectory is the most direct competitor for large multi-city directories, and it has a strong reputation for geo and multi-city setups.

**Address:** GeoDirectory's core strength is multi-city architecture - it's designed from the ground up for city-specific installs sharing a listing database. WB Listora takes a different approach: a single install with a hierarchical location taxonomy (Country, State, City, Neighborhood) and geo radius search. For most directory operators - city guides, niche verticals, regional marketplaces - a single install with a well-structured location taxonomy is simpler to operate and avoids the network-of-sites overhead. Where WB Listora has a structural advantage is the monetization layer: GeoDirectory's payment handling requires multiple add-ons, while WB Listora ships 7 payment integrations in one product with a unified credit ledger. The developer surface is also broader: 259 hooks vs. GeoDirectory's hook count (unconfirmed - check their changelog), a full Interactivity API implementation, and 120 total REST endpoints across Free + Pro. The built-in Needs marketplace (reverse listings where buyers post requests) has no direct equivalent in GeoDirectory.

**Confirm:** For a true multi-city franchise model (e.g., YellowPages for 50 cities with independent operators), GeoDirectory's architecture may be a better fit. For a single-operator directory covering one region or niche, WB Listora gives you more monetization capability in a simpler operational model.
