---
journey: admin-reindex-button-schedules
plugin: wb-listora
roles: [administrator, editor, subscriber]
priority: normal
covers: [BC-10203331648, Search_Indexer, admin_init-handlers, nonce-verification, listora_reindex]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Administrator (a user with manage_listora_settings but not manage_options is the optional step-4 variant)"
  - "WP-Cron reachable"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
changed: true
---

# The Rebuild Search Index button schedules the rebuild

The button once pointed at `?page=listora-settings&action=reindex` with a `listora_reindex` nonce and nothing consumed either: clicking reloaded the settings page and did nothing. A stale search index fails silently (wrong results, no error), so owners following the help text "Run after bulk-editing listings or changing custom fields" believed it was rebuilt when it was not.

The handler is `Admin::handle_search_reindex()` (`includes/admin/class-admin.php:767-801`) on `admin_init`, so the redirect works before admin chrome prints; it calls `Search_Indexer::schedule_full_reindex()` (`includes/search/class-search-indexer.php:75-82`), which clears `wb_listora_reindex_offset` and schedules the single event `wb_listora_search_reindex` 30 s out; each tick processes a batch and reschedules itself 60 s out (`:93-107`).

## Steps

### 1. A bogus nonce is rejected
- **Action**: as administrator visit `$SITE_URL/wp-admin/admin.php?page=listora-settings&action=reindex&_wpnonce=bogus`.
- **Expect**: HTTP **403** ("link expired"). Verified 2026-10-12. HTTP 200 with the settings page rendered is the original regression: an unhandled action looks exactly like that.

### 2. The real button schedules the job
- **Action**: Listora > Settings > Advanced (`page=listora-settings&tab=advanced`), "Maintenance" block, "Search index" row, click the "Rebuild Search Index" button (an `a.button` carrying the `listora_reindex` nonce).
- **Expect**: a 302 to `?page=listora-settings&listora_reindexed=1` and a success notice (`.notice.listora-notice.notice-success`) reading "Search index rebuild scheduled. It runs in the background and may take a few minutes on large directories." Verified live (redirect + notice).
- **Then**:
```bash
wp eval '
$h = \WBListora\Search\Search_Indexer::REINDEX_CRON_HOOK;
echo "scheduled: " . var_export( (bool) wp_next_scheduled( $h ), true ) . "\n";
echo "offset: " . var_export( get_option( "wb_listora_reindex_offset", "unset" ), true ) . "\n";'
```
- **Expect**: `scheduled: true` (verified) and the offset cursor `unset` (set it to e.g. `500` first to prove the click clears it; not run).

### 3. It is scheduled, not run inline
- **Expect**: the click returns immediately; the rebuild is batched across cron ticks so a 100k-listing site never rebuilds inside a page load. A click that hangs for seconds means the work was inlined.

### 4. Capability, not just nonce (role ladder)
| Role | Direct URL with a VALID nonce | Result |
|---|---|---|
| administrator (control) | valid | schedules |
| user with `manage_listora_settings` only | valid | schedules |
| editor / subscriber | any | no reindex scheduled: the handler returns without scheduling (editor holds no `manage_listora_settings`) and the Settings page itself answers 403 |
| logged out | any | redirect to wp-login.php |

- A subscriber with a bogus nonce got 403 (verified).

### 5. Other entry point
- Site Health's "Search index" check offers the same fix link ("Rebuild Search Index →", `includes/admin/class-health-check.php:308-314`), using the same action and nonce: it must schedule the same job.

### 6. The index actually repopulates
- **Action**: let cron run, then compare `{prefix}listora_search_index` row count with published listings (`wp listora reindex` is the synchronous equivalent).
- **Expect**: the table repopulated. Scheduling without rebuilding is a different failure wearing the same success notice. Not run in this pass (2,800 listings).

## Cleanup
Idempotent. To avoid the background work on a large site: `wp eval 'wp_unschedule_hook( \WBListora\Search\Search_Indexer::REINDEX_CRON_HOOK );'` (done after the 2026-10-12 verification).

## Fail diagnostics
| Symptom | File |
|---|---|
| 200 instead of 403 / no schedule | handler not on `admin_init`: `includes/admin/class-admin.php:767` |
| Button missing | `includes/admin/class-advanced-settings-tab.php:226` |
| Notice missing | `includes/admin/class-settings-page.php:715-720` |

Verified via: curl as administrator and subscriber; wp eval for the scheduled event. Steps 3 (timing), 4 (the `manage_listora_settings`-only user) and 6 not executed.
