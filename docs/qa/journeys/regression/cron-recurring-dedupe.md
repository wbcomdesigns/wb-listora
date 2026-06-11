---
journey: cron-recurring-dedupe
plugin: wb-listora
priority: high
roles: [administrator]
covers: [cron-scheduler-dedupe-pending, cron-scheduler-dedupe-batch, plugin-bootstrap-cleanup-sweep]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Free active (Action Scheduler is bundled in Free's vendor/woocommerce/action-scheduler; no Pro required)"
  - "Action Scheduler runtime ready (did_action('action_scheduler_init') > 0 by the time init:16 fires)"
estimated_runtime_minutes: 5
covers_card: 9910208588
covers_commit: a4b4e6f
---

# Cron Scheduler auto-deduplicates pending recurring actions on bootstrap

Regression sentinel for BC 9910208588 — the reporter saw 5 Free recurring hooks (`wb_listora_check_expirations`, `wb_listora_draft_reminder_cron`, `wb_listora_daily_cleanup`, `wb_listora_expire_featured`, `wb_listora_cleanup_unverified_listings`) each with **two** pending AS actions (consecutive action_ids, identical schedule). Every callback was firing twice — double expiration processing, double cleanup, double reminders.

Root cause: a cross-request activation race. `Cron_Scheduler::schedule_recurring()` has a same-request guard (`$scheduled_in_request`) + an AS-level check (`as_next_scheduled_action`). Both prevent same-request duplicates. But two simultaneous requests during activation could both pass the AS check before either had committed → two inserts.

The fix (`a4b4e6f`) adds `Cron_Scheduler::dedupe_pending($hook, $group)` + `dedupe_pending_batch($hooks)` + a `Plugin::dedupe_recurring_cron()` callback hooked on `init` priority 16. Every request now sweeps the 6 known Free recurring hooks and cancels any extras. Steady state cost: 6 indexed `as_get_scheduled_actions` queries returning 1 row each; the cancel path only fires when duplicates actually exist.

## Setup

- Site: `$SITE_URL`
- The 6 hooks under sentinel:
  ```
  wb_listora_check_expirations
  wb_listora_draft_reminder_cron
  wb_listora_daily_cleanup
  wb_listora_expire_featured
  wb_listora_cleanup_unverified_listings
  wb_listora_email_log_prune
  ```

## Steps

### 1. Baseline — every recurring hook has exactly 1 pending action
- **Action**:
  ```
  wp eval "
  \$hooks = ['wb_listora_check_expirations','wb_listora_draft_reminder_cron','wb_listora_daily_cleanup','wb_listora_expire_featured','wb_listora_cleanup_unverified_listings','wb_listora_email_log_prune'];
  foreach (\$hooks as \$h) {
    \$c = count(as_get_scheduled_actions(['hook'=>\$h,'status'=>'pending','group'=>'wb-listora','per_page'=>10],'ids'));
    echo \$h.':'.\$c.PHP_EOL;
  }
  "
  ```
- **Expect**: each hook prints `:1` exactly. No `:0` (would mean activation never ran), no `:2` or higher (would mean a race occurred and the sweep didn't catch it).

### 2. Inject 2 duplicates → sweep removes them
- **Action**:
  ```
  wp eval "
  // Force a 2-duplicate state for one hook
  as_schedule_recurring_action(time()+60, DAY_IN_SECONDS, 'wb_listora_daily_cleanup', [], 'wb-listora');
  as_schedule_recurring_action(time()+120, DAY_IN_SECONDS, 'wb_listora_daily_cleanup', [], 'wb-listora');
  \$before = count(as_get_scheduled_actions(['hook'=>'wb_listora_daily_cleanup','status'=>'pending','group'=>'wb-listora','per_page'=>10],'ids'));
  echo 'before: '.\$before.PHP_EOL;
  // Manually invoke the sweep (same code path init@16 runs)
  \$report = \WBListora\Workflow\Cron_Scheduler::dedupe_pending_batch(['wb_listora_daily_cleanup']);
  echo 'cancelled: '.\$report['wb_listora_daily_cleanup'].PHP_EOL;
  \$after = count(as_get_scheduled_actions(['hook'=>'wb_listora_daily_cleanup','status'=>'pending','group'=>'wb-listora','per_page'=>10],'ids'));
  echo 'after: '.\$after.PHP_EOL;
  "
  ```
- **Expect**:
  - `before: 3` (1 original + 2 injected)
  - `cancelled: 2`
  - `after: 1`

### 3. Idempotency — second sweep on clean state is a no-op
- **Action**:
  ```
  wp eval "\$r = \WBListora\Workflow\Cron_Scheduler::dedupe_pending_batch(['wb_listora_daily_cleanup']); echo 'cancelled: '.\$r['wb_listora_daily_cleanup'];"
  ```
- **Expect**: `cancelled: 0`. The sweep ran but did no work.

### 4. Bootstrap sweep fires via init priority 16
- **Action**: inject another duplicate, then trigger any frontend or admin request (which fires `init`):
  ```
  wp eval "as_schedule_recurring_action(time()+60, DAY_IN_SECONDS, 'wb_listora_daily_cleanup', [], 'wb-listora'); echo count(as_get_scheduled_actions(['hook'=>'wb_listora_daily_cleanup','status'=>'pending','group'=>'wb-listora','per_page'=>10],'ids'));"
  ```
  → reports `2` (duplicate present). Now issue any HTTP request to the site (e.g. `curl -s $SITE_URL/?test=1 >/dev/null`). Then re-check:
  ```
  wp eval "echo count(as_get_scheduled_actions(['hook'=>'wb_listora_daily_cleanup','status'=>'pending','group'=>'wb-listora','per_page'=>10],'ids'));"
  ```
- **Expect**: post-request count is `1`. The bootstrap hook detected + cancelled the duplicate on the next page-load.

### 5. Group + has_action_scheduler gate
- **Action**:
  ```
  wp eval "echo \WBListora\Workflow\Cron_Scheduler::has_action_scheduler() ? 'as-ready' : 'as-not-ready';"
  ```
- **Expect**: `as-ready`. If `as-not-ready` is reported, the dedup-batch must be a no-op (verified by next sub-step):
  ```
  wp eval "\$r = \WBListora\Workflow\Cron_Scheduler::dedupe_pending_batch(['wb_listora_daily_cleanup']); echo json_encode(\$r);"
  ```
  Expect either `{"wb_listora_daily_cleanup":0}` (AS ready, nothing to do) or `{"wb_listora_daily_cleanup":0}` (AS not ready, function returns 0).

## Notes

- The fix doesn't try to PREVENT the activation race (would require database-level locking around a cross-request operation). It instead self-heals on every request — within one page-load after a duplicate appears, the cancel path runs.
- New recurring hooks added in the future MUST be appended to the `$known_hooks` list in `Plugin::dedupe_recurring_cron()`. The PR adding the hook should also add a row here in the "hooks under sentinel" list.
- The sweep is Free-only. Pro hooks (group `wb-listora-pro`) are not currently swept — Pro recurring schedulers should add their own equivalent if needed (`Notification_Digest::send_digest`, `Audit_Log` cleanup, `Outgoing_Webhooks` log prune all schedule via `Cron_Scheduler` which has the in-request guard, but no cross-request sweep).
