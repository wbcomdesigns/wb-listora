#!/usr/bin/env python3
"""
Smoke-coverage gate. Decides whether a smoke report proves the walk LOOKED,
not merely that it found nothing.

Usage:  smoke-coverage-gate.py <report.json> <release-version>
Prints one problem per line and exits 1 when the report fails the gate.
Silent + exit 0 when it passes.

--------------------------------------------------------------------------
Why this exists, and why it is not a ratio any more
--------------------------------------------------------------------------
The 2026-08-11 combo walk finished 14 passed / 3 failed / 133 SKIPPED - about
10% coverage, with sections B and F executing zero rows. `build-release.sh`
checked only `failures` and `debug_log_issues`, so once those three failures
were fixed that walk would have opened the release gate having exercised
neither upgrade nor cross-browser.

v1 of this gate was a blanket ratio: fail when skipped > executed. It caught
that, but it had a design flaw worth recording, because it would have bitten
every future release:

    Section D grows by a row on EVERY bug fix, forever. 1.5.0 alone added 11.

So a ratio gets harder to satisfy each release purely because the regression
suite got BETTER, and the pressure that creates is toward --skip-browser-smoke,
which is strictly worse than an honest partial walk. A gate people route around
protects nothing.

v2 therefore targets risk where it actually lives - the code that changed this
release - instead of penalising accumulated history:

  1. no section may execute zero rows      (the original failure mode)
  2. every [CORE] row must run             (the must-run set, named)
  3. every D row added for THIS release    (guards what changed)
  4. an absolute floor                     (catches a degenerate walk)

Rule 3 is the important one: a release's own regression rows are the guards
written for the bugs that release fixed, so skipping them is skipping the only
evidence that this build is better than the last.
"""

import json
import sys

FLOOR_PERCENT = 15.0


def main():
    if len(sys.argv) < 3:
        print("usage: smoke-coverage-gate.py <report.json> <release-version>")
        return 2

    report_path, version = sys.argv[1], sys.argv[2]

    try:
        with open(report_path) as fh:
            report = json.load(fh)
    except Exception as exc:  # noqa: BLE001 - any read/parse failure is a gate failure
        print(f"smoke report is not valid JSON: {exc}")
        return 1

    problems = []

    sections = report.get("sections") or {}
    if not sections:
        print("smoke report has no sections{} - cannot prove coverage")
        return 1

    executed = skipped = 0
    empty = []
    for name, counts in sections.items():
        if not isinstance(counts, dict):
            continue
        passed = int(counts.get("pass", 0) or 0)
        failed = int(counts.get("fail", 0) or 0)
        skip = int(counts.get("skipped", 0) or 0)
        executed += passed + failed
        skipped += skip
        if passed + failed == 0 and skip > 0:
            empty.append(f"{name} ({skip} skipped, 0 executed)")

    # 1. No section may execute zero rows.
    if empty:
        problems.append("these sections executed nothing: " + "; ".join(empty))

    # 2. Every [CORE] row must run.
    core = report.get("core_rows")
    if not core:
        problems.append(
            "smoke report has no core_rows{} - cannot prove the [CORE] set ran"
        )
    else:
        missed = core.get("not_executed") or []
        if missed:
            problems.append(
                f"{len(missed)} [CORE] row(s) never ran: "
                + "; ".join(str(m)[:80] for m in missed)
            )

    # 3. Every D row added for THIS release must run.
    release_rows = report.get("release_d_rows")
    if not release_rows:
        problems.append(
            f"smoke report has no release_d_rows{{}} - cannot prove the D rows "
            f"added in {version} ran. These guard the bugs {version} fixed."
        )
    else:
        d_missed = release_rows.get("not_executed") or []
        if d_missed:
            problems.append(
                f"D rows added in {version} that never ran: "
                + "; ".join(map(str, d_missed))
            )

    # 4. Absolute floor, to catch a degenerate walk.
    total = executed + skipped
    if not executed:
        problems.append("no rows executed at all")
    elif total and (100.0 * executed / total) < FLOOR_PERCENT:
        problems.append(
            f"only {executed} of {total} rows ran "
            f"({100.0 * executed / total:.0f}%) - below the {FLOOR_PERCENT:.0f}% floor"
        )

    for problem in problems:
        print(problem)

    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
