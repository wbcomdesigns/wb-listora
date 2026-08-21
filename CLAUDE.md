# WB Listora — CLAUDE.md

## Where the status lives — read this before asking what is done

Three files answer "what is done, what is pending, what should not be tested". Keep them current in
the PR that moves a row; a status file that lags the code is worse than none, because it reads as
assurance.

| Question | File |
|---|---|
| **What is done / pending / skipped, plugin AND app, in one view** | [`plan/app-parity.md`](plan/app-parity.md) — and [`plan/app-parity.html`](plan/app-parity.html) for the same data as a scannable board. The app repo carries an identical copy at `listora-app/docs/feature-coverage.html`. |
| **What QA should NOT test** (declared omissions, not defects) | The "What QA should and should not test" table in `listora-app/docs/FEATURE-COVERAGE.md`, plus the Deferred / Web-only / Blocked sections of the parity doc |
| **Open plugin defects with reproduction steps** | [`audit/GAP_AUDIT_2026-08-06.md`](audit/GAP_AUDIT_2026-08-06.md) and its Pro twin. **Fix markers there can lag or overstate** — the parity doc records where the two disagree and why |

The app's release gate is `listora-app/docs/FEATURE-COVERAGE.md`: zero ❌ Missing rows before a
release.

---

> **READ FIRST (in order):**
> 1. [`audit/manifest.summary.json`](audit/manifest.summary.json) — ≤3 KB plugin shape index.
> 2. [`docs/qa/qa-index.json`](docs/qa/qa-index.json) — QA artifact discovery + release gate + maintenance loop (machine-readable).
> 3. The **Repository layout** + **QA Pipeline** sections below in this file.
> 4. Most-recent [`audit/wppqa-baseline-2026-08-12/SUMMARY.md`](audit/wppqa-baseline-2026-08-12/SUMMARY.md) — current bug surface.
>
> Full inventory in [`audit/manifest.json`](audit/manifest.json) (schema v2.1, corrected 2026-08-20 against released **1.6.0**): **63 REST** · 5 AJAX · 11 tables · 11 blocks (9 layout-owning) · 13 admin pages · **326 fired hooks** (150 actions + 176 filters with `consumed_by`) · 15 caps · 6 taxonomies · 1 CPT · 10 cron · 10 services · 1 WP-CLI command (11 subcommands) · 7 interactivity blocks · 8 static detectors. Counts here are mirrored from [`audit/manifest.summary.json`](audit/manifest.summary.json) — if the two ever disagree, the manifest arrays win and this line is the one that drifted. Pre-computed sub-checks at [`audit/derived/`](audit/derived/) (**2** cache files: `cross-plugin-coupling.json`, **71** Free→Pro pairs, and `wiring-baseline.json`). **Both derived caches were computed 2026-06-10 and predate the 1.4.x, 1.5.0 and 1.6.0 waves — recompute before trusting either.** See [`audit/FEATURE_AUDIT.md`](audit/FEATURE_AUDIT.md), [`audit/CODE_FLOWS.md`](audit/CODE_FLOWS.md), [`audit/ROLE_MATRIX.md`](audit/ROLE_MATRIX.md). **Manifest refresh strategy for this plugin: TARGETED / agent-enumeration only — do NOT commit the deterministic generator (`write-manifest.mjs`) output.** It scans the bundled `libs/wbcom-credits-sdk` and emits the SDK's `wbcom-credits/v1` routes as plugin routes (real ns is `listora/v1`), mis-parses the controller registry, and drops `plugin.version`. Refresh via `/wp-plugin-onboard --refresh` but keep the curated manifest as the base.

## Repository layout (post 1.0.4 reorg)

Plugin is **private — wbcomdesigns only**, never published to wordpress.org. The repo is organized so each directory has exactly one job:

| Directory | Owns | Notes |
|---|---|---|
| `audit/` | Architecture, specs, machine-generated inventory, current baseline | Onboard skill's domain. Includes `architecture/`, `cleanup/`, `derived/`, the most-recent `wppqa-baseline-*/`, and the top-level `manifest.json` / `manifest.summary.json` / `FEATURE_AUDIT.md` / `CODE_FLOWS.md` / `ROLE_MATRIX.md` / `GAP_AUDIT_*.md`. **Only the current state lives here** — dated one-off audits and superseded baselines are deleted once acted on, not archived (git history keeps them). |
| `tests/` | Verification (phpunit + QA) | `docs/qa/` holds the smoke runbook, qa-config, qa-index, journeys, fixtures, and the last smoke verdict. (Dated one-off audits - launch-readiness yaml, data-flow verification, the QA presentation - were deleted 2026-08-20 as superseded; git history has them.) `tests/{bootstrap.php,factories/,integration/,unit/}` is the phpunit code. |
| `plan/` | Human-authored **open** plans and handoffs | Only work that is still in flight - see [`plan/README.md`](plan/README.md) for the current index, which is kept in step with the directory. **Delete a plan when its wave ships** — shipped plans are history, and agents that read them re-plan work that already exists. NOT for architecture (→ audit) or QA (→ docs/qa). |
| `docs/` | Customer + integrator-facing only | Public REST-API reference, contributor guide, docs-site source (`docs/website/`). Never put internal artifacts here. |
| `bin/` | Developer scripts | Build/release/CI scripts + dev-only utilities (`seed-demo.php`, `verify-notifications.php`). Excluded from dist. |
| `demo/` | Customer-facing demo data | Setup Wizard + `wp listora seed-demo` load these. Ships in dist. Do not confuse with `bin/seed-demo.php` (dev-only). |

GitHub Actions CI was retired in 1.0.4 — `composer ci` (local-CI pre-push hook) is the single quality gate.

## Latest smoke verdict (2026-08-19 — 1.6.0 combo)

Smoke source: [`docs/qa/.last-smoke-pass.json`](docs/qa/.last-smoke-pass.json) (rewritten by every `/wp-plugin-smoke combo` run — read it rather than trusting this heading, which is a mirror and can drift).

**Release gate: GREEN.** `release_version` 1.6.0 and `pro_version` 1.6.0 both match the version constants, `failures[]` is empty, and the contract audit passed (0 errors, 7 warnings, 1 info, 15 baselined). 7 sections walked.

**One debug-log entry, third-party:** a BuddyPress core PHP 8.4 nullable-parameter deprecation from `bp-core-template-loader.php:661`, fired while browsing BP-adjacent pages during the member-dashboard walk. Not Listora code — file against BuddyPress, do not chase it here.

**7 items marked `manual_required`** — checks the run could confirm statically but not exercise live, because the smoke session is verification-only and may not write a mu-plugin. The largest is `D.related-listings-hooks`: `wb_listora_before_related_listings` / `_after_` are confirmed present at the right position in `blocks/listing-detail/render.php` with the documented args, but no external listener was attached to prove firing. A follow-up run with Write access closes these.

## Free → Pro upscale-journey contract (apply MediaVerse lesson)

WB Listora is a Free + Pro pair shipped under the **upscale-model** rule: Free is mandatory, Pro extends Free, Pro NEVER stands alone. The same trap that hit MediaVerse before us — Pro shipping its own copies of Free's code instead of consuming Free's existing surface — is what `bin/cleanup-duplicate-detect.php` + `bin/cleanup-boundary-check.sh` exist to catch.

**The 5-step extension order is non-negotiable:**

1. **Find what Free already exposes.** Read `audit/manifest.json#/hooks_fired[]`, `audit/derived/cross-plugin-coupling.json`, `audit/architecture/pro-coupling-contract.md` (in Pro). Free already publishes hooks, contracts, and `wb_listora_service()`-resolved services for almost every cross-cutting concern.
2. **Consume via documented surface.** Documented hooks (`add_filter`/`add_action`), `\WBListora\Contracts\*` interfaces, `wb_listora_service($name)` locator. Never direct refs to `\WBListora\Core\*` etc. (INV-3 in Pro's `bin/architecture-checks.sh`).
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

## Migrator ownership (Free owns the pipeline, Pro owns the UX)

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

### The split is done — do not re-open it

Completed in 1.1.0 and verified still true at 1.6.0: Pro's `includes/migration/` holds exactly one file, `class-competitor-detector.php`. The five migrator classes it used to carry (Base_Migrator + Directorist + GeoDirectory + WPBDP + HivePress) were private implementation details with zero external consumers, so they were deleted without a deprecation window.

All five competitor migrators now live in Free — `class-{bdp,directorist,geodirectory,hivepress,listingpro}-migrator.php`. HivePress was briefly pulled from the customer-facing product during the split and is back; there is no longer any "coming in a later release" response on Pro's `Competitor_Migration` endpoint.

## Production rules (live-site protection — non-negotiable)

These rules protect live customer sites against the failure modes we (and MediaVerse before us) learned the hard way. Enforced where possible by Pro's `bin/architecture-checks.sh` (run from the Pro tree, with Free checked out alongside); the rest are review-time hard gates. **No exceptions in patch releases.**

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

### Stored-shape rule: `business_hours`

**Every consumer of `business_hours` MUST call `wb_listora_normalize_hours()`. Every producer must emit one of the three known shapes and cap with `wb_listora_max_hours_slots()`.**

Three shapes exist in stored data because the format changed twice and old rows were never rewritten: the canonical list, the older day-keyed single range, and the current day-keyed `ranges` array. A sweep found **five** readers with five interpretations, four of them wrong - and every one failed *silently*: storage correct, one surface blank, no error anywhere. Schema.org output was publishing an empty `openingHoursSpecification` for every member-submitted listing while the page itself rendered hours perfectly.

That is the failure mode to expect from a sixth reader. It will not throw; it will just quietly show nothing.

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

### IAPI directive rule

**`data-wp-class--*` and `data-wp-bind--*` MUST read a tracked property, never a literal-comparison expression.** The Interactivity API tracks property *reads*, so `state.activeModal === 'claim'` is evaluated once and never re-evaluated when `activeModal` mutates - the directive silently stops updating. Introduce a derived getter and bind to that:

```js
get isClaimModalOpen() { return state.activeModal === 'claim'; }
```

Same pattern everywhere it recurs: `activeTab` -> `isReviewsTabActive`, `currentStep` -> `isStepDetailsActive`.

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
| Smoke runbook (canonical) | [`docs/qa/AGENT_SMOKE_RUNBOOK.md`](docs/qa/AGENT_SMOKE_RUNBOOK.md) | A-G customer contracts for fresh install, upgrade, all flows, regression guards, Pro extensions, cross-browser, post-release. **863 lines, last refreshed 2026-08-21** — carries a `[CORE]` must-run set, and cross-cutting checks 7-10 (no DB errors / counters must agree with what they count / visible means computed-visible / translated means rendered-translated), each added because it caught a shipped bug the old checks passed. | Bug-fix + feature PRs (write); smoke skill (read) |
| Pro supplements | [`../wb-listora-pro/docs/qa/AGENT_SMOKE_RUNBOOK.md`](../wb-listora-pro/docs/qa/AGENT_SMOKE_RUNBOOK.md) | Pro-only **S1-S24** ops (lockstep / license / INV-12 / coupling / strict HMAC / toggle isolation / app-config license gate). | Pro PRs |
| Journeys (executable) | [`docs/qa/journeys/`](docs/qa/journeys/) | **175** self-contained markdown flows an agent runs end-to-end via Playwright + WP-CLI + curl + mysql_query (20 customer / 18 admin / **134** regression / 3 system, plus `README.md` and `.template.md` which are not journeys). Pro carries a further **91** (18c / 20a / 50r / 3s). Returns PASS/FAIL with exact step + likely_files for triage. See [`docs/qa/journeys/README.md`](docs/qa/journeys/README.md) for the schema. | Bug-fix + feature PRs (write); `bin/run-journeys.sh` (execute) |
| QA index (machine-readable) | [`docs/qa/qa-index.json`](docs/qa/qa-index.json) | The structured index: artifacts, release gate requirements, maintenance loop, discovery order. CLAUDE.md prose mirrors it; this file is canonical. | This wiring pass; refreshed when QA shape changes |
| wppqa baseline | [`audit/wppqa-baseline-2026-08-12/SUMMARY.md`](audit/wppqa-baseline-2026-08-12/SUMMARY.md) | Static-analysis bug finder (plugin-dev-rules / REST↔JS contract / wiring). Latest run: 18 passed, 8 failed, **0 real failures** (all 8 false positives). The older 2026-05-24 baseline is still on disk; this is the one to read. Re-run with `wppqa_audit_plugin --plugin_path=$(pwd)`. | Onboarding refresh |
| Manifest | [`audit/manifest.json`](audit/manifest.json) + summary | Plugin shape + 8 static detectors. Refresh via `/wp-plugin-onboard --refresh` after non-trivial commits. | Onboarding skill |
| Smoke gate (release) | [`bin/build-release.sh`](bin/build-release.sh) ~lines 105-135 | **Refuses to package** unless `docs/qa/.last-smoke-pass.json` exists and `release_version` matches the VERSION constant. `failures[]` and `debug_log_issues[]` are triaged **by origin** by [`bin/smoke-coverage-gate.py`](bin/smoke-coverage-gate.py) — a third-party entry (a BuddyPress core deprecation, say) does not block, which is why the green 1.6.0 report carries one. Blanket blocking on any entry is only the no-python3 fallback. The coverage gate also requires no empty section, every `[CORE]` row run, and every section-D row added for THIS release run. Emergency only: `--skip-browser-smoke`. | Release script |

### Release gate (must be GREEN before tagging)

Run before every release tag — copy the checklist:

1. **Architecture invariants** — `bash ../wb-listora-pro/bin/architecture-checks.sh` returns 0. The script lives in **Pro only** (it checks the Free→Pro coupling contract, so it needs both trees side by side); there is no copy in this repo.
2. **wppqa baseline** — most-recent `audit/wppqa-baseline-*/SUMMARY.md` shows `0 release blockers`. Re-run via the MCP tool if older than 7 days.
3. **Smoke pass** — run `/wp-plugin-smoke combo`. Confirms:
   - Walks every section of `docs/qa/AGENT_SMOKE_RUNBOOK.md`
   - Executes every authored journey under `docs/qa/journeys/`
   - Writes `docs/qa/.last-smoke-pass.json` with `release_version` matching `WB_LISTORA_VERSION`
   - `failures[]` and `debug_log_issues[]` are triaged **by origin**, not by emptiness — `bin/smoke-coverage-gate.py` owns that call. An entry traced to a third-party plugin (BuddyPress, a theme) does not block; an entry traced to Listora does.
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
WB_LISTORA_VERSION        // '1.6.0' — the constant in wb-listora.php is the source of truth
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

## Release history

**Not kept here.** This file used to carry 25 `## Recent Changes` sections going back to 2026-04-05 -
about 400 lines, roughly 43% of the file, largely restating `CHANGELOG.md` in a second format that
drifted from it. Removed 2026-08-20.

Where to look instead:

| Question | Read |
|---|---|
| What shipped in a release, customer-facing | [`CHANGELOG.md`](CHANGELOG.md) and [`readme.txt`](readme.txt) |
| What the plugin *is* right now | [`audit/manifest.json`](audit/manifest.json) + [`audit/FEATURE_AUDIT.md`](audit/FEATURE_AUDIT.md) |
| Why a specific line looks the way it does | `git log -S'<symbol>'` and `git blame` |
| What changed between two releases | `git log --oneline v1.5.0..v1.6.0` |

The two rules that were buried in those sections and are *not* changelog entries have been moved to
where they belong: the `business_hours` stored-shape rule is under **Production rules**, and the IAPI
directive rule is under **Frontend v2 architecture**.

If you are tempted to add a changelog entry here, add it to `CHANGELOG.md` instead. A second copy is
how the first one drifted.

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

Authored journeys (**175** total — 20 customer / 18 admin / **134** regression / 3 system; Pro carries a further 91). The tables below are a curated highlight subset and are not maintained per-journey; `docs/qa/journeys/` is the full index and the only count worth trusting:

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
