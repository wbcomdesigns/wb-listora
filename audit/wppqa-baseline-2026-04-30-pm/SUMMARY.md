# wppqa baseline — WB Listora — 2026-04-30 (PM refresh)

Run during `/wp-plugin-onboard --refresh` (Phase 0). Three checks captured BEFORE manifest refresh, so the manifest reflects the same reality the bug-finder did.

Prior baseline: [`../wppqa-baseline-2026-04-30/SUMMARY.md`](../wppqa-baseline-2026-04-30/SUMMARY.md).

## Headline counts

| Check | Passed | Failed | Skipped | Duration |
|---|---|---|---|---|
| `plugin_dev_rules`            | 7 | 2 | 0 | 81ms |
| `rest_js_contract`            | 6 | 0 | 0 | 14ms |
| `wiring_completeness`         | 5 | 2 | 0 | 52ms |

**Total:** 18 passed / 4 failed / 0 skipped — pipeline cost ~147ms.

`rest_js_contract` is fully clean (unchanged from prior). Both PHP shape extraction and JS access scan agreed: no envelope drift on any of the 6 controllers within the 50-line proximity window.

---

## Delta vs. prior baseline (2026-04-30 AM)

| Check | Prior failed | Current failed | Delta | Notes |
|---|---|---|---|---|
| `plugin_dev_rules` | 2 | 2 | 0 | Rule 10 hit count: **1 → 1** in Free (T1 changed the *call site*, not the count — see below). Nonce-no-cap unchanged (intentional FP per T4). |
| `rest_js_contract` | 0 | 0 | 0 | Still clean. |
| `wiring_completeness` | 2 | 2 | 0 | Same two admin-only false-positives. |

### Rule 10 (`confirm()` ban) — what actually changed

| State | File:Line | Note |
|---|---|---|
| Prior (AM) | `src/interactivity/store.js:824` | A bare `window.confirm()` was the only Deactivate-path confirmation. **Real**, treated as release-blocker. |
| Current (PM) | `src/interactivity/store.js:835` | T1 (commit `f69f47f`) replaced the bare `confirm()` with the `listoraConfirm` modal helper at lines 827-833. The native `window.confirm( confirmMsg )` at line 835 is now a **defensive fallback** — only reached if `listoraConfirm` is unavailable (CSP, ad-blocker, asset 404). The wppqa Rule 10 detector matches any `confirm(` literal regardless of guard, so it still flags the fallback. |

**Classification of the new `store.js:835` finding: VERIFIED FALSE POSITIVE.**

Same shape as the previously-known Pro `dashboard-needs.js:39` defensive fallback the user has documented. Both are `window.listoraConfirm ? listoraConfirm(...) : window.confirm(...)` ternaries, where the right branch is the asset-blocker safety net the design system explicitly endorses. wppqa's pattern doesn't model the conditional. Adding it to `audit/manifest.json#/notes` per Phase 0 false-positive hygiene.

The user-described expectation ("Rule 10 hits should be down by 1") was based on the assumption that the prior baseline counted both the Free `store.js:824` AND the Pro `dashboard-needs.js:39` against the Free plugin scan. wppqa only scans the Free plugin path supplied to the tool, so the Free-side Rule 10 count was 1 prior and is 1 now — but the *site* moved (824 → 835) and is now a properly-guarded fallback rather than the only path. The bug-fix is real even though the count is unchanged.

### Nonce-no-cap (`class-pro-promotion.php:1193`) — unchanged, intentional

T4 documented in `audit/manifest.json#/notes` that this is a false positive: action is registered as `wp_ajax_wb_listora_dismiss_promo` only (no `_nopriv_` companion), so WP core gates it to logged-in users upstream. Handler sets a per-user 3-day cookie — no shared mutation. Adding `current_user_can` would over-restrict (CTA targets all logged-in users). No code change planned. Note retained in this refresh.

---

## 1. `plugin_dev_rules` — 2 high-severity errors + 15 low/medium warnings

### High-severity

| ID | File:Line | Issue | Status |
|---|---|---|---|
| PLUGIN-DEV-RULES-001 | `src/interactivity/store.js:835` | `confirm()` browser dialog — admin-ux-rulebook Rule 10 bans this | **FALSE POSITIVE — defensive fallback.** Primary path is `listoraConfirm` modal at lines 827-833. Native fallback retained per T1 commit message ("CSP/blocker scenarios"). Symmetric to Pro `dashboard-needs.js:39`. Documented in `audit/manifest.json#/notes`. |
| PLUGIN-DEV-RULES-002 | `includes/admin/class-pro-promotion.php:1193` | Nonce check without paired `current_user_can()` — security.md authorization gap | **FALSE POSITIVE — already classified by T4.** `wp_ajax_*`-only registration gates to logged-in users upstream; per-user cookie write only. Documented in `audit/manifest.json#/notes`. |

### Medium/low-severity (unchanged from prior baseline)

- **PLUGIN-DEV-RULES-003** — 8 distinct breakpoints across the plugin (1024, 1700, 480, 600, 640, 782, 900, 960px). Real breakpoint sprawl, mostly in admin CSS — consolidate during the next admin-CSS refactor.
- **PLUGIN-DEV-RULES-004 .. -017** — 14 low-severity tap-target warnings (button heights 14-34px vs the 40px minimum). All in admin CSS. Customer-touch surfaces unaffected. Defer to admin-UI consolidation sprint.

### Likely false-positive

The 2 high-severity findings above are both verified false-positives. The medium/low warnings are real but lower-priority.

---

## 2. `rest_js_contract` — 6 passed, 0 failed

No envelope-mismatch drift found. Unchanged from prior baseline.

**Caveat (heuristic):** the 50-line proximity window means any controller→JS pair where the JS handler is more than 50 lines below the URL constant gets a clean pass even if drift exists. For wb-listora, the IAPI store pattern (`src/interactivity/store.js`) and per-block view scripts mostly read responses near the URL, so the heuristic is a good fit here.

---

## 3. `wiring_completeness` — 2 high-severity errors (unchanged)

### Findings

| ID | File:Line | Setting | Classification |
|---|---|---|---|
| WIRING-001 | `includes/admin/class-listing-columns.php:291` | `listora_duplicate_filter` | **LIKELY FALSE-POSITIVE.** Admin list-table filter; reader is `class-listing-columns.php` itself via `pre_get_posts`, not a `templates/` file. The check only inspects `templates/`, so admin-only wiring is invisible to it. |
| WIRING-002 | `includes/admin/class-pro-promotion.php:468` | `license_key` | **LIKELY FALSE-POSITIVE.** License keys never have a frontend template surface; consumed by the licensing/upsell module the check doesn't traverse. |

Both findings are heuristic-driven false-positives because the check only inspects `templates/`.

---

## Findings beyond what the prior refresh knew about

The prior refresh's `static_analysis.cap_context_mismatches=0` and `dead_listeners=0` are **confirmed unchanged** — wppqa surfaced no new issues in those classes after F1 (which removed 3 typo-listeners and added 1 canonical listener) and O3 (which added a new firer for `wb_listora_map_provider`).

**No new findings since prior refresh.** The PM commits (`f69f47f` T1+T4, `0aa62ca` F1, `847dcc8` O3) introduce no new findings in any of the three checks. T1 retained the wppqa Rule 10 hit but moved it to a guarded defensive fallback (now classified as a verified false positive).

---

## Release-readiness gate

Per skill hard rule #6: any time `wppqa_audit_plugin` returns `failed > 0`, the plugin is NOT release-ready.

**WB Listora — current status:** `failed=4` (2 plugin_dev_rules, 0 rest_js_contract, 2 wiring_completeness).

Of the 4 failures:
- **0 are release-blockers** — both `plugin_dev_rules` failures are now verified false-positives (defensive fallback + per-user-cookie nonce-no-cap), and both `wiring_completeness` failures are admin-only settings the check doesn't model.
- The store.js Rule 10 hit moved from "real" (prior) → "false positive" (current) because the design-system modal is now the primary path.

The plugin is materially closer to release-ready than the AM baseline implied. The remaining 4 failures are fully classified and documented in `audit/manifest.json#/notes`.
