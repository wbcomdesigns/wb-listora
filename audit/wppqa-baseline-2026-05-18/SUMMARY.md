# wppqa baseline — 2026-05-18

**Plugin:** wb-listora
**Version:** 1.0.4
**Branch:** main
**Run by:** wp-plugin-onboard --refresh (Phase 0)

## Headline

**0 release blockers** (1 high-severity finding classified false-positive; 5 wiring findings classified service-layer-read false-positives; 15 admin tap-target warnings classified known-limitation per CLAUDE.md).

| Check | Passed | Failed | Real bugs | False positives |
|---|---|---|---|---|
| plugin-dev-rules | 8 | 1 | 0 | 1 (nonce-no-cap proximity-heuristic) |
| rest-js-contract | 6 | 0 | 0 | 0 |
| wiring-completeness | 5 | 5 | 0 | 5 (all service-layer reads) |

## Detail

### plugin-dev-rules — 1 finding, classified false positive

**`includes/admin/class-featured-metabox.php:138` — nonce-no-cap (high)**

The sniff flagged `wp_verify_nonce()` at line 138 as missing a capability check. Verified: `current_user_can( 'edit_post', $post_id )` IS present at line 131, 7 lines BEFORE the nonce check. Both gates fire in the correct order (cap → nonce → action).

Root cause of the false positive: the sniff's proximity heuristic scans for a cap check AFTER the nonce check, but this codebase puts the cap check BEFORE the nonce check — which is the correct order (fail fast on auth before crypto). Same pattern was applied to `Pro_Promotion::ajax_dismiss_promo` (also classified false-positive per CLAUDE.md prior baseline).

**Action: keep code as-is. Document classification here. No change required for release.**

15 warnings (low-sev) — all admin-context tap-target sizes, classified known-limitation per CLAUDE.md `Known-limitation: admin button tap-target` section. wp-admin context follows WP-core button conventions (28-30px) rather than the customer-facing 40px minimum.

### rest-js-contract — clean

6/6 passed. No silent envelope-mismatch class bugs detected.

### wiring-completeness — 5 findings, all classified false positives

The sniff only inspects `templates/` for setting reads. All 5 findings are settings consumed by the admin layer or service classes (not frontend templates) — that's the design.

| Setting | Posted at | Actual reader |
|---|---|---|
| `wb_listora_featured_enabled` | class-featured-metabox.php:77 | Same file's `save_post()` method + `Featured::feature_listing()` service |
| `wb_listora_featured_days` | class-featured-metabox.php:92 | Same file's `save_post()` + `Featured::feature_listing()` duration arg |
| `listora_duplicate_filter` | class-listing-columns.php:313 | Admin listing list-table filter (pre_get_posts) — not a frontend setting |
| `license_key` | class-pro-promotion.php:484 | Pro plugin's REST endpoint validates the key server-side. Not surfaced to template layer. |
| `retention_days` | class-settings-page.php:1898 | `Notifications::PRUNE_HOOK` daily cron reads it for email-log retention pruning |

None of these should be exposed via a frontend template. The sniff's `templates/`-only scope is a known heuristic limitation.

**Action: keep code as-is. No template reads needed. Same classification pattern as prior baselines (2/2 → 5/5 with f4fb0b5 additions).**

## Comparison vs prior baseline (2026-05-11)

| Metric | 2026-05-11 | 2026-05-18 |
|---|---|---|
| plugin-dev-rules passed / failed | 9 / 0 | 8 / 1 |
| Real bugs | 0 | 0 |
| Admin tap-target warnings | 15 | 15 |
| wiring half-wired | 2 (FP) | 5 (FP) |
| Release blockers | 0 | 0 |

The single new finding (Featured metabox nonce-no-cap) is from commit `f4fb0b5` (pre-launch Featured metabox + bulk moderation + Anti-Spam + Contact Form sweep). Cap check exists 7 lines earlier; sniff direction limitation only.

The wiring sniff went from 2 FP → 5 FP for the same reason: f4fb0b5 added 3 new admin-only settings (featured_enabled, featured_days, anti-spam-via-Anti_Spam-class) and none of these should reach `templates/`.

## Recommendation

**Release-ready as 1.0.5 once F-04 + F-05 fixes re-verify in the next combo smoke** (both fixes already in code per `docs/qa/launch-readiness-2026-05-18.yaml`). The wppqa baseline contributes zero blockers.
