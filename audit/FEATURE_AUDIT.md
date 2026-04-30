# WB Listora — Feature Audit Report

**Generated:** 2026-04-30
**Version:** 1.0.0
**Branch:** main
**Source:** [`manifest.json`](manifest.json) (schema v2.1) · [`manifest.summary.json`](manifest.summary.json) (≤3 KB index) · [`derived/`](derived/) (cached sub-checks)
**Totals:** 11 frontend blocks · 4 admin AJAX actions · 48 REST endpoints · 12 admin pages · 11 DB tables · 6 taxonomies · 6 cron jobs · 1 WP-CLI namespace · 183 fired hooks · 15 custom capabilities · 10 listing types · 9 layout-owning blocks · 74 Interactivity API actions across 6 view scripts · 38 IAPI state keys (35 base + 3 modal-getter derivations)

The canonical machine-readable inventory is `audit/manifest.json`. This document is the human-readable companion: read top-down for a complete tour of every feature surface. The manifest uses **schema v2.1** which adds (over v2): `category_sources` for diff-driven refresh, `consumed_by[]` populated on every fired hook, the companion `manifest.summary.json` index, and the `audit/derived/` cache directory. v2 sections (`args_signature`, taxonomy `capabilities` map, `blocks[].layout_owning`, top-level `interactivity` / `ui_activation` / `static_analysis`) all carry forward.

## Recent Changes

| Commit | Date | Area | What changed |
|---|---|---|---|
| `63411c8` | 2026-04-30 | Interactivity | Modal-getter pattern — `data-wp-class--*` directives must read a tracked property, not an inline `===` expression. Added 3 derived getters (`isClaimModalOpen`, `isShareModalOpen`, `isLoginModalOpen`) under `state` in `src/interactivity/store.js` (lines 89-98). The `activeModal` property is the source of truth (`'claim'\|'share'\|'login'\|null`). Modal markup in `blocks/listing-detail/render.php` updated to bind to the boolean getters. **Manifest impact:** `interactivity[0].state_keys` now 38 (was 35). |
| `253cef9` | 2026-04-30 | Detail | Added the Helpful vote button to the Reviews tab in `templates/blocks/listing-detail/tabs.php`. REST endpoint already existed (`POST /reviews/{id}/helpful`) — just a template hookup. |
| `7606f8c` | 2026-04-30 | Activator | Split FULLTEXT index out of `dbDelta()` to avoid the SQL-syntax error MySQL throws when dbDelta tries to compose CREATE TABLE with a FULLTEXT clause. Patches `includes/class-activator.php`. |
| `182f654` | 2026-04-30 | Dashboard | CSS-only fix in `blocks/user-dashboard/style.css` — submit-state inner spans now hidden via `is-hidden` class so label and spinner never both show at once. |
| `e01486b` | 2026-04-30 | Dashboard | Wired the dashboard Reply button to the existing `/reviews/{id}/reply` endpoint via an inline form (not a modal). Touched `templates/blocks/user-dashboard/tab-reviews.php` + `src/interactivity/store.js`. |

These five commits are all surgical bug fixes — no new REST endpoints, AJAX actions, blocks, tables, capabilities, or fired hooks. The only manifest delta is `interactivity[0].state_keys` (35 → 38).

---

## 1. Frontend Features (Blocks)

All blocks register under namespace `listora/` and use the WordPress Interactivity API (single store namespace `listora/directory`). Each block has 20 standard attributes (uniqueId, responsive padding/margin, border radius, box shadow, device visibility) and per-instance CSS scoping via `WBListora\Block_CSS`.

### 1.1 listora/listing-grid
- **Render:** `blocks/listing-grid/render.php`
- **Roles:** all (public)
- **Settings toggle:** none
- **Hooks:** `wb_listora_grid_query_args` (filter), `wb_listora_before_listing_grid`, `wb_listora_grid_after_card`, `wb_listora_after_listing_grid`
- **Purpose:** Paginated grid of listings with filters/facets

### 1.2 listora/listing-card
- **Render:** `blocks/listing-card/render.php` + WooCommerce-style template overrides under `templates/blocks/listing-card/`
- **Roles:** all
- **Hooks:** `wb_listora_before_card`, `wb_listora_after_card`, `wb_listora_card_actions`, `wb_listora_before_card_image`, `wb_listora_after_card_image`, `wb_listora_before_card_content`, `wb_listora_after_card_content`, `wb_listora_before_card_actions`, `wb_listora_after_card_actions`
- **Purpose:** Reusable listing card (image, title, rating, actions)

### 1.3 listora/listing-search
- **Render:** `blocks/listing-search/render.php`
- **Roles:** all
- **Hooks:** `wb_listora_after_search_results`
- **REST:** `/listora/v1/search`, `/listora/v1/search/suggest`
- **Purpose:** Faceted search bar with autocomplete + geo

### 1.4 listora/listing-map
- **Render:** `blocks/listing-map/render.php`
- **Roles:** all
- **Hooks:** `wb_listora_map_provider` (filter; Pro swaps to Google Maps), `wb_listora_map_config`, `wb_listora_before_map`, `wb_listora_after_map`
- **Purpose:** Map view of listings (default Leaflet, Pro = Google Maps)

### 1.5 listora/listing-detail
- **Render:** `blocks/listing-detail/render.php` + templates `gallery.php`, `sidebar.php`, `tabs.php`
- **Roles:** all
- **Hooks:** `wb_listora_detail_actions`, `wb_listora_detail_reviews_limit`, `wb_listora_detail_tabs_view_data`, `wb_listora_before_detail_gallery` / `after`, `wb_listora_before_detail_sidebar` / `after`, `wb_listora_after_listing_fields` (Pro lead form), `wb_listora_appointment_button`, `wb_listora_before_detail_tabs` / `after`
- **Purpose:** Single listing page (gallery + sidebar + tabs)

### 1.6 listora/listing-reviews
- **Render:** `blocks/listing-reviews/render.php` + `templates/blocks/listing-reviews/reviews.php`
- **Roles:** all (write requires login)
- **Hooks:** `wb_listora_review_criteria` (filter; Pro adds multi-criteria), `wb_listora_before_reviews`, `wb_listora_after_reviews`, `wb_listora_review_after_content`, `wb_listora_review_form_after_content`
- **REST:** `/listora/v1/listings/{id}/reviews`, `/listora/v1/reviews/{id}`, helpful/reply/report
- **Purpose:** Reviews list + submission form

### 1.7 listora/listing-submission
- **Render:** `blocks/listing-submission/render.php`
- **Roles:** capability `submit_listora_listing` (incl. subscriber)
- **Hooks:** `wb_listora_submission_login_buttons`, `wb_listora_submission_plan_step` (Pro plan picker)
- **REST:** `/listora/v1/submit`, `/listora/v1/submit/check-duplicate`, `/listora/v1/submit/{id}` (PUT)
- **Purpose:** Frontend listing submission flow (multi-step, guest registration, conditional fields, draggable map pin)

### 1.8 listora/listing-categories
- **Render:** `blocks/listing-categories/render.php`
- **Hooks:** `wb_listora_before_categories_grid`, `wb_listora_category_card_data`, `wb_listora_after_categories_grid`
- **Purpose:** Category grid with counts

### 1.9 listora/listing-featured
- **Render:** `blocks/listing-featured/render.php`
- **Hooks:** `wb_listora_before_featured_listings`, `wb_listora_featured_query_args`, `wb_listora_after_featured_listings`
- **Purpose:** Featured listings carousel

### 1.10 listora/listing-calendar
- **Render:** `blocks/listing-calendar/render.php`
- **Hooks:** `wb_listora_before_calendar`, `wb_listora_calendar_events`, `wb_listora_after_calendar`
- **Purpose:** Event calendar (recurring + virtual occurrences)

### 1.11 listora/user-dashboard
- **Render:** `blocks/user-dashboard/render.php` + tab templates `tab-listings.php`, `tab-reviews.php`, `tab-claims.php`, `tab-credits.php`, `tab-profile.php`, `nav.php`
- **Roles:** logged-in users
- **Hooks:** `wb_listora_dashboard_sections` (Pro adds Needs tab), `wb_listora_show_dashboard_pro_cta`, `wb_listora_before_dashboard_*`/`after_*` for each tab
- **REST:** all `/listora/v1/dashboard/*` endpoints
- **Purpose:** User self-service dashboard (listings, reviews, claims, credits, profile, notifications)

---

## 2. AJAX Handlers

| # | Action | Handler | File | Nonce | Capability | Purpose |
|---|---|---|---|---|---|---|
| 1 | `listora_dismiss_onboarding` | `Admin::ajax_dismiss_onboarding` | `includes/admin/class-admin.php` | `listora_admin_nonce` | `manage_listora_settings` | Dismiss onboarding banner |
| 2 | `listora_run_migration` | `Admin::ajax_run_migration` | `includes/admin/class-admin.php` | `listora_admin_nonce` | `manage_listora_settings` | Run DB migration |
| 3 | `wb_listora_validate_license` | `Pro_Promotion::ajax_validate_license` | `includes/admin/class-pro-promotion.php` | `wb_listora_promo_nonce` | `manage_listora_settings` | Validate Pro license from promo CTA |
| 4 | `wb_listora_dismiss_promo` | `Pro_Promotion::ajax_dismiss_promo` | `includes/admin/class-pro-promotion.php` | `wb_listora_promo_nonce` | `manage_listora_settings` | Dismiss Pro promo (3-day cookie) |

The frontend uses REST exclusively — no `wp-admin/admin-ajax.php` from blocks.

---

## 3. REST API Endpoints

Full table in `audit/manifest.json` under `rest.endpoints`. Highlights:

| Group | Routes | Purpose |
|---|---|---|
| Listings (8) | `/listings`, `/listings/{id}/detail`, `/listings/{id}` (DELETE), `/listings/{id}/feature`, `/listings/{id}/related`, `/listings/{id}/renewal-quote`, `/listings/{id}/renew`, `/listings/bulk` | Listing read/update/feature/renew |
| Submission (5) | `/submit`, `/submit/check-duplicate`, `/submit/{id}`, `/submission/resend-verification`, `/submission/verify` | Frontend submission flow |
| Reviews (5) | `/listings/{id}/reviews`, `/reviews/{id}`, `/reviews/{id}/helpful`, `/reviews/{id}/reply`, `/reviews/{id}/report` | Review CRUD + helpful/reply/report |
| Dashboard (7) | `/dashboard/{stats,listings,reviews,claims,profile,notifications,notifications/read}` | User dashboard data |
| Search (2) | `/search`, `/search/suggest` | Faceted/geo/fulltext search |
| Favorites (2) | `/favorites`, `/favorites/{listing_id}` | Favorites list/add/remove |
| Claims (2) | `/claims`, `/claims/{id}` | Business ownership claims (user-scoped list lives at `/dashboard/claims`) |
| Listing Types (3) | `/listing-types`, `/listing-types/{slug}`, `/listing-types/{slug}/fields` | Type management |
| Services (3) | `/listings/{id}/services`, `/services/{id}`, `/listings/{id}/services/reorder` | Listing services CRUD |
| Settings (7) | `/settings`, `/settings/maps`, `/settings/app-config`, `/settings/export`, `/settings/import`, `/settings/notifications/test`, `/settings/notifications/log` | Plugin settings |
| Import/Export (4) | `/export/csv`, `/import/csv`, `/import/json`, `/import/geojson` | Bulk data |

**Pattern:** All write endpoints fire `wb_listora_before_<op>` (filter, can return WP_Error to abort) and `wb_listora_after_<op>` (action). All responses pass through `wb_listora_rest_prepare_<resource>` filter.

---

## 4. Admin Pages

| # | Page | Slug | Parent | Capability |
|---|---|---|---|---|
| 1 | Listora (Dashboard) | `listora` | (top) | `edit_listora_listings` |
| 2 | Listing Types | `listora-listing-types` | listora | `manage_listora_types` |
| 3 | Categories | edit-tags taxonomy | listora | `manage_listora_types` |
| 4 | Locations | edit-tags taxonomy | listora | `manage_listora_types` |
| 5 | Features | edit-tags taxonomy | listora | `manage_listora_types` |
| 6 | Reviews | `listora-reviews` | listora | `manage_listora_types` |
| 7 | Claims | `listora-claims` | listora | `manage_listora_types` |
| 8 | Settings | `listora-settings` | listora | `manage_listora_settings` |
| 9 | Health Check | `listora-health` | listora | `manage_listora_settings` |
| 10 | Setup Wizard | `listora-setup` | listora | `manage_listora_settings` |
| 11 | Pro Promotion | `listora-pro-promotion` | listora | `manage_listora_settings` |

Settings page is tabbed (General, Submissions, Maps, Reviews, Credits, Features, Import/Export). Pro adds: License, Pro Features, White Label, Visibility, SEO via `wb_listora_settings_tabs` filter.

---

## 5. Settings Inventory

| Key | Type | Default | Controls |
|---|---|---|---|
| `wb_listora_settings` | array | `{}` | Master settings (all tab data) |
| `wb_listora_overflow_cost` | int | 10 | Credits per overflow listing |
| `wb_listora_low_credit_threshold` | int | 5 | Low-credit alert |
| `wb_listora_setup_complete` | bool | false | Wizard flag |
| `wb_listora_features` | array | `{}` | Feature flags (reviews/claims/events/services) |

---

## 6. Database Tables (prefix `wp_listora_`)

| Table | Key columns | Purpose |
|---|---|---|
| `geo` | listing_id, lat, lng, geohash, address parts | Location index |
| `search_index` | listing_id, title, content_text, meta_text, avg_rating, is_featured, is_verified, is_claimed, lat, lng | Denormalized search index |
| `field_index` | listing_id, field_key, field_value, numeric_value, listing_type | Faceted custom-field filter |
| `reviews` | id, listing_id, user_id, overall_rating, criteria_ratings, content, photos, helpful_count, owner_reply | Reviews |
| `review_votes` | user_id, review_id | Helpful-vote tracking |
| `favorites` | user_id, listing_id, collection | User favorites |
| `claims` | id, listing_id, user_id, status, proof_text, proof_files | Business claims |
| `hours` | listing_id, day_of_week, open_time, close_time, is_24h | Operating hours |
| `analytics` | id, listing_id, event_type, event_date, count, meta | Listing analytics (Pro fills) |
| `payments` | id, user_id, listing_id, plan_id, gateway, amount, status, refund_amount | Payment transactions |
| `services` | id, listing_id, title, price, duration_minutes, image_id, gallery, sort_order | Listing services |

---

## 7. Content Types

| Type | Slug | Hierarchical | Show UI | Rewrite |
|---|---|---|---|---|
| CPT | `listora_listing` | — | yes | listing |
| Tax | `listora_listing_type` | no | no | listing-type |
| Tax | `listora_listing_cat` | yes | yes | listing-category |
| Tax | `listora_listing_tag` | no | yes | listing-tag |
| Tax | `listora_listing_location` | yes | yes | listing-location |
| Tax | `listora_listing_feature` | no | yes | listing-feature |
| Tax | `listora_service_cat` | no | yes | service-category |

CPT custom statuses: `listora_rejected`, `listora_expired`, `listora_deactivated`, `listora_payment`.

---

## 8. JavaScript Modules

| Handle | Source | Purpose |
|---|---|---|
| `listora-confirm` | `assets/js/shared/confirm.js` | Custom confirm modal |
| `listora-submit-lock` | `assets/js/shared/submit-lock.js` | Prevent double-submit on forms |
| `listora-i18n` | (inline) | Localization shim for Interactivity API |
| `listora-directory` | `assets/js/blocks/directory.js` (module) | Block interactivity store (`viewScriptModule`) — single namespace `listora/directory` |
| Per-block `view.js` | `blocks/<name>/view.js` | Imports shared store; do NOT define client defaults for server-provided keys |

---

## 9. Email Templates

Driven by `WBListora\Workflow\Notifications`. Templates in `templates/emails/`. 14 events including: listing approved/rejected, review submitted, owner reply, claim submitted/approved/rejected, expiration reminders, draft reminder, password reset for guest registration, email verification.

Hooks: `wb_listora_send_notification`, `wb_listora_email_subject_{event}`, `wb_listora_email_content_{event}`, `wb_listora_email_logo_url`, `wb_listora_email_footer_text`, `wb_listora_email_from_name`, `wb_listora_email_from_address`, `wb_listora_email_headers`, `wb_listora_notification_recipients`.

Themes can override templates via `{theme}/wb-listora/emails/<template>.php` (`wb_listora_locate_template`).

---

## 10. Cron Jobs

| Hook | Interval | Handler | Purpose |
|---|---|---|---|
| `wb_listora_check_expirations` | twicedaily | `Expiration_Cron::check_expirations` | Mark expired + 7d/1d reminders |
| `wb_listora_draft_reminder_cron` | twicedaily | `Expiration_Cron::send_draft_reminders` | Nudge stale drafts (48h+) |
| `wb_listora_daily_cleanup` | daily | `Expiration_Cron::prune_analytics` | Analytics retention (90d default) |
| `wb_listora_expire_featured` | daily | `Featured::expire_featured` | Expire featured upgrades |
| `wb_listora_cleanup_unverified_listings` | daily | `Email_Verification::cleanup_unverified` | Delete unverified listings (14d+) |
| `wb_listora_search_reindex` | single-event (chunked) | `Search_Indexer::process_scheduled_reindex` | Background full reindex after schema bumps. Migrator schedules; handler processes 200 listings per tick and re-schedules until done. |

---

## 11. Integrations

| Plugin | Required? | Detection | What it enables |
|---|---|---|---|
| WB Listora Pro | No | `class_exists('WBListoraPro\Pro_Plugin')` (via `wb_listora_pro_loaded`) | License-gated premium features |
| Action Scheduler | bundled by Pro | n/a | Used in Pro for high-volume retention |
| reCAPTCHA v3 | optional | API key in settings | Submission spam gate |
| Cloudflare Turnstile | optional | API key in settings | Submission spam gate |

---

## 12. WP-CLI Commands

`wp listora <subcommand>` — handler `WBListora\CLI_Commands`:
- `stats` — totals + status breakdown
- `reindex [--type=…] [--dry-run]` — rebuild `search_index` (synchronous; the background cron `wb_listora_search_reindex` is the post-upgrade path)
- `listing-types` — list/manage types
- `import <file>` — JSON / CSV / GeoJSON
- `export [--type=…] [--output=…]`
- `repair` — fix orphaned data
- `migrate --from=<directorist|geodirectory|bdp|listingpro>` — competitor migration
- `demo {seed|remove|reseed} [--pack=…] [--with-users] [--reindex]` — demo content control (`reseed` wipes existing demo content then seeds fresh)

---

## 13. Build & Quality Gates

- **Build:** `npm run build` (wp-scripts dual config: classic IIFE + ESM modules for `viewScriptModule`)
- **Lint JS:** `npm run lint:js`
- **Lint CSS:** `npm run lint:css`
- **PHPStan:** level 7 (with `wp-stubs` 6.9, baseline at `phpstan-baseline.neon`)
- **WPCS:** WordPress standard via `phpcs.xml`
- **Tests:** PHPUnit 9.6
- **Plugin Check:** PCP CLI (no wp.org publishing per project rules)
- **CI:** GitHub Actions — PHP Lint → WPCS → PHPStan L7 → PHPUnit → PCP

---

## 14. Extensibility Patterns (for Pro / 3rd parties)

1. **Write-op hook pairs** — every create/update/delete fires `wb_listora_before_<op>` (filter; return WP_Error to abort) + `wb_listora_after_<op>` (action). Lets Pro/integrations veto or react.
2. **REST response filters** — every endpoint passes its payload through `wb_listora_rest_prepare_<resource>`. Pro uses this to inject Google Maps URLs, multi-criteria ratings, photo URLs, badges, etc.
3. **Settings extensibility** — `wb_listora_settings_tabs` (filter) + `wb_listora_settings_tab_content` (action). Pro adds 5 tabs.
4. **Map provider swap** — `wb_listora_map_provider` filter (`leaflet` default → `google` when Pro+API key).
5. **Feature flags** — `wb_listora_features_registry`, `wb_listora_default_features`, `wb_listora_feature_{key}_enabled`.
6. **Template overrides** — `{theme}/wb-listora/blocks/<block>/<file>.php` for cards, detail, dashboard, emails.
7. **Custom field types** — `wb_listora_register_field_types` action + `wb_listora_field_types` filter; sanitize via `wb_listora_field_sanitize_callbacks`.
8. **Title-row promotional badges** — `wb_listora_listing_title_badges` action fires inline with the Type / Verified pills in the listing-detail header. Pro's Badges feature uses this; the older `wb_listora_after_listing_fields` hook is still fired for sidebar content like lead-capture forms.
9. **Rate-limit overrides** — `wb_listora_rate_limit_config` (filter, per-action limits) + `wb_listora_rate_limit_bypass` (filter, exempt trusted roles). Centralised in `\WBListora\Rate_Limiter` per ADR-001.

---

## 15. Security Baseline (ADR-001 / ADR-002)

| Concern | Implementation |
|---|---|
| Public POST rate-limiting | `\WBListora\Rate_Limiter::check( $action )` behind submission, review-create / vote / reply / report, claim-submit, favorite-add. Per-user + per-IP transient counters with bot-detection thresholds (real users never hit them). Filter: `wb_listora_rate_limit_config`, bypass: `wb_listora_rate_limit_bypass`. |
| CAPTCHA | `\WBListora\Captcha::verify()` on submission + review-create. Supports reCAPTCHA v3 + Cloudflare Turnstile. Optional. |
| Nonce / X-WP-Nonce | All POST routes require either a form nonce (`listora_submit_listing` etc.) or the standard X-WP-Nonce header for REST callers. |
| Webhook auth | Pro receiver requires HMAC-SHA256 signature header OR shared-secret header; idempotency on `(gateway, transaction_id)`; ±5 min timestamp freshness when payload includes one. |
| File uploads | `wp_handle_upload` with mime allowlist. Demo seeder downloads to tmp first, sniffs bytes via `getimagesize()`, then forces a sane filename for `media_handle_sideload` (avoids URL-extension-based filetype rejection on CDN URLs). |
| SQL | `$wpdb->prepare` everywhere; CI flags any `$wpdb->query` without it. |

---

---

## 16. Static Analysis Findings (v2 schema)

Generated by Phase 2.5 detectors of the wp-plugin-onboard skill. Each finding has a file:line citation in `manifest.json` under `static_analysis.<key>[]`.

| Detector | Findings | Status | What it means |
|---|---|---|---|
| Dead listeners (plugin-own) | 0 | ✓ clean | Hooks listened-to but never fired (cron-scheduled and dynamic firers excluded) |
| Cap context mismatches | 0 | ✓ clean | meta-cap consumed without object context (Gutenberg taxonomy panels etc.) |
| Extensibility gaps | 0 | ✓ clean | Server-rendered list with DOM-injection action but no list-shape filter |
| JS-only UI activation | 3 | ⚠ fragility | Hidden-by-default panels with no server-side `is-active` fallback |
| REST hang risks | 43 | informational | apiFetch/fetch call sites — abort-signal + timeout status |
| Visual required not enforced | 1 | ⚠ review | `<span class=required>*</span>` near a hidden input lacking required/aria-required/data-*-required |
| Grid 1fr collapse risks | 16 | ⚠ review | Multi-track CSS grids with bare `1fr` (use `minmax(0, 1fr)`) |
| Hook signature drift | 0 | n/a (standalone) | Cross-plugin: companion's listener signature mismatches firer's `args_signature` |

### 16.1 JS-only UI activation

Two are fragility flags by design (modal opens via JS click, migration-progress panel is JS-only). The third (`.listora-settings-section`) **has `php_fallback: true`** thanks to the recent settings fix (commit fda50ee) that renders the first section with `is-active` server-side.

| Selector | Default-hidden by | Active modifier | PHP fallback |
|---|---|---|---|
| `.listora-detail__modal` | `blocks/listing-detail/style.css:939` | `is-open` | — false |
| `.listora-migration-progress` | `assets/css/admin/migration.css:157` | `is-active` | — false |
| `.listora-settings-section` | `assets/css/admin/settings.css:156` | `is-active` | ✓ true |

### 16.2 Visual-required without enforcement

- `templates/blocks/listing-submission/step-media.php:27` — field `featured_image`. Missing: **aria-required**. Has visible `<span class=required>*</span>` and `data-listora-required` (custom flag) but no a11y attribute. Recommend adding `aria-required="true"` for screen readers.

### 16.3 Grid 1fr collapse risks

Multi-track grids using bare `1fr` instead of `minmax(0, 1fr)`. The listing-detail content-grid fix (commit b1b1775c) is reflected: `blocks/listing-detail/style.css` no longer flags for the content track. Remaining entries are mostly admin CSS and dashboard widgets — review per-rule whether intrinsic content can overflow.

Top 8 entries:

- `blocks/user-dashboard/style.css:7` `.listora-dashboard` — `grid-template-columns: 260px 1fr` → suggest `grid-template-columns: 260px minmax(0, 1fr)`
- `blocks/user-dashboard/style.css:753` `.listora-dashboard__profile-grid` — `grid-template-columns: 1fr 1fr` → suggest `grid-template-columns: minmax(0, 1fr) minmax(0, 1fr)`
- `blocks/user-dashboard/style.css:1236` `.listora-dashboard__transaction` — `grid-template-columns: 1fr auto` → suggest `grid-template-columns: minmax(0, 1fr) auto`
- `blocks/user-dashboard/style.css:1279` `.listora-dashboard__stats` — `grid-template-columns: 1fr 1fr` → suggest `grid-template-columns: minmax(0, 1fr) minmax(0, 1fr)`
- `blocks/user-dashboard/style.css:1288` `.listora-dashboard__limit-grid` — `grid-template-columns: 1fr 1fr` → suggest `grid-template-columns: minmax(0, 1fr) minmax(0, 1fr)`
- `blocks/user-dashboard/style.css:1450` `.listora-dashboard__renew-modal-grid` — `grid-template-columns: max-content 1fr` → suggest `grid-template-columns: max-content minmax(0, 1fr)`
- `blocks/listing-detail/style.css:53` `.listora-detail__gallery-mosaic` — `grid-template-columns: 2fr 1fr 1fr` → suggest `grid-template-columns: 2fr minmax(0, 1fr) minmax(0, 1fr)`
- `blocks/listing-featured/style.css:126` `.listora-featured__track` — `grid-template-columns: repeat(1, minmax(min(260px, 80vw), 1fr))` → suggest `grid-template-columns: repeat(1, minmax(min(260px, 80vw), minmax(0, 1fr)))`
- _(8 additional entries in `manifest.json`)_

### 16.4 REST hang risks (informational)

All 43 apiFetch/fetch call sites enumerated with abort-signal + timeout status.

Highlights:

- **Search action** (`src/interactivity/store.js:184`): `uses_abort_signal: true`, `has_timeout_ms: 20000` ✓ confirms commit 50dc326's robustness fix landed.
- Other apiFetch sites in `store.js` and per-block view scripts mostly do not wire abort signals — flagged for awareness, not necessarily bugs.
- Submission, dashboard, reviews, claims, favorites, contact endpoints all enumerated.

### 16.5 Capability classification

**2 CPT-derived meta caps** (require object context — bare `current_user_can()` returns false):

- `edit_listora_listing` — `meta: true`, `requires_context: "post"`
- `delete_listora_listing` — `meta: true`, `requires_context: "post"`

All 6 taxonomies' `assign_terms` map to **`edit_listora_listings`** (plural primitive cap). Confirms commit 9abbfcb's fix — Gutenberg sidebar panels now render correctly because the cap check no longer requires a post object.

### 16.6 Layout-owning blocks

Blocks whose top-level wrapper is a multi-track grid OR a 100%/wide-size container — these expect to BE the page, not sit alongside theme widget areas. The Theme_Defenses class can auto-register them as full-width.

- `listora/listing-grid`
- `listora/listing-map`
- `listora/listing-detail`
- `listora/listing-reviews`
- `listora/listing-submission`
- `listora/listing-categories`
- `listora/listing-featured`
- `listora/listing-calendar`
- `listora/user-dashboard`

### 16.7 Hook signature catalog (sample)

Every fired hook now includes `args_count`, `args_signature` (best-effort type inference), and `consumed_by` (intra-plugin listeners). Sample:

| Hook | Args | Signature | Where | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_add_favorite` | 3 | `int $listing_id, int $user_id, WP_REST_Request $request` | `includes/rest/class-favorites-controller.php:258` | includes/core/class-cache.php:86 |
| `wb_listora_after_create_listing` | 2 | `int $post_id, WP_REST_Request $request` | `includes/rest/class-submission-controller.php:512` | includes/core/class-cache.php:75, includes/search/class-search-indexer.php:60 |
| `wb_listora_after_create_review` | 3 | `int $review_id, int $listing_id, WP_REST_Request $request` | `includes/rest/class-reviews-controller.php:533` | includes/core/class-cache.php:81 |
| `wb_listora_after_update_listing` | 2 | `int $post_id, WP_REST_Request $request` | `includes/rest/class-submission-controller.php:676` | includes/core/class-cache.php:76, includes/search/class-search-indexer.php:61 |
| `wb_listora_listing_submitted` | 3 | `int $post_id, mixed($new_status) $new_status, mixed($synthetic_request) $synthetic_request` | `includes/admin/class-listing-columns.php:470` | includes/workflow/class-notifications.php:36 |


## Verification

- **Manifest:** `audit/manifest.json` (machine-readable)
- **Code flows:** `audit/CODE_FLOWS.md`
- **Permissions:** `audit/ROLE_MATRIX.md`
- **Architecture:** `docs/ARCHITECTURE.md` (already published)
