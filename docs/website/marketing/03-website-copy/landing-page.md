# WB Listora - Landing Page Copy

Full copy for wblistora.com. Uses Variant A from `landing-headlines.md` as the chosen H1. All screenshot references are from `docs/website/images/`.

---

## Hero Section

**H1:** Run a fully-monetizable business directory on WordPress.

**Sub:** Free covers the public directory - search, reviews, claims, frontend submission, anti-spam. Pro adds the business model: credit plans, lead capture, verification badges, comparison, moderators, and a reverse marketplace.

**Primary CTA:** Get Free
**Secondary CTA:** See Pro features

**Hero image:** `home-frontend.png`

---

## Social Proof Bar 1 (below hero, before scroll)

Built by Wbcom Designs - WordPress 6.9+ compatible - PHP 7.4+ - works with any block theme

**Trust logos row:** WordPress | BuddyPress | WooCommerce | Stripe

---

## Section 1 - What you get with Free

**Heading:** A complete public directory. At no cost.

**Sub:** Free isn't a demo. It's a fully-functioning directory plugin you can launch on tomorrow and never upgrade.

### Feature Card 1: Search and Filters
**Heading:** Faceted search that works at any scale.

Full-text search on a dedicated index table - not a slow `LIKE %query%` on post content. Geo radius search with Near Me, drag-to-update map bounds, URL-preserved filter state so visitors can share exact searches.

**Image:** `search-and-filters.png`

### Feature Card 2: Frontend Submission Wizard
**Heading:** Vendors submit without touching wp-admin.

Multi-step guided form with draft auto-saving, conditional fields, draggable map pin, image gallery upload, and business hours. Guest submissions with email verification - no forced account required.

**Image:** `frontend-submission.png`

### Feature Card 3: Reviews and Claims
**Heading:** Trust infrastructure built in.

5-star reviews with written feedback, owner public replies, helpful votes, and a report-a-review workflow. Business claims transfer ownership cleanly - no duplicate listings, no manual database edits.

**Image:** `reviews-system.png`

### Feature Card 4: Anti-Spam - 6 Layers
**Heading:** Real protection, not checkbox compliance.

Honeypot, per-IP rate limits, reCAPTCHA v3 or Cloudflare Turnstile, Akismet, keyword blacklist, and URL density cap - all layered, all active by default. Spam has to defeat all six.

**Image:** `spam-protection-settings.png`

### Feature Card 5: Maps
**Heading:** Geo search and interactive map - included.

Leaflet and OpenStreetMap in Free. Draggable map pins, "Search this area" bounds drag, cluster markers, geo radius filters. Works without a paid API key.

**Image:** `settings-map.png`

### Feature Card 6: Modern WordPress Blocks
**Heading:** 11 native Gutenberg blocks. No page-builder required.

Listing grid, card, search, map, detail, reviews, submission, categories, featured carousel, calendar, and user dashboard. Every block has responsive controls, per-instance CSS scoping, and 20 standard attributes.

**Image:** `blocks-overview.png`

---

## Social Proof Bar 2 (between Free and Pro sections)

226 documented hooks - 55 REST endpoints - 8 WP-CLI commands - 10 Schema.org types

---

## Section 2 - Pro adds the business model

**Heading:** When you're ready to charge vendors, Pro is ready.

**Sub:** Install Pro on top of Free. Your existing directory stays exactly as it is. Pro adds the revenue and trust layer on top.

### Feature Card 1: Credits and Pricing Plans
**Heading:** Charge vendors on your terms.

Define credit packs and pricing plans. Vendors buy credits and activate plans. Hold-and-Commit activation means no partial charges on failed listings - credits are held at plan selection and committed only when the listing goes live.

**Image:** `credits-and-plans.png`

### Feature Card 2: Lead Forms
**Heading:** Every contact is a trackable lead.

Lead forms replace the basic contact form with analytics, custom questions, and per-listing conversion tracking. Every fill arrives in the owner's inbox with Reply-To set - one click to reply.

**Image:** `lead-forms.png`

### Feature Card 3: Verification Badges
**Heading:** Verified vendors stand out.

Admin-defined badge types (Verified Owner, Top Rated, Editor's Choice) with configurable icons, colors, and optional expiration. Badges render on listing cards, detail pages, and in search result facets.

**Image:** `verification-badges.png`

### Feature Card 4: Side-by-Side Comparison
**Heading:** Let visitors compare - then convert.

Heart-save up to 4 listings, hit Compare, see them side by side. Floating comparison bar tracks selections across the whole site. State persists via localStorage and shares via URL.

**Image:** `compare-listings.png`

### Feature Card 5: Needs Marketplace
**Heading:** Flip the directory. Buyers post. Businesses respond.

Buyers post what they need ("caterer for 200 guests in Austin"). Matching businesses filter open requests and respond with a quote. Two flywheels in one plugin - both feed each other.

**Image:** `needs-marketplace.png`

### Feature Card 6: Moderators and Analytics
**Heading:** Delegate safely. Measure everything.

Grant team members exactly the moderation capabilities they need - nothing more. Full audit trail of every action. Per-listing and directory-wide analytics: views, clicks, leads, top categories, conversion rates.

**Image:** `moderators.png`

---

## Section 3 - Built on modern WordPress

**Heading:** No shortcuts. No legacy debt.

**Sub:** WB Listora is built on the APIs WordPress ships today - not the patterns from 2016 that still haunt most directory plugins.

**Four pillars:**

**Gutenberg-native:** 11 Free blocks + 5 Pro blocks. All editor-configurable. No shortcodes required (one optional shortcode exists for the comparison embed).

**Interactivity API:** Single shared store for all IAPI-powered blocks. Real-time filtering, map updates, and form interactions without a jQuery dependency.

**REST-first:** 55 REST endpoints in Free, 65 in Pro. Every surface accessible to headless frontends, mobile apps, and external integrations.

**Action Scheduler:** Vendored in Free - expiration crons, email sends, reindexing jobs, and digest batches all run on AS, not brittle WP-Cron.

---

## Section 4 - Compatibility

**Heading:** Works with the stack you already have.

| What | Details |
|---|---|
| WordPress | 6.9+ |
| PHP | 7.4+ |
| Themes | Any block theme or classic theme with template override support |
| Payment gateways | Stripe (direct), PayPal (direct), WooCommerce, WooCommerce Subscriptions, MemberPress, Paid Memberships Pro, WooMemberships |
| Community | BuddyPress activity sync |
| Maps | Leaflet/OSM (Free), Google Maps with custom styles (Pro) |
| Internationalization | Translation-ready (`wb-listora` text domain), RTL stylesheets included |
| Multisite | Supported |
| Headless | Next.js, Astro, mobile apps via REST |

---

## Section 5 - Testimonials (Placeholder)

**Heading:** What directory operators are saying.

*Five testimonial placeholders - fill in from real customer quotes before publishing. See `06-sales-materials/testimonials.md` for structured placeholders.*

---

## Section 6 - Pricing

**Heading:** Start free. Add Pro when you're ready.

**Free:**
- Always free
- Download from wblistora.com (the plugin is distributed direct - never via wordpress.org)
- Full public directory
- 11 Gutenberg blocks
- Search, reviews, claims, submission, spam protection
- 55 REST endpoints, 8 WP-CLI commands

**Pro:**
{{PRICING_PLACEHOLDER}}
- Everything in Free
- 32 Pro feature modules
- Credits, plans, lead forms, verification, comparison, moderators, needs marketplace, analytics, white-label, BuddyPress, and more
- License from wblistora.com

*Note: actual pricing pulled from live wblistora.com store. Do not hardcode prices in this document.*

---

## Section 7 - FAQ Link

**Heading:** Questions before you start?

Read the [full FAQ](faq-content.md) - 25 questions covering setup, features, payments, migration, and compatibility.

Or email us at support@wbcomdesigns.com.

---

## Footer CTA Section

**Heading:** Your directory is 30 minutes away.

**Sub:** Install Free, run the wizard, load demo data. You'll have a working directory before your coffee goes cold.

**Primary CTA:** Get Free
**Secondary CTA:** See Pro features

**Social proof:** Built by Wbcom Designs · WordPress 6.9+ · PHP 7.4+ · Active development
