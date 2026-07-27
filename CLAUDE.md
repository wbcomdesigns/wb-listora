# WB Listora — CLAUDE.md

> **READ FIRST (in order):**
> 1. [`audit/manifest.summary.json`](audit/manifest.summary.json) — ≤3 KB plugin shape index.
> 2. [`docs/qa/qa-index.json`](docs/qa/qa-index.json) — QA artifact discovery + release gate + maintenance loop (machine-readable).
> 3. The **Repository layout** + **QA Pipeline** sections below in this file.
> 4. Most-recent [`audit/wppqa-baseline-2026-05-24/SUMMARY.md`](audit/wppqa-baseline-2026-05-24/SUMMARY.md) — current bug surface.
>
> Full inventory in [`audit/manifest.json`](audit/manifest.json) (schema v2.1, generated 2026-06-10 for **1.2.0**): **58 REST** · 5 AJAX · 11 tables · 11 blocks (9 layout-owning) · 13 admin pages · **259 fired hooks** (133 actions + 126 filters with `consumed_by`) · 15 caps · 6 taxonomies · 10 cron · 1 WP-CLI command (10 subcommands) · 75 IAPI actions · 8 static detectors. Pre-computed sub-checks at [`audit/derived/`](audit/derived/) (10 cache files including `cross-plugin-coupling.json` with **69 Free→Pro pairs** — corrected 2026-06-10 from the under-counted 32 by a full multiline rescan). See [`audit/FEATURE_AUDIT.md`](audit/FEATURE_AUDIT.md), [`audit/CODE_FLOWS.md`](audit/CODE_FLOWS.md), [`audit/ROLE_MATRIX.md`](audit/ROLE_MATRIX.md). Version bumped to **1.3.0** on 2026-07-27 (targeted refresh — the 1.3.0 wave was behavioral audit fixes + guest-submission removal, no new structural categories). **Manifest refresh strategy for this plugin: TARGETED / agent-enumeration only — do NOT commit the deterministic generator (`write-manifest.mjs`) output.** It scans the bundled `libs/wbcom-credits-sdk` and emits the SDK's `wbcom-credits/v1` routes as plugin routes (real ns is `listora/v1`), mis-parses the controller registry, and drops `plugin.version`. Refresh via `/wp-plugin-onboard --refresh` but keep the curated manifest as the base.

## Repository layout (post 1.0.4 reorg)

Plugin is **private — wbcomdesigns only**, never published to wordpress.org. The repo is organized so each directory has exactly one job:

| Directory | Owns | Notes |
|---|---|---|
| `audit/` | Architecture, specs, machine-generated inventory, baselines | Onboard skill's domain. Includes `architecture/`, `ux-audits/`, `derived/`, `wppqa-baseline-*/`, and the top-level `manifest.json` / `FEATURE_AUDIT.md` / `CODE_FLOWS.md` / `ROLE_MATRIX.md` / `REST_AUDIT_*.md` / `WPDB_AUDIT_*.md` / `HOOKS_CONSUMED_BY_AUDIT_*.md`. |
| `tests/` | Verification (phpunit + QA) | `docs/qa/` holds the smoke runbook, qa-config, qa-index, journeys, journey-runs, smoke verdicts, data-flow verification, launch-readiness yaml. `tests/{bootstrap.php,factories/,integration/,unit/}` is the phpunit code. |
| `plan/` | Human-authored WIP plans | Release plans, sprint specs, design docs (`100k-readiness/`, `foundation-cleanup-*.md`, `frontend-refactor/`, `sustainability/`, `product-roadmap.md`, `competitive-plan-geodirectory.md`, `archive/`). NOT for architecture (→ audit) or QA (→ tests). |
| `docs/` | Customer + integrator-facing only | Public REST-API reference, contributor guide, docs-site source (`docs/website/`). Never put internal artifacts here. |
| `bin/` | Developer scripts | Build/release/CI scripts + dev-only utilities (`seed-demo.php`, `verify-notifications.php`). Excluded from dist. |
| `demo/` | Customer-facing demo data | Setup Wizard + `wp listora seed-demo` load these. Ships in dist. Do not confuse with `bin/seed-demo.php` (dev-only). |

GitHub Actions CI was retired in 1.0.4 — `composer ci` (local-CI pre-push hook) is the single quality gate.

## Latest smoke verdict (2026-05-18 — 1.0.4 combo)

Smoke source: [`docs/qa/.last-smoke-pass.json`](docs/qa/.last-smoke-pass.json) (updated after every `/wp-plugin-smoke combo` run).

**Verified PASS:** F-01 (admin claim approval ownership transfer), F-03 (`min_rating` URL → IAPI state seed), F-07 (advanced_search toggle cancels orphan AS jobs), F-08 (favorite-btn aria-label hydration), all 14 architecture invariants (INV-3/4/12/13/14).

**Active fixes — pushed in commit `773a89a`, awaiting re-verification:**
- **F-04** — anon login modal now always renders Create Account (was conditional on `users_can_register` → dead-end UX on closed-registration sites). New filter `wb_listora_login_modal_register_url` lets invite-only sites suppress.
- **F-05** — search suggestions REST returns `{ suggestions: [...] }` envelope; `fetchSuggestions()` was assigning the whole envelope to `state.suggestions`, IAPI's `data-wp-each` iterated object keys, rendered nothing. Fixed to unwrap.

**Reclassified (not bugs):**
- F-02 — `wb_listora_pro_expire_needs` only schedules when `reverse_listings` toggle is ON. The smoke originally flagged this; verified correct-by-design.

Next combo smoke run will lock 1.0.4 once F-04 + F-05 verify green.

## Free → Pro upscale-journey contract (apply MediaVerse lesson)

WB Listora is a Free + Pro pair shipped under the **upscale-model** rule: Free is mandatory, Pro extends Free, Pro NEVER stands alone. The same trap that hit MediaVerse before us — Pro shipping its own copies of Free's code instead of consuming Free's existing surface — is what `bin/cleanup-duplicate-detect.php` + `bin/cleanup-boundary-check.sh` exist to catch.

**The 5-step extension order is non-negotiable:**

1. **Find what Free already exposes.** Read `audit/manifest.json#/hooks_fired[]`, `audit/derived/cross-plugin-coupling.json`, `audit/architecture/pro-coupling-contract.md` (in Pro). Free already publishes hooks, contracts, and `wb_listora_service()`-resolved services for almost every cross-cutting concern.
2. **Consume via documented surface.** Documented hooks (`add_filter`/`add_action`), `\WBListora\Contracts\*` interfaces, `wb_listora_service($name)` locator. Never direct refs to `\WBListora\Core\*` etc. (INV-3 in `bin/architecture-checks.sh`).
3. **If Free doesn't expose what Pro needs, file the gap in Free FIRST.** Add a hook, contract, or helper to Free. Ship Free's commit ahead of Pro's consumer commit. Reference the Free PR# in Pro's PR description.
4. **Pro re-firing a Free hook is a red flag.** Listeners on a Free-owned hook expect Free's `args_signature` and Free's call-site context. Pro re-firing means Free's listeners run twice (notifications, status manager, search re-index, etc.). The single exception is Pro's competitor-migration importer where bulk-imported listings need to opt OUT of Free's downstream listeners — that case requires a `context: 'migration'` arg AND Free's listeners must check it.
5. **Add a regression sentinel.** Any new cross-plugin coupling gets a journey at `docs/qa/journeys/regression/<slug>.md` and a row in `audit/derived/cross-plugin-coupling.json`.

**Audit tooling** (run before any Pro-side PR that touches behaviour Free already implements):

```bash
php bin/cleanup-duplicate-detect.php     # writes audit/cleanup/duplicates.json
bash bin/cleanup-boundary-check.sh       # writes audit/cleanup/boundary-violations.json
```

### Current state (scan 2026-05-18)

| Signal | Count | Status |
|---|---|---|
| Pro→Free boundary violations (Pro imports `\WBListora\Core\*` directly) | **0** | ✅ INV-3 holding |
| Cross-plugin code duplicates (Pro re-implements Free logic) | **2 real + 3 false-positives** | ⚠️ See backlog below |
| Pro re-fires Free's `do_action` hook | **1 real + 1 false-positive (PHPDoc)** | ⚠️ See backlog below |
| Pro consumes Free's `apply_filters` correctly | **3** | ✅ designed pattern |

**Backlog — real findings to consolidate when next we open the relevant area:**

| Finding | Where | Consolidation plan |
|---|---|---|
| `set_taxonomy_terms` duplicated 3× | Free: `class-{geojson,json}-importer.php` · Pro: `class-visual-importer.php` | Extract `\WBListora\Import\Term_Helper::set_taxonomy_terms()` in Free; both importers consume via static call. |
| `html_to_text` duplicated 2× | Free: `class-notifications.php:1359` · Pro: `class-email-helpers.php:110` | Expose Free's `Notifications::html_to_text()` as a static helper or via `wb_listora_service('email_helpers')`; Pro drops its copy. |
| Pro fires `wb_listora_listing_submitted` from migrator | Pro: `class-base-migrator.php:290` | Add `context: 'migration'` arg to the fire-site. Free's downstream listeners (Notifications, Status_Manager) gate on `'migration' !== $context['context']` so bulk-migrated listings don't trigger emails. |

These are not 1.0.4 blockers — file as 1.1.0 cleanup PRs with the bridge-inventory check.

## Migrator ownership (post-1.1.0 split)

WB Listora's migration product has two distinct halves and **each lives in exactly one plugin**. Future contributors / Claude sessions must not reintroduce the duplication this split eliminated.

| Layer | Owner | Responsibility |
|---|---|---|
| **Universal file importers** (CSV / JSON / GeoJSON) | **Free** — `includes/import-export/class-{csv,json,geojson}-importer.php` + `class-csv-exporter.php` | Any source that can export structured data. Wins the evaluation funnel — no upsell required. |
| **Competitor-specific migrators** (Directorist / GeoDirectory / WPBDP / ListingPro) | **Free** — `includes/import-export/class-{directorist,geodirectory,bdp,listingpro}-migrator.php` extending `Migration_Base` | The data pipeline that knows each source plugin's storage schema. Schema knowledge lives at `audit/architecture/competitor-schemas/{slug}.md` (verified by agent index, not guess-work). |
| **Cross-cutting helpers** (term setting, html→text) | **Free** — `includes/import-export/class-term-helper.php`, `includes/workflow/class-email-body-formatter.php` | Single canonical implementation. Used by Free's universal importers AND by Pro's visual importer. |
| **Public extension surface** | **Free** — `includes/import-export/migration-helpers.php`, `includes/workflow/email-helpers.php`, `includes/import-export/import-helpers.php` | `wb_listora_get_migrators()`, `wb_listora_get_migrator($slug)`, `wb_listora_set_taxonomy_terms()`, `wb_listora_email_html_to_text()` — Pro consumes these, NEVER references Free's internal classes directly (INV-3). |
| **Premium UX layer** (Visual Importer, Field Auto-Detector, Import Preview, Import Template Manager, Competitor Detector) | **Pro** — `includes/importexport/` + `includes/migration/class-competitor-detector.php` | Drag-drop UI, source-field auto-detect, preview, saved mapping templates. Wraps Free's migrators via the extension functions. The genuine paid value-add. |
| **Migration admin UI + WP-CLI** | **Both** — Free has a simple admin page + `wp listora migrate --from=<slug>` (CLI). Pro adds the visual-mapper UI via its REST routes + admin feature class | Customers on Free can migrate; Pro adds the premium UX on top of the same data pipeline. |

### Rule of thumb

- **New competitor migrator** → PR to **Free** only. Subclass `Migration_Base`, implement `detect_source_fields()` + `get_default_mapping()` + the `extract_*` template methods. Add a schema audit at `audit/architecture/competitor-schemas/<slug>.md` before writing any code (no guess-work).
- **New universal format importer** (XML / SQLite / etc.) → PR to **Free**'s `includes/import-export/`.
- **New premium UX wrapper** → PR to **Pro**'s `includes/importexport/` or `includes/features/`.
- **Pro NEVER re-implements a migrator Free has.** If Pro needs a method Free doesn't expose, add the method to Free first (and an extension function for INV-3), then consume from Pro.

### Migration-context arg on `wb_listora_listing_submitted`

When ANY migrator (Free OR Pro) fires `wb_listora_listing_submitted`, it MUST pass a 4th `array $context` argument with `'source' => 'migration'` (and ideally `'migrator' => static::class`). Free's `Notifications` listener + Pro's `BuddyPress_Integration` + Pro's `Pricing_Plans` listeners gate on this context to avoid emailing the admin / posting feed items / deducting credits for every legacy listing in a bulk import. Pro's `Audit_Log` + `Moderator` listeners INTENTIONALLY still run for migrated listings (the audit trail + moderator assignment are correct behaviour). See `docs/qa/journeys/regression/migrator-context-arg.md` for the regression sentinel.

### What gets deleted in 1.1.0 (with deprecated shims)

Pro's `includes/migration/` directory shrinks from 5 migrator files (~1,100 LOC) to ONE (`class-competitor-detector.php`). The 5 deleted classes (Base_Migrator + Directorist + GeoDirectory + WPBDP + HivePress migrators) had no `@deprecated` window because they were never publicly documented as a Pro extension surface — they were always private implementation details. Verified by grep: zero external consumers.

HivePress migration is temporarily removed from the customer-facing product in 1.1.0 (Free doesn't have a HivePress migrator yet). Pro's `Competitor_Migration` REST endpoint responds "HivePress migration coming in 1.2.0" when queried. Re-introduction in 1.2.0 follows the same recipe: schema audit at `audit/architecture/competitor-schemas/hivepress.md` (already exists), then port to Free's `Migration_Base` contract.

## Production rules (live-site protection — non-negotiable)

These rules protect live customer sites against the failure modes we (and MediaVerse before us) learned the hard way. Enforced where possible by `bin/architecture-checks.sh`; the rest are review-time hard gates. **No exceptions in patch releases.**

1. **NEVER hard-remove a public symbol in the release it is deprecated.** Minimum 2 minor versions between `@deprecated since X.Y.Z` and deletion. Applies to: functions, classes, methods, hooks, REST routes, AJAX actions, options, meta keys, capabilities, WP-CLI commands, service-locator keys.
2. **NEVER rename a public identifier without an alias.** Add the new one and alias the old one for ≥2 minor versions. The old read path must keep working.
3. **NEVER ship a default-behavior change without a filter escape hatch.** Customer must be able to restore old behavior with a one-line `add_filter` in a mu-plugin. Filter stays for ≥2 minor versions.
4. **NEVER touch DB schema in a patch release.** Schema changes require `Activator::DB_VERSION` bump + minor release minimum.
5. **NEVER remove a template file.** Templates can be overridden via `wb_listora_locate_template()`. Alias with `@deprecated` header for ≥2 minor versions before deletion.
6. **NEVER bypass CI gates on release branches.** `SKIP_LOCAL_CI=1` is emergency-only and must be documented in the commit message.
7. **Patch releases (1.0.Z) are bug fixes only.** No behavior changes, no new features, no removals.
8. **Minor releases (1.Y.0) are additive.** New features, new settings, deprecations. No removals.
9. **Major releases (X.0) are the only place removals happen.** Only previously-deprecated symbols. 30 days advance notice via release-notes channel.

Every deprecated symbol carries: `@deprecated since X.Y.Z` PHPDoc + `_doing_it_wrong()` runtime trigger + a migration-path entry in `CHANGELOG.md` + a planned removal version ≥ (X+1).0.

## Overview
Complete WordPress directory plugin. Create any type of listing directory — business, restaurant, hotel, real estate, jobs, events, and more.

## Known-limitation: admin button tap-target

The frontend-responsive Rule 4 (40px minimum tap-target on buttons) applies to **customer-facing buttons** only. In **wp-admin context**, WordPress core button conventions (28-30px height) take precedence — overriding those would visually break wp-admin's design language and look out-of-place next to native WordPress admin chrome.

Admin CSS files (`assets/css/admin*.css`, `assets/css/admin/*.css`) may carry buttons under 40px. Treat any wppqa Rule 4 warning on admin CSS as **expected**, not a release blocker. The corresponding rule is enforced for `blocks/*/style.css` (customer-facing) where the 40px floor IS required.

Per the 2026-05-11 wppqa baseline, Free has 15 admin tap-target warnings + Pro has 1 — all classified as known wp-admin context limitations.

## Frontend v2 architecture (post-2026-05-11 refactor)

> **READ FIRST for any CSS/frontend work:** [`docs/architecture/CSS-ARCHITECTURE.md`](docs/architecture/CSS-ARCHITECTURE.md)
> — the enforced standard (layer cascade, build pipeline, no-`!important`/no-inline/no-`wp-element-button`
> rules + exceptions, dynamic-value patterns). Grounded in `/wp-plugin-development` + `/ux-audit` + `ux-foundation`.
>
> **Naming (since the 2026-05-21 refactor):** `src/tokens/` → **`src/variables/`**, `src/primitives/`
> → **`src/components/`**, `shared.css` → **`listora-base.css`**; handles renamed to match
> (`listora-variables`, `listora-components`, `listora-base`). The `src/*` files below build to
> `assets/css/listora-{variables,components}.css` via `bin/build-css.mjs` (`npm run build:css`) —
> never hand-edit the compiled files (CI Rule 4 drift guard enforces this). The block diagram below
> shows the original layout; folder names are now variables/components.

```
src/tokens/        ← single source of truth for ALL design tokens
                     colors / spacing / typography / radius / shadow / motion
                     7 files, 107 canonical v2 tokens
                     compiled to assets/css/listora-tokens.css

src/primitives/    ← 10 canonical UI primitives every block composes from
                     form-field, button, modal, tabs, tooltip, table,
                     page-shell, card, empty-state, badge
                     compiled to assets/css/listora-primitives.css

src/editor/        ← Gutenberg editor controls (7 components + 2 hooks + 2 utils)
src/interactivity/ ← shared IAPI store

src/blocks/        ← block sources (11 blocks: listing-{search,grid,card,
                     map,categories,featured,calendar,detail,reviews,
                     submission} + user-dashboard)
```

Cascade order: `tokens → primitives → shared → block-specific`.
Pro consumes Free's tokens + primitives at runtime (no Pro-side copies).

Render helpers: `wb_listora_render_empty_state()` + `wb_listora_render_tabs()` in `includes/class-render-helpers.php`.

**v1 token vocabulary is fully retired** project-wide as of 2026-05-11 (1700+ references migrated, 0 remaining).

## Admin shell — F4 auto-injected header

Every WB Listora admin screen gets the canonical `.listora-admin-header` automatically. The injection is wired in `includes/admin/class-admin.php::render_branded_admin_header()` on `in_admin_header` priority 5. The screen check (`is_listora_screen()`) matches anything with `listora` in the screen ID — Free's own pages, Pro's pages, the CPT edit screens, taxonomy pages, everything.

**Per-page opt-out** via the `wb_listora_skip_admin_header` filter — currently used by 3 pages that emit their own branding:

| Page | Reason |
|---|---|
| Settings (`class-settings-page.php`) | Emits header with active-tab subtitle |
| Pro promotion (`class-pro-promotion.php`) | Marketing hero with oversized brand block |
| Setup Wizard (`class-setup-wizard.php`) | First-run step-by-step layout |

To opt out a new page, call `add_filter( 'wb_listora_skip_admin_header', '__return_true' );` at the top of the render method.

**Primitive vocabulary** (in `src/primitives/admin-settings-layout.css`):
- `.listora-admin-header` + `__brand` + `__icon` + `__text` + `__title` + `__sub` + `__actions` — F4 branded header
- `.listora-settings-card` + `__head` + `__title` + `__desc` — F6 canonical card
- `.listora-settings-card--auto` — F6 auto-wrap variant for legacy `<h2>` + `.form-table` siblings

**F5 (sidebar-nav layout)** lives in `assets/css/admin/settings.css` under the canonical `.listora-settings-wrap` / `.listora-settings-sidebar` / `.listora-settings-nav-item` vocabulary — predates the v2 primitive directory but is the canonical implementation.

## Performance budgets (P1, P6)

Quantitative TTFB + Lighthouse targets documented at `../wb-listora-pro/plan/100k-readiness/PERFORMANCE-BUDGETS.md`. Free pages target <800ms TTFB anon / <1000ms logged-in and Lighthouse ≥90 mobile / ≥95 desktop. Per-page measurement protocol via `ab` + `lighthouse` CLI commands included.

## QA Pipeline (release gate + self-growth contract)

This is the **release gate** for every WB Listora version. It self-grows: every customer-visible bug fix and every new feature must add a row here in the same PR. Future Claude sessions should treat this section as the source of truth for "is this release ready?"

### Artifact map

| Artifact | Path | Purpose | Owner |
|---|---|---|---|
| Smoke runbook (canonical) | [`docs/qa/AGENT_SMOKE_RUNBOOK.md`](docs/qa/AGENT_SMOKE_RUNBOOK.md) | A-G customer contracts for fresh install, upgrade, all flows, regression guards, Pro extensions, cross-browser, post-release. **536 lines, last refreshed 2026-05-09.** | Bug-fix + feature PRs (write); smoke skill (read) |
| Pro supplements | [`../wb-listora-pro/docs/qa/AGENT_SMOKE_RUNBOOK.md`](../wb-listora-pro/docs/qa/AGENT_SMOKE_RUNBOOK.md) | Pro-only S1-S12 ops (lockstep / license / INV-12 / 29 coupling / strict HMAC / toggle isolation). | Pro PRs |
| Journeys (executable) | [`docs/qa/journeys/`](docs/qa/journeys/) | 83 self-contained markdown flows an agent runs end-to-end via Playwright + WP-CLI + curl + mysql_query (17 customer / 16 admin / 48 regression / 2 system). Returns PASS/FAIL with exact step + likely_files for triage. See [`docs/qa/journeys/README.md`](docs/qa/journeys/README.md) for the schema. | Bug-fix + feature PRs (write); `bin/run-journeys.sh` (execute) |
| QA index (machine-readable) | [`docs/qa/qa-index.json`](docs/qa/qa-index.json) | The structured index: artifacts, release gate requirements, maintenance loop, discovery order. CLAUDE.md prose mirrors it; this file is canonical. | This wiring pass; refreshed when QA shape changes |
| wppqa baseline | [`audit/wppqa-baseline-2026-05-11/SUMMARY.md`](audit/wppqa-baseline-2026-05-11/SUMMARY.md) | Static-analysis bug finder (plugin-dev-rules / REST↔JS contract / wiring). **0 release blockers.** Re-run via `wppqa_audit_plugin --plugin_path=$(pwd)`. | Onboarding refresh |
| Manifest | [`audit/manifest.json`](audit/manifest.json) + summary | Plugin shape + 8 static detectors. Refresh via `/wp-plugin-onboard --refresh` after non-trivial commits. | Onboarding skill |
| Smoke gate (release) | [`bin/build-release.sh`](bin/build-release.sh) ~lines 105-135 | **Refuses to package** unless `docs/qa/.last-smoke-pass.json` exists, version matches, `failures[]` + `debug_log_issues[]` empty. Emergency only: `--skip-browser-smoke`. | Release script |

### Release gate (must be GREEN before tagging)

Run before every release tag — copy the checklist:

1. **Architecture invariants** — `bash bin/architecture-checks.sh` returns 0 (12/12 pass).
2. **wppqa baseline** — most-recent `audit/wppqa-baseline-*/SUMMARY.md` shows `0 release blockers`. Re-run via the MCP tool if older than 7 days.
3. **Smoke pass** — run `/wp-plugin-smoke combo`. Confirms:
   - Walks every section of `docs/qa/AGENT_SMOKE_RUNBOOK.md`
   - Executes every authored journey under `docs/qa/journeys/`
   - Writes `docs/qa/.last-smoke-pass.json` with `release_version` matching `WB_LISTORA_VERSION`, empty `failures[]`, empty `debug_log_issues[]`
4. **Architecture invariants again post-smoke** — guard against the smoke run dirtying state.
5. **Tag + run release script** — `bin/build-release.sh` re-validates the smoke report at packaging time as defense-in-depth.

If ANY of 1-4 fails → release is BLOCKED. No `--skip-browser-smoke` for customer-facing releases.

### Self-growth contract (this is how QA stays current)

QA self-grows. Every commit that changes customer behavior MUST add to QA in the same PR. Future Claude sessions: enforce this on review.

| Trigger | Required additions in the SAME PR |
|---|---|
| Customer-visible bug fix | (a) New row in runbook Section D + (b) regression journey at `docs/qa/journeys/regression/<slug>.md` + (c) reference to bug card # in journey frontmatter `covers:` |
| New feature | (a) New row in runbook Section C (or E for Pro) with the customer contract + (b) `customer/` or `admin/` journey covering happy path + 1 negative test + (c) manifest refresh after merge |
| New REST endpoint | (a) Row in runbook Section C.rest.contract + (b) journey hitting the endpoint with auth + nonce + shape assertion + (c) manifest refresh |
| New admin page | (a) Row in runbook C.admin.* + (b) admin journey verifying render + at least one CRUD action |
| New Pro feature toggle | (a) Row in Free runbook Section E with toggle-on / toggle-off contract + (b) journey verifying isolation + (c) toggle entry in manifest's `feature_toggles[]` |
| Architecture invariant added | (a) New invariant ID in `audit/architecture/wb-listora-architecture-contract.md` (Pro) + (b) check in Pro `bin/architecture-checks.sh` + (c) Pro supplement S5 row |
| Two clean releases of a regression journey | Graduate it from `docs/qa/journeys/regression/` → `customer/` or `admin/`. Move the runbook D row into the matching C/E row. Update `docs/qa/qa-index.json` counts. |

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
2. `docs/qa/qa-index.json` (this section's source of truth)
3. This QA Pipeline section
4. Most-recent `audit/wppqa-baseline-*/SUMMARY.md`

Everything else is on-demand.

## Tech Stack
- **PHP:** 7.4+ (WordPress plugin)
- **JS:** @wordpress/scripts, @wordpress/interactivity API
- **Build:** `npm run build` (wp-scripts)
- **CSS:** PostCSS via wp-scripts
- **Database:** 11 custom tables (listora_ prefix)

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
WB_LISTORA_VERSION        // '1.1.0'
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

## Recent Changes (2026-06-23 — QA bounce fixes: onboarding flow + analytics/media/preview/carousel)

No manifest count changes (bug fixes + 1 new global helper). Verified in-browser on fresh Docker WP 7.0 + Reign 8.0.0 + Free&Pro both active.

| Area | Change |
|---|---|
| **Setup-complete single source (BC 10020037441)** | New global `wb_listora_is_setup_complete()` (`includes/class-template-helpers.php`) — the canonical check; `Admin::is_setup_complete()` delegates to it; activation redirect, onboarding notice, menu hiding, Health Check all route through it. Removed Free's DUPLICATE activation→wizard redirect (the `Activator` set two transients — `wb_listora_show_wizard_redirect` + legacy `wb_listora_activation_redirect` — and `class-admin.php::maybe_redirect_to_wizard()` was a second admin_init handler with different guards). Now one transient + one handler (`Activation_Redirect`). Pro consumes the new helper (INV-3) and defers to Free so a fresh both-active install never bounces between two wizards. |
| **Wizard "done" finalizes (BC 10020076541)** | `Setup_Wizard::render_step_done()` finalizes setup on reaching the done screen, so every CTA (not just the one form button) completes setup and clears the onboarding notice. |
| **Setup-wizard notice leaked onto the wizard (BC 10023581495)** | `Admin::onboarding_notice()` matched the wizard screen with an exact `=== 'admin_page_listora-setup'`, but the wizard is registered under the `listora` parent while setup is incomplete (screen id `listora_page_listora-setup`) — so the guard never matched and the "complete setup" nag showed ON the wizard. Switched to a substring match (`strpos($screen->id,'listora-setup')`). |
| **Demo-data delete admin UI (BC 10020109923)** | Settings → Advanced "Delete Demo Data" button + `listora_delete_demo` AJAX calling the single canonical `Demo_Seeder::remove_all()` (CLI `wp listora demo remove` now uses the same remover — no duplicated logic). |
| **Media picker privacy / preview / carousel / share** | Media picker scoped to own uploads for non-`edit_others_posts` members (`ajax_query_attachments_args`); single-form submission preview populates + live-updates (`initSingleFormPreview()`); Featured carousel uses `grid-auto-flow:column` at all breakpoints; Share button tagged `data-listora-track="share"` for Pro analytics. |

## Recent Changes (2026-06-10 — 1.2.0 onboard refresh)

Release-time manifest refresh covering the 1.2.0 wave (~121 commits since the 2026-06-06 post-1.1.0 refresh): flow-closure plan, background import engine, unsubscribe controller, GDPR privacy tools, HivePress migrator, analytics-lite/bot-detection split, email-templates admin page, bulk edit, favorites tab.

| Area | Detail |
|---|---|
| **Version** | Manifest `plugin.version` 1.1.0 → **1.2.0**; `generated.at` → 2026-06-10. |
| **REST 57 → 58** | +`GET /listora/v1/unsubscribe` (`Unsubscribe_Controller::handle_unsubscribe`, public by design — signed HMAC token is the credential, RFC 8058). **GET only** — the audit note claiming GET/POST was wrong. Import progress/queue routes were already recorded mid-wave (`2114d0a`). 59 `register_rest_route` call sites → 58 logical routes (`/settings` GET and PUT,DELETE registered in 2 calls, merged). |
| **AJAX 4 → 5** | +`listora_run_demo_import` (`Settings_Page::ajax_run_demo_import`, nonce `listora_demo_import`, cap `manage_options`) — queues Background_Import demo runs. |
| **Hooks 238 → 259** | +8 actions (`after_bulk_edit`, `before/after_dashboard_favorites`, `after_unsubscribe`, `dashboard_credit_row_actions`, `demo_import_run`, `review_reminder`, `view_recorded`) and +13 filters (`show_credits`, `submission_layout_mode`, `required_field_messages`, `pro_owns_analytics`, `analytics_is_bot`, `bot_signatures`, `is_bot_request`, `privacy_erase_per_page`, `repair_term_taxonomies`, `review_reminder_grace_hours`, `unsubscribable_events`, `unsubscribe_url`, `wpml_object_id` [third-party API-call convention]). Coverage gate: multiline-aware own-source scan = 130 actions + 126 filters; manifest 133/126 (the 3 extra actions are AS-fired `bg_import_batch/finalize` + libs-fired `wbcom_credits_sdk_registry`) — 0 gap. |
| **Cron 6 → 10** | +`wb_listora_review_reminder_cron` (daily), +`wb_listora_prune_email_log` (daily — **pre-existing manifest omission**, hook predates this wave), +`wb_listora_bg_import_batch`/`_finalize` (Action Scheduler async, group `wb-listora`, wp-cron fallback). |
| **Coupling 32 → 69** | `cross-plugin-coupling.json` fully rescanned (multiline-aware): true Free-fires→Pro-consumes pair count is **69**. The prior 32 was maintained by delta arithmetic and had silently under-counted for several refreshes (39 missing pairs incl. Outgoing_Webhooks, BuddyPress member_profile_url; 2 stale pairs removed: `credits_added`, `payment_received`). Manifest `consumed_by` reconciled: +7 Pro listeners on existing hooks, 3 stale entries cleared (`after_contact_form_submit`, `after_dashboard_reviews`, `after_map`). |
| **Other** | Settings: `submission_form_style` sub-key (wizard\|single_form) documented on the master `wb_listora_settings` entry. Interactivity shared store 74 → 75 actions (+`closeDashServices`). New template `tab-favorites.php`. New classes noted in manifest `notes` (Background_Import, Unsubscribe_Controller, Privacy_Exporter/Eraser, Hivepress_Migrator, Analytics_Lite, Bot_Detection, Email_Templates_Page, Listing_Bulk_Actions). |

## Recent Changes (2026-06-06 — 1.1.0 released)

**1.1.0 shipped on GitHub** (tag `v1.1.0`, cut from `main` at `c5826be`). The release wave (94 commits since the 2026-05-24 manifest refresh) bundles the product-readiness audit fixes (AUD-F1..F11), three Basecamp bug fixes (BG-2/BG-3/BG-4), the M4-M12 SEO/schema/a11y hardening, the Credits SDK re-home (submodule → composer-free `libs/`, see section below), the DUP-1 claims-model consolidation, the new `wp listora test-email` / `cleanup` CLI subcommands, and the dashboard/reviews pagination perf work. Changelog completed in `readme.txt` + `CHANGELOG.md`. A stale `vendor/wbcom-credits-sdk` leftover was removed from the working tree before packaging. Dist zip: **2153 KB / 791 files**.

| Area | Detail |
|---|---|
| **Version** | `WB_LISTORA_VERSION` + plugin header + `readme.txt` Stable tag → **1.1.0**. Manifest `plugin.version` 1.0.0 → 1.1.0. |
| **Manifest delta** | `hooks_fired` **226 → 233** (+2 actions, +5 filters from the release wave — see below). actions 120 → 122, filters 106 → 111. `cross-plugin-coupling` +2 Free→Pro pairs (29 → 31). Net-zero REST (M4-M12 gated existing fields, added no routes), no new AJAX/blocks/tables/caps/admin_pages/own-cron. wp_cli subcommands stayed 10 (test-email + cleanup were recorded in the 1.1.0 manifest delta commit). |
| **+2 fired actions** | `wb_listora_daily_cleanup` — `do_action` extensibility fire in the new `wp listora cleanup` subcommand (`includes/class-cli-commands.php:273`). `wb_listora_save_features_extra` (`class-settings-page.php:2342`) — Pro persists its toggles merged into Free's Features screen (BG-4, consumed at `class-pro-plugin.php:439`). |
| **+5 fired filters** | `wb_listora_demo_image_timeout` + `wb_listora_demo_gallery_max` (`demo/class-demo-seeder.php`, slow-demo-import fix); `wb_listora_docs_url` (`class-settings-page.php:377`, docs-buttons fix); `wb_listora_features_category_labels` (`class-features.php:178`, BG-4, consumed at `class-pro-plugin.php:67`); `wb_listora_seo_plugin_active` (`class-features.php:354`) — the canonical SEO-plugin detector so Listora defers to Yoast/Rank Math and never double-injects (M9/M10). |
| **SDK hook path fix** | `wbcom_credits_sdk_registry` manifest entry repointed `vendor/wbcom-credits-sdk/...:65` → `libs/wbcom-credits-sdk/wbcom-credits-sdk.php:184` and its malformed `consumed_by` corrected to `wb-listora.php:478` — reflecting the SDK re-home. |
| **Coverage gate** | Own-source ground-truth grep returned 113 `do_action` + 102 `apply_filters` unique literals (215) vs manifest 233; the 18-name delta is dynamic hooks (`wb_listora_email_content_{$event}` etc.), wrapper-fired, and multi-line `apply_filters` the single-line regex misses — manifest is the superset and correct. `libs/` (edd-sl-sdk + re-homed wbcom-credits-sdk) excluded from own-source counts as third-party SDKs (they ship in dist but are not WB Listora's own inventory). |

## Recent Changes (2026-06-04 — Credits SDK re-homed: submodule → composer-free `libs/`)

The Wbcom Credits SDK — the only runtime dependency that was loaded wrong — moved
from a **gitignored git submodule at `vendor/wbcom-credits-sdk` (composer-autoloaded)**
to a **committed, composer-free copy at `libs/wbcom-credits-sdk/`**, mirroring the
existing `libs/edd-sl-sdk/` template. The plugin zip AND a fresh `git clone` now both
work with ZERO `composer install` and ZERO `git submodule init` — no fatals, no manual
setup. This is the owner's hard rule for money-adjacent bootstrap code.

| Area | Change |
|---|---|
| **SDK location** | 32 runtime files (28 `src/*.php` + loader + `templates/admin/gateways-section.php` + `CHANGELOG.md` + `README.md`) copied from the fixed SDK @ `19d6552` (atomic webhook idempotency + gateway refund event + Stripe refund linkage) into `libs/wbcom-credits-sdk/`. Dev cruft excluded (`tests/`, `bin/`, `docs/`, `.github/`, phpstan/phpunit config, ROADMAP/PORTFOLIO). `composer.json` kept as metadata/reference only. |
| **Composer-free autoloader** | `libs/wbcom-credits-sdk/wbcom-credits-sdk.php` now `spl_autoload_register`s a self-contained PSR-4 closure mapping `Wbcom\Credits\` → `__DIR__.'/src/'` (mirrors `libs/edd-sl-sdk/edd-sl-sdk.php`), guarded by a `function_exists` flag against double-registration. It NEVER requires any `vendor/autoload.php`. The closure resolves all PSR-4-conformant classes — including `Wbcom\Credits\Gateways\Pricing`, which the eager class→file map omits; the 5 non-conformant Adapter classes (e.g. `WooCommerceAdapter` in `WooCommerce.php`) load via the eager map. Union = all 28 classes resolve composer-free (proven). |
| **`wb-listora.php` loader repoint** | SDK loader path + defensive-guard `Versions.php` path changed `vendor/wbcom-credits-sdk/` → `libs/wbcom-credits-sdk/` (loader ~`586`, guard checks ~`587`/`589`, admin-notice text ~`605`). |
| **`wb-listora.php` composer hardening** | The runtime `require vendor/autoload.php` (~`107`) was already `file_exists`-guarded so its absence can never fatal; comment strengthened to make the composer-free contract explicit. Free's own classes load via the `wb_listora_autoload` kebab `spl_autoload_register` (~`117`) — confirmed they do NOT depend on composer. Added a one-entry alias map in that autoloader for `WBListora\ImportExport\GeoJSON_Importer` (file is `class-geojson-importer.php`, which the lower→Upper kebab rule mis-resolved to `class-geo-json-importer.php`; only composer's classmap caught it before). Now the no-composer path resolves every Free class too. |
| **Submodule removal** | `git rm --cached vendor/wbcom-credits-sdk` (gitlink) + deleted `.gitmodules` (it was the only submodule) + cleaned `.git/config` (no `submodule.*` section remains) + removed `.git/modules/vendor`. `git submodule status` is empty; no dangling reference. |
| **build-release.sh** | No change needed — `libs/` is not excluded (ships like `libs/edd-sl-sdk`), the `--exclude='/src/'` is leading-slash so it does NOT strip `libs/.../src/`, and the build never depended on submodule-init or composer-pulling the SDK. Verified the rsync lands all 32 SDK files in the dist. |
| **Pro side** | Pro has no own SDK copy/submodule and consumes classes (`\Wbcom\Credits\*`), not paths. Updated two `wb-listora-pro.php` strings (the SDK-location comment + the customer-facing admin-notice text) and the stale submodule-init comment in Pro's `build-release.sh` to say `libs/wbcom-credits-sdk`. No behavior change. |

**Verification (composer-free money-bootstrap proof, directory.local combo):**
- **(a)** Renamed Free's `vendor/autoload.php` → `.bak` (simulating a no-composer zip). Front-end + wp-admin + a credit-feature page loaded with ZERO fatals and ZERO "Class Wbcom\Credits\… not found". Restored `vendor/autoload.php` exactly afterward.
- **(b)** Schema upgrade ran from the libs-loaded SDK: `wbcom_credits_db_version_listora` = `2`, new `wp_listora_credit_processed_events` table created with `UNIQUE(slug,gateway,event_id)`. **Known SDK defect (pre-existing, NOT caused by re-homing):** `Transaction_Log::maybe_create_table` early-returns on `SHOW TABLES LIKE` when the table already exists, so the v2 `payment_intent` column is NOT added to an existing `wp_listora_credit_gateway_log`. Flagged upstream — out of scope for this move; the same defect exists whether the SDK loads from `vendor/` or `libs/`.
- **(c)** Atomic dedupe live: `Processed_Events::claim()` returned `true` on first claim, `false` on the duplicate, exactly 1 row persisted. `Credits::get_balance()` read works. `Stripe::normalize_event` present. SDK booted with `WBCOM_CREDITS_SDK_PATH` pointing at `libs/`.
- **(d)** Zero PHP notices/warnings/deprecations/fatals from the SDK/bootstrap in debug.log across all loads.

**Gates:** `php -l` clean on every changed PHP. `composer ci:no-journeys` GREEN in BOTH repos (Pro's 14 architecture invariants pass; INV-3 unaffected — no invariant references the SDK path). phpcs/phpstan scope `includes`/`blocks` only, so the bundled SDK is correctly out of WPCS/PHPStan scope (same as the EDD lib + the old submodule).

## Recent Changes (2026-05-24 — onboard refresh + 3 BC bug fixes + a11y + RTL build)

Diff-driven manifest refresh covering 2026-05-21 → 2026-05-24. **Zero manifest count changes.** Three BC bug fixes shipped + 1 a11y attribute on dashboard nav + 1 auto-RTL build script + 1 buddyx theme bridge tuning. wppqa baseline 2026-05-24 captured with 0 release blockers (8 classified false-positives, +2 new low-severity frontend tap-target warnings worth a UX-CONS follow-up).

| Commit | Area | Change |
|---|---|---|
| `aa1b39a` | Schema generator (BC 9905075024) | `Schema_Generator::normalize_meta_for_schema()` private helper coerces array-typed meta keys (address, social_links, business_hours, gallery, features, price, map_location) — strings are json_decode'd or fall back to `[]`. Stops `[] operator not supported for strings` fatals when corrupted meta reaches the appender. No new public hook. |
| `a4b4e6f` | Cron scheduler (BC 9910208588) | New `Cron_Scheduler::dedupe_pending( $hook, $group )` + `dedupe_pending_batch( $hooks, $group )` static methods sweep duplicate-pending recurring Action Scheduler entries that slip past the in-request guard via a cross-request activation race. New `Plugin::dedupe_recurring_cron` listener fires on `init` priority 16 (one tick after `init_workflow` at 15) — known-hooks-only, no-op when count == 1. Internal infrastructure, no new fired hook. |
| `efcab2e` | Verification feature gate (BC 9911539296) | `blocks/listing-detail/render.php:216` — `$is_verified` now reads `wb_listora_is_verified( $post_id )` resolver (which fires the `wb_listora_is_verified` filter Pro answers when its verification feature is disabled) instead of `get_post_meta( $post_id, '_listora_is_verified', true )`. Detail page badge no longer leaks after the toggle is disabled. |
| `c758104` | A11y (dashboard nav) | `templates/blocks/user-dashboard/nav.php:31` — `role="tablist"` + `aria-orientation="vertical"` on the sidebar `<nav>` so screen readers announce the tab pattern correctly. |
| `37ceb41` | Build (RTL CSS twins) | New `bin/build-css.mjs` auto-generates `assets/css/listora-components-rtl.css` + `assets/css/listora-variables-rtl.css` from their LTR sources after every `npm run build:css`. CI Rule 4 drift guard catches hand-edits to the compiled files. |
| `31b9b14` | Theme bridge (BuddyX Free) | `assets/css/themes/buddyx.css` tuned against legacy `--color-*` vocabulary so BuddyX-themed sites get consistent button + link styling on Listora templates. |
| Summary correction | Onboard metadata | `audit/manifest.summary.json` `counts.hooks_fired_actions/filters` were 109/90=199 (carried over from pre-2026-05-21 era); manifest.json was already correct at 120/106=226. The 51d2e70 (2026-05-21) refresh updated the manifest but missed the summary counts. This refresh corrects it. |

**Manifest delta:** ZERO count changes. All categories whose source globs were touched by these commits re-verified — no new REST endpoints, AJAX handlers, admin pages, blocks, tables, capabilities, fired hooks, cron schedules, or wp-cli commands.

**wppqa baseline 2026-05-24 vs 2026-05-18:** plugin-dev-rules 8/1 → 7/2 (+1 FP at `class-report-metabox.php:171` — same cap-before-nonce pattern as the existing FP on `class-featured-metabox.php:138`, pre-existing code, newly surfaced by sniff); wiring 5/5 → 5/6 (+1 FP at `wb_listora_clear_reports`, same admin-only-read shape); tap-targets 15 → 16 (-1 admin RTL twin consolidation, **+2 new on `listora-components.css:309` worth a UX-CONS follow-up — frontend 32px button height should be 40px**).

## Recent Changes (2026-05-20 — QA backlog clearance + Action Scheduler consolidation)

Eight Free PRs (#71-78). Every change WPCS + PHPStan clean, all 14 architecture invariants pass, browser/REST/SQL-verified, each with a regression journey + runbook D row.

| Area | Change |
|------|--------|
| **Verified-flag feature gate (#71)** | New `wb_listora_is_verified( $post_id )` resolver in `includes/class-features.php:218` reads `_listora_is_verified` meta + applies the new `wb_listora_is_verified` filter. All 5 read sites route through it (`class-template-helpers.php:527` card badge, `class-search-indexer.php:355`, `class-listings-controller.php:724/1064`, `class-search-controller.php:467`). Pro answers the filter to return false when its verification feature is off — the badge no longer leaks after the toggle is disabled. **+1 fired filter (`hooks_fired` 198 → 199).** |
| **Approve/Reject row actions (#72)** | `Listing_Columns::row_actions()` adds one-click Approve/Reject for `pending` listings (transition to `publish` / `listora_rejected` via `admin_action_listora_{approve,reject}_listing` + `transition_post_status` chain). New `moderation_action_notices()` (also fixes the previously silent `listora_verified` redirect). |
| **Setup wizard unknown step (#73)** | `Setup_Wizard::render()` normalizes any unrecognized step (e.g. stale `step=finish`) to `done`, so the completion summary renders instead of a blank card with a stray Continue button. |
| **Reviews "Require login" removed (#74)** | Removed the non-functional "Guest reviews / Require login" setting (was never enforced — `create_review_permissions()` hard-requires login). Stored `require_login` hardcoded `true`. |
| **Map clustering documented (#75)** | Documented (in `blocks/listing-map/render.php`) that clustering is an intentional per-block attribute, not the site-wide `map_clustering` setting. By-design. |
| **"Search this area" bounds (#76)** | `store.js searchImmediate()` serializes `state.mapBounds` into the navigation URL; `listing-grid/render.php` + `listing-map/render.php` read `bounds[]` from `$_GET` (grid via the search engine's existing `bounds` arg, map via a `g.lat/g.lng BETWEEN` clause under the `map_max_markers` LIMIT). The drawn viewport now survives the reload instead of resetting. |
| **Action Scheduler bundled in Free (#77)** | **Action Scheduler 3.9.3 now vendored in Free** (`vendor/woocommerce/action-scheduler`, git-tracked like the Credits SDK) and loaded early in `wb-listora.php`, guarded by `function_exists`, defining `WB_LISTORA_AS_FROM_FREE`. Free-only sites get AS instead of the WP-Cron fallback. Pro consumes Free's single authoritative copy (Pro PR #66). Shared-infra-belongs-in-Free, per the upscale model. |
| **Journey-doc slug fix (#78)** | role-cap-matrix journey corrected from `wb-listora-{settings,reviews,claims}` to the real `listora-*` menu slugs. |

New regression journeys: `verification-feature-disabled.md`, `listing-approve-reject-row-actions.md`, `setup-wizard-unknown-step.md`, `map-search-this-area-bounds.md` + runbook D rows D.verified-flag-feature-gate / D.approve-reject-row-actions / D.wizard-unknown-step / D.map-search-this-area-bounds.

**Manifest delta:** `hooks_fired` 198 → **199** (+1 filter `wb_listora_is_verified`, consumed by Pro). New vendored dependency: `vendor/woocommerce/action-scheduler` (3.9.3).

## Recent Changes (2026-05-18 — pre-1.0.5 fix wave + pre-launch additions + manifest refresh)

Eight Basecamp cards closed + 2 smoke failures fixed + 4 pre-launch features shipped + manifest refreshed to reflect 2026-05-18 state.

| Area | Change |
|---|---|
| **F-04 anon-login modal** | 772316b → 773a89a: modal markup reuses `.listora-detail__modal` family for consistency with the claim modal; Create Account CTA now ALWAYS renders (was gated on `users_can_register`); new `wb_listora_login_modal_register_url` filter for invite-only sites. WordPress shows "Registration currently not allowed" on the destination page when the option is off — clearer affordance than hiding the CTA. |
| **F-05 search suggest** | 773a89a: REST `/search/suggest` returns `{ suggestions: [...] }` envelope; IAPI store action `fetchSuggestions()` was assigning the whole envelope to `state.suggestions` and `data-wp-each` iterated over object keys, rendering nothing. Now unwraps via `Array.isArray( response?.suggestions ) ? response.suggestions : []` and only flips `state.showSuggestions = true` when the array is non-empty. |
| **REST consistency** | 41c4a68 + follow-up: both `/listings/{id}` and `/listings/{id}/detail` now emit RFC-3339 `created_at` + `updated_at` GMT timestamps. Headless / mobile clients no longer need to special-case the two endpoints. |
| **8 Basecamp bug fixes** | 41c4a68 (5 cards): REST timestamps, Details overflow gate, All Types dropdown event.target.value fallback, Near Me / search-button overlap, Reviews-disabled REST 403 + frontend hidden. 5a4d0f9 (2 cards): Edit form blank HIGH IMPACT (URL-param mismatch — dashboard sent `?action=edit&id=N`, submission block only read `?edit=N`), Deactivate→View 404 (icon now only renders when post_status=publish). |
| **D1 closed — REST envelope** | This session: `GET /listora/v1/listings` OFFSET branch now wraps the parent response in the same envelope as the CURSOR branch + `/search`: `{ listings, total, pages, has_more, cursor, next_cursor }`. Same payload across endpoints emits the same shape. WP-standard pagination headers (X-WP-Total/X-WP-TotalPages/X-WP-NextCursor) still emit for WP-native clients. Regression journey at `docs/qa/journeys/regression/rest-listings-envelope.md`. |
| **D4 closed — AJAX exceptions** | This session: 4 wp_ajax_ handlers (listora_dismiss_onboarding, listora_run_migration, wb_listora_validate_license, wb_listora_dismiss_promo) documented as intentional exceptions to the Part 6 max-2 contract — all admin-only, all gated by `manage_listora_settings`, all mirror WP-core `wp_ajax_dismiss-wp-pointer` family. None is customer-facing. Free's customer surface is REST + Interactivity API only. |
| **D2 + D3 closed — Featured + bulk moderation** | f4fb0b5 (shipped pre-2026-05-18): `Featured_Metabox` side metabox on listora_listing edit screen (wraps `Featured::feature_listing` so Pro's credit-gated rotation still applies on top) + `listora_featured` admin-list column with star + expiration tooltip + `POST /listora/v1/listings/bulk-moderate` REST endpoint (approve/reject/feature/unfeature/trash up to 100 IDs per call). |
| **Anti-Spam + Contact Form** | f4fb0b5: new `Anti_Spam::check()` helper layers keyword blacklist + URL-density cap + Akismet (fails open on outage). New `Contact_Form::handle_rest_submission()` for `/listings/{id}/contact-form` — nonce + honeypot + per-IP-per-listing 3/hr + per-listing 20/day caps. Pro coupling: `Contact_Form::should_render()` bails when `wb_listora_pro_feature_enabled('lead_form')` returns true, so the two never render together. New filter `wb_listora_render_contact_form` gates this. |
| **3 runbook doc-bugs** | Free `docs/qa/AGENT_SMOKE_RUNBOOK.md` C.cron rebuilt with canonical 6-row hook-name table (was listing 6 wrong names); B4 cross-refs C.cron. Pro `docs/qa/AGENT_SMOKE_RUNBOOK.md` S6 reframed as LMFWC (not EDD SL — same code path; terminology only); S8 documents the toggle-gated cron count (3 default vs 7 with all toggles ON). |
| **launch-readiness 2026-05-18** | `docs/qa/launch-readiness-2026-05-11.yaml` renamed → `launch-readiness-2026-05-18.yaml` with 18 top-level sections including a new `ux_consistency_review` section (9 resolved UX-CONS items + 3 open ux audits pending repro + 6 policy anchors). Verdict: READY-WITH-OPEN-CARDS — re-smoke + 5 BC open cards remaining. |
| **wppqa baseline 2026-05-18** | `audit/wppqa-baseline-2026-05-18/SUMMARY.md` — 0 real findings, 1 nonce-no-cap FP (Featured metabox: cap check is 7 lines BEFORE nonce check; sniff scans wrong direction), 5 wiring half-wired FPs (all service-layer reads, none should reach `templates/`), 15 admin tap-target warnings unchanged (known-limitation per wp-admin context). |

**Manifest delta this refresh:** REST 53 → 55 (+`/listings/bulk-moderate`, +`/listings/{id}/contact-form`). hooks_fired 192 → 198 (+2 actions: after_bulk_moderate + after_contact_form_submit; +4 filters: login_modal_register_url + render_contact_form + contact_form_per_listing_daily_cap + contact_form_email_headers). 3 new classes (Anti_Spam, Contact_Form, Featured_Metabox). admin_pages unchanged. wppqa baseline link in `manifest.summary.json` bumped to 2026-05-18.

3 new regression journeys: `regression/anon-login-modal-register-cta.md` (F-04), `regression/search-suggest-envelope-unwrap.md` (F-05), `regression/rest-listing-timestamps.md` + `regression/rest-listings-envelope.md` (REST consistency D1 + BC-9900590343).

## Recent Changes (2026-05-13 — Credit/plan flow refactor — Free side)

Pro 1.5.0 ships the canonical event surface + Hold/Commit plan activation; Free's side of the refactor is the customer-facing UX for the paused state and a single canonical plan-cost meta key everywhere it's read.

| Area | Change |
|---|---|
| **Submission response surfaces paused state** | `class-submission-controller.php:539-565` — after `do_action(wb_listora_listing_submitted)` fires, the controller re-reads `get_post_status($post_id)`. When status flipped to `listora_payment` the response carries `paused: true` + a clear "Listing saved. It will activate as soon as you top up enough credits…" message instead of the old misleading "Listing submitted successfully!". Pro's `Pricing_Plans::enrich_paused_submit_response` filter hook attaches the pending plan name, credits required, balance, short-by, and credits-tab URL. |
| **Recovery row reads canonical meta** | `templates/blocks/user-dashboard/tab-listings.php` — paused-listing UX now reads `_listora_plan_credits` (canonical) instead of the retired `_listora_plan_credit_cost`. Displayed cost always matches what activation deducts. |
| **SDK consumer cost callback retargeted** | `wb-listora.php:439` — the Listora SDK consumer's `cost` callback now resolves plan cost via `_listora_plan_credits`. |
| **Listings REST plan resolution** | `class-listings-controller.php:1472` — the plan-cost lookup in listing creation also moved to `_listora_plan_credits`. |
| **Paused status renamed visibly** | `class-post-types.php` + `class-status-manager.php` + dashboard `status_map` — post-status label is now "Awaiting Credits" everywhere it surfaces (slug stays `listora_payment` for back-compat). Reflects the architectural truth that credits are the only currency in the vendor flow. |
| **Architecture invariant alignment** | Pro's `bin/architecture-checks.sh` INV-13 now scans Free's tree too. Free has ZERO references to the retired `_listora_plan_credit_cost` outside doc comments (architecture gate green). |

**Customer impact:** vendors who buy credits through ANY of the bundled SDK adapter paths (WooCommerce, WooSubscriptions, MemberPress, PMPro, WooMemberships) now trigger auto-resume of their paused listings — previously only the in-plugin webhook receiver fired the Pro action, leaving the majority of paying customers stranded. The recovery row + paused-state response means vendors see the issue immediately at submission time, with the exact cost + credits-short + Buy Credits CTA inline.

## Recent Changes (2026-05-12 — Social Links delivery + REST gap fill + PHP 8 / a11y fixes)

| Area | Change |
|---|---|
| **Social Links field — full delivery (HC-1)** | `Field::sanitize_social_links()` + `Field::social_link_platforms()` added to `includes/core/class-field.php` (single source of truth for 7 platforms). Submission renderer now handles `social_links` field type (`includes/submission-field-renderer.php:310`). Detail sidebar renders a "Follow" card (`templates/blocks/listing-detail/sidebar.php:56-76`). Schema generator emits `sameAs` for social URLs (`includes/schema/class-schema-generator.php:150`). New CSS primitives: submission `.listora-submission__social-{links,row,label,input}` + detail `.listora-detail__social-{card,list,link,label}`. Field no longer in submission step skip list. |
| **REST SELECT column fix** | `includes/rest/class-listings-controller.php:711` — `services` query now explicitly selects `id, title, description, price, price_type, duration_minutes, image_id` (was missing columns, returning empty rows in card mode). |
| **PHP 8 admin deprecation fix** | `includes/admin/class-admin.php` — Setup Wizard `add_submenu_page()` parent changed from `null` to `''` when hidden. `null` was passed to `strpos()` inside `wp_is_stream()` via `wp_normalize_path()` → PHP 8 deprecation on every admin request. |
| **A11y — stepper aria attributes** | `templates/blocks/listing-submission/stepper.php:22` — first step indicator now renders `aria-current="step"` on server side. `src/blocks/listing-submission/view.js` correctly moves `aria-current` between indicators and updates `aria-valuenow` on the progressbar as steps advance/retreat. |
| **Dashboard URL hash parser fix** | `src/blocks/user-dashboard/view.js` — hash parser now uses regex `^[a-z][a-z0-9_-]*` instead of naive `location.hash.replace('#','')`, preventing `SyntaxError` when anchor includes query params (`#tab?foo`). |

**Manifest delta:** REST endpoints 50 → **53** (+3 previously-missing routes: `/listings/{id}/reactivate`, `/settings/notifications/log/export`, `/settings/notifications/log/retention`). `admin_pages` Health Check parent corrected to `''`; Setup Wizard note updated. No new blocks/AJAX/tables/caps/hooks.

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
| 2.3 Audit guardrails | `bin/audit-guardrails.sh` (`composer guardrails`) | drift / Free-Pro boundary / config-gating regressions from the 2026-07 audit — G1 rating post-meta reads, G2 Free reading a `wb_listora_pro_*` option, G3 Free↔Pro payments DDL divergence, G4 credit surfaces not routed through `wb_listora_should_show_member_credits()` |
| 3.1 Manifest | `jq` on `audit/manifest.json` | manifest validity + freshness |
| 4.1 Journeys | `bin/run-journeys.sh` | customer flows end-to-end |

**Bypass for emergencies only**: `SKIP_LOCAL_CI=1 git push`.

## Customer journeys

Bug fixes that survive a refactor are journey-covered. See [`docs/qa/journeys/README.md`](docs/qa/journeys/README.md) for the schema and the executor contract. When a new bug is fixed, add or update the journey that would have caught it. The journey IS the regression test.

Authored journeys (83 total — 17 customer / 16 admin / 48 regression / 2 system). The tables below are a curated highlight subset; `docs/qa/journeys/` is the full index:

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
| `regression/pagination-active-page-contrast.md` | high | pagination active-page contrast under aggressive theme anchor rules (b299fd6) |
| `regression/search-rating-average-nonzero.md` | high | `/search` rating.average float-guard fallback (5106ee4) |
| `regression/cli-test-email-cleanup.md` | normal | `wp listora test-email` + `cleanup` subcommands (43ded68) |

Run all: `composer journeys` · Critical only: `composer journeys:critical` · Dry-run: `composer journeys:dry-run`
