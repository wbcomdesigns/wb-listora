# wppqa baseline — 2026-05-24

**Plugin:** wb-listora
**Version:** 1.0.4
**Branch:** main
**Run by:** wp-plugin-onboard --refresh (Phase 0)
**Diff since prior baseline:** 2026-05-18 → 2026-05-24

## Headline

**0 release blockers** (2 high-severity findings classified false-positive; 6 wiring findings classified service-layer / admin-only-read false-positives; 16 admin/listora-components tap-target warnings classified known-limitation per CLAUDE.md).

| Check | Passed | Failed | Real bugs | False positives |
|---|---|---|---|---|
| plugin-dev-rules | 7 | 2 | 0 | 2 (nonce-no-cap proximity-heuristic, both reverse-order cap → nonce) |
| rest-js-contract | 6 | 0 | 0 | 0 |
| wiring-completeness | 5 | 6 | 0 | 6 (all admin-only or service-layer reads) |

Totals: **18 passed / 8 failed / 0 real bugs / 8 classified false-positives.**

## Detail

### plugin-dev-rules — 2 findings, both classified false-positive

| File:line | Code | Classification | Evidence |
|---|---|---|---|
| `includes/admin/class-featured-metabox.php:138` | nonce-no-cap | **FP** — cap check at line 131 (7 lines BEFORE nonce check). Correct fail-fast order: autosave → cap → nonce isset → nonce verify → CPT check. | `save_post()` `current_user_can( 'edit_post', $post_id )` precedes `wp_verify_nonce` by 7 lines. |
| `includes/admin/class-report-metabox.php:171` | nonce-no-cap | **FP** — same shape. cap check at line 164 (7 lines BEFORE nonce). | `save_post()` `current_user_can( 'edit_post', $post_id )` precedes `wp_verify_nonce` by 7 lines. |

Root cause of both: the sniff's proximity heuristic scans for `current_user_can` AFTER the nonce check, but this codebase puts the cap check BEFORE the nonce check (fail-fast on auth before crypto verification). Same pattern previously classified false-positive for `Pro_Promotion::ajax_dismiss_promo` per prior baselines.

**Action: keep code as-is. No change required for release.**

#### Delta vs 2026-05-18 baseline

- `class-featured-metabox.php:138` was already FP in 2026-05-18 baseline.
- `class-report-metabox.php:171` is **newly surfaced** by the sniff — it pre-existed in the codebase (not from any commit since 2026-05-18) but the previous baseline run did not flag it. Verified by reading the file directly: cap-before-nonce order is correct; same fail-fast structure as Featured metabox.

16 warnings (low-sev) — all admin-context + canonical components tap-target sizes:
- 14 admin CSS warnings unchanged (15 → 14: one moved as a result of RTL twin regen on 2026-05-23 commit 37ceb41).
- **2 NEW warnings on `assets/css/listora-components{-rtl}.css:309`** (32px button height — frontend-customer surface, NOT admin). Worth a follow-up — frontend tap-targets should hit 40px. Filing as low-priority UX-CONS item.

### rest-js-contract — clean

6/6 passed. No silent envelope-mismatch class bugs detected. Delta vs 2026-05-18: identical (the only PHP changes this window were internal helpers + verification resolver swap, no REST shape changes).

### wiring-completeness — 6 findings, all classified false-positives

| Setting | Posted at | Actual reader | Class |
|---|---|---|---|
| `wb_listora_featured_enabled` | class-featured-metabox.php:77 | Same file's `save_post()` + `Featured::feature_listing()` service | admin-only |
| `wb_listora_featured_days` | class-featured-metabox.php:92 | Same file's `save_post()` + `Featured::feature_listing()` duration arg | admin-only |
| `listora_duplicate_filter` | class-listing-columns.php:340 | Admin listing list-table `pre_get_posts` filter | admin-only |
| `license_key` | class-pro-promotion.php:484 | Pro plugin's REST endpoint validates server-side | admin-only |
| `wb_listora_clear_reports` | class-report-metabox.php:148 | Same file's `save_post()` clears the option | admin-only |
| `retention_days` | class-settings-page.php:1900 | `Notifications::PRUNE_HOOK` daily cron reads it for email-log retention | service-layer |

None of these should reach `templates/`. The sniff's `templates/`-only scope is a known heuristic limitation.

**Action: keep code as-is. Same classification pattern as prior baselines (2 → 5 → 6 with each added admin-side setting).**

#### Delta vs 2026-05-18 baseline

5 → 6 wiring findings. The +1 is `wb_listora_clear_reports` (was previously not flagged; same shape as the other 5).

## Comparison vs prior baseline (2026-05-18)

| Metric | 2026-05-18 | 2026-05-24 | Delta |
|---|---|---|---|
| plugin-dev-rules passed / failed | 8 / 1 | 7 / 2 | +1 FP (pre-existing report-metabox surfaced) |
| Real bugs | 0 | 0 | 0 |
| Admin tap-target warnings | 15 | 14 | -1 (RTL twin regen consolidation) |
| Frontend tap-target warnings | 0 | 2 | **+2 worth a UX-CONS follow-up** |
| wiring half-wired (FP) | 5 | 6 | +1 (clear_reports surfaced; same shape) |
| Release blockers | 0 | 0 | 0 |

## Why this refresh

Diff-driven refresh after the 2026-05-21 → 2026-05-24 commit window:

| Commit | Impact on baseline |
|---|---|
| `aa1b39a` schema-generator defensive coercion (BC 9905075024) | No new hooks/REST. Internal `normalize_meta_for_schema()` helper. No wppqa surface change. |
| `a4b4e6f` cron dedup on bootstrap (BC 9910208588) | Adds internal `Cron_Scheduler::dedupe_pending` + `dedupe_pending_batch` + `Plugin::dedupe_recurring_cron` listener. No new hook firings. No wppqa surface change. |
| `efcab2e` verification detail block via resolver (BC 9911539296) | Single line swap: `get_post_meta` → `wb_listora_is_verified()`. No wppqa surface change. |
| `c758104` a11y aria-orientation on dashboard nav | Template attribute only. No wppqa surface change. |
| `37ceb41` build script generates RTL CSS twins | Build infra. Regenerated `listora-components-rtl.css` + `listora-variables-rtl.css` — explains the -1/+1 tap-target warning shuffle. |
| `31b9b14` buddyx theme bridge tuning | Theme CSS bridge. No wppqa surface change. |

## Recommendation

**Release-ready as 1.0.5 or 1.1.0 from a wppqa standpoint** (0 blockers). Two follow-ups to file as UX-CONS / future cleanup:

1. **Investigate `listora-components.css:309` button height (32px → 40px).** Frontend tap-target Rule 4 applies here, unlike admin CSS. Low priority — wait for the next frontend pass; not a release blocker.
2. **(Optional) Add a docblock note above `Report_Metabox::save_post()` documenting the cap-before-nonce intentional ordering** so the next sniff run doesn't re-surface it. Same suggestion was made for Featured_Metabox in the prior baseline.

No fix-and-re-run required for this refresh.
