# WB Listora vs GeoDirectory — Competitive Closure Plan

**Goal:** Reach feature parity with GeoDirectory in 90 days so we can credibly position WB Listora to mature directory customers, not just greenfield projects.

**Non-goal:** Match every GeoDirectory addon. We pick the 80/20 — features that 90% of directory customers actually use.

---

## Scoring legend

| | |
|---|---|
| 🟢 | We win or match |
| 🟡 | Comparable but they have polish/depth advantage |
| 🔴 | They beat us materially |
| ⚫ | Not applicable / niche |

---

## 1 · Core directory features

| Feature | GeoDirectory | WB Listora | Status | Plan |
|---|---|---|---|---|
| Listing CPT + custom fields | ✓ | ✓ 23 field types | 🟢 | None |
| Multiple listing types | ✓ via CPT addon | ✓ 10 native types | 🟢 | None |
| Hierarchical categories | ✓ | ✓ | 🟢 | None |
| Location taxonomy | ✓ | ✓ | 🟢 | None |
| Frontend submission | ✓ | ✓ multi-step wizard | 🟢 | None |
| Guest submission | ✓ | ✓ inline registration | 🟡 | Add email-verification gate (1 day) |
| Listing expiration | ✓ | ✓ configurable + cron | 🟢 | None |
| Listing renewal flow | ✓ admin + frontend | ✓ admin only | 🟡 | Add frontend "Renew Now" button (2 days) |
| Bulk import (CSV) | ✓ mature | ✓ visual wizard | 🟢 | None |
| Multi-location per listing | ✓ via addon | ✗ | 🔴 | **Plan: ship in v1.1 — 1 week** |
| Location-aware default | ✓ IP geolocation | ✗ | 🟡 | Add via free MaxMind DB (3 days) |

**Subtotal effort to close gaps: ~2 weeks**

---

## 2 · Search & discovery

| Feature | GeoDirectory | WB Listora | Status | Plan |
|---|---|---|---|---|
| Full-text search | ✓ | ✓ FULLTEXT index | 🟢 | None — we're faster |
| Faceted filters | ✓ | ✓ dynamic facets | 🟢 | None |
| Geo / radius search | ✓ | ✓ Haversine | 🟢 | None |
| "Near me" | ✓ | ✓ | 🟢 | None |
| Map clustering | ✓ | ✓ both OSM + Google | 🟢 | None |
| Search results sort options | ✓ 6 sorts | ✓ 8 sorts | 🟢 | None |
| Auto-suggestions | ✓ | ✓ keyword + location | 🟢 | None |
| Saved searches + alerts | ✗ | ✓ Pro | 🟢 | None — we're ahead |
| Search analytics | ✓ via addon | ✗ | 🔴 | Track search keywords + zero-result queries (1 week) |
| Voice search | ✗ | ✗ | ⚫ | Skip |

**Subtotal effort: ~1 week**

---

## 3 · Reviews & trust

| Feature | GeoDirectory | WB Listora | Status | Plan |
|---|---|---|---|---|
| Star ratings | ✓ | ✓ | 🟢 | None |
| Multi-criteria | ✓ via addon | ✓ Pro | 🟢 | None |
| Photo reviews | ✗ | ✓ Pro | 🟢 | None |
| Helpful votes | ✓ | ✓ | 🟢 | None |
| Owner replies | ✓ | ✓ | 🟢 | None |
| Reviews moderation | ✓ | ✓ admin queue | 🟢 | None |
| Review verification (purchase) | ✗ | ✗ | ⚫ | Skip — community-relevant later |
| Review reminders | ✗ | ✗ | 🔴 | Auto-email post-engagement (3 days) |
| Sentiment / spam ML | ✗ | ✗ | ⚫ | Skip |
| Verification badges | ✓ | ✓ Pro | 🟢 | None |
| Business claims | ✓ | ✓ proof upload + admin review | 🟢 | None |

**Subtotal effort: ~3 days**

---

## 4 · Monetization

| Feature | GeoDirectory | WB Listora | Status | Plan |
|---|---|---|---|---|
| Pricing plans | ✓ via GD Convert addon (req's WooCommerce) | ✓ Pro native + SDK | 🟢 | None — we're cleaner |
| Coupons | ✓ via addon | ✓ Pro | 🟢 | None |
| Featured listings | ✓ | ✓ | 🟢 | None |
| Recurring billing | ✓ via Subscriptions addon | ✗ | 🔴 | **Plan: ship in v1.2 via SDK + Stripe — 1 week** |
| One-time payment | ✓ via WooCommerce | ✓ via SDK | 🟢 | None |
| Tax handling | ✓ via WooCommerce | ✗ | 🟡 | Add Stripe Tax integration (3 days) |
| Invoice generation | ✓ via addon | ✗ | 🟡 | PDF invoice on credit purchase (2 days) |
| Multi-currency | ✓ via WooCommerce | ✗ | 🟡 | SDK already accepts currency; surface in UI (2 days) |
| Refund flow | ✓ via WooCommerce | ✗ | 🔴 | Admin "Refund credits" button (2 days) |
| Trial / freemium tiers | ✗ | ✗ | 🟡 | Plan trial duration on plan CPT (1 day) |

**Subtotal effort: ~2 weeks**

---

## 5 · UX / frontend

| Feature | GeoDirectory | WB Listora | Status | Plan |
|---|---|---|---|---|
| Block editor support | ✓ recent | ✓ Interactivity API native | 🟢 | None — we're ahead |
| Native blocks | ✓ basic | ✓ 14 blocks | 🟢 | None |
| Theme integrations | ✓ many premium directory themes | ✗ generic | 🔴 | **Build BuddyX + Reign integration packs — 2 weeks** |
| Mobile responsive | ✓ | ✓ 390/640/1024 | 🟢 | None |
| Dark mode | ✗ | ✓ auto | 🟢 | None |
| RTL | ✓ | 🟡 build pipeline ready, needs commit | 🟡 | Run `wp i18n make-rtl` + commit (2 hours) |
| Quick view modal | ✗ | ✓ Pro | 🟢 | None |
| Infinite scroll | ✓ via addon | ✓ Pro | 🟢 | None |
| Comparison table | ✗ | ✓ Pro shortcode | 🟡 | Ship native block (3 days) |
| Sticky-header safe layout | ✓ | ✓ just fixed | 🟢 | None |
| Customizable single-listing template | ✓ | ✓ template overrides | 🟢 | None |

**Subtotal effort: ~2 weeks (mostly themes)**

---

## 6 · Community / Marketplace (our moat)

| Feature | GeoDirectory | WB Listora | Status |
|---|---|---|---|
| Reverse listings (post-a-need) | ✗ | ✓ Pro | 🟢🟢 unique |
| Vendor quote responses | ✗ | ✓ Pro | 🟢🟢 unique |
| Buyer accept/reject | ✗ | ✓ Pro | 🟢🟢 unique |
| BuddyPress / BuddyBoss native | ✓ via addon | ✓ Pro native | 🟢 |
| Activity stream integration | ✓ basic | ✓ deeper | 🟢 |
| Member directory linking | ✓ | ✓ | 🟢 |
| User messaging / inbox | ✗ | ✗ | 🔴 |

**Plan:** Build the messaging system in v1.2. This is the core promise of the marketplace pivot. ~2 weeks.

---

## 7 · Admin & operations

| Feature | GeoDirectory | WB Listora | Status | Plan |
|---|---|---|---|---|
| Settings tabs | ✓ many | ✓ 10 tabs | 🟢 | None |
| Setup wizard | ✓ | ✓ Free + Pro | 🟢 | None |
| Demo data | ✓ via addon | ✓ 9 packs | 🟢 | None |
| Admin columns | ✓ | ✓ + duplicate flag | 🟢 | None |
| Bulk edit | ✓ | ✗ inherits WP default | 🟡 | Add bulk listing actions (3 days) |
| Audit log | ✗ | ✓ Pro 90-day | 🟢🟢 unique |
| Moderator role | ✓ basic | ✓ Pro round-robin | 🟢 |
| Outgoing webhooks | ✗ | ✓ Pro 11 events | 🟢🟢 unique |
| White-label | ✗ | ✓ Pro | 🟢 |
| Coming-soon mode | ✗ | ✓ Pro visibility modes | 🟢 |
| Email log | ✗ | ✓ just shipped | 🟢 |
| Notification toggles | ✓ basic | ✓ 10 user + 14 admin | 🟢 |

**Subtotal effort: ~3 days (bulk edit only)**

---

## 8 · Developer surface

| Feature | GeoDirectory | WB Listora | Status |
|---|---|---|---|
| REST API | ✓ partial | ✓ 74+ endpoints, 100% coverage | 🟢🟢 |
| Hook surface | ✓ | ✓ 245+ hooks | 🟢 |
| Filter on every read | ✗ partial | ✓ 33 rest_prepare_* | 🟢 |
| Before/after on every write | ✗ partial | ✓ 79 hooks | 🟢 |
| Template overrides | ✓ | ✓ 64 templates | 🟢 |
| CSS token system | ✗ | ✓ `--listora-*` | 🟢 |
| WP-CLI | ✓ minimal | ✓ 7 subcommands | 🟢 |
| PHPStan level | ✗ | ✓ Level 7 | 🟢 |
| Block extension API | ✗ | ✓ shared infrastructure | 🟢 |
| Public docs site | ✓ extensive | ✗ | 🔴 |

**Plan:** Build hook reference docs site (auto-generated from source). 1 week.

---

## 9 · i18n / internationalization

| Feature | GeoDirectory | WB Listora | Status | Plan |
|---|---|---|---|---|
| Translatable strings | ✓ | 🟡 Free 38% .pot | 🟡 | Regenerate .pot, hire translation (1 week) |
| Pro .pot | n/a | ✗ missing | 🔴 | Generate Pro .pot (2 hours) |
| WPML config | ✓ | ✓ Free only | 🟡 | Add Pro wpml-config.xml (1 day) |
| RTL CSS | ✓ | 🟡 build configured, output not committed | 🟡 | Commit build output (2 hours) |
| 30+ language packs | ✓ | ✗ | 🔴 | Translate top 5 langs via community + paid (3 weeks) |

**Subtotal effort: ~5 weeks (mostly translator time, parallelizable)**

---

## 10 · Ecosystem / market presence

| | GeoDirectory | WB Listora | Status |
|---|---|---|---|
| Active installs | ~30k | 0 | 🔴 |
| Themes shipped with us | 0 | 2 (Reign + BuddyX) **after bundle** | 🟢 closing fast |
| Premium addon ecosystem | 10+ | Pro is monolith | 🟡 |
| Documentation | 100+ articles, video courses | spec files only | 🔴 |
| Community / forums | active | none | 🔴 |
| Agency partners | yes | yes (Wbcom + ecosystem) | 🟡 |
| Case studies | many | 0 | 🔴 |

**Plan:**
1. **Bundle Free with BuddyX + Reign** — instant install base of thousands. **0 days, just ship.**
2. **Get 3 case studies** by month 2 (offer Pro free to first 3 customers willing to publish a story). 1 month elapsed.
3. **Launch docs site** at `docs.wblistora.com` with hook reference + tutorials. 2 weeks.
4. **Top 5 community questions answered on Stack Overflow + GitHub Discussions.** Ongoing.

---

## 30/60/90 day roadmap

### Days 1–30 — Close obvious gaps
- ✅ Bundle Free with BuddyX + Reign (Day 1)
- Multi-location per listing
- Bulk edit listings
- Renewal frontend button
- Email verification gate for guest submission
- Comparison block (replace shortcode)
- RTL stylesheet commit
- Pro .pot generation
- Native search analytics

**Output:** Feature parity for 95% of GeoDirectory's "core" use cases.

### Days 31–60 — Build the marketplace moat
- User messaging / inbox (the missing community feature)
- Recurring billing via SDK + Stripe
- Refund flow + tax handling + invoice PDF
- Review reminder emails
- IP-based geolocation default
- Bulk import improvements (resume on failure)

**Output:** "Community marketplace" pitch is fully real.

### Days 61–90 — Polish & ecosystem
- Public docs site (hook reference + tutorials)
- 5 language packs
- BuddyX + Reign theme integration packs (purpose-built directory templates)
- 3 published case studies
- WordPress.org listing for Free
- Trial tier on pricing plans

**Output:** "Switch from GeoDirectory" pitch becomes credible for community sites.

---

## Where we will NEVER chase GeoDirectory

| Their feature | Why we skip |
|---|---|
| Multi-location addon depth | Niche; <5% of users actually need >2 locations |
| Custom CPT generator addon | Our 10 native types + 23 field types cover 95% of use cases |
| GeoDirectory Lite (separate plugin) | We have one plugin |
| Heavy enterprise scale (1M+ listings) | Not our market; community sites are 100s–10k |
| Salesforce / HubSpot deep integrations | Add via webhooks if customer needs it |
| Wholesale custom theme builder | Stay focused on BuddyX + Reign integration |

---

## Honest summary

**Today we are:**
- 🟢 Better than GeoDirectory on: developer surface, REST API, block editor, modern PHP, audit log, outgoing webhooks, reverse listings, photo reviews, dark mode, monetization SDK, search performance ceiling.
- 🟡 Roughly equal on: core directory CRUD, claims, basic monetization, BuddyPress integration.
- 🔴 Behind on: ecosystem maturity, translations, themes, case studies, multi-location, recurring billing, docs.

**90-day plan closes most gaps.** After that we have a defensible "switch from GeoDirectory" pitch for community/marketplace verticals.

**Strategic anchor:** Don't chase horizontal parity. Win the BuddyX/Reign vertical first. Bundle Free → upsell Pro → publish wins → expand outward.
