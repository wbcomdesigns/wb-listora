# Foundation-Grade Cleanup — Free + Pro

**Quality bar:** 100K-site foundation infrastructure used by many developers. Organization + code quality must be **perfect**. No junk, 100% wp-plugin-development compliance, onboard skill drives organization.

**Method:** Three concentric passes, executed in order.

---

## Pass 1 — Repo hygiene (delete junk)

Safe deletions, no behavior change.

### 1.1 Stale wppqa baselines

Keep latest 2 baselines per plugin. Older ones are point-in-time snapshots that the latest baseline subsumes.

**Free — delete:**
- `audit/wppqa-baseline-2026-04-30/` ← superseded by 05-07 + 05-08
- `audit/wppqa-baseline-2026-04-30-pm/`
- `audit/wppqa-baseline-2026-05-07-postP0/`

**Free — keep:**
- `audit/wppqa-baseline-2026-05-07/`
- `audit/wppqa-baseline-2026-05-08/`
- (new) `audit/wppqa-baseline-2026-05-11/` after Pass 2 runs

**Pro — delete:**
- `audit/wppqa-baseline-2026-04-30/`
- `audit/wppqa-baseline-2026-04-30-pm/`

**Pro — keep:**
- `audit/wppqa-baseline-2026-05-07/`
- `audit/wppqa-baseline-2026-05-08/`
- (new) `audit/wppqa-baseline-2026-05-11/` after Pass 2 runs

### 1.2 Stale plan/ docs

Plans that shipped + are no longer load-bearing get archived (not deleted — investigation value preserved in `plan/archive/`).

**Free plan/ — candidates for archive:**
- `1.0.0-release-plan.md` (release plan for v1.0.0, work shipped)
- `browser-test-results.md` (point-in-time browser run, audit/journey-runs/ supersedes)
- `qa-bugs-triage-2026-04-30.md` (point-in-time triage, all bugs closed or in journey)
- `release-issues-and-flow-tests.md` (early QA scaffold, superseded by audit/journeys/ + docs/qa/AGENT_SMOKE_RUNBOOK.md)
- `qa/journeys-by-role.md` (subsumed by audit/journeys/)

**Free plan/ — KEEP (still load-bearing):**
- `ARCHITECTURE-5YR.md` (forward-looking architecture vision)
- `data-flow-verification-plan.md` + `-results.md` (recent — 2026-05-09)
- `page-registry.md` (current architecture doc)
- `100k-readiness/` directory (active scale strategy)
- `frontend-ux-audit/` directory (recent — 2026-05-11)
- `frontend-refactor/` directory (active execution)
- `foundation-cleanup-2026-05-11.md` (this doc)
- `sustainability/` directory (forward-looking)

**Pro plan/ — candidates for archive:**
- `release-issues-and-flow-tests.md` (early QA scaffold, superseded)

**Pro plan/ — KEEP:**
- `credit-gateways-frontend-purchase.md` (active credit system architecture)
- `roadmap.md` (forward-looking)
- `wb-listora-architecture-contract.md` (the 12 invariants — load-bearing)
- `100k-readiness/` directory (active)

### 1.3 Files that should NEVER be in repo

Already clean. Re-verified:
- `node_modules/` — gitignored ✓
- `dist/` — gitignored ✓
- `.DS_Store` — 0 in tracked files ✓
- `*.bak` / `*.orig` — 0 from our code ✓ (some in node_modules but those aren't tracked)
- `build/` — INTENTIONALLY tracked (WP plugin distribution convention; allows install-without-npm)

### 1.4 Build artifacts in src/

Need to verify no stale generated files in src/. After refactor, src/blocks/ should be the source-of-truth; build/blocks/ is the compiled output.

---

## Pass 2 — Static analysis (find code quality issues)

### 2.1 Refresh wppqa baseline

Run `mcp__wp-plugin-qa__wppqa_audit_plugin --plugin_path=$pwd` on both repos. Save as `audit/wppqa-baseline-2026-05-11/SUMMARY.md`.

Expected to surface:
- Any new findings introduced by the refactor (regressions)
- Any pre-existing findings the May 8 baseline classified as false-positive
- REST↔JS contract drift
- Wiring completeness gaps

### 2.2 Refresh manifest

Run `/wp-plugin-onboard --refresh` on both repos.

Expected manifest changes:
- New tokens layer (`src/tokens/` with 7 files) — manifest's `category_sources` should include this
- New primitives layer (`src/primitives/` with 11 files)
- Render helpers (`includes/class-render-helpers.php`) — adds 2 public functions to manifest's `wp_cli` / `helpers` section
- Block count unchanged (16 total)
- Reduced hex literal count, reduced px literal count

### 2.3 Run architecture invariants

`bash bin/architecture-checks.sh` on Pro. All 12 must pass.

### 2.4 WPCS + PHPStan on both

`composer ci:quick` on both repos. Already passing per recent commits but re-verify post-refactor.

---

## Pass 3 — Comprehensive compliance audit

Once Pass 1 + 2 surface concrete findings, build a checklist against the 6 wp-plugin-development production-readiness gates:

| Gate | Source | Pass criteria |
|---|---|---|
| Q1-Q6 | wp-plugin-development Part 0.7 (code-quality gates) | All Q-gates green per `bin/ci-local.sh` |
| R1-R8 | wp-plugin-development Part 0.7 (REST contract gates) | wppqa_check_rest_js_contract returns 0 failures |
| S1-S6 | wp-plugin-development Part 0.7 (security gates) | wppqa_check_plugin_dev_rules returns 0 high-severity |
| F1-F8 | wp-plugin-development Part 0.7 (frontend gates) | All inline `<style>`/`<script>` extracted; 100% token-based CSS; admin header on every page |
| P1-P9 | wp-plugin-development Part 0.7 (performance gates) | Cache idioms, no N+1, no external HTTP in request path |
| E1-E6 | wp-plugin-development Part 0.7 (release engineering gates) | bin/build-release.sh + bin/ci-local.sh + .distignore present |
| O1-O4 | wp-plugin-development Part 0.7 (onboarding/audit gates) | CLAUDE.md fresh, manifest fresh, wppqa baseline, plan document exists |

Each gate gets a "pass / fail / known-limitation" classification with a per-file remediation list when failing.

---

## Execution sequence

1. **Pass 1.1** — delete stale baselines (5 dirs total). Safe, immediate.
2. **Pass 1.2** — archive stale plan docs. Move to `plan/archive/`, not delete.
3. **Pass 2.1** — wppqa baseline. Surfaces backend code quality findings.
4. **Pass 2.2** — manifest refresh. Reflects current architecture.
5. **Pass 2.3** — architecture-checks.sh. Verifies 12 invariants.
6. **Pass 2.4** — WPCS + PHPStan. Final clean-state lint.
7. **Pass 3** — gate-by-gate compliance audit. Findings drive remediation PRs.

Pass 1 is ~30 min. Pass 2 is ~1 hour (mostly dispatching tool calls). Pass 3 is multi-day depending on what's surfaced.

---

## What this delivers

After all 3 passes:

- **Zero junk in repo** — every file traces to active load-bearing purpose.
- **Single canonical source per concern** — manifest is authoritative, wppqa baseline is current, plan/ has only active docs.
- **Documented compliance vs wp-plugin-development gates** — every Q/R/S/F/P/E/O gate has a pass-or-known-limitation classification.
- **Onboard refresh** — future Claude sessions read up-to-date manifests and know the current state.

The 100K-site foundation has a clean, organized, gate-verified codebase that any new developer can navigate without confusion.
