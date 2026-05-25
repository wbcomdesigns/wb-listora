---
title: WB Listora - Sales One-Pager
format: pandoc-pdf
date: 2026-05-24
---

# WB Listora

**The directory plugin that lets WordPress sites earn revenue from listings - without stitching together five separate plugins.**

---

## The Problem

Most WordPress directory plugins are either too simple to monetize or so complex that setup takes weeks. Operators end up bolting together a form plugin, a payment gateway, a search extension, and a reviews add-on - and then wondering why they break each other on every update.

- Listing plugins that can't charge vendors force operators to use separate WooCommerce products, PayPal buttons, or manual invoicing.
- Plugins built for simplicity lack the API surface developers need to build headless frontends or mobile apps.
- Migrating from an existing tool (Directorist, GeoDirectory, WPBDP) means exporting CSV files, losing custom fields, and rebuilding from scratch.

---

## The Solution

WB Listora is a Free + Pro pair built on a single codebase. Free gives you a complete, public, searchable directory. Pro layers on the business model - credit-based plans, lead capture, analytics, multi-criteria reviews, verification badges, BuddyPress sync, and a reverse "Needs" marketplace.

- One setup wizard (6 steps) gets you from empty site to populated, searchable directory in under 30 minutes.
- Credits are the sole currency across all 7 payment integrations (Stripe, PayPal, WooCommerce, WooSubscriptions, MemberPress, Paid Memberships Pro, WooMemberships) - no per-gateway logic to maintain.
- Migrate from Directorist, GeoDirectory, WPBDP, or ListingPro with built-in migrators - field mapping happens at import, not after.

---

## 5 Key Features

**1. Credit-and-Plan monetization with Hold-and-Commit**
Define credit packs and pricing plans (Basic / Featured / Premium). When a vendor submits a listing, credits are held and only committed after the listing activates - no partial charges, no manual refunds. Seven payment integrations all feed the same credit ledger, so vendors can top up via WooCommerce checkout, a MemberPress membership, or a Stripe webhook and get the same result.

**2. 226 hooks + 55 Free REST routes + 65 Pro REST routes**
Every write operation fires a before- and after-hook. Every REST response is filterable. Developers can extend behavior, build headless frontends (Next.js, Astro), or connect mobile apps without touching core files.

**3. 6-layer anti-spam**
Honeypot on every form, per-IP sliding-window rate limits, reCAPTCHA v3 or Cloudflare Turnstile, Akismet on reviews and claims, keyword blacklist, and URL-density cap per submission. Operators can fine-tune or disable layers individually.

**4. 9 demo packs + 11 Free blocks + 5 Pro blocks**
Seed a restaurant, hotel, real estate, job board, classified, education, healthcare, event, or general directory with realistic content in one CLI command. Every block ships with 20 responsive attributes, InspectorControls, and WooCommerce-style template overrides.

**5. Reverse marketplace (Needs)**
Buyers post what they are looking for - "catering for 200 guests in Brooklyn, budget $4,000." Vendors browse, filter by type and urgency, and respond with a quote. Operators can charge a response fee, making every buyer post a revenue event.

---

## Who It's For

- **Directory operators** building city guides, niche listings, or local services hubs who want to charge vendors for placement.
- **Agencies** building client directories as a productized service - white-label mode removes all Wbcom branding.
- **Developers** who need a headless-ready, hook-rich foundation rather than a closed shortcode system.
- **Marketplace builders** who want buyer-posted needs alongside vendor listings on the same site.
- **Membership site owners** who already run MemberPress or Paid Memberships Pro and want to grant listing credits as a membership benefit.

---

## What's Included

**WB Listora Free**
- Complete directory (search, facets, geo, reviews, claims, favorites, submission wizard)
- 11 Gutenberg blocks, 9 demo packs, 55 REST endpoints
- 226 hooks, 8 WP-CLI commands, CSV / JSON / GeoJSON import
- Built-in competitor migrators (Directorist, GeoDirectory, WPBDP, ListingPro)
- Action Scheduler bundled - no external cron required
- WordPress 6.9+, PHP 7.4+, Multisite compatible, RTL ready

**WB Listora Pro (requires Free)**
- Credit system with Hold-and-Commit plan activation
- 7 payment integrations (Stripe + PayPal direct, 5 SDK adapters)
- 65 additional REST endpoints, 5 blocks, 32 feature modules
- Reverse marketplace (Needs), multi-criteria reviews, photo reviews
- Lead forms, analytics, verification badges, moderator team, audit log
- BuddyPress activity sync, white-label, coming-soon gate
- Saved search alerts, advanced search builder, SEO landing pages

---

## Pricing

**Free:** {{PRICING_PLACEHOLDER_FREE}} - available at wblistora.com

**Pro:** {{PRICING_PLACEHOLDER_PRO}} - see wblistora.com for tier details and renewal terms

---

## Social Proof

> {{TESTIMONIAL_PLACEHOLDER_1}}

> {{TESTIMONIAL_PLACEHOLDER_2}}

{{REVIEW_RATING_PLACEHOLDER}} from {{CUSTOMER_COUNT_PLACEHOLDER}} customers

---

## Get Started

**Try Free:** Download WB Listora from wblistora.com and run the setup wizard in under 30 minutes.

**Buy Pro:** Visit {{PRICING_PLACEHOLDER_PRO_URL}} to add the business model layer on top of your Free install.

Questions? Contact the team at varun@wbcomdesigns.com or use the live chat at wblistora.com.
