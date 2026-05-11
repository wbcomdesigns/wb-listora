# Frontend Refactor — Approval Summary

**Status:** PLAN. No code touched. This page is what you read to approve the refactor and trigger Phase 0 execution.

---

## What we're building toward

A frontend where:

1. **Every visual property has ONE token.** Not three competing scales. `--listora-space-{1..12}` for spacing, `--listora-text-size-{xs..4xl}` for type, `--listora-fg-{default,strong,muted,faint,inverse,accent,danger,success,warning}` for color. Done.

2. **Every block uses the SAME primitives.** Not 16 different card classes. Not 5 different empty-state implementations. One `.listora-card`, one `.listora-empty`, one `.listora-form-field`, one `.listora-modal`, one `.listora-tabs`.

3. **Files live where the customer's mental model puts them.** `src/blocks/discovery/card/`, `src/blocks/member/dashboard/`, `src/blocks/submission/wizard/`. Not a flat `src/blocks/listing-card/` next to `src/blocks/user-dashboard/`.

4. **Refactor is reversible.** v1 tokens live alongside v2 until each block migrates. Each block is a separate PR. Each PR's regression journey + smoke walk validates before the next.

---

## What's in the 7 plan docs

| Doc | What it does |
|---|---|
| `00-target-architecture.md` | Defines the v2 directory shape + guiding principles |
| `01-token-system-v2.md` | Every canonical token name with v1→v2 migration table |
| `02-primitive-layer-v2.md` | API + variants + a11y wiring for each of 11 primitives |
| `03-directory-structure.md` | File-by-file move plan (theme override compat preserved) |
| `04-migration-phases.md` | 5 phases over 3-4 weeks. Block-by-block. Reversible. |
| `05-decisions.md` | 8 architectural decisions resolved upfront so execution doesn't stall |
| `99-refactor-summary.md` | This page |

---

## Key resolved decisions

1. **`.listora-card` is canonical.** `.listora-ui-card` dies. Every block uses `.listora-card`.
2. **`--listora-text-*` namespace splits.** Size → `--listora-text-size-*`. Color → `--listora-fg-*`.
3. **Dark mode = theme's responsibility for v1.0.5.** Revisit in v1.1.
4. **Inline `<script>` in PHP = banned.** Two current violations extract to enqueued files.
5. **Block names stay flat (`listora/listing-card`); directories go grouped.** Customer API preserved.
6. **Pro has no `src/shared/`** — imports from Free across plugin boundary at build time.
7. **Theme override paths supported for 1 release** with `_doing_it_wrong` notice, removed in v1.0.6.
8. **FSE + classic templates both ship for v1.0.5.** Classic deprecates in v1.1.

---

## Effort + outcome

**3-4 weeks of focused work, single-developer pace.**

| Outcome | Status before | Status after |
|---|---|---|
| Spacing tokens | 2 systems (gap-named + space-numeric) | 1 canonical `--listora-space-{1..12}` |
| Typography tokens | 2 systems + overloaded color/size namespace | 2 separated namespaces (`--listora-text-size-*` + `--listora-fg-*`) |
| Color aliases | `--listora-error-*` AND `--listora-danger-*` (same hex) | `--listora-danger-*` only |
| Card primitive | `.listora-ui-card` (0 uses) + 6 block-local card classes | `.listora-card` canonical, 0 block-local |
| Empty state primitive | 3 of 11 blocks use canonical | every block uses canonical |
| Form field primitive | (does not exist — each form inlines its own) | `.listora-form-field` canonical |
| Modal primitive | 3 modals inlined in listing-detail render.php | `.listora-modal` canonical, all 4 modals consume |
| Tabs primitive | listing-detail tabs.php duplicates ARIA wiring | `.listora-tabs` canonical with PHP helper that emits correct ARIA |
| Per-block style.css avg size | 290 lines | ~150 lines (50% reduction estimated) |
| Hardcoded hex literals | 233 across 16 blocks | 0 (all → tokens) |
| Hardcoded `px` >2px | 132 across 11 Free blocks | 0 (all → tokens) |
| Inline `<script>` in PHP | 2 (listing-detail + Pro comparison) | 0 |
| `src/` directory shape | Flat (`src/blocks/{slug}/`) | Grouped (`src/blocks/{group}/{slug}/`) |
| Templates location | Flat (`templates/blocks/{slug}/`) | Grouped (`templates/blocks/{group}/{slug}/`) |
| user-dashboard total lines | 3,746 (largest in plugin) | ~1,800 (~50% reduction via primitives + tab template splits) |
| listing-detail total lines | 2,642 | ~1,500 (modals + tabs split + inline JS removed) |
| Theme override paths | one set | dual paths supported through v1.0.5 |

---

## Risk analysis

### Risk 1 — Phase 2 block migrations introduce regressions
**Mitigation:** every migration PR runs the block's regression journey + smoke walk. PRs that fail journeys don't merge. We've shipped 9 regression journeys + 22 customer journeys + 22 Pro journeys — coverage is high.

### Risk 2 — Theme overrides break for customers using them
**Mitigation:** dual-path lookup for one full release. `_doing_it_wrong` notice tells customers to rename. Documented in release notes for v1.0.5.

### Risk 3 — IAPI selectors break when class names change
**Mitigation:** IAPI directives bind to classes like `.listora-detail__tab` — primitives RENAME but the BLOCK still owns its data-wp-on--click bindings. The primitive class change is purely visual; the JS wiring stays on the block-local class root.

Wait — this isn't quite right. If `.listora-detail__tab` becomes `.listora-tabs__tab` via primitive adoption, IAPI selectors must update. We have two options:
- (a) Keep block-specific class as a SECONDARY class: `<button class="listora-tabs__tab listora-detail__tab">`. IAPI binds to `.listora-detail__tab`. Primitive styles apply via `.listora-tabs__tab`.
- (b) Migrate IAPI selectors in lockstep with template migration.

(a) is cleaner. We'll dual-class during transition.

### Risk 4 — Build pipeline doesn't handle grouped src/blocks/ tree
**Mitigation:** verify in Phase 0. `@wordpress/scripts` recursively walks src/blocks/**/block.json by default. If it doesn't, update webpack config. Either way it's a 10-minute fix.

### Risk 5 — Refactor stalls mid-execution, leaving the codebase in a worse half-migrated state
**Mitigation:** every phase ends with all journeys + smoke green. If we have to pause after Phase 2 block 7 of 16, the migrated blocks are clean v2, the unmigrated blocks are clean v1, both work. Worst case we ship v1.0.5 with 7-of-16 migrated and tackle the rest in v1.0.6.

---

## What needs your approval

Three approval gates before execution:

**[ ] Approval 1: Architecture + plan**
You've reviewed `00-target-architecture.md` + `01-token-system-v2.md` + `02-primitive-layer-v2.md` and agree with the v2 shape.

**[ ] Approval 2: Migration phases**
You've reviewed `04-migration-phases.md` and agree with the phasing + reversibility design.

**[ ] Approval 3: Decisions**
You've reviewed `05-decisions.md` and agree with the 8 resolved decisions.

When all 3 are checked, Phase 0 starts: create `src/tokens/` + populate the 7 token files + build pipeline updates. One day of work. Committed as `refactor: Phase 0 — v2 tokens scaffold (parallel with v1, no breaking changes)`.

---

## Decision needed from you NOW (before Phase 0)

Read this summary + the 5 supporting docs. Tell me:

- ✓ "Approved, start Phase 0" → I execute immediately.
- "Change X" → I revise the plan, push, re-ask.
- "Defer the refactor" → we don't start; existing audit findings stay open.

The plan is honest about scope (3-4 weeks). It's honest about what doesn't change (block names, REST paths, IAPI namespace). It's honest about reversibility (every phase is its own PR with green-gate journey + smoke).
