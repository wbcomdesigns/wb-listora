# WB Listora — Post-Fix Audit (2026-04-14)

Follow-up to `audit-2026-04-14.md`. All blocker-class work completed.

## Deltas (before → after)

| Metric | Before | After | Δ |
|---|---|---|---|
| Total errors (14 checks) | 165 | **81** | **−51%** |
| PHPCS errors | 68 | 17 | −75% |
| PCP-Deep errors | 47 | 15 | −68% |
| a11y errors | 4 | 0 | ✓ cleared |
| a11y-grep errors | 1 | 0 | ✓ cleared |
| PHP lint | 134/134 pass | 134/134 pass | ✓ |
| REST 500s | 29/29 | 0/29 | ✓ all green |

## Fixes applied

### Infrastructure
- **Enabled `WP_DEBUG_LOG`** in `wp-config.php` (DEBUG_DISPLAY=false).
- **Regenerated Composer autoload** (`composer dump-autoload -o`) — new `Listing_Limits` and `Featured` classes now load; all 29 REST 500s resolved.
- **Initialized `vendor/wbcom-credits-sdk` submodule**.

### Code
- **`$wpdb` prepare sweep** (sub-agent verified): zero unprepared variable-derived queries. All pre-existing queries already use `prepare()` or are pure-constant SQL with `phpcs:ignore` annotations. **0 code changes needed.**
- **Output escaping** in `includes/admin/class-settings-page.php`: 51× raw `echo $opt` → `esc_attr( $opt )`.
- **Template escaping**: 21 class/attr ternaries hardened to `esc_attr()` across 15 template files.
- **a11y**: 6 form inputs in `tab-listings.php` got `<label for>` + `id` associations; favourite button got static `aria-label` fallback; `outline:none` in `admin.css` replaced with `:focus-visible` rule.
- **Pagination regression fixed** — added defensive defaults in `pagination.php` for partial-context renders.

## Remaining items (non-blocker)

### Scanner false positives (ignore)
- 48 "security-scan errors" for `$wpdb` in `class-admin.php`, CLI, migrators, search — all confirmed to be pure-constant SQL or already-prepared. Scanner regex is triggering on `{$prefix}` in interpolation even though `$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX`. Already annotated.
- 5 PCP-deep "late escaping" in block render.php — all `get_block_wrapper_attributes()` or `Block_CSS::render()` output, which is pre-escaped. Already annotated `phpcs:ignore`; PCP-Deep ignores the annotation.

### Real follow-ups (low priority)
- **PHPCS warnings (166):** mostly formatting (equals alignment, multi-item array wrap). Run `phpcbf` locally when convenient.
- **Performance:** `class-featured.php:77` uses `posts_per_page = -1`. Bound to a sane limit.
- **a11y:** 2 `printf with variable` warnings in `class-listing-columns.php:283`, 8 in `class-block-css.php` — all receive format args via `wp_kses_post()`; verify escape chain.
- **Lifecycle hooks false positive:** audit flagged "missing activation/deactivation/uninstall hooks" — all exist and are registered. Scanner couldn't trace the namespaced callback.

## Verification performed
- `php -l` on every edited PHP file → clean.
- `wp plugin list` → wb-listora + wb-listora-pro both active.
- Live REST probe: `/wp-json/`, `/wp-json/listora/v1/listings`, `/search`, `/listing-types` → 200; `/settings` → 401 (auth-gated, expected).
- Homepage `/` → 200; debug.log clean (after pagination fix).
- No git commits — diff left unstaged for your review.
