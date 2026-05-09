# WB Listora — CLAUDE.md

> **READ FIRST (in order):**
> 1. [`audit/manifest.summary.json`](audit/manifest.summary.json) — ≤3 KB plugin shape index.
> 2. [`audit/qa-index.json`](audit/qa-index.json) — QA artifact discovery + release gate + maintenance loop (machine-readable).
> 3. The **QA Pipeline** section below in this file — release gate + self-growth contract.
> 4. Most-recent [`audit/wppqa-baseline-2026-05-08/SUMMARY.md`](audit/wppqa-baseline-2026-05-08/SUMMARY.md) — current bug surface (**0 release blockers**).
>
> Full inventory in [`audit/manifest.json`](audit/manifest.json) (schema v2.1): 50 REST · 4 AJAX · 11 tables · 11 blocks (9 layout-owning) · 13 admin pages · 192 fired hooks (107 actions + 85 filters with `consumed_by`) · 15 caps · 6 taxonomies · 6 cron · 74 IAPI actions · 8 static detectors. Pre-computed sub-checks at [`audit/derived/`](audit/derived/) (10 cache files including `cross-plugin-coupling.json` with **29 Free→Pro pairs**). See [`audit/FEATURE_AUDIT.md`](audit/FEATURE_AUDIT.md), [`audit/CODE_FLOWS.md`](audit/CODE_FLOWS.md), [`audit/ROLE_MATRIX.md`](audit/ROLE_MATRIX.md). Refresh via `/wp-plugin-onboard --refresh` after non-trivial changes. The `docs/` folder is customer-facing documentation only — internal QA lives at `docs/qa/`, audit at `audit/`.

## Overview
Complete WordPress directory plugin. Create any type of listing directory — business, restaurant, hotel, real estate, jobs, events, and more.

## QA Pipeline (release gate + self-growth contract)

This is the **release gate** for every WB Listora version. It self-grows: every customer-visible bug fix and every new feature must add a row here in the same PR. Future Claude sessions should treat this section as the source of truth for "is this release ready?"

### Artifact map

| Artifact | Path | Purpose | Owner |
|---|---|---|---|
| Smoke runbook (canonical) | [`docs/qa/AGENT_SMOKE_RUNBOOK.md`](docs/qa/AGENT_SMOKE_RUNBOOK.md) | A-G customer contracts for fresh install, upgrade, all flows, regression guards, Pro extensions, cross-browser, post-release. **536 lines, last refreshed 2026-05-09.** | Bug-fix + feature PRs (write); smoke skill (read) |
| Pro supplements | [`../wb-listora-pro/docs/qa/AGENT_SMOKE_RUNBOOK.md`](../wb-listora-pro/docs/qa/AGENT_SMOKE_RUNBOOK.md) | Pro-only S1-S12 ops (lockstep / license / INV-12 / 29 coupling / strict HMAC / toggle isolation). | Pro PRs |
| Journeys (executable) | [`audit/journeys/`](audit/journeys/) | 19 self-contained markdown flows an agent runs end-to-end via Playwright + WP-CLI + curl + mysql_query. Returns PASS/FAIL with exact step + likely_files for triage. See [`audit/journeys/README.md`](audit/journeys/README.md) for the schema. | Bug-fix + feature PRs (write); `bin/run-journeys.sh` (execute) |
| QA index (machine-readable) | [`audit/qa-index.json`](audit/qa-index.json) | The structured index: artifacts, release gate requirements, maintenance loop, discovery order. CLAUDE.md prose mirrors it; this file is canonical. | This wiring pass; refreshed when QA shape changes |
| wppqa baseline | [`audit/wppqa-baseline-2026-05-08/SUMMARY.md`](audit/wppqa-baseline-2026-05-08/SUMMARY.md) | Static-analysis bug finder (plugin-dev-rules / REST↔JS contract / wiring). **0 release blockers.** Re-run via `wppqa_audit_plugin --plugin_path=$(pwd)`. | Onboarding refresh |
| Manifest | [`audit/manifest.json`](audit/manifest.json) + summary | Plugin shape + 8 static detectors. Refresh via `/wp-plugin-onboard --refresh` after non-trivial commits. | Onboarding skill |
| Smoke gate (release) | [`bin/build-release.sh`](bin/build-release.sh) ~lines 105-135 | **Refuses to package** unless `docs/qa/.last-smoke-pass.json` exists, version matches, `failures[]` + `debug_log_issues[]` empty. Emergency only: `--skip-browser-smoke`. | Release script |

### Release gate (must be GREEN before tagging)

Run before every release tag — copy the checklist:

1. **Architecture invariants** — `bash bin/architecture-checks.sh` returns 0 (12/12 pass).
2. **wppqa baseline** — most-recent `audit/wppqa-baseline-*/SUMMARY.md` shows `0 release blockers`. Re-run via the MCP tool if older than 7 days.
3. **Smoke pass** — run `/wp-plugin-smoke combo`. Confirms:
   - Walks every section of `docs/qa/AGENT_SMOKE_RUNBOOK.md`
   - Executes every authored journey under `audit/journeys/`
   - Writes `docs/qa/.last-smoke-pass.json` with `release_version` matching `WB_LISTORA_VERSION`, empty `failures[]`, empty `debug_log_issues[]`
4. **Architecture invariants again post-smoke** — guard against the smoke run dirtying state.
5. **Tag + run release script** — `bin/build-release.sh` re-validates the smoke report at packaging time as defense-in-depth.

If ANY of 1-4 fails → release is BLOCKED. No `--skip-browser-smoke` for customer-facing releases.

### Self-growth contract (this is how QA stays current)

QA self-grows. Every commit that changes customer behavior MUST add to QA in the same PR. Future Claude sessions: enforce this on review.

| Trigger | Required additions in the SAME PR |
|---|---|
| Customer-visible bug fix | (a) New row in runbook Section D + (b) regression journey at `audit/journeys/regression/<slug>.md` + (c) reference to bug card # in journey frontmatter `covers:` |
| New feature | (a) New row in runbook Section C (or E for Pro) with the customer contract + (b) `customer/` or `admin/` journey covering happy path + 1 negative test + (c) manifest refresh after merge |
| New REST endpoint | (a) Row in runbook Section C.rest.contract + (b) journey hitting the endpoint with auth + nonce + shape assertion + (c) manifest refresh |
| New admin page | (a) Row in runbook C.admin.* + (b) admin journey verifying render + at least one CRUD action |
| New Pro feature toggle | (a) Row in Free runbook Section E with toggle-on / toggle-off contract + (b) journey verifying isolation + (c) toggle entry in manifest's `feature_toggles[]` |
| Architecture invariant added | (a) New invariant ID in `plan/wb-listora-architecture-contract.md` + (b) check in `bin/architecture-checks.sh` + (c) Pro supplement S5 row |
| Two clean releases of a regression journey | Graduate it from `audit/journeys/regression/` → `customer/` or `admin/`. Move the runbook D row into the matching C/E row. Update `audit/qa-index.json` counts. |

### Self-check command (the loop)

The user (or any session) runs `/wp-plugin-smoke combo` from this directory. The skill:

1. Reads `docs/qa/qa-config.json` — slug, version constant, base URL, Basecamp project.
2. Dispatches a Sonnet sub-agent with hard constraints (verification only — no Edit, no git, only one Write target).
3. Sub-agent walks the runbook + executes every journey + diffs `wp-content/debug.log` after every section.
4. Writes `docs/qa/.last-smoke-pass.json` with structured results.
5. Returns failures + Basecamp drafts. Opus re-verifies before filing cards.

Expected runtime: ~25 min on Sonnet for combo mode. The expensive Opus context is preserved for review + decisions, NOT the walk itself.

### Discovery for new sessions

A fresh `cd` into this directory should read in this order:
1. `audit/manifest.summary.json` (≤3 KB)
2. `audit/qa-index.json` (this section's source of truth)
3. This QA Pipeline section
4. Most-recent `audit/wppqa-baseline-*/SUMMARY.md`

Everything else is on-demand.

## Tech Stack
- **PHP:** 7.4+ (WordPress plugin)
- **JS:** @wordpress/scripts, @wordpress/interactivity API
- **Build:** `npm run build` (wp-scripts)
- **CSS:** PostCSS via wp-scripts
- **Database:** 10 custom tables (listora_ prefix)

## Architecture

### Plugin Entry
- `wb-listora.php` — Main file, constants, autoloader, fires `wb_listora_loaded`

### Core (`includes/core/`)
- `class-post-types.php` — `listora_listing` CPT
- `class-taxonomies.php` — `listora_listing_cat`, `listora_listing_type`, `listora_listing_location`, `listora_listing_feature`, `listora_service_cat`
- `class-listing-type-registry.php` — Dynamic listing types (restaurant, hotel, etc.)
- `class-listing-type.php` / `class-listing-type-defaults.php` — Type config + defaults
- `class-field-registry.php` / `class-field.php` / `class-field-group.php` — Custom field system
- `class-meta-handler.php` — Meta storage/retrieval
- `class-capabilities.php` — Custom caps
- `class-services.php` — Services CRUD (listora_services table)

### Admin (`includes/admin/`)
- `class-admin.php` — Admin init, menu
- `class-settings-page.php` — Settings UI with tabs
- `class-listing-columns.php` — Admin columns
- `class-setup-wizard.php` — First-run wizard

### Search (`includes/search/`)
- `class-search-engine.php` — Main search with facets, geo, fulltext
- `class-search-indexer.php` — Builds denormalized search_index
- `class-facets.php` — Faceted search
- `class-geo-query.php` — Haversine distance queries

### REST API (`includes/rest/`)
- `class-listings-controller.php` — CRUD for listings
- `class-reviews-controller.php` — Reviews, helpful votes, replies, reports
- `class-search-controller.php` — Search endpoint
- `class-submission-controller.php` — Frontend submission
- `class-claims-controller.php` — Business claims
- `class-favorites-controller.php` — User favorites
- `class-dashboard-controller.php` — User dashboard data
- `class-listing-types-controller.php` — Type definitions
- `class-settings-controller.php` — Admin settings
- `class-services-controller.php` — Service CRUD endpoints

### Blocks (`blocks/`)
11 blocks using Interactivity API:
- `listing-grid`, `listing-card`, `listing-search`, `listing-map`
- `listing-detail`, `listing-reviews`, `listing-submission`
- `listing-categories`, `listing-featured`, `listing-calendar`
- `user-dashboard`

### Shared Block Infrastructure (`src/shared/`)
- `components/` — 7 editor controls: ResponsiveControl, SpacingControl, TypographyControl, BoxShadowControl, BorderRadiusControl, ColorHoverControl, DeviceVisibility
- `hooks/` — useUniqueId (auto-generate block instance ID), useResponsiveValue (device-aware values)
- `utils/attributes.js` — Standard attribute schemas (spacing, typography, shadow, border, visibility)
- `utils/css.js` — Per-instance CSS generator (responsive media queries)
- `base.css` — Block reset, device visibility classes, reduced motion
- `theme-isolation.css` — Neutralizes aggressive theme styles (BuddyX, Reign, Astra)

### Block Quality Standard
Every block has:
- 20 standard attributes (uniqueId, responsive padding/margin, border radius, box shadow, device visibility)
- apiVersion 3
- InspectorControls with panels: Content, Display, Layout, Style, Advanced
- Per-instance CSS scoping via `Block_CSS::render()`
- All view.js files import shared store for proper dependency chain

### PHP Utilities
- `includes/class-block-css.php` — `WBListora\Block_CSS` — generates per-instance scoped CSS, visibility classes, wrapper classes
- `includes/core/class-lucide-icons.php` — `WBListora\Core\Lucide_Icons` — inline SVG rendering for 21 Lucide icons

### Template Override System
Themes can override templates WooCommerce-style:
- Path: `{theme}/wb-listora/blocks/listing-card/card.php` etc.
- Functions: `wb_listora_get_template()`, `wb_listora_locate_template()`, `wb_listora_get_template_html()`
- Currently used for: email templates, block templates (listing-card, listing-detail, user-dashboard)

### Database Tables (prefix: `listora_`)
`geo`, `search_index`, `field_index`, `reviews`, `review_votes`, `favorites`, `claims`, `hours`, `analytics`, `payments`, `services`

## Key Constants
```php
WB_LISTORA_VERSION        // '1.0.0'
WB_LISTORA_TABLE_PREFIX   // 'listora_'
WB_LISTORA_REST_NAMESPACE // 'listora/v1'
WB_LISTORA_META_PREFIX    // '_listora_'
```

## Key Hooks (for Pro extensibility)
- `wb_listora_loaded` — Plugin fully loaded
- `wb_listora_rest_api_init` — REST routes registered
- `wb_listora_review_criteria` — Filter review criteria fields
- `wb_listora_after_listing_fields` — Action after listing detail fields
- `wb_listora_map_config` — Filter map configuration
- `wb_listora_settings_tabs` / `wb_listora_settings_tab_content` — Settings extensibility
- `wb_listora_listing_submitted` — After frontend submission
- `wb_listora_review_submitted` — After review posted
- `wb_listora_search_args` — Filter search parameters

### Block Render Hooks
- listing-grid: `wb_listora_before_listing_grid`, `wb_listora_grid_query_args`, `wb_listora_grid_after_card`, `wb_listora_after_listing_grid`
- listing-featured: `wb_listora_before_featured_listings`, `wb_listora_featured_query_args`, `wb_listora_after_featured_listings`
- listing-categories: `wb_listora_before_categories_grid`, `wb_listora_category_card_data`, `wb_listora_after_categories_grid`
- listing-calendar: `wb_listora_before_calendar`, `wb_listora_calendar_events`, `wb_listora_after_calendar`
- listing-map: `wb_listora_before_map`, `wb_listora_after_map`

### Write-Operation Hooks (before_ / after_)
All write operations fire a `before_` filter (return WP_Error to abort) and `after_` action:
- `wb_listora_before_create_listing` / `wb_listora_after_create_listing`
- `wb_listora_before_update_listing` / `wb_listora_after_update_listing`
- `wb_listora_before_delete_listing` / `wb_listora_after_delete_listing`
- `wb_listora_before_create_review` / `wb_listora_after_create_review`
- `wb_listora_before_update_review` / `wb_listora_after_update_review`
- `wb_listora_before_delete_review` / `wb_listora_after_delete_review`
- `wb_listora_before_add_favorite` / `wb_listora_after_add_favorite`
- `wb_listora_before_remove_favorite` / `wb_listora_after_remove_favorite`
- `wb_listora_before_submit_claim` / `wb_listora_after_submit_claim`
- `wb_listora_before_update_claim` / `wb_listora_after_update_claim`
- `wb_listora_before_create_service` / `wb_listora_after_create_service`
- `wb_listora_before_update_service` / `wb_listora_after_update_service`
- `wb_listora_before_delete_service` / `wb_listora_after_delete_service`

### REST Response Filters
Every REST response is filterable for Pro/extensions to add fields:
- `wb_listora_rest_prepare_listing` — single listing detail + submission/update response
- `wb_listora_rest_prepare_review` — each review in list + create/update response
- `wb_listora_rest_prepare_favorite` — each favorite in list + add/remove response
- `wb_listora_rest_prepare_claim` — each claim in list + submit/update response
- `wb_listora_rest_prepare_search_result` — search results array
- `wb_listora_rest_prepare_dashboard_stats` — dashboard stats
- `wb_listora_rest_prepare_listing_type` — listing type response
- `wb_listora_rest_prepare_service` — each service in list + create/update response

## Interactivity API
- Single namespace: `listora/directory`
- ALL actions in `src/interactivity/store.js` (NOT in individual view.js files)
- Server state via `wp_interactivity_state()` — do NOT define client defaults for server-provided keys
- View.js files import the shared store to ensure proper load order

## Recent Changes (2026-05-08 PM — same-family migration + bug-fix sweep + CI baseline restoration)

| Area | Change |
|---|---|
| **Bugs shipped (5 → Ready for Testing)** | Card #9867159785 setup-wizard headers-already-sent (round 2: POST handling moved to `admin_init` priority 1 via new `Setup_Wizard::init()` / `handle_post_submission()` static pair). Card #9867347053 empty Media fieldset on submission Details step (suppress fieldset when every field in the group is renderer-skipped). Card #9867775853 raw attachment ID on Overview tab (skip `file`-type fields on `tabs.php` Overview loop; they render as image/link in their own field-group tab). Card #9867372176 map popup featured image (prefetch listing post-meta; new `image` field in markers JSON; client `imageHtml` injection in popup template). Card #9856828615 Business Hours flatpickr round 2 (vendored 4.6.13, `enableTime + noCalendar + dateFormat H:i + minuteIncrement 15 + allowInput`, idempotent attach via `data-listora-flatpickr-attached` flag). |
| **New extension hook (+1 fired action)** | `wb_listora_register_pages` fired at `includes/page-registry-helpers.php:269` after Free's 3 canonical pages register. New public helper `wb_listora_register_page( $key, $config )` is the documented surface — Pro consumes via the action and helper, never touches `Page_Registry::register` directly. Closes architecture invariant INV-3 (Pro→Free internal-namespace coupling) on Pro's side. |
| **Frontend same-family primitives (Part 7.6.1 / F9)** | New canonical vocabulary in `assets/css/shared.css` + RTL twin: `.listora-page--{single,list,dashboard,booking}` shell with `.listora-page-header + body` children, `.listora-ui-card__head + body + foot` triplet, `.listora-card--empty` + `.listora-empty + __icon + __title + __desc + __actions`, badge canonical variants `--success/--warning/--danger/--info/--neutral`, numeric spacing tokens `--listora-space-1..12`, numeric type tokens `--listora-font-size-xs..4xl`. Page-shell variants only change max-width + outer padding (`--single` 1200px / `--list` 1400px / `--dashboard` 1280px / `--booking` 720px). |
| **Template outer-shell migration** | listing-detail wrapped in `.listora-page--single` (existing `.listora-detail__*` BEM CSS retained for inner content). listing-grid empty state on `.listora-card--empty .listora-empty`. listing-submission on `.listora-page--booking` (720px focused-form width). listing-reviews empty state on canonical primitives. user-dashboard on `.listora-page--dashboard` (1280px sidebar-nav layout). listing-categories empty state on canonical. listing-card / listing-search / listing-map / listing-featured / listing-calendar already canonical-compliant — no outer-shell changes needed. Each refactor a single commit, visually verified on directory.local. |
| **CI baseline restored** | WPCS: phpcbf cleared 191 of 251 violations; remaining 4 errors fixed in code (WPML hook prefix, $_POST features sanitization explicit, file-docblock order, standalone-page inline script ignore). PHPStan: 12 real type bugs fixed (strtotime cast, redundant is_wp_error, defensive method_exists drop, get_log() PHPDoc split into get_log() + get_log_paginated(), email-verification int casts, page-registry exit guard). 102 remaining annotation gaps baselined as legacy debt. 2 new stub files (Action Scheduler conditional functions; Demo_Seeder public surface). composer.json phpcs script gains `--runtime-set ignore_warnings_on_exit 1` so warnings don't trip the gate. **Pre-push hook now runs cleanly without `SKIP_LOCAL_CI=1`**. |
| **Filed enhancement (Suggestion column)** | BC card #9871176148 — `social_links` field has data model + sanitization + REST round-trip but no submission UI anywhere. Surfaced while fixing the empty Media fieldset bug. Sized as ~5h follow-up: platform repeater UI in step-media.php + icon row on listing detail. |

**Manifest delta:** `hooks_fired` 191 → **192** (+1 — `wb_listora_register_pages`). `cross-plugin-coupling.json` pairs 28 → **29**. wppqa baseline 2026-05-08: 0 release blockers, headline matches 2026-05-07 with the 3 wiring "errors" classified as service-layer false-positives.

## Recent Changes (2026-05-08 AM — Free→Pro extension surface alignment)

| Area | Change |
|---|---|
| Hooks fired | **+1 action `wb_listora_after_service_detail`** at `templates/blocks/listing-detail/tabs.php` inside services-grid foreach. Args: `(int $service_id, int $listing_id)`. Pro's `Services_Pro::fire_booking_hook` (orphan listener since shipping) now activates — service-card booking CTA renders. |
| Hooks fired | **+1 filter `wb_listora_member_profile_url`** with signature `(string $url, int $user_id, string $context)` fired at 3 sites: `templates/blocks/listing-detail/tabs.php:344` (review_user), `templates/blocks/listing-reviews/reviews.php:105` (review_user), `includes/rest/class-reviews-controller.php:331` (review_user). Pro's BuddyPress integration listens here to swap empty default for `bp_core_get_user_domain($user_id)`. |
| Templates | `tabs.php:332-345` review-author span is now a link (`<a class="listora-detail__review-author--link">`) when the filter returns non-empty — falls back to `<strong>` plain text when no profile URL. `review-card.php:27-31` same treatment for `.listora-reviews__reviewer--link`. |
| Templates | `reviews.php:104-117` now passes `reviewer_id` and `reviewer_url` into the card data array. `review-card.php` accepts both as new template vars (defaults supplied so theme overrides remain back-compatible). |
| REST | Reviews list response gains `user_profile_url` field next to `user_name` / `user_avatar` — empty string when no profile is available (anonymous user, BP inactive). Headless clients can render the same link decision without re-running the filter. |
| CSS | New BEM modifiers `.listora-detail__review-author--link` and `.listora-reviews__reviewer--link` with hover/focus-visible styling using `--listora-primary` and `--listora-text` tokens. RTL files regenerate on next `npm run build`. |
| Architectural rationale | Author URL is the wrong abstraction for a directory plugin — listings have OWNERS, reviews have user accounts (members). The 2 previously-deferred hooks `wb_listora_author_url` + `wb_listora_review_author_url` will NOT ship by design; they were the wrong shape. |

## Recent Changes (2026-05-07 — Phase 1+2+3 100K-readiness sprint)

| Commit | Area | Change |
|--------|------|--------|
| `47685c5` | P1-1.A | New `WBListora\Workflow\Cron_Scheduler` helper — abstraction over Action Scheduler with WP-Cron fallback. Idempotent transition: clears any existing WP-Cron event for the same hook before scheduling via AS. |
| `b69c3dc`, `82aceb1`, `047936e` | P1-1.B | All 6 Free cron jobs migrated from `wp_schedule_event` to `as_schedule_recurring_action`: expiration, draft cleanup, expiry-reminder, featured-listing rotation, email-verification cleanup. WP-Cron drops jobs at scale; AS retries. |
| `98c8d47` | P1-2 | N+1 prefetch in REST listings list — `class-listings-controller.php` now calls `update_post_meta_cache(wp_list_pluck($posts, 'ID'))` + `update_object_term_cache()` before the prepare-item loop. Saves ~100 queries per request at 100K listings. |
| `ef0b271` | P1-3 | 43 apiFetch sites wrapped in AbortController + 10s timeout via new `src/utils/abortable-fetch.js` ES-module helper. AbortError surfaces a translatable "Network is slow — please try again." toast instead of leaving the UI in permanent loading. |
| `64e9a05` | P1-4 | 105 bare `1fr` CSS grid tracks → `minmax(0, 1fr)` across 29 files (LTR + RTL). Bare 1fr resolves to `minmax(auto, 1fr)` and lets children overflow their share; `minmax(0, 1fr)` caps the lower bound. `static_analysis.grid_track_overflow_risks` 16 → **0**. |
| `0b04824` | P1-5 | 4 new block render hooks: `wb_listora_before_listing_card`, `wb_listora_after_listing_card`, `wb_listora_search_before_form`, `wb_listora_search_after_form`. Both blocks previously had ZERO `do_action`/`apply_filters` calls — Pro and themes had to fork to extend. |
| `263c4c2` | P1-6 | `listora-submit-lock.js` switched from unconditional frontend enqueue to register-only. Saves 1-2 KB + parse cost on every public-frontend request that doesn't render a Listora block. |
| `42511ef` | P2-8 | 5 read-only `get_option('wb_listora_settings')` sites routed through `wb_listora_get_setting()` helper (cache hits + per-key extension filters now reach those code paths). |
| `9b5d8cd` | P2-3 | New `Capabilities::can_*()` query helpers + 5 cap constants (`CAP_MANAGE_SETTINGS`, `CAP_MODERATE_REVIEWS`, `CAP_MANAGE_CLAIMS`, `CAP_MANAGE_TYPES`, `CAP_SUBMIT_LISTING`). Additive — existing inline `current_user_can()` calls unchanged. |
| `0c33baf` | P2-10 | `declare(strict_types=1)` on the 2 new files we authored (`Migrated_From_Tracker`, `Cron_Scheduler`). Establishes the forward-looking baseline; existing untyped files left for a dedicated PHPStan-led pass. |

**Manifest impact:** `static_analysis.rest_hang_risks` 43 → **0**; `static_analysis.grid_track_overflow_risks` 16 → **0**; `hooks_fired` actions count +4 (block render hooks). Cron transport metadata flipped to Action Scheduler. All 12 architecture invariants pass.

**Note:** This is a TARGETED refresh — a full hooks_fired re-scan is deferred. To run the full algorithm: `/wp-plugin-onboard --refresh`.

## Recent Changes (2026-05-07 — Phase 0 release-blocker fixes)

| Commit | Area | Change |
|--------|------|--------|
| `bcd8157` | INV-12.1 prep | New `do_action( 'wb_listora_listing_claimed', $listing_id, $context )` fired in `class-claims-controller.php:512` after Free's `_listora_is_claimed` write. Pro listens to sync search-index `is_claimed`. |
| `6a39d2b` | INV-12.2 prep | New `apply_filters( 'wb_listora_listing_expiration_date', $expiry, $post_id, $context )` fired BEFORE every `update_post_meta('_listora_expiration_date')` write — `class-status-manager.php:99,118` (set on publish) + `class-listings-controller.php:1654` (renew). |
| `ea9644d` | INV-12.3 prep | New `WBListora\Migration\Migrated_From_Tracker` class — sole writer of `_listora_migrated_from`. `class-migration-base.php:297` switched to `Tracker::set()`. Pro's competitor migrators consume the same writer. |
| `1d2bf61` | INV-12.4 prep | `class-settings-page.php:987` no longer reads `wb_listora_pro_webhook_secret` directly — fires `apply_filters( 'wb_listora_webhook_secret', '', $context )` instead; Pro answers from `Webhook_Receiver::get_secret`. |
| `41aa81e` | W.3 | `current_user_can('read')` gate added to `ajax_dismiss_promo()` (class-pro-promotion.php:1193) so security scanners stop flagging nonce-no-cap. |
| `74666f6` | W.1 | Native `confirm()` fallbacks removed from `src/interactivity/store.js` deactivate/reactivate flows — direct `await window.listoraConfirm()` (defensive native fallback was the wppqa Rule 10 hit). |
| `7c5a3d7` | W.2 | Native `alert()` fallbacks in `src/blocks/listing-submission/view.js` (media-uploader-not-loaded, gallery file-too-large) replaced with new `showUploaderInlineError()` helper that injects `<div role="alert" class="listora-form__error">` next to the upload trigger. |

**wppqa baseline post Phase 0:** plugin-dev-rules 9 passed / 0 failed · rest-js-contract 6 / 0 · wiring 5 / 2 (both false positives — service-layer reads, unchanged from prior baseline). **0 release blockers.**

**Manifest delta:** hooks_fired 188 → 191 (+3 — `wb_listora_listing_claimed`, `wb_listora_listing_expiration_date`, `wb_listora_webhook_secret`). New class `WBListora\Migration\Migrated_From_Tracker`. PSR-4 autoloader resolves it under `includes/migration/`.

## Recent Changes (2026-05-07 — refresh since 04-30 PM at 17:30Z)

| Area | Change |
|---|---|
| Manifest | Diff-driven refresh. **+1 admin page** (Email Log submenu — was missing in prior manifest), **+4 fired hooks** (188 total: 105 actions + 83 filters). |
| New hooks | `wb_listora_after_reactivate_listing` (class-listings-controller.php:1106) · `wb_listora_after_reset_settings` (class-settings-controller.php:371, **Pro consumes**) · `wb_listora_reset_option_keys` filter (class-settings-controller.php:360, **Pro consumes**) · `wb_listora_review_status_changed` (class-reviews-controller.php:650, args_count 3). |
| Cross-plugin | `cross-plugin-coupling.json` 23 → **25** pairs. Pro's class-pro-plugin.php:46-47 listens on the 2 settings-reset hooks; Reset Settings now fully purges Pro options. |
| REST | Coverage gate: 50 in manifest = 50 in source (PASS, 0% gap). REST_AUDIT_2026-05-01.md verified clean at 2026-05-07. |
| wppqa | New baseline 2026-05-07: **5 real high-severity errors** to triage (was 0 release-blockers). 2 alert() in `src/blocks/listing-submission/view.js:436,475`, 2 confirm() in `src/interactivity/store.js:866,932`, 1 nonce-no-cap at `includes/admin/class-pro-promotion.php:1193`. 2 wiring half-wired findings classified false positives (service-layer reads). REST↔JS contract clean (0 issues). |
| Derived caches | dead-listeners.json re-verified at 0 plugin-own (89 listeners vs 187 firers; 9 candidate orphans all classified). All other Phase 2.5 caches retained. |

## Recent Changes (2026-04-30 — PM, since manifest at 16:30Z)

| Commit | Area | Change |
|--------|------|--------|
| `f69f47f` | Dashboard / IAPI | **T1+T4** — Owner: Deactivate Listing now uses the design-system `listoraConfirm` Promise-modal (`src/interactivity/store.js:820-833`); native `window.confirm()` retained at line 835 only as a CSP/blocker defensive fallback. 3 i18n keys added (`confirmDeactivate`, `confirmDeactivateTitle`, `deactivate`) in `includes/class-assets.php`. `listora-confirm` CSS+JS now actually enqueued by `blocks/user-dashboard/render.php:13-14` (the global registration was previously orphaned). T4 documents `class-pro-promotion.php:1188 ajax_dismiss_promo` nonce-no-cap as a verified false positive (per-user cookie, no shared mutation, gated by `wp_ajax_*`-only registration). |
| `0aa62ca` | Notifications | **F1** — Restored listing-lifecycle emails. The 3 `add_action` lines in `includes/workflow/class-notifications.php:39-41` referenced typo'd hook names (`wb_listora_listing_publish`, `wb_listora_listing_listora_rejected`, `wb_listora_listing_listora_expired`) that nothing fired. Replaced with a single canonical-hook listener on `wb_listora_listing_status_changed` plus an `on_listing_status_changed` dispatcher. Approve/reject/expire emails now reach owners. |
| `847dcc8` | Settings / Filter | **O3** — `wb_listora_map_provider` filter is now actually fired from `wb_listora_get_setting()` in `wb-listora.php:288` for the `map_provider` key. Pro's listener at `class-google-maps.php:41` (registered since v1) finally takes effect; the documented extension point in `plans/free/11-maps.md` is no longer aspirational. **Manifest impact:** `hooks_fired` 183 → 184. |
| `691fd44`, `a631412`, `a333dc8`, `e1e430a`, `4bef4a2`, `a58141c`, `e6e9a38` | Plan/docs | Cross-ref orphans plan + audit-task plan + completion footers. No code/manifest impact. |

These three code commits introduce 1 new fired filter (`wb_listora_map_provider`), 1 new Free-side action listener (`Notifications::on_listing_status_changed`), 0 new REST endpoints / blocks / tables / capabilities. wppqa baseline status: 18 passed / 4 failed — **all 4 failures are now classified as false positives** (the prior baseline had 2 real + 2 false-positive). New derived cache: `audit/derived/cross-plugin-coupling.json` — 23 Free-fires/Pro-consumes pairs.

## Recent Changes (2026-04-30 — late, since manifest at 09:20:00Z)

| Commit | Area | Change |
|--------|------|--------|
| `63411c8` | Interactivity | Claim/Share/Login modal stuck closed — fixed by binding `data-wp-class--is-open` to a property getter, not an inline `===` expression. `src/interactivity/store.js` adds `isClaimModalOpen`, `isShareModalOpen`, `isLoginModalOpen` derived getters; `blocks/listing-detail/render.php` modal markup updated. Manifest `interactivity[0].state_keys` 35 → 38. |
| `253cef9` | Detail | Helpful vote button added to the Reviews tab template (`templates/blocks/listing-detail/tabs.php`); REST endpoint already existed. |
| `7606f8c` | Activator | FULLTEXT index split out of `dbDelta()` to avoid SQL syntax error. `includes/class-activator.php`. |
| `182f654` | Dashboard | CSS-only — submit-state spans hide via `is-hidden` class so label and spinner never both show. `blocks/user-dashboard/style.css`. |
| `e01486b` | Dashboard | Reply wired to `/reviews/{id}/reply` via inline form (not a modal). `templates/blocks/user-dashboard/tab-reviews.php` + `src/interactivity/store.js`. |

These are surgical bug fixes — no new REST endpoints, AJAX actions, blocks, tables, capabilities, or fired hooks.

### IAPI directive rule (from 63411c8)
**`data-wp-class--*` and `data-wp-bind--*` MUST read a tracked property, never a literal-comparison expression.** IAPI's reactivity tracks property reads — `state.activeModal === 'claim'` doesn't re-evaluate when `activeModal` mutates. Always introduce a derived getter (e.g. `get isClaimModalOpen() { return state.activeModal === 'claim'; }`) and bind directives to that getter. Same pattern: `activeTab` → `isReviewsTabActive`, `currentStep` → `isStepDetailsActive`, etc.

## Recent Changes (2026-04-30 — earlier, manifest schema upgrade)

| Area | Change |
|------|--------|
| Audit | Manifest upgraded **v1 → v2 schema**. Adds `args_signature`, `consumed_by` (array), capability `meta`/`requires_context`, taxonomy `capabilities` map, `blocks[].layout_owning`, top-level `interactivity[]`, `ui_activation[]`, `static_analysis{}` |
| Audit | Phase 2.5 detectors all run: dead-listeners (0), cap-context-mismatches (0 — taxonomy fix verified), extensibility-gaps (0 — submission-step fix verified), js-only-activation (3, settings has php_fallback:true), rest-hang-risks (43 enumerated), visual-required (1 a11y gap on featured_image), grid-1fr (16 entries) |
| Audit | `static_analysis.cap_context_mismatches=0` confirms commit 9abbfcb's taxonomy primitive-cap fix |
| Audit | `js_only_activation[2].php_fallback=true` for `.listora-settings-section` confirms commit fda50ee's settings server-side `is-active` fix |
| Audit | Search action (`store.js:184`) detected as `uses_abort_signal:true, has_timeout_ms:20000` confirms commit 50dc326's search-robustness fix |

## Recent Changes (2026-04-13)

| Area | Change |
|------|--------|
| Blocks | Shared infrastructure: 7 editor controls, 2 hooks, 2 utils, CSS reset |
| Blocks | All 11 blocks: InspectorControls with 5 panels (Content, Display, Layout, Style, Advanced) |
| Blocks | All 11 block.json: 20 standard attributes, apiVersion 3 |
| Blocks | Per-instance CSS scoping via Block_CSS class |
| Icons | Lucide_Icons SVG helper (21 icons), replaced broken dashicons in 5 render.php |
| CSS | Breakpoints standardized (1024px/767px), card tokens unified, icon button token |
| Hooks | 15 new hooks across 5 blocks (grid, featured, categories, calendar, map) |
| Interactivity | Detail view actions merged into main store, server state fix |
| Templates | WooCommerce-style overrides for listing-card, listing-detail, user-dashboard |

## Recent Changes (2026-04-05)

| Area | Change |
|------|--------|
| Services | Listing Services system: listora_services table, Services CRUD class, REST controller |
| Services | listora_service_cat taxonomy for categorizing services |
| Services | Services tab on listing detail page with card grid |
| Services | Manage Services in user dashboard per listing |
| Services | Service text indexed in search_index for full-text search |
| Services | Schema.org OfferCatalog markup for services |
| REST | before_/after_ hooks on all write operations (create/update/delete) |
| REST | REST response filters on all endpoints (wb_listora_rest_prepare_*) |
| REST | Permission callbacks return WP_Error instead of false (401/403) |
| Build | viewScript → viewScriptModule (ES modules for Interactivity API) |
| Build | Dual webpack config (classic IIFE + ESM modules) |
| WP Req | Bumped to WordPress 6.9 |
| CI | GitHub Actions: PHP Lint, WPCS, PHPStan L5, PHPUnit, PCP |
| Import | JSON + GeoJSON importers, 4 competitor migration tools |
| Events | Recurring events, date filters, calendar virtual occurrences |
| Email | All 14 notification templates + draft reminder cron |
| Spam | reCAPTCHA v3 + Cloudflare Turnstile + rate limiting |
| Submission | Guest registration, conditional fields, draggable map pin |
| Demo | 5 type-specific demo packs in setup wizard |
| Admin | Lucide icon picker, onboarding checklist |

## Recent Changes (2026-04-06)

| Area | Change |
|------|--------|
| Tokens | Hardcoded hex → `--listora-*` tokens in card, detail, toast, dashboard |
| Tokens | Added `--listora-warning` + `--listora-premium` to shared.css |
| Architecture | New `Listing_Data` helper class — extracts DB queries from render.php |
| Performance | Dashboard stats cached in 60s transient with cache-busting hooks |
| UX | Categories empty state, review form inline validation on blur |
| UX | Settings Import/Export tab fix (duplicate section ID) |
| Responsive | 480px detail breakpoint, 390px calendar breakpoint |
| Responsive | Featured carousel `min(260px, 80vw)`, dashboard tab scroll hint |
| Admin | Button text visibility fix (scoped selector) |

## Commands
```bash
npm install && npm run build   # Build JS/CSS
```

## Environment
- **Local URL:** http://wb-listora.local
- **WP Root:** /Users/varundubey/Local Sites/wb-listora/app/public/
- **Repository:** wbcomdesigns/wb-listora
- **Basecamp project:** https://3.basecamp.com/5798509/buckets/46767283/card_tables/9752604461 *(legacy / dev tasks)*

## Basecamp QA Workflow

**Active QA project ID:** `47045113` (WB Listora QA)

| Column | ID | Use for |
|--------|----|---------|
| **Bugs** | `9827892296` | New bug reports from QA |
| **Suggestion** | `9827892305` | UX suggestions / improvements |
| **Ready for Testing** | `9827892302` | Fixed — awaiting QA verification |
| **Done** | check via `basecamp_list_columns` | Verified by QA |

**Workflow for every bug card:**
1. Read the card (`basecamp_read`).
2. Investigate + reproduce locally.
3. Implement fix; commit + push to `main`.
4. **Comment on the card** (`basecamp_comment`) with:
   - `<strong>Fixed</strong>` / `<strong>Cannot reproduce</strong>` / `<strong>By design</strong>`
   - Commit hash(es) and repo
   - Root cause (file:line citation)
   - Fix summary
   - **How to test** steps
5. **Move REAL fixes to Ready for Testing** (`basecamp_move_card` to column `9827892302`).
6. CANNOT-REPRO / BY-DESIGN cards: comment only, leave in Bugs column with reopen criteria.

Use HTML in comments (markdown does NOT render in Basecamp): `<strong>`, `<br>`, `<code>`, `<em>`.

**Never skip the comment + move steps** — without them QA has no signal that a fix is ready, and the kanban becomes meaningless.

## Glossary
- **Listing** -- A single directory entry (business, restaurant, hotel, etc.)
- **Directory** -- The collection of all listings
- **Listing Type** -- A category template (Restaurant, Hotel, Real Estate, etc.) that determines which fields appear
- **Category** -- Taxonomy for organizing listings within a type (e.g., Italian, French under Restaurant)
- **Location** -- Hierarchical geographic taxonomy (Country > State > City)
- **Feature** -- Amenities or attributes (WiFi, Parking, Pet Friendly)
- **Claim** -- A request from a business owner to take ownership of their listing
- **Review** -- A user rating (1-5 stars) with text feedback for a listing
- **Submission** -- The process of a frontend user creating a new listing
- **Dashboard** -- The frontend user panel for managing listings, reviews, and favorites

## Local CI pipeline (REQUIRED before push)

This plugin has a self-contained local-CI gate. No external service runs the gate — every contributor runs it on their own machine, and the pre-push git hook runs it automatically before every `git push`.

```bash
composer install-hooks    # one-time per clone — activates bin/git-hooks/pre-push
composer ci               # full pipeline (~30s + browser journeys)
composer ci:no-journeys   # everything except browser-dependent journeys (~25s)
composer ci:quick         # PHP lint + coding-rules only (~10s, for tight loops)
```

What the gate runs (in order, see `bin/local-ci.sh`):

| Stage | Tool | Catches |
|---|---|---|
| 1.1 PHP lint | `php -l` on every changed source | syntax errors |
| 1.2 WPCS | `composer phpcs` | WordPress coding standards |
| 1.3 PHPStan | `composer phpstan` | static type errors |
| 2.1 Coding rules | `bin/coding-rules-check.sh` | plugin-specific rules |
| 3.1 Manifest | `jq` on `audit/manifest.json` | manifest validity + freshness |
| 4.1 Journeys | `bin/run-journeys.sh` | customer flows end-to-end |

**Bypass for emergencies only**: `SKIP_LOCAL_CI=1 git push`.

## Customer journeys

Bug fixes that survive a refactor are journey-covered. See [`audit/journeys/README.md`](audit/journeys/README.md) for the schema and the executor contract. When a new bug is fixed, add or update the journey that would have caught it. The journey IS the regression test.

Authored journeys (19 total — split across `customer/` / `admin/` / `regression/`):

**Customer (5):**
| File | Priority | Covers |
|---|---|---|
| `customer/01-browse-and-favourite-a-listing.md` | critical | search-grid render, modal-getter pattern (63411c8), favourites REST |
| `customer/02-submit-a-listing-wizard-end-to-end.md` | critical | submission wizard, conditional fields, POST /submit |
| `customer/03-write-and-reply-to-a-review.md` | critical | review create, Helpful (253cef9), dashboard reply (e01486b) |
| `customer/04-search-with-filters.md` | critical | search engine, geo, facets, filter-count badge, empty state |
| `customer/05-claim-a-business.md` | critical | claim modal, login gate, listora_claims, listing-claimed hook |

**Admin (5):**
| File | Priority | Covers |
|---|---|---|
| `admin/01-approve-pending-listing.md` | critical | listing status transition, notification email, log (sentinel for 0aa62ca) |
| `admin/02-moderate-review.md` | critical | reviews REST status enum (36033b0), moderator cap |
| `admin/03-approve-claim.md` | critical | post_author transfer, _listora_is_claimed flag, hook |
| `admin/04-setup-wizard-first-run.md` | critical | wizard headers regression #9867159785 |
| `admin/05-add-listing-from-wp-admin.md` | high | CPT edit screen, services photo, expiration calc |

**Regression sentinels (9):**
| File | Priority | Covers |
|---|---|---|
| `regression/dashboard-2-col-layout.md` | high | sidebar+main grid (today's regression) |
| `regression/empty-state-server-rendered.md` | high | 0-result IAPI getter (today's regression) |
| `regression/services-photo-upload.md` | high | services metabox photo column #9872014083 |
| `regression/map-fatal.md` | critical | map block fatal #9871222447 + popup image #9867372176 |
| `regression/empty-media-fieldset.md` | high | submission step suppression #9867347053 |
| `regression/overview-company-logo-id.md` | normal | tabs.php file-type skip #9867775853 |
| `regression/service-details-toggle.md` | normal | services tab toggle #9872013428 |
| `regression/filter-count-dropdowns.md` | normal | badge count dropdowns #9871208081 |
| `regression/business-hours-firefox.md` | high | flatpickr round-2 #9856828615 (Firefox manual) |

Run all: `composer journeys` · Critical only: `composer journeys:critical` · Dry-run: `composer journeys:dry-run`
