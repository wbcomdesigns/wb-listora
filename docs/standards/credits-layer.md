# Credits layer (Free and Pro)

How Listora reaches the bundled Credits SDK. Normative; `bin/audit-guardrails.sh` G19 enforces the ratchet.

1. **Spend** only through `wb_listora_charge()` (`Core\Charge`): hold, work, settle that hold by id, cancel by id on failure, all under `wb_listora_with_credits_lock()` (the SDK's own per-user lock).
2. **Everything else** through `includes/credits-helpers.php`: `wb_listora_credit_balance()`, `_currency()`, `_decimals()`, `_to_major()`, `_to_minor()`, `_ledger_count()`, `_ledger_page()`, `_ledger_rows()`, `_ledger_row()`, `_gateways()`, `_adapters()`. The slug is `WB_LISTORA_CREDITS_SLUG`; a bare `'wb-listora'` passed to the SDK is a bug.
3. **Units:** the ledger stores integer MINOR units; features use MAJOR via the helpers. Compare amounts in minor integers, never floats.
4. **Pro never names an SDK class** for anything the helpers cover. New Pro code calls the helpers; if one is missing, add it to Free first.
5. **No SQL on the ledger table** where `query_ledger()` / `get_ledger_row()` answer it. Shapes the SDK cannot answer yet (id cursors, id lists, entry-type lists) wait for SDK 1.14.0, then become `wb_listora_credit_ledger_rows()` calls.
6. **Returning credits for an item** is `Credits::credit()`, never `refund()`: `refund()` releases whatever hold is open on the item.
7. **Readiness:** `wb_listora_credits_ready()` checks every SDK method Listora calls through `Credits::sdk_ready()`. Add a method to that list in the same change that first calls it.

## Remaining debt (baseline `bin/credits-layer-baseline.txt`)

Direct SDK use left in Pro write paths (top-up, adjust, refund controllers, gateway settings, receipts, transactions page) and five ledger query shapes. Both shrink when SDK 1.14.0 is bundled; the baseline may only go down.
