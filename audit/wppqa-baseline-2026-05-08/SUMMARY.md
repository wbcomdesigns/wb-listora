# wppqa baseline — wb-listora — 2026-05-08

Run as part of `/wp-plugin-onboard --refresh` Phase 0 (per skill hard rule #6: wppqa is the bug-finder, manifest is the map). All checks invoked via the `wp-plugin-qa` MCP.

## Headline

**0 release blockers.** Same status as the prior 2026-05-07 baseline — every real high-severity finding from previous baselines has been resolved or is documented as a wppqa heuristic false-positive.

## Per-check results

| Check | Passed | Failed | Severity of failures |
|---|---|---|---|
| `wppqa_check_plugin_dev_rules` | 9 | 0 | — (15 warnings, all low/medium, all pre-existing tap-target + breakpoint counts in admin CSS) |
| `wppqa_check_rest_js_contract` | 6 | 0 | — clean |
| `wppqa_check_wiring_completeness` | 5 | 3 | 3 high — **all 3 are heuristic false-positives** (see below) |

## Wiring "errors" — all classified false-positive

The wiring sniff only inspects `templates/` for setting reads. Settings consumed at the admin layer or by service-layer classes look "half-wired" to it. All 3 findings on this run fit that pattern:

| Setting | File:Line | Why it's a false positive |
|---|---|---|
| `listora_duplicate_filter` | `includes/admin/class-listing-columns.php:292` | Admin-only filter on the listings list-table screen. Read in the admin column callback, not in a customer-facing template. |
| `license_key` | `includes/admin/class-pro-promotion.php:468` | Pro-promotion form posts a license key for the Pro plugin's updater to consume. Not a Free-side setting. |
| `retention_days` | `includes/admin/class-settings-page.php:1791` | Notification log retention control read by the daily cleanup cron in `Notifications::cleanup_log()`. Server-side only — no template output. |

## Plugin-dev-rules warnings — all pre-existing low/medium

15 low-severity tap-target violations across admin CSS files (button heights of 14–34px on legacy admin tables / type-editor controls). These are pre-existing from before the Part 7.6.1 same-family migration began; the new canonical primitives all hit the 40px floor. Touch-device polish work, not a release blocker.

1 medium-severity finding: 8 distinct breakpoints found across CSS files (1024 / 1700 / 480 / 600 / 640 / 782 / 900 / 960px). Skill rule frontend-responsive #1 says ≤3 breakpoints. The new primitives (`shared.css` Part 7.6.1 section) only use 640px so this number drops as bespoke per-block CSS retires.

## Comparison to 2026-05-07 baseline

- Prior baseline had 5 real high-severity errors (2 alert(), 2 confirm(), 1 nonce-no-cap) — all fixed in commits A1–A7 on 2026-05-07.
- This baseline has 0 real findings. The 3 wiring "errors" carry over from the prior baseline classification.

**Plugin is release-ready as of this run.**
