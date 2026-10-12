---
journey: uninstall-removes-every-status
plugin: wb-listora
priority: high
roles: [admin]
covers: [uninstall-custom-statuses]
prerequisites:
  - "A SCRATCH site only (Docker bench / throwaway Local site); never wb-listora.local or any site with data you need"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# Uninstall removes listings in every status

Single-role destructive CLI journey, so no viewport/theme/role ladder (uninstall is admin-only). Uninstall used to fetch listings with `post_status => 'any'`, which skips statuses registered with `exclude_from_search` (all of Listora's: `listora_expired`, `listora_rejected`, `listora_deactivated`, `listora_payment`), and at uninstall time they are not registered at all, so those listings and their meta outlived the plugin. `uninstall.php:98-115` now reads IDs straight from the posts table in batches of 200; the opt-in is "Permanently delete all {product} data on plugin uninstall" under Listora > Settings > Advanced > Data Management (`includes/admin/class-advanced-settings-tab.php:279-285`, option `wb_listora_settings[delete_on_uninstall]`).

## Steps

### 1. Scratch site run
- **Action**: activate Free; create one listing each in `publish`, `pending`, `listora_expired`, `listora_rejected`, `listora_deactivated`, `listora_payment`, each with `_listora_phone` set; also create a credit ledger row so the credits tables exist. Tick the Data Management box (or `update_option( 'wb_listora_settings', array( 'delete_on_uninstall' => 1 ) + get_option( 'wb_listora_settings' ) )`); `wp plugin deactivate wb-listora && wp plugin uninstall wb-listora`.
- **Expect**: `SELECT COUNT(*) FROM wp_posts WHERE post_type = 'listora_listing'` = 0; `SELECT COUNT(*) FROM wp_postmeta WHERE meta_key LIKE '\_listora\_%'` = 0; options `wbcom_credits_%` for this consumer (`%\_listora`, `%wb-listora%`) = 0, so a reinstall recreates the credit tables; options `wb_listora_%` (except `wb_listora_pro_%`) = 0.
- **Positive control**: the same run with the box UNTICKED leaves all six listings (the opt-in is what deletes).

### 2. Pro opt-in boundary
- **Expect**: Pro's own tables (audit_log, saved_searches, coupon_usage, webhook_log, need_responses, search_terms) and `wb_listora_pro_*` options are removed by Pro's own `uninstall.php`, not Free's.

### 3. Multisite
- **Expect**: network uninstall iterates sites; user meta goes only when no site asked to keep data (`uninstall.php:15-40`).

## Fail diagnostics
| Symptom | File |
|---|---|
| Expired/rejected listings survive | `uninstall.php:98-115` (`SELECT ID FROM wp_posts WHERE post_type = 'listora_listing'`) |
| Credit tables fail on reinstall | `uninstall.php:182-196` options sweep |
| Gallery images left on disk | the postmeta wipe ran before the listing loop (ordering comment near `uninstall.php:215`) |

## Not verified
Not run: destructive, and `wb-listora.local` is a shared QA site. Source pointers re-read on 2026-10-12.

Verified via: source only.
