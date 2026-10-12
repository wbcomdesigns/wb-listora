---
journey: uninstall-deletes-everything-both-orders
group: platform-lifecycle
actor: admin
mode: cli
plugin: wb-listora
priority: high
roles: [administrator, editor]
covers: [uninstall-delete-data, uninstall-every-status, uninstall-credits-sdk-data, uninstall-order-free-first, uninstall-order-pro-first, uninstall-no-collateral, uninstall-like-wildcards, uninstall-multisite, 2.0.0-uninstall, bc-10393557222, bc-10393846493, bc-10393803413, bc-10393531299, uninstall-custom-statuses, credits-tables-reinstall, multisite-uninstall]
prerequisites:
  - "SCRATCH site only (Docker bench or a disposable Local site): this journey deletes the plugins' data permanently"
  - "Free and Pro installed and active, demo content imported (all packs), some credits activity, one listing in each status: publish, pending, draft, listora_rejected, listora_deactivated, listora_payment, listora_expired"
  - "A second plugin's data present as a control (WooCommerce options, an unrelated custom post type with postmeta, another Wbcom plugin's wbcom_credits_* rows if available)"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
supersedes:
  - ora:admin/2.0-uninstall-deletes-everything-both-orders.md
  - ora:system/uninstall-removes-every-status.md
fails_on: steps 3 read-back 9ac05f73 (categories, locations, types and listing images survived); step 6 d71e6b86; step 5 unknown (card 10393557222, no fix commit found)
site_profile: required
---

# "Delete data on uninstall" leaves nothing of Listora behind in either deletion order, and nothing of anyone else's is removed

## Why
Before 2.0.0 statuses outside `any` (expired, rejected, paused, awaiting payment) survived, the Credits SDK tables/options survived, and deleting Pro before Free (or Free before Pro) stranded the other's rows because the second uninstall could no longer read the first one's setting. `uninstall.php` now reads listing IDs from the posts table in batches of 200 (no `post_status => any`, which skipped the `exclude_from_search` statuses). Opt-in: Settings > Advanced > Data Management, option `wb_listora_settings[delete_on_uninstall]`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
1. **Snapshot script** (run before and after each uninstall; core functions only so it works with the plugins gone) saved at `$SCRATCH/snap.sh`:
   ```bash
   wp eval '
   global $wpdb; $p=$wpdb->prefix;
   $q=function($sql) use($wpdb){ return (int)$wpdb->get_var($sql); };
   echo json_encode([
    "listings" => $q("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"listora_listing\""),
    "pro_cpts" => $q("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN (\"listora_plan\",\"listora_need\",\"listora_coupon\",\"listora_badge\",\"listora_webhook\")"),
    "listora_meta" => $q("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE \"\\_listora\\_%\""),
    "demo_attachments" => $q("SELECT COUNT(*) FROM {$wpdb->posts} a JOIN {$wpdb->postmeta} m ON m.post_id=a.ID AND m.meta_key=\"_listora_demo_content\" WHERE a.post_type=\"attachment\""),
    "listing_attachments" => $q("SELECT COUNT(*) FROM {$wpdb->posts} a JOIN {$wpdb->posts} p ON a.post_parent=p.ID WHERE a.post_type=\"attachment\" AND p.post_type=\"listora_listing\""),
    "termmeta" => $q("SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key LIKE \"\\_listora\\_%\""),
    "terms" => $q("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE \"listora\\_%\""),
    "tables" => count($wpdb->get_col("SHOW TABLES LIKE \"".$wpdb->esc_like($p."listora_")."%\"")),
    "options_free" => $q("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \"wb\\_listora\\_%\" AND option_name NOT LIKE \"wb\\_listora\\_pro\\_%\""),
    "options_pro" => $q("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \"wb\\_listora\\_pro\\_%\""),
    "options_credits_listora" => $q("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \"wbcom\\_credits\\_%\" AND (option_name LIKE \"%\\_listora\" OR option_name LIKE \"%wb-listora%\")"),
    "usermeta" => $q("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE \"\\_listora\\_%\" OR meta_key LIKE \"wb\\_listora\\_%\""),
    "CONTROL_options_other" => $q("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name NOT LIKE \"%listora%\" AND option_name NOT LIKE \"\\_transient%\" AND option_name NOT LIKE \"\\_site\\_transient%\""),
    "CONTROL_postmeta_other" => $q("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE \"\\_listora\\_%\" AND meta_key NOT LIKE \"\\_wb\\_listora%\""),
    "CONTROL_users" => $q("SELECT COUNT(*) FROM {$wpdb->users}"),
   ],JSON_PRETTY_PRINT);'
   ```
   `bash $SCRATCH/snap.sh > $SCRATCH/before.json`.
2. Settings > Advanced > Data Management: tick "Permanently delete all WB Listora data on plugin uninstall" (warning "this removes every listing, review, favorite, claim, custom table, and setting. Cannot be undone."), Save. Confirm `wp option get wb_listora_settings --format=json | jq .delete_on_uninstall` is 1 and, with Pro active, `wp option get wb_listora_pro_delete_on_uninstall` is 1 (Pro mirrors the switch so it survives Free being deleted first).

## Steps

### 1. RUN 1, Free deleted first: delete
- **Action**: `wp plugin deactivate wb-listora-pro wb-listora && wp plugin uninstall wb-listora`, then `wp plugin uninstall wb-listora-pro`.
- **Expect**: both succeed, no PHP warning/notice/fatal in `debug.log` from either `uninstall.php`.

### 2. RUN 1: verify
- **Action**: `bash $SCRATCH/snap.sh > $SCRATCH/after.json`; diff against `before.json`.
- **Expect**: `listings` 0 (every status), `pro_cpts` 0, `listora_meta` 0, `demo_attachments` 0, `listing_attachments` 0 (images of deleted listings leave the media library and disk), `termmeta` 0, `terms` 0 (categories, locations, tags, features, types, service categories), `tables` 0 (all `${PREFIX}listora_*` including `listora_credit_*`), `options_free` 0, `options_pro` 0, `options_credits_listora` 0, `usermeta` 0, and `wp-content/uploads/wb-listora-import` removed.
- **Collateral**: every `CONTROL_*` row identical to the digit; the unrelated plugin's options/postmeta/users intact; WooCommerce still loads.

### 3. RUN 2, Pro deleted first (restore the scratch site to the same starting state and re-snapshot)
- **Action**: `wp plugin deactivate wb-listora-pro wb-listora && wp plugin uninstall wb-listora-pro` THEN `wp plugin uninstall wb-listora`.
- **Expect**: same final snapshot as step 2, including Pro's tables (`audit_log`, `coupon_usage`, `webhook_log`, `need_responses`, `search_terms`, `rate_limits`, `credit_log`), Free's `payments` table, roles/caps (`listora_moderator` removed, moderators demoted to subscriber), and Free's listings + `listora_*` options even though Free was uninstalled last.
- **Positive control**: the same sequence with "Delete data on uninstall" UNticked on a third scratch copy: all counts stay as in the baseline.
- **Read-back** (card 10393531299): `SELECT COUNT(*) FROM ${PREFIX}term_taxonomy WHERE taxonomy LIKE 'listora\_%'` = 0 and `SELECT COUNT(*) FROM ${PREFIX}posts WHERE post_type='attachment' AND post_parent IN (<ids of the deleted listings>)` = 0, in BOTH run orders. Not verified live.

### 4. Reinstall sanity
- **Action**: install and activate Free again.
- **Expect**: tables and Credits SDK tables recreated (no "table doesn't exist" errors), the setup wizard opens, nothing from before is shown.

### 5. Look-alike rows owned by someone else survive (LIKE wildcard control)
- **Action**: before RUN 1 seed `wp option add wbxlistora_test 1`, `wp post meta add <any non-listing post id> xlistora_test 1`, `wp user meta add 1 wbxlistora_test 1` (these match `wb_listora_%` / `_listora_%` only if `_` is a wildcard). Run RUN 1 and RUN 2 with the box ticked.
- **Expect**: after each uninstall all three look-alike rows still exist (value 1) while `options_free` stays 0; negative control: `wb_listora_settings` is gone.
- **Read-back**: `SELECT COUNT(*) FROM ${PREFIX}options WHERE option_name='wbxlistora_test'` = 1 and `SELECT COUNT(*) FROM ${PREFIX}postmeta WHERE meta_key='xlistora_test'` = 1. Not verified live.

### 6. Multisite: every subsite is cleaned with its own types (bc-10393846493)
- **Action**: 3-site network, network-activate Free, give each subsite a different custom listing type and attachments, tick Delete data on each, Network Admin > Plugins > Delete.
- **Expect**: on every subsite the tables `${PREFIX}_N_listora_*`, `wb_listora_*` options, listing posts and attachments are gone; site 1's type list is not reused for 2 or 3; a subsite that did not tick the flag keeps its data.
- **Read-back**: loop `wp site list --field=url`, run the snapshot per `--url`; every key 0 on opted-in sites and equal to "before" on the opt-out site. Not verified live.

### 7. Backup/restore edge (added)
- **Action**: restore the pre-uninstall DB dump onto the scratch site and activate the plugin.
- **Expect**: plugin works with the restored data (uninstall left no half state in files). Not verified live.

### 8. Scale: 2000+ listings in mixed statuses
- **Action**: seed 2000+ listings across publish, pending, `listora_expired`, `listora_rejected`, `listora_deactivated`, `listora_payment`, then run RUN 1 (step 1).
- **Expect**: uninstall completes in batches of 200 without timeout or memory error and the `listings` snapshot key reaches 0.
- **Read-back**: snapshot `listings` and `listing_attachments` both 0. Not verified live.

## Role ladder

| Rung | Expect |
|---|---|
| {admin} (only actor) | steps 1-7 |
| {editor} | `wp-admin/plugins.php` Delete link absent (core cap `delete_plugins`); `uninstall.php` exits unless `WP_UNINSTALL_PLUGIN` is defined |
| multisite | per-site loop: sites with the switch OFF keep rows and users' meta; a network user's meta is removed only when every site opted in |

## Pass criteria
1. After either deletion order every Listora row/table/option/term/file is gone, in all listing statuses.
2. The Credits SDK data for this consumer is gone and other consumers' rows untouched.
3. Unrelated plugins, users and options are identical before/after, including look-alike names.
4. With the switch off nothing is deleted.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Expired/rejected listings survive | uninstall loop uses `post_status => any` | `uninstall.php` (listing loop queries `post_type` only) |
| Pro tables left after Free-first | Pro cannot read `wb_listora_settings` | Pro `uninstall.php` (`wb_listora_pro_delete_on_uninstall`), `includes/functions.php` mirror |
| `wbcom_credits_*` left | SDK option pattern changed | `uninstall.php` credit options/tables block |
| Other plugin's rows removed | LIKE pattern too wide | `uninstall.php` option/usermeta deletes (anchor with `NOT LIKE 'wb\_listora\_pro\_%'`) |
| Gallery files left on disk | postmeta wipe ran before the listing loop | `uninstall.php` (ordering comment near the postmeta sweep) |

## Not verified
Steps 3 read-back, 5, 6, 7; the reference run counts were taken on the dev site only.

## Cleanup
Discard the scratch site.
