# WB Listora — SaaS-Level Feature Gap Analysis (2026-05-31)

Method: agentic. HAVE inventory built from `audit/manifest.json` + `FEATURE_AUDIT.md` + CLAUDE.md (Free + Pro). Benchmarks built from web research of WP directory plugins (GeoDirectory, Directorist, HivePress, Business Directory) and SaaS directory platforms (Brilliant Directories, eDirectory, Wild Apricot, Sharetribe/Bubble).

**Manifest freshness:** manifest stamped 2026-05-24; 233 files changed since, all bug fixes — verified **zero new REST routes / hooks / blocks / CPTs / taxonomies** added. Counts (55+65 REST, 11+5 blocks, 226+198 hooks, 6+7 cron) are still accurate. Only the timestamp + "Recent Changes" narrative are stale. A full `/wp-plugin-onboard --refresh` is a re-stamp, not a feature change — defer to release time.

---

## Verdict in one line

WB Listora is **already competitive-to-ahead on WP directory table-stakes**, and has several genuinely SaaS-grade foundations (credit economy, Hold/Commit, 5 payment adapters, outgoing webhooks+HMAC, reverse-marketplace "Needs", 100k-readiness, 120 REST endpoints, white-label). The gaps that separate it from a **SaaS-level** product are concentrated in: **AI, messaging/lead-inbox economy, bookings, owner-facing analytics, native subscriptions/dunning, and email marketing.**

---

## A. Table-stakes (WP directory market) — WB status

| Capability | WB Listora | Notes |
|---|:--:|---|
| Custom listing types + unlimited custom fields | ✅ | 10 types, Field_Registry, social_links, business hours |
| Front-end submission (+ guest) | ✅ | wizard, conditional fields, guest registration, drafts, edit |
| Advanced/faceted search on custom fields | ✅ | Pro Advanced_Search + field_index facets |
| Maps + geo/radius search | ✅ | Leaflet (Free) / Google (Pro) + clustering + "search this area" |
| Reviews & ratings (multi-criteria, photo) | ✅ | Pro multi-criteria + photo reviews + replies + votes |
| Claim listings | ✅ | claims table, modal, admin transfer |
| Paid listings / packages / featured | ✅ | Pro pricing plans + credits + featured |
| Multiple payment gateways | ✅ | WooCommerce/WooSubs/MemberPress/PMPro/WooMemberships adapters |
| Front-end user dashboard | ✅ | listings/reviews/claims/credits/profile/notifications |
| CSV/JSON/GeoJSON import + export | ✅ | + competitor migrators (Directorist/GeoDir/WPBDP/ListingPro) — **ahead of market** |
| SEO + schema.org | ✅ (partial) | Schema_Generator + Pro programmatic SEO pages; **no XML sitemap module** |
| Owner contact / lead form | ✅ | Free Contact_Form + Pro Lead_Form |
| Anti-spam (reCAPTCHA/Turnstile/Akismet) | ✅ | **ahead of market** |
| Gutenberg blocks | ✅ | 16 blocks; **no Elementor/Bricks/Divi widgets** |
| Multilingual + RTL | ✅ | WPML config + auto RTL twins |
| Custom emails / notifications | ✅ | 14 templates + Pro digests |
| Listing comparison | ✅ | Pro comparison block |
| Headless REST API | ✅ | 120 endpoints, consistent envelopes — **ahead of market** |
| Performance at scale | ✅ | Action Scheduler, indexing, 100k-readiness — **ahead of market** |

**Conclusion:** WB clears essentially all WP-directory table-stakes. Two table-stakes weak spots: **(a) no dedicated XML sitemap**, **(b) Gutenberg-only (no page-builder widgets)** — GeoDir/Directorist lean heavily on page builders.

---

## B. SaaS-level gaps (priority-ranked)

### P0 — Close to expectation, high demand, leverage existing assets

| Gap | Status | Why it matters / leverage |
|---|:--:|---|
| **AI suite** (listing description gen, review reply/summary, AI moderation, AI SEO meta) | ❌ Missing | The defining 2026 expectation. eDirectory v13.8 + Directorist set the bar. Highest perceived-value, relatively self-contained. Wbcom already ships AI elsewhere. |
| **Messaging / unified inbox** (user↔owner, vendor inbox) | ❌ Missing | Only one-way contact/lead email today. HivePress Messages + Directorist Live Chat are table-stakes-trending. Foundation for the lead economy below. |
| **Lead economy** (per-vendor lead inbox + monetized **lead credits** / pay-per-lead) | ⚠️ Partial | Lead forms exist but no inbox or monetization. **Huge leverage: the credit system + webhooks already exist** — charging credits per lead is incremental, and it's a core BD/eDirectory SaaS revenue model. |
| **Owner/vendor analytics dashboard** (views, leads, conversions per listing) | ⚠️ Partial | Pro Analytics captures events; needs a vendor-facing dashboard. SaaS expects dual-audience analytics (operator + vendor). Data is largely already collected. |
| **Bookings / appointments / reservations** | ❌ Missing | HivePress + Directorist offer it; Sharetribe core. Calendar block exists (events) but no paid booking/availability. Migrating from differentiator → expectation. |

### P1 — Strong SaaS differentiators, meaningful build

| Gap | Status | Notes |
|---|:--:|---|
| **Native recurring subscriptions + dunning** (own tiers, auto suspend/reactivate on failed payment) | ⚠️ Partial | Today recurring is delegated to external membership plugins via adapters; credits paused/resume is close but credit-based. SaaS expects turnkey subscription tiers + dunning. Could formalize on top of the credit/plan engine. |
| **Email marketing / newsletters / drip automation** | ❌ Missing | Transactional emails only. WA/BD ship newsletters + segmentation + open/click. Natural integration with Groundhogg/Mailchimp (Wbcom already runs Groundhogg). |
| **Ad / sponsored-slot monetization** (banner CPM/CPC, AdSense slots, sponsored placement beyond "featured") | ❌ Missing | eDirectory's monetization stack. Operator revenue lever. |
| **Page-builder widgets** (Elementor/Bricks/Divi) | ❌ Missing | Market leans on page builders; Gutenberg-only narrows the addressable market. Decision needed: commit to FSE-only or add widgets. |
| **XML sitemap + deeper SEO automation** | ⚠️ Partial | Add a listing sitemap + per-listing meta automation rather than relying on a third-party SEO plugin. |

### P2 — Niche / nice-to-have

| Gap | Status | Notes |
|---|:--:|---|
| Native mobile app / PWA | ❌ | Directorist app; eDir/WA native. Headless REST already enables a PWA path. |
| Multi-currency pricing/payout | ⚠️ | Credits abstract currency; explicit multi-currency not surfaced. |
| Consumer "deals/offers/coupons + QR" on listings | ⚠️ | Coupons today are purchase discounts, not consumer-facing listing deals (eDirectory). |
| Social importers (FB / Google Business / Yelp) | ❌ | GeoDirectory differentiator for real-world data onboarding. |
| Zapier app / documented public API marketplace | ⚠️ | Outgoing webhooks + HMAC exist; a Zapier app + public API docs would complete it. |
| Franchise / multi-location management | ⚠️ | Hierarchical location taxonomy exists; no franchise manager. |
| Display-view variety (multiple skins) | ⚠️ | grid/list/map; not 80+ skins (Listdom). |
| GDPR consent + data export/erasure | ⚠️ | a11y mostly present (2 minor aria gaps); GDPR tooling not headlined. |

---

## C. Where WB is already SaaS-grade or ahead (keep marketing these)

- **Credit economy + Hold/Commit + 5 payment adapters** — more robust than most competitors' "paid listings."
- **Reverse marketplace ("Needs")** — matches HivePress Requests, rare in the category.
- **Outgoing webhooks + strict HMAC + replay defense** — real integration surface.
- **Import/export + 4 competitor migrators** — best-in-class onboarding/migration.
- **100k-readiness** (Action Scheduler, denormalized indexes, cursor pagination, N+1 prefetch) — genuinely SaaS-scale engineering.
- **Headless REST (120 endpoints, consistent envelopes, RFC-3339)** — headless/PWA ready.
- **White-label + coming-soon + visibility modes** (Pro).
- **Anti-spam stack** (reCAPTCHA v3 + Turnstile + Akismet + rate limiting).

---

## D. Recommended sequencing (highest leverage first)

1. **Lead inbox + lead credits** — reuses the credit system + webhooks; turns an existing weak spot (one-way lead email) into a monetizable SaaS feature. Pairs with messaging.
2. **AI suite** — listing description generation + review reply/summary + AI moderation; highest perceived value, self-contained, on-brand for Wbcom.
3. **Vendor analytics dashboard** — surface already-captured Pro analytics to listing owners.
4. **Bookings/appointments** — extend the calendar block into paid availability/reservations.
5. **Native subscriptions + dunning** — formalize recurring tiers on the plan/credit engine.
6. **Email marketing integration** (Groundhogg/Mailchimp) + **ad/sponsored slots** + **XML sitemap**.
7. Decide the **page-builder** question (FSE-only vs Elementor/Bricks widgets) — it's a market-reach strategy call, not just a build.

These are roadmap items beyond 1.1.0 (1.1.0 is the bug-fix release). This doc is the input for the 1.2.0+ feature roadmap.
