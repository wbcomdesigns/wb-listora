---
journey: cron-scheduler-deferred-init
plugin: wb-listora
priority: high
roles: [system]
covers: [cron-scheduler, action-scheduler-init-timing, debug-log-cleanliness]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP_DEBUG + WP_DEBUG_LOG enabled"
  - "Both wb-listora and wb-listora-pro active"
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# Cron Scheduler defers Action Scheduler calls until its data store is ready

Every `as_*()` call must be guarded behind `did_action( 'action_scheduler_init' )`. Calls before that point emit `_doing_it_wrong` notices that spam `debug.log` on every WP-CLI invocation and admin page load during bootstrap. The guard lives in ONE place: `Cron_Scheduler::has_action_scheduler()` (`includes/workflow/class-cron-scheduler.php:88`), which `schedule_recurring()` (`:124`) consults; Pro's `maybe_schedule_*` methods delegate to `Cron_Scheduler::schedule_recurring( ..., 'wb-listora-pro' )` rather than carrying their own checks (`wb-listora-pro/includes/features/class-analytics.php:119`, `class-advanced-search.php:81`, `class-audit-log.php:77`, `class-notification-digest.php:268`). `schedule_recurring()` also returns early unless the request is a scheduling request (cron, wp-admin non-AJAX, WP-CLI), so a visitor request never queries the schedule.

System journey by design: nothing here is role-visible. The role dimension that matters is the request type: WP-CLI, wp-admin, front-end, REST.

## Setup

- Site `$SITE_URL`. `WP_DEBUG` + `WP_DEBUG_LOG` enabled; both wb-listora and wb-listora-pro active.
- Note the log size, or truncate it: `: > wp-content/debug.log` (on a shared QA site prefer recording `wc -l wp-content/debug.log` and counting only NEW lines).

## Steps

### 1. Run a WP-CLI command that hits both plugins
- **Action**: `wp plugin list --status=active --format=csv`
- **Expect**: completes successfully.

### 2. Check for AS init-timing notices
- **Action**: `grep -c "as_next_scheduled_action\|as_schedule_recurring_action" wp-content/debug.log` (new lines only).
- **Expect**: `0` (verified 2026-10-12).
- **On fail**: the `did_action( 'action_scheduler_init' )` guard was removed from `Cron_Scheduler::has_action_scheduler()` (`class-cron-scheduler.php:92`), or a call site started calling `as_*()` directly instead of going through `Cron_Scheduler` (grep Pro for `as_schedule_recurring_action(` / `as_next_scheduled_action(`).

### 3. The jobs ARE scheduled despite the deferral
- **Action**:
  ```bash
  wp action-scheduler action list --status=pending --group=wb-listora --fields=hook --format=csv
  wp action-scheduler action list --status=pending --group=wb-listora-pro --fields=hook --format=csv
  ```
  (the subcommand is `action list`; there is no `wp action-scheduler list`.)
- **Expect**: 7 distinct recurring hooks in `wb-listora` (`wb_listora_check_expirations`, `_draft_reminder_cron`, `_daily_cleanup`, `_expire_featured`, `_cleanup_unverified_listings`, `_prune_email_log`, `_review_reminder_cron`; one may be momentarily `in-progress` while running) and 6 in `wb-listora-pro` (`_audit_cleanup`, `_analytics_cleanup`, `_saved_search_alerts`, `_webhook_log_cleanup`, `_expire_needs`, `_license_check`; plus `_send_digest` when digest mode is on). The Pro group also lists `wb_listora_pro_deliver_webhook` one-shot rows, which are not recurring.
- Confirms the deferral does not break scheduling: the next scheduling request after AS init picks it up.

### 4. Hit a front-end page, a wp-admin page and a REST endpoint
- **Action**:
  ```bash
  curl -s -o /dev/null $SITE_URL/listings/
  curl -s -o /dev/null -b <admin jar> $SITE_URL/wp-admin/index.php
  curl -s -o /dev/null $SITE_URL/wp-json/listora/v1/listings
  ```
- **Expect**: zero new AS init-timing notices in `debug.log`.

### 5. Recurring jobs run successfully
- **Action**: `wp action-scheduler run --hooks=wb_listora_check_expirations`
- **Expect**: exit 0, no fatal in `debug.log`. (When the hook is not due, output is "0 scheduled tasks completed."; force one with `wp eval 'as_enqueue_async_action("wb_listora_check_expirations",[],"wb-listora");'` first.)

### 6. Positive control
- **Control**: step 3 (jobs present) is the control for step 2 (no notices): a plugin that stopped scheduling would trivially have no notices.

## Pass criteria

1. Zero `_doing_it_wrong` notices for `as_next_scheduled_action` / `as_schedule_recurring_action` in `debug.log` after a CLI + admin + front-end + REST round-trip.
2. All recurring jobs (7 Free + 6 Pro, 7 with digest mode) still scheduled in their groups.
3. Manually firing a recurring hook completes without a fatal.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Notices spam `debug.log` on WP-CLI runs | guard removed | `includes/workflow/class-cron-scheduler.php:88-93` (`has_action_scheduler()` must include `did_action( 'action_scheduler_init' ) > 0`) |
| Notices on first admin page load only | a caller bypasses `Cron_Scheduler` | Pro `class-analytics.php:119`, `class-advanced-search.php:81`, `class-audit-log.php:77`, `class-notification-digest.php:268` |
| Jobs missing from AS list entirely | guard never true, or `is_scheduling_request()` never true | `class-cron-scheduler.php:73`, `:124-135` |

## Verified

Steps 2 (0 matching lines in `debug.log`), 3 (list output, group counts as above) and 5 (`0 scheduled tasks completed`, exit 0) were run live with wp-cli on 2026-10-12. Steps 1 and 4 curl requests were made. Note: this QA site's `debug.log` is shared with other runs; the count of `0` is for the whole file.
