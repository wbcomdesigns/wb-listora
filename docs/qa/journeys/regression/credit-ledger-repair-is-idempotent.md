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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# Running the ledger repair twice must not pay twice

`wp listora repair-credit-ledger` compensates members whose top-ups landed in minor units (a 50-credit purchase that credited 0.50). It APPENDS a correcting adjustment per member; it never rewrites history and never claws back. That makes **re-running it** the whole risk: a double run over-pays every member who was already made whole, with no signal that it happened.

Two failures were caught building it and are asserted forever:

1. **Detection must be complete.** Rows are detected by note text (`Credits from WooCommerce order`, `... WooCommerce Membership`, `... subscription`, `... PMPro`, `... MemberPress`) AND by amount: `amount < 1 whole credit (factor)` OR `amount equals a configured pack size` (pack sizes come from the SDK mappings plus the `wb_listora_credit_pack_sizes` filter).
2. **The settled-ID guard must run before the query.** Each adjustment's note carries the ledger IDs it settled (`Minor-units repair: rows 165 - crediting the shortfall ...`); those IDs are parsed back and excluded.

Single-role journey: WP-CLI as an administrator; the member-facing result is the balance on their dashboard Credits tab. Another member and logged out never see a repair (no UI).

## Setup

- Monetization ON, credits in money mode, currency exponent 100 (`wp listora repair-credit-ledger` otherwise says "This site does not run the ledger in money mode..." or "Store currency X has no minor unit (factor 1)...").
- A throwaway member `qa-j1-ledger` (`wp user create qa-j1-ledger qa-j1-ledger@example.test --role=subscriber`).
- Seed the pre-fix shape THROUGH THE REAL TOP-UP PATH, not by writing ledger rows: a raw `Credits::topup()` writes unscaled minor units, exactly the old bug:
  ```bash
  wp eval '$u=get_user_by("login","qa-j1-ledger")->ID; \Wbcom\Credits\Credits::topup("wb-listora",$u,50,"Credits from WooCommerce order #QA-J1-1");'
  ```
  Seed a small (50), a large and a filter-added pack size the same way (add the size through `wb_listora_credit_pack_sizes` in an mu-plugin to test the filter).

## Steps

### 1. Record the truth before touching anything
- **Action**: `wp listora repair-credit-ledger --format=csv > before.csv` (dry run is the default); note each affected member's balance (`Credits::get_balance`).
- **Expect** (QA site, with the one seeded row): `ledger_id,user,credited,should_be,note,date` row `165,qa-j1-ledger,0.5,50,Credits from WooCommerce order #QA-J1-1,...`, then "1 purchase(s) across 1 member(s) were under-credited. Total owed: 49.5 USD." and "Dry run - nothing was written. Re-run with --execute to credit the difference."

### 2. Every broken purchase is detected, not just the familiar sizes
- **Fails if** the dry run reports fewer rows than were seeded (a missed row is a member who stays short while the command reports success).
- **Note**: a top-up of 250 minor units (a "2.50" row, not under 1 credit and not a configured pack size) is NOT detected by design; only amounts under one whole credit or equal to a configured pack size are. Seed realistic broken rows.
- The unmatched-note warning ("N top-up(s) look unscaled but match no known payment-source note...") lists rows that look unscaled but whose note matches nothing; one such row pre-exists on the QA site and is a baseline.

### 3. Apply
- **Action**: `wp listora repair-credit-ledger --execute`
- **Expect**: "Members credited: 1" and "Success: Repair complete. Original rows were left untouched; each correction is a new, auditable adjustment." Balance rose from 0.50 to 50.00 (5000 minor units: the original 50 plus an adjustment of 4950).

### 4. Apply again (the actual assertion)
- **Action**: `wp listora repair-credit-ledger --execute`
- **Expect**: "Note: 1 repair adjustment(s) already exist on this site." then "Success: No under-credited purchases found." Balance unchanged, no new ledger row.
- **Fails if** any balance changes or any new adjustment row is written.

### 5. Nothing was rewritten or reversed
- **Expect**: the original row (`id 165`, amount 50, note unchanged) is still present; row count went up by exactly the number of adjustments; no historical amount changed. The adjustment's note starts `Minor-units repair: rows <ids>`.

### 6. A partially-repaired ledger converges
- **Action**: seed one further broken purchase, run `--execute` again.
- **Expect**: only the new row is paid; earlier IDs are skipped.

### 7. Positive control
- **Control**: step 1 detecting the seeded row is the positive control for step 4's "none found": without step 1 returning a row, step 4 passing would mean nothing.

## Cleanup

Delete the throwaway user and its ledger rows (`DELETE FROM {prefix}listora_credit_ledger WHERE user_id = <id>`), and remove the test mu-plugin.

## Test-data trap

The guard reads the ledger note of prior adjustments. A fixture written directly into the ledger without the command's note makes run two look idempotent for the wrong reason. Drive the fixture through the real top-up path and assert the adjustment note contains the settled IDs before trusting step 4.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Second run pays again | settled-ID parse after the query / regex | `includes/class-cli-commands.php:1243-1270` |
| A seeded row not detected | pack sizes / note fragments | `includes/class-cli-commands.php:1228-1245`, `configured_pack_sizes()` and the `wb_listora_credit_pack_sizes` filter at `:1486` |
| Command not found | subcommand dropped | `includes/class-cli-commands.php:1203` (`@subcommand repair-credit-ledger`) |

## Verified

Steps 1, 3, 4 and 5 were run live on 2026-10-12 with a throwaway member and a raw `Credits::topup()` of 50 (one pack size, WooCommerce note): dry run listed 1 row, `--execute` credited 49.50, the second `--execute` found none and left the balance at 52.50 (50 + 2.50 seeded second row), and the ledger held the 2 originals plus 1 adjustment. The throwaway member and its ledger rows were deleted. Steps 2 (large and filter-added sizes) and 6 were NOT run.
