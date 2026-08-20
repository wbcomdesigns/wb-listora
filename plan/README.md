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
| [`automation-integration-surface.md`](automation-integration-surface.md) | **Open design, retargeted 1.7.0.** The triggers half shipped in 1.6.0; the actions half, its auth model and the discovery endpoint are what remain. Pro points here. |
| [`currency-money-refactor.md`](currency-money-refactor.md) | **Open.** In-progress refactor against the portfolio money-journey standard. Gated on owner decision #1. |
| [`app-parity.md`](app-parity.md) / [`app-parity.html`](app-parity.html) | Living plugin + app parity board - done / pending / skipped in one view. Last verified against 1.5.0; a 1.6.0 pass is owed. |
| [`HANDOFF-2026-08-09-business-hours.md`](HANDOFF-2026-08-09-business-hours.md) | Open handoff - business-hours work. State pinned at branch `1.4.2`. |
| [`HANDOFF-2026-08-11-rft-sweep-complete.md`](HANDOFF-2026-08-11-rft-sweep-complete.md) | Open handoff - RFT sweep finished; carries every open item forward. State pinned at branch `1.4.2`. |
| [`100k-readiness/POINTER.md`](100k-readiness/POINTER.md) | Pointer to the consolidated 100K-readiness plan in the Pro repo. |

Deleted on 2026-08-20 under the retention rule above, when 1.6.0 shipped:
`1.6.0-flow-remediation/` (all seven flows landed, detectors G5-G10 live) and
`2026-08-15-automation-triggers.md` (all 11 tasks verified against the code before
deletion). `git log -- includes/automation/` and the `v1.6.0` tag are the record.

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
