# wppqa baseline - 2026-08-12 (1.5.0 release gate)

**Verdict: 0 release blockers.** Supersedes the 2026-05-24 baseline, which predated
the 1.3.x / 1.4.x / 1.5.0 waves.

## Read this before trusting the raw output

**The audit tool mis-identified the plugin.** It reported
`# Plugin Audit: EDD SL SDK v1.0.3` and flagged
`Version mismatch: readme "1.5.0" vs header "1.0.3"` - it picked up the bundled
`libs/edd-sl-sdk` plugin header instead of `wb-listora.php`. Everything it
derived from that header is about the SDK, not Listora, which is why the raw
report claims Listora has "no activation hook", "no uninstall hook", "no
taxonomies registered", and classifies the product as **E-Commerce (53%)** and
grades it against Shopify's cart and checkout.

Treat the gap list and the maturity/category sections as void. The per-tool
checks below ran against the correct tree and ARE meaningful.

## Trustworthy results

| Check | Result |
|---|---|
| PHPCS | PASS - 0 errors, 0 warnings |
| PHPSTAN | PASS - 0 errors, 0 warnings |
| PHP-LINT | PASS - 285/285 files |
| PHPCOMPAT | PASS |
| I18N | PASS |
| PLUGIN-CHECK | PASS |
| REST-JS-CONTRACT | PASS - 0 issues |
| EDITOR-LAYOUT-BIAS | PASS |
| UX-GUIDELINES | PASS |

## Findings verified and dismissed

| Finding | Verdict |
|---|---|
| 3 critical `composer-audit` CVEs (phpcsutils, php_codesniffer, wpcs) | **Not shipped.** All three are `require-dev`. `require` is `php` only. `build-release.sh` runs `composer install --no-dev` and excludes `composer.json`, so no customer zip contains them. |
| a11y: "outline:none without :focus-visible replacement" x10 | **False positive.** Naive proximity grep. The flagged declarations sit INSIDE `:focus-visible` rules that replace the outline with a `box-shadow` focus ring in the next rule. Verified at `blocks/listing-detail/style.css:1448` and by rule counts (detail 1 vs 7 focus-visible rules, dashboard 3 vs 8, reviews 1 vs 4). |
| "Version mismatch readme 1.5.0 vs header 1.0.3" | **False.** Read from the bundled SDK. `wb-listora.php` header, `WB_LISTORA_VERSION` and `readme.txt` Stable tag are all `1.5.0`. |
| GAP: no activation/deactivation/uninstall hook, no taxonomies, CPT lacks REST | **False.** Same SDK-header confusion. Listora ships `Activator`, `uninstall.php`, 5 taxonomies, and a REST-enabled CPT. |

## Open, not blocking

- `qa-coverage` reports uncovered REST routes and `uncovered_total` 159 -> 186.
  The count grew because 1.5.0 ADDED routes; the journey suite grew this wave
  too (135 journeys). Worth a dedicated coverage pass, not a 1.5.0 blocker.
- `wiring` half-wired settings and `enum-consistency` drift: same shapes
  classified as service-layer false positives in prior baselines. Unchanged.
