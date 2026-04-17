# plans/

Internal planning + QA documents. Not shipped to end users.

**`docs/`** is reserved for customer + site-owner documentation.
**`plans/`** is for the team — specs, audits, roadmap, flow checklists.

## What's here

| File / folder | Purpose |
|---|---|
| [`QA-FLOWS.md`](QA-FLOWS.md) | Human-runnable flow test checklist. Every user/admin flow + expected states (empty/loading/error/success + 390px mobile). Run live before every release. |
| [`product-roadmap.md`](product-roadmap.md) | Long-term product direction |
| [`remaining-gaps.md`](remaining-gaps.md) | Known gaps, tech debt, deferred items |
| [`qa-code-issues.md`](qa-code-issues.md) | Code-quality issues tracked across audits |
| [`ux-audit-issues.md`](ux-audit-issues.md) | UX/visual audit findings |
| [`free/`](free/) | Per-feature specs for Free plugin features (42 docs) |
| [`pro/`](pro/) | Per-feature specs for Pro plugin features (13 docs + sprint plan) |
| [`audit/`](audit/) | Dated audit snapshots |

## Plan conventions

Each per-feature plan uses a uniform structure:

```
# Feature name

## Goal
One sentence — what user problem this solves.

## Scope
What's in / out for this iteration.

## Data
Tables / options / post meta involved.

## UX
Entry points, states (empty/loading/error/success/mobile), key interactions.

## Acceptance tests
Bullet list — convert each into a PHPUnit acceptance test before coding.

## Risks + open questions
```

When starting work on a plan:
1. Write PHPUnit acceptance tests that match the plan's "Acceptance tests" section. They should fail initially.
2. Implement until tests pass.
3. Run the plan's flow steps from `QA-FLOWS.md` live in the browser before marking done.

## Editing rules

- Don't create new top-level planning docs. Either extend an existing one or add to `free/` or `pro/`.
- Date-stamp audits (YYYY-MM-DD) and keep the previous audit for diff comparison.
- When a plan is fully shipped + verified, mark it done at the top of the file — don't delete it.
