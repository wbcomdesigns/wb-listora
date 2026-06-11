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
---

# Cron Scheduler defers AS calls until data store is ready

The `Cron_Scheduler::has_action_scheduler()` check + the three Pro `maybe_schedule_*` methods must all guard `as_*()` calls behind `did_action( 'action_scheduler_init' )`. Calls before that point emit `_doing_it_wrong` notices that spam debug.log on every WP-CLI invocation and admin pageload during bootstrap. Pre-fix discovered 2026-05-09 during smoke prep.

## Setup

- Site: `$SITE_URL`
- Truncate debug.log:
  ```bash
  > /Users/varundubey/Local\ Sites/directory/app/public/wp-content/debug.log
  ```

## Steps

### 1. Run a WP-CLI command that hits both plugins
- **Action**:
  ```bash
  wp plugin list --status=active --format=csv
  ```
- **Expect**: command completes successfully

### 2. Check for AS init-timing notices
- **Action**:
  ```bash
  grep -c "as_next_scheduled_action\|as_schedule_recurring_action" wp-content/debug.log
  ```
- **Expect**: `0`
- **On fail**: regression — `did_action('action_scheduler_init')` guard removed from one of:
  - `wb-listora/includes/workflow/class-cron-scheduler.php::has_action_scheduler()`
  - `wb-listora-pro/includes/features/class-analytics.php::maybe_schedule_cleanup()`
  - `wb-listora-pro/includes/features/class-advanced-search.php::maybe_schedule_alerts()`
  - `wb-listora-pro/includes/features/class-audit-log.php::maybe_schedule_cleanup()`

### 3. Verify cron jobs ARE scheduled despite the deferral
- **Action**:
  ```bash
  wp action-scheduler list --status=pending --group=wb-listora --format=count
  wp action-scheduler list --status=pending --group=wb-listora-pro --format=count
  ```
- **Expect**: ≥6 pending in `wb-listora` group, ≥6 pending in `wb-listora-pro` group. Confirms the deferral doesn't break scheduling — next page-load (after AS init fires) picks up the scheduling cleanly.

### 4. Hit a frontend page + admin page + AJAX endpoint
- **Action**:
  ```bash
  curl -s -o /dev/null $SITE_URL/listings/
  curl -s -o /dev/null "$SITE_URL/wp-admin/?autologin=1"
  curl -s -o /dev/null "$SITE_URL/wp-json/listora/v1/listings"
  ```
- **Expect**: zero new AS init-timing notices in debug.log

### 5. Verify recurring jobs run successfully
- **Action**:
  ```bash
  wp action-scheduler run --hooks=wb_listora_check_expirations
  ```
- **Expect**: 0 exit code, no fatal in debug.log

## Pass criteria

1. Zero `_doing_it_wrong` notices for `as_next_scheduled_action` or `as_schedule_recurring_action` in debug.log after a fresh CLI/web/REST round-trip
2. All 13 cron jobs (6 Free + 7 Pro) still scheduled in their respective AS groups
3. Manually firing a recurring hook completes without fatal

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Notices spam debug.log on WP-CLI runs | regression — guard removed | `class-cron-scheduler.php::has_action_scheduler` must include `did_action('action_scheduler_init') > 0` |
| Notices on first admin pageload only | guard exists but `maybe_schedule_*` missing the same check | the three Pro feature files |
| Cron jobs missing from AS list entirely | guard too aggressive — never returns true | check `did_action()` semantics; AS init must have fired by the time cron-firing requests run |
