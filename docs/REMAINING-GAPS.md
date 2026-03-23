# WB Listora — Remaining Gaps for Complete Launch

**Updated:** 2026-03-23 (Session 3 — Code review & audit pass)
**Goal:** Ship Free + Pro together as complete product on wbcomdesigns.com

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
- [x] Reviews tab with real data + rating summary + distribution bars ✅ verified
- [x] Dashboard Profile tab with form ✅ verified (but only 3/10 notification toggles shown in dashboard)
- [x] Dashboard tab switching JS fallback ✅ verified in listing-detail
- [x] Admin Reviews moderation page (list, filter, approve/reject/delete) ✅ verified
- [x] Admin Claims management page ✅ verified
- [x] Admin Import/Export page ✅ verified (WP-CLI based)
- [x] Admin menu: Reviews + Claims + Import/Export added ✅ verified
- [x] Per-notification toggle settings (10 events in admin) ✅ verified
- [x] Dark mode CSS tokens (full set) ✅ verified via `[data-listora-dark]`
- [x] RTL CSS support (direction overrides + logical properties) ✅ verified
- [x] Share dialog (Web Share API + clipboard fallback) ✅ verified
- [x] Favorite button toggle ✅ verified
- [x] Button type="button" on all interactive buttons ✅ verified

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

## FREE PLUGIN — Remaining Work

### P0 (Blockers — Must fix before any launch)

| # | Issue | File(s) | Status |
|---|-------|---------|--------|
| ~~1~~ | ~~Unescaped output in 11 render.php~~ | | Needs re-check — may have residual |
| ~~2~~ | ~~Unsanitized `$_GET/$_POST`~~ | | Needs re-check |
| ~~3~~ | ~~Generate `.pot` translation file~~ | | ✅ Done (Session 2) |
| ~~4~~ | ~~Create `readme.txt`~~ | | ✅ Done (Session 2) |
| 5 | Orange loading bar visible when not loading | search block CSS/JS | **TODO** |
| ~~6~~ | ~~Suggest endpoint prefix-only LIKE~~ | | ✅ Done (Session 2) |
| 7 | PHPCS: 4 auto-fixable violations in listing-grid/render.php | `blocks/listing-grid/render.php` | **TODO** |

### P1 (Important — Should fix before launch)

| # | Issue | Status | Notes |
|---|-------|--------|-------|
| ~~7~~ | ~~Reviews tab~~ | ✅ Done | Real data, summary, distribution bars |
| ~~8~~ | ~~Grid pagination~~ | ✅ Done | SEO-friendly `<a>` links, "Showing X-Y of Z" counter |
| ~~9~~ | ~~Dashboard Profile tab~~ | ✅ Done | Form + 3 notification toggles (7 missing from dashboard UI) |
| 10 | Categories block testing | **TODO** | Untested |
| 11 | Featured listings block testing | **TODO** | Untested |
| 12 | Calendar block testing | **TODO** | Untested |
| ~~13~~ | ~~Dark mode CSS~~ | ✅ Done | `[data-listora-dark]` attribute |
| ~~14~~ | ~~RTL CSS~~ | ✅ Done | `[dir="rtl"]` + logical properties |
| ~~15~~ | ~~HTML email templates~~ | ✅ Done | 7 templates in templates/emails/, ob_start+include pattern |
| ~~16~~ | ~~Per-notification toggles in admin~~ | ✅ Done | 10 events in Settings |
| ~~17~~ | ~~Admin Reviews moderation page~~ | ✅ Done | List, filter, approve/reject/delete |
| ~~18~~ | ~~Admin Claims page~~ | ✅ Done | Status filter, actions |
| 19 | Listing edit from dashboard | **TODO** | Edit mode not wired |
| ~~20~~ | ~~Share dialog~~ | ✅ Done | Web Share API + fallback |

### P2 (Nice to have)

| # | Issue | Status |
|---|-------|--------|
| 21 | Conditional field logic | **TODO** |
| 22 | Duplicate listing detection | **TODO** |
| 23 | Listing expiry email reminders | **TODO** |
| 24 | Accessibility formal audit (WCAG 2.1 AA) | **TODO** |
| ~~25~~ | ~~Button type attributes~~ | ✅ Done |
| 26 | Image alt attributes on 4 locations | **TODO** |
| 27 | Dashboard: show all 10 notification toggles (only 3 shown) | **TODO** |

---

## PRO PLUGIN — Remaining Work

### P0 (Security Blockers) — ALL COMPLETE ✅

| # | Issue | Status | Verified |
|---|-------|--------|----------|
| ~~1~~ | ~~permission_callback on REST routes~~ | ✅ Done | All 15 routes have callbacks |
| ~~2~~ | ~~PHPCS auto-fix~~ | ✅ Done | No obvious violations |
| ~~3~~ | ~~$_POST sanitization~~ | ✅ Done | wp_unslash + sanitize_text_field pattern |
| ~~4~~ | ~~Hooks in init() not constructors~~ | ✅ Done | All 14 features use init() |

### P1 (Core Pro Features)

| # | Feature | Backend | Frontend | Status |
|---|---------|---------|----------|--------|
| ~~5~~ | ~~Google Maps~~ | ✅ | ✅ Full JS init + clustering + Near Me | **COMPLETE** |
| ~~6~~ | ~~Plan selection in submission~~ | ✅ | ✅ Plan cards with credit balance | **COMPLETE** |
| ~~7~~ | ~~Analytics owner dashboard~~ | ✅ | ✅ Per-listing stats + CSS bar charts | **COMPLETE** |
| ~~8~~ | ~~Lead form UI + email~~ | ✅ | ✅ Form + template | **COMPLETE** |
| ~~9~~ | ~~Comparison table~~ | ✅ | ✅ [listora_compare] shortcode + floating bar | **COMPLETE** |
| ~~10~~ | ~~Multi-criteria reviews~~ | ✅ | ✅ Criteria averages bars on detail page | **COMPLETE** |
| ~~11~~ | ~~Photo reviews upload UI~~ | ✅ | ✅ Dropzone + previews + gallery | **COMPLETE** |
| ~~12~~ | ~~Verification badge display~~ | ✅ | ✅ Blue badge on cards + detail | **COMPLETE** |
| ~~13~~ | ~~Credit management admin page~~ | ✅ | N/A | **COMPLETE** |
| ~~14~~ | ~~Saved searches dashboard UI~~ | ✅ | ✅ Save button + dashboard panel | **COMPLETE** |
| ~~15~~ | ~~License activation UI~~ | ✅ | ✅ Admin form | **COMPLETE** |

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
| ~~22~~ | ~~Notification digest emails~~ | ✅ Done | Queue + template + per-user routing |
| 23 | Moderator role + assignment | **TODO** — no code |
| 24 | Audit log feature class | **TODO** — table exists, no feature class |
| 25 | Programmatic SEO pages | **TODO** — no code |

---

## Updated Effort Estimates

| Category | Done | Remaining | Effort Left |
|----------|------|-----------|-------------|
| Free P0 | 4/7 | 3 items | ~2hr |
| Free P1 | 10/14 | 4 items (grid pagination, 3 block tests, email templates, edit mode) | ~9hr |
| Free P2 | 1/6 | 5 items | ~12hr |
| Pro P0 | **4/4** | 0 | 0 |
| Pro P1 | **4/11** | 8 items (all need frontend UI/JS) | ~21hr |
| Pro P2 | 1/10 | 9 items | ~35hr |
| **Total** | **24/52** | **29 items** | **~79hr** |

### Next Priority: Pro Frontend UI Sprint

The biggest gap is that Pro has 8 features with working backends but no frontend. These need Interactivity API JS:

| Priority | Feature | Effort | Impact |
|----------|---------|--------|--------|
| 1 | Plan selection in submission form | 3hr | Revenue blocker |
| 2 | Multi-criteria reviews star UI | 3hr | Key Pro differentiator |
| 3 | Google Maps JS interactivity | 3hr | Top upgrade trigger |
| 4 | Photo reviews upload form | 2hr | Review enhancement |
| 5 | Comparison table block | 3hr | Unique Pro feature |
| 6 | Saved searches dashboard | 2hr | User engagement |
| 7 | Verification badge display | 1hr | Trust signal |
| 8 | Analytics owner dashboard | 4hr | Owner retention |
