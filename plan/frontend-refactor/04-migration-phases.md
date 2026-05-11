# Migration Phases (Safe + Reversible)

**Every phase ends with all 51 journeys + smoke gate GREEN. Every phase is one PR or set of small PRs. No "big bang" steps.**

---

## Phase 0 — Design + Tokens

**Scope:**
- Write 7 plan docs (you're reading them now).
- Create `src/tokens/` + 7 files defining v2 token names.
- Add v2 tokens IN PARALLEL with v1 tokens (both live, no migration yet).
- Build pipeline emits `assets/css/listora-tokens.css` (new) alongside `assets/css/shared.css` (unchanged).

**Verification:**
- New tokens load on every page (verify `getComputedStyle(document.documentElement).getPropertyValue('--listora-fg-default')`).
- Old tokens still work (existing blocks unaffected).
- Architecture invariants 12/12 pass.

**Effort:** 1 day (plan docs already written; tokens are mechanical copy + reorganize).

**Reversibility:** delete `src/tokens/` + revert build pipeline = back to v1 only.

---

## Phase 1 — Primitives (parallel layer)

**Scope:**
- Create `src/primitives/` + 11 files. Each primitive is a NEW class (`.listora-card-v2` initially — distinct from existing `.listora-card`).
- Build pipeline emits `assets/css/listora-primitives.css`.
- Add `includes/class-render-helpers.php` with `wb_listora_render_empty_state()` + `wb_listora_render_tabs()`.

**Important:** primitives use NEW class names (`-v2` suffix during transition) so they coexist with v1. Blocks migrate one-by-one in Phase 2; only then do we rename `-v2` away.

**Verification:**
- New CSS loads ✓
- Old blocks unaffected (still use v1 classes) ✓
- Helper renders correctly on a test page (drop a `[wb_listora_render_empty_state]` into a sandbox post)

**Effort:** 2-3 days.

**Reversibility:** delete `src/primitives/` + revert helpers = back to Phase 0 state.

---

## Phase 2 — Block-by-block migration (one PR per block)

**The longest phase. 11 Free blocks + 5 Pro blocks = 16 PRs over 2-3 weeks. Each PR independently mergeable + revertable.**

### Per-block migration steps (template)

For each block (e.g. `listing-card`):

1. **Read the block's current style.css.** Identify which sections map to which primitives.
2. **Rewrite block's templates** to use primitive classes:
   - `.listora-card` (block-local) → `.listora-card` (primitive, same class name post-`-v2`-removal)
   - Update render.php + templates to new BEM
3. **Slim block's style.css** to only block-specific overrides (not the card chrome, not the empty state, not the buttons).
4. **Migrate tokens** in block's style.css using the search-replace table in `01-token-system-v2.md`:
   - `--listora-gap-md` → `--listora-space-4`
   - `--listora-text-sm` (size) → `--listora-text-size-sm`
   - `--listora-text-muted` (color) → `--listora-fg-muted`
   - etc.
5. **Convert hex literals** to tokens (~10 per block on average).
6. **Run the block's regression journey** (e.g. `audit/journeys/regression/dashboard-2-col-layout.md` for user-dashboard).
7. **Smoke walk the surface** (`/listings/` for listing-card, `/dashboard/` for user-dashboard, etc.).
8. **Commit + push.** PR title: `refactor(blocks): migrate listing-card to v2 primitives + tokens`.

### Migration order (low risk first)

| Order | Block | Why this order |
|---|---|---|
| 1 | listing-card (Free) | Simplest. The canonical "card" — establishes the pattern other blocks copy. |
| 2 | listing-grid (Free) | Composes listing-card. Smaller surface than dashboard. |
| 3 | listing-categories (Free) | 167 lines style.css — easy win. |
| 4 | listing-featured (Free) | 134 lines style.css — easy win. |
| 5 | listing-calendar (Free) | 244 lines — moderate. Largely standalone. |
| 6 | listing-map (Free) | 246 lines + inline JS extraction. |
| 7 | listing-search (Free) | 647 lines + split into sub-files. |
| 8 | listing-reviews (Free) | Touches both standalone block AND detail's Reviews tab — coordinate G5-01. |
| 9 | listing-submission (Free) | 1198 lines + 9 step templates + flatpickr. Biggest single-block migration. |
| 10 | listing-detail (Free) | 1194 lines + 539-line tabs.php + 3 modals + 80 lines inline JS. Most touched surfaces. |
| 11 | user-dashboard (Free) | 1641 lines + 5 tabs + extracts to per-tab style.css. Largest single surface. |
| 12 | comparison (Pro) | Includes inline JS extraction + need-detail template split. |
| 13 | credit-purchase (Pro) | Small, similar to listing-featured. |
| 14 | moderator-queue (Pro) | Small, similar to listing-categories. |
| 15 | needs-grid (Pro) | 40 hex → tokens (highest hex count after dashboard). |
| 16 | post-need (Pro) | 265-line template split. |

Each PR ~half a day to 1 full day. Average: 1 working week per ~5 blocks.

### Phase 2 verification (per block)

- Block's regression journey GREEN
- Smoke spot-check on the relevant surface (5 min Playwright)
- Architecture invariants 12/12 still pass
- WPCS + PHPStan clean
- No new debug.log entries

### Phase 2 verification (cumulative)

After every 3 block migrations: run full `/wp-plugin-smoke combo` (when Sonnet dispatch is stable). This catches cross-block regressions early.

---

## Phase 3 — Directory restructure

**After all 16 blocks are migrated, move the directories.**

### Steps

1. Move `src/blocks/listing-card/` → `src/blocks/discovery/card/` (and the other 13 Free + 5 Pro).
2. Move `templates/blocks/listing-card/` → `templates/blocks/discovery/card/` (and rest).
3. Update `BlockRegistrar.php` to scan the new `build/blocks/` paths (block slugs in build output stay flat).
4. Update theme-override lookup in `wb_listora_get_template()` to try new path first, fall back to old path with `_doing_it_wrong` notice.
5. Update `audit/qa-index.json` + `audit/manifest.summary.json` for the new paths.
6. CLAUDE.md "Recent Changes" entry documents the move.

**Verification:**
- All 51 journeys GREEN.
- Full smoke walk GREEN.
- Old theme override paths still resolve (with the notice).

**Effort:** 1-2 days.

**Reversibility:** `git revert` the move PR.

---

## Phase 4 — Cleanup

**Delete dead code + finalize the v2 vocabulary.**

### Steps

1. Drop `-v2` suffix from primitive classes (since all blocks now use them — no v1 to coexist with).
2. Delete `assets/css/shared.css` (everything moved to `src/tokens/` and `src/primitives/`).
3. Delete `src/shared/` (everything moved to `src/editor/`).
4. Delete:
   - `--listora-gap-*` tokens (mapped to `--listora-space-*`)
   - `--listora-font-size-*` tokens (mapped to `--listora-text-size-*`)
   - `--listora-error-*` aliases (mapped to `--listora-danger-*`)
   - `--listora-text-*` color tokens (overloaded namespace — color went to `--listora-fg-*`)
   - `.listora-ui-card` (now `.listora-card`)
5. Run a final `wppqa_audit_plugin` baseline. Expected: 0 release blockers + significantly fewer findings vs pre-refactor.
6. Refresh `audit/manifest.json` via `/wp-plugin-onboard --refresh`.

**Verification:**
- All 51 journeys GREEN.
- Smoke walk GREEN.
- WPCS + PHPStan clean.
- New wppqa baseline shows improvement.

**Effort:** 1-2 days.

**Reversibility:** harder — `git revert` works but you'd be reverting a lot. The cleanup PR should be a single commit so revert is one operation.

---

## Phase 5 — Documentation + theme override deprecation

**Final pass.**

1. Update `references/layered-architecture.md` in `wp-plugin-development` skill with the new src/ layout.
2. Update CLAUDE.md to describe the v2 structure.
3. Add `audit/qa-index.json` entry for primitives layer (so future sessions know it exists).
4. Add the migration map (v1 → v2 token names + class names) to the public docs site for theme authors.
5. Plan the v1.0.6 release that removes the theme-override backward-compat layer.

**Effort:** half day.

---

## Total timeline

| Phase | Duration | Cumulative |
|---|---|---|
| 0 — Tokens scaffold | 1 day | 1 day |
| 1 — Primitives layer | 2-3 days | 3-4 days |
| 2 — 16 block migrations | 2-3 weeks | 17-25 days |
| 3 — Directory restructure | 1-2 days | 18-27 days |
| 4 — Cleanup | 1-2 days | 19-29 days |
| 5 — Docs | 0.5 day | 19.5-29.5 days |

**Range: 3-4 weeks of focused work, single-developer pace.**

This replaces an estimated 6+ months of patching the existing organization as new features ship.

---

## Cross-cutting concerns

### Tests

- Every block's regression journey runs after that block's migration PR.
- Full smoke walk runs after every 3 block migrations (cumulative regression detection).
- WPCS + PHPStan run on every PR (already in pre-push hook).
- Architecture invariants 12/12 run on every PR (already in pre-push hook).

### Communication

- Each PR has a description summarizing: blocks touched, token replacements, primitive adoptions, line count delta.
- CHANGELOG accumulates each migration as a single bullet under a "Refactor" header.
- v1.0.5 ships when Phase 3 + 4 complete. v1.0.6 removes backward-compat in Phase 5.

### What blocks customer-feature work

Phase 2 (block-by-block) is the longest phase but each migration is small. New features can pause + resume between blocks. The refactor doesn't fully block feature development — it serializes work on the same block (don't migrate listing-card while someone else is adding a new feature to it).

Recommended scheduling: pause new-feature work on the block being migrated for that PR's lifetime (typically 1 day). Other blocks remain available for feature work.
