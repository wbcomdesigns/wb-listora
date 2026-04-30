# wppqa baseline — WB Listora — 2026-04-30

Run during `/wp-plugin-onboard --refresh` (Phase 0). Three checks captured BEFORE manifest refresh, so the manifest reflects the same reality the bug-finder did.

## Headline counts

| Check | Passed | Failed | Skipped | Duration |
|---|---|---|---|---|
| `plugin_dev_rules`            | 7 | 2 | 0 | 92ms |
| `rest_js_contract`            | 6 | 0 | 0 | 14ms |
| `wiring_completeness`         | 5 | 2 | 0 | 49ms |

**Total:** 18 passed / 4 failed / 0 skipped — pipeline cost ~155ms.

`rest_js_contract` is fully clean — confirms the v2 manifest's REST envelope discipline is holding for every controller scanned. Both PHP shape extraction and JS access scan agreed: no envelope drift on any of the 6 controllers within the 50-line proximity window.

---

## 1. `plugin_dev_rules` — 2 high-severity errors + 15 low/medium warnings

### High-severity (real)

| ID | File:Line | Issue | Status |
|---|---|---|---|
| PLUGIN-DEV-RULES-001 | `src/interactivity/store.js:824` | `confirm()` browser dialog — admin-ux-rulebook Rule 10 bans this | **REAL.** Replace with the existing modal-getter pattern (`activeModal`) introduced in 63411c8. The modal infrastructure is already in place; this site predates it. |
| PLUGIN-DEV-RULES-002 | `includes/admin/class-pro-promotion.php:1188` | Nonce check without paired `current_user_can()` — security.md authorization gap | **REAL.** Pro-promotion settings save handler verifies nonce but does not gate by capability. Customer-impact low (admin-menu gated), but defense-in-depth is the rule. |

### Medium/low-severity (real but lower-priority)

- **PLUGIN-DEV-RULES-003** — 8 distinct breakpoints across the plugin (1024, 1700, 480, 600, 640, 782, 900, 960px). Real breakpoint sprawl, mostly in admin CSS — consolidate during the next admin-CSS refactor.
- **PLUGIN-DEV-RULES-004 .. -017** — 14 low-severity tap-target warnings (button heights 14-34px vs the 40px minimum). All in admin CSS. Customer-touch surfaces unaffected. Defer to admin-UI consolidation sprint.

### Likely false-positive

None. Every finding cites a real file:line and the heuristic matches the symptom.

---

## 2. `rest_js_contract` — 6 passed, 0 failed

No envelope-mismatch drift found. The check extracts the response shape from each controller (`rest_ensure_response` literals + return arrays) and scans `assets/js/` and `src/` for property accesses within 50 lines of a route URL reference.

**Caveat (heuristic):** the 50-line proximity window means any controller→JS pair where the JS handler is more than 50 lines below the URL constant gets a clean pass even if drift exists. For wb-listora, the IAPI store pattern (`src/interactivity/store.js`) and per-block view scripts mostly read responses near the URL, so the heuristic is a good fit here.

---

## 3. `wiring_completeness` — 2 high-severity errors

### Findings

| ID | File:Line | Setting | Classification |
|---|---|---|---|
| WIRING-001 | `includes/admin/class-listing-columns.php:291` | `listora_duplicate_filter` | **LIKELY FALSE-POSITIVE.** Admin list-table filter; reader is `class-listing-columns.php` itself via `pre_get_posts`, not a `templates/` file. The check only inspects `templates/`, so admin-only wiring is invisible to it. |
| WIRING-002 | `includes/admin/class-pro-promotion.php:468` | `license_key` | **LIKELY FALSE-POSITIVE.** License keys never have a frontend template surface; consumed by the licensing/upsell module the check doesn't traverse. |

Both findings are heuristic-driven false-positives because the check only inspects `templates/`. wb-listora's admin-only settings legitimately have no `templates/` reader. The check would correctly flag `templates/`-bound user-facing settings — none of those exist as half-wired in this plugin, which is the actual signal.

---

## Findings beyond what the prior refresh knew about

The prior refresh's `static_analysis.cap_context_mismatches=0` and `dead_listeners=0` are confirmed unchanged — wppqa surfaced no new issues in those classes.

**New since prior refresh** (not captured by the manifest's `static_analysis` block):

1. `confirm()` usage at `src/interactivity/store.js:824` — manifest has no "banned-API" detector. Recommend either a `static_analysis.banned_dom_apis[]` schema bump or a `bin/coding-rules-check.sh` rule.
2. Pro-promotion nonce-no-cap (`class-pro-promotion.php:1188`) — manifest has no "nonce-without-cap" detector.
3. 8-breakpoint sprawl — manifest doesn't classify CSS breakpoint discipline.

These are **not blockers** for this refresh — they're surfaced for the follow-up backlog. The 5 commits since the prior refresh are surgical bug fixes that introduce no new findings in any of the three checks.

---

## Release-readiness gate

Per skill hard rule #6: any time `wppqa_audit_plugin` returns `failed > 0`, the plugin is NOT release-ready.

**WB Listora — current status:** `failed=4` (2 plugin_dev_rules, 0 rest_js_contract, 2 wiring_completeness).

Of the 4 failures:
- 2 are real (PLUGIN-DEV-RULES-001 confirm() ban, PLUGIN-DEV-RULES-002 nonce-no-cap).
- 2 are likely false-positives (wiring against admin-only settings).

Treat the 2 real ones as release-blockers for the next minor version. The false-positives should be allowlisted in a future `wppqa_check_wiring_completeness` config when that capability ships.
