# plan/

Human-authored plans for work that is **still in flight**. Team-internal, never shipped to users.

Architecture lives in [`audit/`](../audit/). QA lives in [`docs/qa/`](../docs/qa/). Customer
documentation lives in [`docs/`](../docs/). Nothing internal belongs in `docs/`.

## Retention rule — read this before adding a file

**A plan is deleted when its wave ships.** Not archived, not moved to `archive/` — deleted.
Git history and the `v*` tags keep every version, so nothing is lost.

The reason is agent time. A shipped plan reads exactly like an open one: an agent that greps
`plan/` re-plans work that already exists, or "fixes" something the plan describes as pending
when it landed three releases ago. A directory of 100 shipped plans is worse than no directory.

If a plan describes work that is genuinely still open, it stays — and it says so at the top,
with a date.

## What's here

| File / folder | Purpose |
|---|---|
| [`HANDOFF-2026-08-09-business-hours.md`](HANDOFF-2026-08-09-business-hours.md) | Open handoff — business-hours work |
| [`HANDOFF-2026-08-10-rft-sweep.md`](HANDOFF-2026-08-10-rft-sweep.md) | Open handoff — ready-for-testing sweep |
| [`currency-money-refactor.md`](currency-money-refactor.md) | In-progress refactor against the portfolio money-journey standard |
| [`app-parity.md`](app-parity.md) / [`app-parity.html`](app-parity.html) | Plugin + app feature parity board — done / pending / skipped in one view |
| [`100k-readiness/POINTER.md`](100k-readiness/POINTER.md) | Pointer to the consolidated 100K-readiness plan in the Pro repo |

## Plan conventions

```
# Feature name

## Goal          One sentence — what problem this solves.
## Scope         What's in / out for this iteration.
## Data          Tables / options / meta involved.
## UX            Entry, states (empty/loading/error/success/mobile), key interactions.
## Acceptance    Bullets — convert to PHPUnit tests before coding.
## Risks
```
