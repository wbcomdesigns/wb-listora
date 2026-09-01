# Currency & Money Refactor — minor-units, currency-aware, no truncation

> **Status:** PLANNED (not started). Author pass: 2026-07-25.
> **Owner decision still required** before Phase 3 — see "Open decisions".
> This plan is resumable: each phase lists exact files, the reuse rule (no dups),
> and the dead code to remove. Do phases in order; each ends green + verified.

## SDK AUDIT (2026-07-25) — the fix already exists upstream; DO NOT build a currency lib in Listora

Audited the standalone SDK repo `github.com/vapvarun/wbcom-credits-sdk` (master, **1.4.2**, Money class `@since 1.5.0`). **The SDK already solves this correctly** — better than the Eventonomy/WPSS references (which had real bugs, now filed on their boards):

- `src/Money.php` — currency-aware converter: `decimals_for()` (0/2/3 via ZERO_DECIMAL + THREE_DECIMAL lists + `wbcom_credits_currency_decimals` filter), `to_minor()` = `(int) round($major * 10^decimals)` (rounds, documents the `147.35*100=14734.999` float trap), `to_major()`. Correct.
- Ledger `amount INT` is deliberate (append-only integers can't drift). Design contract: money consumers store **minor units**, opt-in at their own boundary, SDK never auto-converts existing rows.
- `Credits::topup/deduct/refund` all `abs()`-guard the sign. Gateways speak `amount_cents` natively (Stripe returns cents). `Abstract_Gateway` refund prorates in minor-unit space, clamps `min(refund, orig - refunded_so_far)`, guards `<=0` — **no ×100 bug** (unlike Eventonomy).

**Root cause of Listora's fractional-credit bug (confirmed):** Listora **bundles an OLD SDK with NO `Money.php`** (`wb-listora/libs/wbcom-credits-sdk` — `ls src/Money.php` → absent), and its consumer code casts `(int) $amount`. So Listora never had the converter and mixes/truncates at its boundaries.

**Revised approach — SDK-first, no new Listora currency lib:**
1. **Update Listora's bundled SDK** to the master/1.4.x line that includes `Money.php` (+ abs-guarded refund + minor-unit gateways). This is the git-committed `libs/` copy per Free's SDK bundling convention.
2. **Make Listora a money-mode consumer**: convert at every Listora boundary via `\Wbcom\Credits\Money::to_minor($amount, $currency)`, store minor units, display via `Money::to_major()`. Fixes the `(int)` truncation using the SDK's own correct helper.
3. Supersedes the "build a `listora_*` currency library" phases below — those only apply if the SDK cannot be updated. Keep them as fallback.

**SDK improvement to raise with the SDK team (separate from Listora work):** money-safety is opt-in at every consumer boundary with **no enforcement and no first-class "money mode."** A money consumer with several entry points (admin-add, its webhook, payment adapters, gateways) must remember to convert at ALL of them; miss one and the ledger silently mixes units → wrong balance. That is exactly Listora's failure mode, and the `Adapters/*` top up a computed integer with no currency awareness. Recommended SDK enhancement: a per-consumer `'money' => ['currency' => 'USD'|callback]` registration flag so the SDK's own admin/REST/gateway/adapter surfaces convert via `Money` automatically (and a `topup_money($major, $currency)` variant), turning the opt-in contract into an enforced one.

## Context — why this exists

A **verified money-flow hunt** (executed live, not a scan) confirmed two real problems in Listora's money handling:

1. **Fractional credits are silently truncated (CONFIRMED, reproduced).**
   `Credit_System::add_credits()` casts `(int) $amount`. Live repro:
   - `add_credits(0.5)` → **0 credits** granted, HTTP 200 "success", phantom 0-amount ledger row. The `/credits/admin-add` REST schema even advertises `minimum:0.01`.
   - Webhook `$9.99 × rate 1.0` → response/`payments` row/fired event all say `9.99`, but the **ledger moves only 9**. Records desync from the ledger on every fractional payment.
2. **No currency-decimal awareness.** Only a currency *code* (`currency => USD`) is stored; `wb_listora_format_currency()` hardcodes 2 decimals (`floor($amount) ? 0 : 2`). Wrong for JPY (0-decimal), KWD/BHD (3-decimal). `payments.amount` is `decimal(10,2)`; `credit_ledger.amount` is `int`.

This is a **global plugin** — site owners pick any currency (0/2/3 minor digits) and any `credit_rate`. Integer-credit + 2-decimal assumptions lose real money.

## Reference pattern — copy the good, avoid the traps

We audited two in-house references at their current branches (both mirror WooCommerce). **Verified hunts found real bugs in BOTH** (filed on their Basecamp boards 2026-07-25), so copy selectively:

| Component | Source to copy | Verdict |
|---|---|---|
| ISO 4217 currency registry (code → name/symbol/decimals) | **Eventonomy** `includes/Support/Currencies.php` (full 148-currency set + THREE_DECIMAL list) | ✅ correct — copy |
| Forward converter `*_to_minor()` = `(int) round($major * 10 ** $decimals)` | Eventonomy `evnm_pro_to_minor` / WPSS `wpss_amount_to_minor_units` | ✅ verified correct (rounds, not truncates) — copy |
| `format_money()` currency-aware display | either | ✅ correct — copy |
| `amounts_match($a,$b,$cur)` via minor units (no float epsilon) | **WPSS** `wpss_amounts_match` | ✅ correct — copy (use for webhook "did they pay what we expected") |
| `minor_to_*()` back-converters | — | ❌ **DO NOT COPY** — Eventonomy's hardcode `÷100` (JPY 1250→'13'). Rebuild using the currency's real decimals. |
| Money storage as `decimal(x,2)` | — | ❌ **DO NOT COPY** — WPSS `decimal(10,2)` + Eventonomy `decimal(12,2)` both lose 3-decimal money. Store **integer minor units**. |
| Refund conversion | — | ❌ **DO NOT COPY** — Eventonomy's refund hardcodes `×100` (100× over-refund on JPY). Convert via `to_minor()`, clamp in minor-unit space. |
| Blanket `round(…, 2)` | — | ❌ **DO NOT COPY** — round to `currency_decimals($cur)`. |

**One-line principle (WooCommerce):** store & compare money as **integer minor units**; keep decimals **currency-aware**; **round exactly once at the boundary**; never `(int)` truncate, never hardcode `× 100`.

## Target architecture

- **One canonical currency source** lives in the shared **Wbcom Credits SDK** (`wb-listora/libs/wbcom-credits-sdk`) so Listora — and every other SDK consumer — shares it (no third private copy; that drift is the exact mistake both references warn about). If the SDK is the wrong home per portfolio policy, fall back to a single `includes/support/class-currencies.php` in Free, exposed via helper functions.
- Public helpers (mirror the `evnm_*`/`wpss_*` names, Listora-prefixed):
  - `listora_currency_decimals( $code = '' ): int`
  - `listora_to_minor( float $major, $code = '' ): int`
  - `listora_from_minor( int $minor, $code = '' ): float`
  - `listora_amounts_match( float $a, float $b, $code = '' ): bool`
  - `listora_format_money( $amount, array $args = [] ): string`  (replaces `wb_listora_format_currency`)
  - all filterable (`wb_listora_currency_registry`, `wb_listora_currency_decimals`).
- **Money is stored/compared as integer minor units** end to end (payments + credit ledger).

## Work breakdown (phased — each phase green before the next)

### Phase 1 — Shared currency library (additive, zero behaviour change)
- Add the currency registry + helpers (in the SDK; else Free `includes/support/`).
- Port Eventonomy's registry data + THREE_DECIMAL / zero-decimal lists.
- Reuse: NONE exists yet — this is the single source. **No dups:** delete nothing yet; later phases route callers here.
- Verify: unit-test `to_minor`/`from_minor`/`decimals`/`amounts_match` for USD(2)/JPY(0)/KWD(3) incl. round-not-truncate (2.675→268) and the `0.1+0.2==0.3` case.

### Phase 2 — Payments stored in minor units
- `payments.amount` (+ tax/discount/refund cols) `decimal(10,2)` → **`bigint` minor units** (or `decimal(14,4)` if a smaller change is preferred short-term; minor-units is the target).
- Writers/readers convert at the edge via the Phase-1 helpers.
- Dead code: remove ad-hoc `number_format(...,2)` money math in the payments path.
- DB version bump (see Phase 6).

### Phase 3 — Move the ledger to the token model (Decision #1 is now made)
- Remove `(int) $amount` truncation in `Credit_System::add_credits()`.
- Webhook `class-webhook-receiver.php`: `$credits = listora_to_minor( $amount * $rate, $currency )` logic (round via currency decimals), and make the ledger row, `payments` record, fired `wb_listora_pro_credits_added` event, and REST response all use the SAME value (kills the desync).
- `/credits/admin-add` REST schema: make it honest to the chosen model (integer credits, or decimal-with-currency-precision — see decision).
- Verify (live, with ledger before/after): `admin-add 0.5` and webhook `9.99` no longer lose value and all records agree.

### Phase 4 — Refunds + payment verification
- Any gateway/refund amount → convert via `listora_to_minor()`, **clamp in minor-unit space** (never `× 100`).
- Replace payment "did they pay what we expected" checks with `listora_amounts_match()`.
- Harden the SDK `Credits::refund()` sign check (negative amount currently → positive grant; REST blocks it today but the SDK primitive shouldn't).

### Phase 5 — Display
- Replace `wb_listora_format_currency()` (hardcoded `?0:2`) with `listora_format_money()` everywhere money renders (grep: `wb_listora_format_currency`, scattered `number_format(...,2)`, price/credit templates + JS surfaces).

### Phase 6 — Migration + versioning
- One idempotent migration: convert existing `decimal` balances/amounts → minor units (existing integer credits become `× 10^decimals` at the store currency, or `x.00` if kept decimal — decide with the model). Guard so it runs once.
- Bump `Activator::DB_VERSION` (Free) + `Pro_Migrator::DB_VERSION` as touched. Per production rule #4, schema changes are minor-release minimum.

### Phase 7 — Tests + QA
- Port the reference test shapes (Eventonomy/WPSS) for the helpers.
- Add regression journeys: fractional top-up, JPY end-to-end (no `.00`, no ×100), KWD 3-decimal round-trip, webhook idempotency + `amounts_match`, refund in minor units.
- Re-run `/wp-plugin-smoke combo` before tagging.

## Open decisions

1. **What is a "credit"? — DECIDED 2026-08-21: (B) abstract token, admin-set rate.**

   A credit is a **token**, not a unit of currency. The site owner decides the exchange
   rate: 10 USD may buy 1000 credits, or 100, or 10. **Default 1:1**, so an owner who
   never touches the setting sees today's behaviour.

   The setting already exists — `wb_listora_pro_credit_rate` ("Credits per 1 {CURRENCY}",
   default `1.0`, Pro credit settings). What does not yet match the decision is the
   ledger: Listora registers **money mode** with the SDK, whose stated contract is the
   opposite — `wb-listora.php` says "A Listora credit IS a unit of the store currency".
   Both cannot be true. They coincide today only because the default rate is 1.0.

   Demonstrated: with `credit_rate = 100`, a 10 USD payment resolves to 1000 credits,
   then `Credits::award()` routes through money mode's `to_minor()` and writes **100000**
   to the ledger. The number a member sees is right; the stored meaning is not — under
   money-mode semantics 100000 reads as 1000.00 of store currency for a 10 USD payment.

   **What (B) requires:** Listora becomes a TOKEN consumer (no `money` registration), the
   ledger holds integer credits, money stays in `payments` / gateway `price_cents`, and
   `credit_rate` is the single conversion boundary applied on every amount-based
   acquisition path. Explicit per-product grants (the Woo mapping "this product = 50
   credits") stay explicit and correctly bypass the rate — they are already a credit
   figure, not an amount.

   **What it costs:** 31 `_money()` call sites across Free and Pro, and every existing
   ledger row is in minor units. Flipping the registration without migrating turns a
   member's 50 credits into 5000. That is Phase 3 + Phase 6 below, and it is a live-data
   migration — not a config change.

   *(Superseded recommendation: (A) value-proxy. Recorded so the reasoning is not
   re-litigated — the owner chose the token model deliberately.)*
2. **Where does the currency library live** — shared Credits SDK (preferred, single-source for the portfolio) or Free-local? Affects other SDK consumers.

## Resume checklist (update as you go)

- [ ] Phase 1 — currency lib + helpers + tests
- [ ] Phase 2 — payments → minor units + migration
- [ ] Phase 3 — credit conversion fix (after Open decision #1)
- [ ] Phase 4 — refunds + amounts_match + SDK refund sign guard
- [ ] Phase 5 — format_money everywhere
- [ ] Phase 6 — migration + DB version bump
- [ ] Phase 7 — tests + journeys + combo smoke

## Evidence / cross-refs
- Listora confirmed bug: reproduced this session (`add_credits(0.5)`→0, `7.8`→7); webhook `9.99`→ledger 9.
- Reference bugs filed 2026-07-25: Eventonomy Basecamp (CRITICAL refund ×100; money-model 2-decimal-only); WP Sell Services Basecamp (decimal(10,2) 3-decimal loss; commission round(,2)).
- Pattern anchor: WooCommerce `wc_get_price_decimals` / `wc_add_number_precision` / `wc_remove_number_precision` / `wc_price`.
