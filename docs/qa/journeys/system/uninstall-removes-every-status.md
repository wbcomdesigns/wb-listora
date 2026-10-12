---
journey: uninstall-removes-every-status
plugin: wb-listora
priority: high
roles: [administrator]
covers: [uninstall-custom-statuses, delete-on-uninstall, credits-tables-reinstall, multisite-uninstall]
prerequisites:
  - "A SCRATCH site only (Docker bench / throwaway Local site); never a shared QA site or any site with data you need"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes: []
fails_on: []
---

# Uninstall removes listings in every status, and only when the owner opted in

## Why
Uninstall fetched listings with `post_status => 'any'`, which skips statuses registered with `exclude_from_search` (all of Listora's: `listora_expired`, `listora_rejected`, `listora_deactivated`, `listora_payment`), and at uninstall time they are not registered at all, so those listings and their meta outlived the plugin. `uninstall.php:98-115` now reads IDs from the posts table in batches of 200; the opt-in is "Permanently delete all {product} data on plugin uninstall" under Listora > Settings > Advanced > Data Management (`includes/admin/class-advanced-settings-tab.php:279-285`, option `wb_listora_settings[delete_on_uninstall]`).

Single-role destructive CLI journey: no viewport/theme/role ladder beyond the table below.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Scratch site only. Seed: one listing each in `publish`, `pending`, `listora_expired`, `listora_rejected`, `listora_deactivated`, `listora_payment`, each with `_listora_phone` set; create a credit ledger row so the credits tables exist; attach a gallery image to one.
- Record `N_SEEDED` = the count of seeded listings (derive, do not assume 6).

## Steps

### 1. Opt-in run deletes everything
- **Action**: tick the Data Management box (or `update_option( 'wb_listora_settings', array( 'delete_on_uninstall' => 1 ) + get_option( 'wb_listora_settings' ) )`); `wp plugin deactivate wb-listora && wp plugin uninstall wb-listora`.
- **Expect**: `SELECT COUNT(*) FROM wp_posts WHERE post_type = 'listora_listing'` = 0; `SELECT COUNT(*) FROM wp_postmeta WHERE meta_key LIKE '\_listora\_%'` = 0; options `wbcom_credits_%` for this consumer (`%\_listora`, `%wb-listora%`) = 0 so a reinstall recreates the credit tables; options `wb_listora_%` (except `wb_listora_pro_%`) = 0.
- **Read-back**: gallery files of the seeded listing are removed from disk (the postmeta wipe must not run before the listing loop, ordering comment near `uninstall.php:215`).

### 2. Positive control: opt-in UNTICKED keeps everything
- **Action**: the same run with the box unticked.
- **Expect**: all `N_SEEDED` listings and their meta remain (the opt-in is what deletes).

### 3. Reinstall recreates the tables
- **Action**: after step 1 reinstall and activate.
- **Expect**: no fatal; the credit tables are recreated; `wb_listora_db_version` set.

### 4. Pro opt-in boundary
- **Expect**: Pro's own tables (audit_log, saved_searches, coupon_usage, webhook_log, need_responses, search_terms) and `wb_listora_pro_*` options are removed by Pro's own `uninstall.php`, not Free's.

### 5. Multisite
- **Expect**: network uninstall iterates sites; user meta goes only when no site asked to keep data (`uninstall.php:15-40`).

### 6. Scale
- **Action**: seed 2000+ listings in mixed statuses, run step 1.
- **Expect**: completes in batches of 200 without timeout or memory error; counts reach 0. Not verified live.

### 7. Role ladder
| Rung | Expect |
|---|---|
| {admin} | CLI/Plugins screen uninstall as above |
| {editor}, member | cannot uninstall plugins (no capability) |

## Pass criteria
1. With the opt-in on, no listing in any status, no `_listora_*` meta, no `wb_listora_%` option and no credits option survives.
2. With it off, nothing is deleted.
3. A reinstall recreates the credit tables.

## Fail diagnostics
| Symptom | File |
|---|---|
| Expired/rejected listings survive | `uninstall.php:98-115` (`SELECT ID FROM wp_posts WHERE post_type = 'listora_listing'`) |
| Credit tables fail on reinstall | `uninstall.php:182-196` options sweep |
| Gallery images left on disk | postmeta wipe ran before the listing loop (`uninstall.php:215`) |

## Not verified
- Not run: destructive, and the usual QA site is shared. Source pointers re-read on 2026-10-12. Steps 3, 6 added in consolidation.

## Cleanup
Destroy the scratch site.
