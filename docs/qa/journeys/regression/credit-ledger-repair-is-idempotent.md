---
journey: credit-ledger-repair-is-idempotent
plugin: wb-listora
priority: critical
roles: [admin]
covers: [BC-10190573574, credits, money-mode, wp-cli, idempotence]
prerequisites:
  - "Monetization ON, credits in money mode, currency exponent 100"
  - "At least one member whose ledger holds a minor-units top-up (the pre-fix shape)"
estimated_runtime_minutes: 10
covers_card: 10190573574
---

# Running the ledger repair twice must not pay twice

`wp listora repair-credit-ledger` compensates members whose top-ups landed in
minor units — a 50-credit purchase that credited 0.50. It appends a correcting
adjustment; it never rewrites history and never claws back.

Which makes **re-running it** the whole risk. A repair that is not idempotent is
worse than the bug it fixes: the bug under-paid a few members, a
double-run over-pays every member who was already made whole, and there is no
signal that it happened.

Two failures were caught building it, both worth asserting forever:

1. **Detection was incomplete.** Note-matching only recognised the pack sizes it
   had hardcoded, so 1 of 3 seeded purchases was silently left broken. The
   command now reads configured pack sizes from the SDK mappings plus the
   `wb_listora_credit_pack_sizes` filter.
2. **The settled-ID guard did not run.** The parsing that collects
   already-settled ledger IDs sat *after* the query meant to exclude them, so
   the second run re-paid everything. Ordering, not logic.

## Steps

### 1. Record the truth before touching anything
```bash
wp listora repair-credit-ledger --format=csv > /tmp/before.csv
```
Dry run is the default. Capture each affected member's balance from the DB too.

### 2. Every broken purchase is detected, not just the familiar sizes
Seed top-ups at a **small, a large, and a filter-added** pack size.
- **Fails if** the dry run reports fewer rows than were seeded. A missed row is
  a member who stays short, and the command reports success either way.

### 3. Apply
```bash
wp listora repair-credit-ledger --execute
```
Assert each member's balance now equals the major-units figure they paid for.

### 4. Apply again — the actual assertion
```bash
wp listora repair-credit-ledger --execute
```
- **Fails if** any balance changes.
- **Fails if** any new adjustment row is written.
- The second run must report zero to repair.

### 5. Nothing was rewritten or reversed
The original (wrong) ledger rows must still be present and unmodified; the fix
is additive. Assert row count went **up** by exactly the number of adjustments
and that no historical row's amount changed.

### 6. A partially-repaired ledger converges
Repair, then seed one further broken purchase, then repair again. Only the new
one may be paid.

## Test-data trap

The settled-ID guard reads the ledger *note* of prior adjustments. A fixture
seeded by writing ledger rows directly — without the note the command writes —
makes run two look idempotent for the wrong reason: it never detected run one's
work, it just found nothing to do. Drive the fixture through the real top-up
path, and assert the adjustment note contains the settled IDs before trusting
step 4.
