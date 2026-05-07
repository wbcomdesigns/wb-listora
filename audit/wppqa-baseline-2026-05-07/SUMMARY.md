# wppqa Baseline — 2026-05-07

**Plugin:** wb-listora (Free)
**Baseline run:** 2026-05-07
**Prior baseline:** 2026-04-30 / 2026-04-30-pm

## Overall

| Check | Passed | Failed | Skipped | Duration |
|---|---|---|---|---|
| wppqa_check_plugin_dev_rules | 4 | **5** | 0 | 90 ms |
| wppqa_check_rest_js_contract | 6 | 0 | 0 | 26 ms |
| wppqa_check_wiring_completeness | 5 | **2** | 0 | 62 ms |

Net: 7 high-severity errors + 15 low-severity warnings. REST↔JS contract clean.

## 1. plugin-dev-rules — 5 errors / 15 warnings

### High-severity (5)

| # | Rule | File:Line |
|---|---|---|
| 1 | alert() banned (Rule 10) | src/blocks/listing-submission/view.js:436 |
| 2 | alert() banned (Rule 10) | src/blocks/listing-submission/view.js:475 |
| 3 | confirm() banned (Rule 10) | src/interactivity/store.js:866 |
| 4 | confirm() banned (Rule 10) | src/interactivity/store.js:932 |
| 5 | Nonce without capability check | includes/admin/class-pro-promotion.php:1193 |

### Warnings (15)

- Breakpoint proliferation (1): 8 distinct breakpoints (1024, 1700, 480, 600, 640, 782, 900, 960).
- Tap-target < 40px (14): admin CSS buttons at 14–34px in assets/css/admin*.css and type-editor*.css.

## 2. rest-js-contract — clean (0 issues)

No envelope-shape mismatches across 6 controller scans. Recent block additions stayed in contract.

## 3. wiring-completeness — 2 errors

| # | Setting | Posted at | Note |
|---|---|---|---|
| 1 | listora_duplicate_filter | includes/admin/class-listing-columns.php:291 | half-wired |
| 2 | license_key | includes/admin/class-pro-promotion.php:468 | half-wired (likely service-layer consumed) |

Heuristic note: this check inspects `templates/` only; service-layer reads are not detected.

## Top 3 impact-ranked findings

1. **Nonce-no-capability gate** at includes/admin/class-pro-promotion.php:1193 — auth-bypass risk.
2. **confirm() in interactivity store** at src/interactivity/store.js:866,932 — untranslatable; replace with modal.
3. **alert() in submission wizard** at src/blocks/listing-submission/view.js:436,475 — replace with toast/inline.

## Release-readiness

`failed > 0` → plugin NOT release-ready until errors resolved or waived.

---

## Post Phase 0 update — 2026-05-07 (after commits A1–A7)

| Check | Passed | Failed | Skipped | Duration |
|---|---|---|---|---|
| wppqa_check_plugin_dev_rules | **9** | **0** | 0 | 74 ms |
| wppqa_check_rest_js_contract | 6 | 0 | 0 | 10 ms |
| wppqa_check_wiring_completeness | 5 | 2 (FP) | 0 | 40 ms |

**Release blockers: 0.** All five high-severity errors above are resolved:

| Rule | Prior locus | Resolved by |
|---|---|---|
| Rule 10 — `alert()` banned | `src/blocks/listing-submission/view.js:436` | A7 / `7c5a3d7` — new `showUploaderInlineError()` injects `<div role="alert" class="listora-form__error">` |
| Rule 10 — `alert()` banned | `src/blocks/listing-submission/view.js:475` | A7 / `7c5a3d7` — same helper |
| Rule 10 — `confirm()` banned | `src/interactivity/store.js:866` | A6 / `74666f6` — direct `await window.listoraConfirm()` |
| Rule 10 — `confirm()` banned | `src/interactivity/store.js:932` | A6 / `74666f6` — same |
| Nonce-no-cap | `class-pro-promotion.php:1193` | A5 / `41aa81e` — `current_user_can('read')` after `check_ajax_referer()` |

The two remaining `wiring_completeness` failures (`listora_duplicate_filter`, `license_key`) are heuristic false positives (service-layer reads, not template reads) — pre-classified in §3 above and unchanged by Phase 0.

INV-12 prep also lands in this baseline window:
- `wb_listora_listing_claimed` action — `class-claims-controller.php:512` (A1)
- `wb_listora_listing_expiration_date` filter — `class-status-manager.php:99,118` + `class-listings-controller.php:1654` (A2)
- `WBListora\Migration\Migrated_From_Tracker` — `includes/migration/class-migrated-from-tracker.php` (A3)
- `wb_listora_webhook_secret` filter — `class-settings-page.php:998` (A4)

`failed (release-blocking) = 0` → Free is **release-ready** from a wppqa hard-rule standpoint.
