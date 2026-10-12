---
journey: cron-recurring-dedupe
plugin: wb-listora
priority: high
site_profile: required
roles: [admin, guest, system]
covers: [cron-scheduler-dedupe-pending, cron-scheduler-dedupe-batch, plugin-bootstrap-cleanup-sweep, card-9910208588, commit-a4b4e6f]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Free active (Action Scheduler is bundled in Free's vendor/woocommerce/action-scheduler; no Pro required)"
  - "Action Scheduler runtime ready (did_action('action_scheduler_init') > 0 by the time init:16 fires)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes: []
fails_on: "pre-a4b4e6f (no bootstrap sweep: a duplicate pending action stays pending)"
---

# Cron Scheduler auto-deduplicates pending recurring actions on bootstrap

## Why

A cross-request activation race can leave two pending Action Scheduler actions for one recurring hook, so every callback (expiration, cleanup, reminders) fires twice. `Plugin::dedupe_recurring_cron()` (`includes/class-plugin.php:900`, `init` priority 16) self-heals by cancelling extras, but only on a scheduling request (`wp_doing_cron()`, wp-admin page, WP-CLI: `Cron_Scheduler::is_scheduling_request()`, `includes/workflow/class-cron-scheduler.php:73`), never on a visitor or REST request. Pro registers its hooks through the `wb_listora_dedupe_recurring_hooks` filter.

## Setup

- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Site `$SITE_URL`, `wp` commands run with `--path=$WP_PATH`. Action Scheduler ready (`did_action( 'action_scheduler_init' ) > 0`); bundled in Free's vendor.
- The hooks under sentinel (Free, group `wb-listora`):
  ```
  wb_listora_check_expirations
  wb_listora_draft_reminder_cron
  wb_listora_daily_cleanup
  wb_listora_expire_featured
  wb_listora_cleanup_unverified_listings
  wb_listora_prune_email_log          (Notifications::PRUNE_HOOK; the old name wb_listora_email_log_prune matched nothing)
  wb_listora_review_reminder_cron
  ```
  Pro (group `wb-listora-pro`): `wb_listora_pro_audit_cleanup`, `wb_listora_pro_analytics_cleanup`, `wb_listora_pro_saved_search_alerts`, `wb_listora_pro_send_digest` (only scheduled when digest mode is on), `wb_listora_pro_webhook_log_cleanup`, `wb_listora_pro_expire_needs`, `wb_listora_pro_license_check`.
- IMPORTANT: every `wp` command is itself a scheduling request, so it sweeps during bootstrap, before your script runs. To observe a duplicate that no request has swept yet, count with `wp --skip-plugins=wb-listora,wb-listora-pro eval` (plugins not loaded, no sweep) and read the table directly:
  `SELECT COUNT(*) FROM ${PREFIX}actionscheduler_actions WHERE hook = '<hook>' AND status = 'pending'`.

## Steps

### 1. Baseline: every recurring hook has exactly 1 pending action
- **Action**: run step 1 of `system/cron-schedule-and-maintenance.md`, which owns the "scheduled exactly once" assertion for the seven Free hooks (and the Pro group). This step only adds the stale-name control.
- **Expect**: no `:2` or more; a hook in the Setup list that is not a real job (e.g. the old `wb_listora_email_log_prune`) counts `:0` forever: that is the stale-name defect.

### 2. Inject 2 duplicates, sweep removes them
- **Action** (single process; the in-process counts are what you read):
  ```
  wp eval '
  as_schedule_recurring_action( time()+60,  DAY_IN_SECONDS, "wb_listora_daily_cleanup", [], "wb-listora" );
  as_schedule_recurring_action( time()+120, DAY_IN_SECONDS, "wb_listora_daily_cleanup", [], "wb-listora" );
  $n = function() { return count( as_get_scheduled_actions( ["hook"=>"wb_listora_daily_cleanup","status"=>"pending","group"=>"wb-listora","per_page"=>10], "ids" ) ); };
  echo "before: ", $n(), PHP_EOL;
  $r = \WBListora\Workflow\Cron_Scheduler::dedupe_pending_batch( ["wb_listora_daily_cleanup"] );
  echo "cancelled: ", $r["wb_listora_daily_cleanup"], PHP_EOL, "after: ", $n(), PHP_EOL;'
  ```
- **Expect**: `before: 3`, `cancelled: 2`, `after: 1`.

### 3. Idempotency: a second sweep on clean state is a no-op
- **Action**: `wp eval 'print_r( \WBListora\Workflow\Cron_Scheduler::dedupe_pending_batch( ["wb_listora_daily_cleanup"] ) );'`
- **Expect**: `[wb_listora_daily_cleanup] => 0`.

### 4. Bootstrap sweep fires on a scheduling request, not on a visitor request
- **4a. Setup**: inject one duplicate (`as_schedule_recurring_action( time()+60, DAY_IN_SECONDS, "wb_listora_daily_cleanup", [], "wb-listora" )` in a `wp eval`), then count with `wp --skip-plugins=wb-listora,wb-listora-pro eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE hook=\"wb_listora_daily_cleanup\" AND status=\"pending\"");'`.
- **Expect**: `2`.
- **4b. Positive control inverse**: `curl -s -o /dev/null $SITE_URL/listings/` and `curl -s -o /dev/null $SITE_URL/wp-json/listora/v1/listings`, then recount (plugins-skipped command).
- **Expect**: still `2` (verified 2026-10-12): front-end and REST requests do not sweep.
- **4c**: load a wp-admin page as an administrator (`curl -b <{admin} cookie jar> $SITE_URL/wp-admin/index.php`), then recount.
- **Expect**: `1`: the next admin page-load (or cron run, or any WP-CLI command) cancels the duplicate.
- Other agents or visitors hitting wp-admin / WP-CLI at the same moment can sweep your duplicate before you count; repeat if the injected count reads 1.

### 5. Group + has_action_scheduler gate
- **Action**: `wp eval 'echo \WBListora\Workflow\Cron_Scheduler::has_action_scheduler() ? "as-ready" : "as-not-ready";'`
- **Expect**: `as-ready` (when not ready, `dedupe_pending_batch()` must be a no-op returning zeros).

### 6. Pro hooks are swept through the filter
- **Action**: `wp eval 'echo json_encode( apply_filters( "wb_listora_dedupe_recurring_hooks", [] ) );'`
- **Expect**: a map of the `wb_listora_pro_*` hooks above to `"wb-listora-pro"`. A hook missing from the map is never deduplicated, silently.

### 7. Maintenance contract (read-only)
- **Action**: none; applies when a recurring hook is added.
- **Expect**:

- The fix does not PREVENT the activation race (that would need cross-request DB locking); it self-heals within one scheduling request.
- New recurring hooks MUST be appended to `$known_hooks` in `Plugin::dedupe_recurring_cron()` (Free) or the Pro filter map; the PR adding one also adds a row to the list above.

## Role ladder

| Actor | Request | Sweeps duplicates? |
|---|---|---|
| {guest} | front-end `$SITE_URL/` and REST `listings` | No (step 4b) |
| {admin} | any wp-admin page load | Yes (step 4c) |
| system | wp-cron / WP-CLI | Yes (steps 2, 3) |

## Pass criteria

1. Baseline: each Free recurring hook holds exactly 1 pending action.
2. `dedupe_pending_batch()` cancels injected duplicates and is idempotent.
3. Visitor and REST requests leave a duplicate in place; a wp-admin request removes it.
4. Pro hooks appear in the filter map with group `wb-listora-pro`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| A hook prints `:2` or more after an admin load | hook missing from the sweep list or wrong group | `includes/class-plugin.php:904-921`, Pro `class-pro-plugin.php:140` |
| Duplicates never cancelled | `is_scheduling_request()` false for the caller, or AS not ready | `includes/workflow/class-cron-scheduler.php:73`, `:88` |
| Sweep cancels the wrong one / all | batch logic | `includes/workflow/class-cron-scheduler.php:285` (`dedupe_pending`), `:337` (`dedupe_pending_batch`) |
| A hook prints `:0` | never scheduled, or stale hook name | `Cron_Scheduler::schedule_recurring()` `:124` |

## Not verified

All steps ran live 2026-10-12 (baseline all `:1`; `before: 3 / cancelled 2 / after: 1`; second sweep `0`; visitor and REST left 2; wp-admin gave 1). Not run: the activation race itself (needs cross-request DB locking to reproduce), and Pro's hooks being swept end to end (step 6 only reads the filter map).

## Cleanup

The pending state is left at 1 per hook; if an injected duplicate remains, run `wp eval '\WBListora\Workflow\Cron_Scheduler::dedupe_pending_batch( ["wb_listora_daily_cleanup"] );'`.
