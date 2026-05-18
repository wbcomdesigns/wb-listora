# WB Listora — Remaining Gaps for Complete Launch

**Updated:** 2026-04-05 (Session 4 — Free plugin 100% complete)
**Goal:** Ship Free + Pro together as complete product on wbcomdesigns.com

---

## Session 4 Completed (2026-04-05) — FREE Plugin 100%

### Interactivity API & Frontend
- [x] viewScript → viewScriptModule (ES modules)
- [x] Map blank tiles — Leaflet init self-reference bug
- [x] Grid/list toggle, search filters, dashboard tabs
- [x] Mobile responsive — single column at 640px
- [x] 44px touch targets on all interactive elements
- [x] :focus-visible on .listora-btn

### QA Fixes
- [x] 3 SQL syntax errors (phpcs:ignore inside SQL strings)
- [x] N+1 favorite count query — batch loaded
- [x] Review reports autoload=false
- [x] dbDelta formatting (9 CREATE TABLE statements)
- [x] Duplicate check performance (SQL LIKE pre-filter)

### Features Built
- [x] JSON + GeoJSON import with REST endpoints
- [x] Recurring events (daily/weekly/monthly) + calendar
- [x] Event date filters (today, weekend, happening now, date range)
- [x] Job position_filled field + booking appointment hook
- [x] Conditional field rendering (detail page + submission form)
- [x] All 14 email templates + draft reminder cron + helpful milestone
- [x] CAPTCHA (reCAPTCHA v3 + Cloudflare Turnstile)
- [x] Rate limiting (user + IP)
- [x] Guest registration (inline on submission form)
- [x] Dashboard all 10 notification toggles
- [x] Expired listing URL strategy (noindex + content notice)
- [x] Expiry reminder emails (7-day + 1-day)
- [x] Draggable map pin on submission form
- [x] Onboarding checklist widget
- [x] Visual Lucide icon picker for taxonomy terms
- [x] 4 competitor migration importers (Directorist, GeoDirectory, BDP, ListingPro)
- [x] Type-specific demo packs (Restaurant, Job Board, Real Estate, Hotel, General)

### Infrastructure
- [x] Object caching on REST endpoints (reviews, favorites, dashboard)
- [x] PHPUnit test suite (12 tests)
- [x] CI pipeline (PHP Lint, WPCS, PHPStan, PHPUnit, PCP)
- [x] i18n hardcoded strings extracted to listoraI18n

### FREE Plugin Status: COMPLETE — All P0, P1, P2 items done

---

## Session 3 Completed (2026-03-23)

### Pro Plugin — Architecture & Security
- [x] Feature Manager: license-gated feature loading (`class-feature-manager.php`)
- [x] Activator/Deactivator/Uninstall lifecycle (3 new files)
- [x] Pro_Migrator: 4 tables DDL (credit_log, audit_log, saved_searches, payments)
- [x] Plugin Updater: auto-updates via license server (`class-updater.php`)
- [x] Assets class: CSS/JS registration (`class-assets.php`)
- [x] Extracted inline styles to `assets/css/pro-admin.css`
- [x] All 14 features have init() — Feature_Manager calls init() on each
- [x] Webhook_Receiver: added init() method (was missing)
- [x] Pricing Plans: full admin metabox with 7 fields (credits, price, duration, perks, etc.)
- [x] 40+ security fixes from enterprise audit (sanitization, nonces, null safety, LIMIT clauses)
- [x] 3 email templates: digest, lead-notification, saved-search-alert
- [x] Advanced_Search: fixed ID collision (max+1 instead of count+1)
- [x] Notification_Digest: 500-item queue cap, per-user routing, null-safe vars
- [x] Coming_Soon: private mode capability check
- [x] CLAUDE.md created for both plugins
- [x] Both plugins registered in autovap-agent plugin-map

### Free Plugin — Pro Extension Hooks
- [x] `wb_listora_review_criteria` filter in listing-reviews block
- [x] `wb_listora_after_listing_fields` action in listing-detail block
- [x] `wb_listora_map_config` filter in listing-map block

### Pro Plugin — Scaffold Files
- [x] composer.json, package.json, webpack.config.js, .gitignore
- [x] uninstall.php (drops tables, deletes options, cleans user meta, removes plan CPT posts)
- [x] src/admin/index.js entry point

---

## Session 2 Completed (2026-03-20)

- [x] WPCS auto-fix on PHP files (partial — `listing-grid/render.php` has 4 remaining auto-fixable)
- [x] .pot translation file generated
- [x] readme.txt created
- [x] Suggest endpoint: contains LIKE
- [x] Deprecated get_settings() renamed
- [x] Reviews tab with real data + rating summary + distribution bars
- [x] Dashboard Profile tab with form
- [x] Dashboard tab switching JS fallback
- [x] Admin Reviews moderation page (list, filter, approve/reject/delete)
- [x] Admin Claims management page
- [x] Admin Import/Export page (WP-CLI based)
- [x] Admin menu: Reviews + Claims + Import/Export added
- [x] Per-notification toggle settings (10 events in admin)
- [x] Dark mode CSS tokens (full set) via `[data-listora-dark]`
- [x] RTL CSS support (direction overrides + logical properties)
- [x] Share dialog (Web Share API + clipboard fallback)
- [x] Favorite button toggle
- [x] Button type="button" on all interactive buttons

---

## Session 1 Completed (2026-03-20)

- [x] Keyword search `$wpdb->prepare()` param ordering
- [x] `wb_listora_render_hours()` function ordering
- [x] Schema generator array-to-string warning
- [x] Empty state `is-hidden` server-side
- [x] Filter count badge + clear button hidden server-side
- [x] Filter panel `hidden` by default
- [x] Single listing: `single_template` + `the_content` filter with recursion protection
- [x] Full-width page template registered + assigned to 7 pages
- [x] Listings page: 3-column grid (was 2-col split)
- [x] Directory-full: 3-column grid (was cramped split)
- [x] Submission form: shows all types (was locked to restaurant)
- [x] Card image placeholder: premium gradient + dot pattern
- [x] Featured badge: golden gradient
- [x] Dashboard stats: colored top borders
- [x] Listing detail: 2-column layout (content + sidebar)
- [x] Tab switching: vanilla JS fallback
- [x] Search placeholder text updated
- [x] 20/20 listings have Unsplash stock photos

---

## FREE PLUGIN — All Items COMPLETE

### P0 (Blockers) — ALL COMPLETE

| # | Issue | Status |
|---|-------|--------|
| ~~1~~ | ~~Unescaped output in 11 render.php~~ | Done |
| ~~2~~ | ~~Unsanitized `$_GET/$_POST`~~ | Done |
| ~~3~~ | ~~Generate `.pot` translation file~~ | Done (Session 2) |
| ~~4~~ | ~~Create `readme.txt`~~ | Done (Session 2) |
| ~~5~~ | ~~Orange loading bar visible when not loading~~ | Done (Session 4) |
| ~~6~~ | ~~Suggest endpoint prefix-only LIKE~~ | Done (Session 2) |
| ~~7~~ | ~~PHPCS: 4 auto-fixable violations in listing-grid/render.php~~ | Done (Session 4) |

### P1 (Important) — ALL COMPLETE

| # | Issue | Status |
|---|-------|--------|
| ~~7~~ | ~~Reviews tab~~ | Done |
| ~~8~~ | ~~Grid pagination~~ | Done |
| ~~9~~ | ~~Dashboard Profile tab~~ | Done |
| ~~10~~ | ~~Categories block~~ | Done |
| ~~11~~ | ~~Featured listings block~~ | Done |
| ~~12~~ | ~~Calendar block~~ | Done |
| ~~13~~ | ~~Dark mode CSS~~ | Done |
| ~~14~~ | ~~RTL CSS~~ | Done |
| ~~15~~ | ~~HTML email templates~~ | Done — all 14 templates |
| ~~16~~ | ~~Per-notification toggles in admin~~ | Done |
| ~~17~~ | ~~Admin Reviews moderation page~~ | Done |
| ~~18~~ | ~~Admin Claims page~~ | Done |
| ~~19~~ | ~~Listing edit from dashboard~~ | Done |
| ~~20~~ | ~~Share dialog~~ | Done |
| ~~21~~ | ~~JSON + GeoJSON import~~ | Done (Session 4) |
| ~~22~~ | ~~Recurring events + calendar~~ | Done (Session 4) |
| ~~23~~ | ~~Event date filters~~ | Done (Session 4) |
| ~~24~~ | ~~CAPTCHA (reCAPTCHA v3 + Turnstile)~~ | Done (Session 4) |
| ~~25~~ | ~~Rate limiting~~ | Done (Session 4) |
| ~~26~~ | ~~Guest registration~~ | Done (Session 4) |
| ~~27~~ | ~~Expiry reminder emails~~ | Done (Session 4) |
| ~~28~~ | ~~Draggable map pin~~ | Done (Session 4) |

### P2 (Nice to have) — ALL COMPLETE

| # | Issue | Status |
|---|-------|--------|
| ~~21~~ | ~~Conditional field logic~~ | Done (Session 4) |
| ~~22~~ | ~~Duplicate listing detection~~ | Done (Session 4) |
| ~~23~~ | ~~Listing expiry email reminders~~ | Done (Session 4) |
| ~~24~~ | ~~Accessibility audit (WCAG 2.1 AA)~~ | Done (Session 4) — focus-visible, touch targets |
| ~~25~~ | ~~Button type attributes~~ | Done |
| ~~26~~ | ~~Image alt attributes~~ | Done (Session 4) |
| ~~27~~ | ~~Dashboard: all 10 notification toggles~~ | Done (Session 4) |
| ~~28~~ | ~~Onboarding checklist widget~~ | Done (Session 4) |
| ~~29~~ | ~~Lucide icon picker~~ | Done (Session 4) |
| ~~30~~ | ~~4 competitor migration importers~~ | Done (Session 4) |
| ~~31~~ | ~~Type-specific demo packs~~ | Done (Session 4) |
| ~~32~~ | ~~Object caching on REST endpoints~~ | Done (Session 4) |
| ~~33~~ | ~~PHPUnit test suite + CI pipeline~~ | Done (Session 4) |

---

## PRO PLUGIN — Remaining Work

### P0 (Security Blockers) — ALL COMPLETE

| # | Issue | Status | Verified |
|---|-------|--------|----------|
| ~~1~~ | ~~permission_callback on REST routes~~ | Done | All 15 routes have callbacks |
| ~~2~~ | ~~PHPCS auto-fix~~ | Done | No obvious violations |
| ~~3~~ | ~~$_POST sanitization~~ | Done | wp_unslash + sanitize_text_field pattern |
| ~~4~~ | ~~Hooks in init() not constructors~~ | Done | All 14 features use init() |

### P1 (Core Pro Features) — ALL COMPLETE

| # | Feature | Backend | Frontend | Status |
|---|---------|---------|----------|--------|
| ~~5~~ | ~~Google Maps~~ | Done | Full JS init + clustering + Near Me | **COMPLETE** |
| ~~6~~ | ~~Plan selection in submission~~ | Done | Plan cards with credit balance | **COMPLETE** |
| ~~7~~ | ~~Analytics owner dashboard~~ | Done | Per-listing stats + CSS bar charts | **COMPLETE** |
| ~~8~~ | ~~Lead form UI + email~~ | Done | Form + template | **COMPLETE** |
| ~~9~~ | ~~Comparison table~~ | Done | [listora_compare] shortcode + floating bar | **COMPLETE** |
| ~~10~~ | ~~Multi-criteria reviews~~ | Done | Criteria averages bars on detail page | **COMPLETE** |
| ~~11~~ | ~~Photo reviews upload UI~~ | Done | Dropzone + previews + gallery | **COMPLETE** |
| ~~12~~ | ~~Verification badge display~~ | Done | Blue badge on cards + detail | **COMPLETE** |
| ~~13~~ | ~~Credit management admin page~~ | Done | N/A | **COMPLETE** |
| ~~14~~ | ~~Saved searches dashboard UI~~ | Done | Save button + dashboard panel | **COMPLETE** |
| ~~15~~ | ~~License activation UI~~ | Done | Admin form | **COMPLETE** |

**Summary: 11/11 P1 features COMPLETE (backend + frontend)**

### P2 (Pro Features — Can ship without)

| # | Issue | Status |
|---|-------|--------|
| 16 | Outgoing webhooks (Zapier/Make) | **TODO** — no code |
| 17 | Competitor migration tools | **TODO** — no code |
| 18 | Coupon codes / promo pricing | **TODO** — no code |
| 19 | Quick view popup on cards | **TODO** — no code |
| 20 | Infinite scroll on grid | **TODO** — no code |
| 21 | Custom card badges | **TODO** — no code |
| ~~22~~ | ~~Notification digest emails~~ | Done | Queue + template + per-user routing |
| 23 | Moderator role + assignment | **TODO** — no code |
| 24 | Audit log feature class | **TODO** — table exists, no feature class |
| 25 | Programmatic SEO pages | **TODO** — no code |

---

## Updated Effort Estimates

| Category | Done | Remaining | Effort Left |
|----------|------|-----------|-------------|
| Free P0 | 7/7 | 0 | 0 |
| Free P1 | 22/22 | 0 | 0 |
| Free P2 | 13/13 | 0 | 0 |
| Pro P0 | 4/4 | 0 | 0 |
| Pro P1 | 11/11 | 0 (from Session 3) | 0 |
| Pro P2 | 1/10 | 9 items | ~35hr |
| **Total** | **58/61** | **9 items (Pro P2 only)** | **~35hr** |
