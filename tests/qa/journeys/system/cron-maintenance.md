---
journey: cron-maintenance
plugin: wb-listora
priority: normal
roles: [system]
covers: [cron, draft-reminder, daily-cleanup, cleanup-unverified, search-reindex]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI available"
  - "Action Scheduler available (bundled in Free)"
estimated_runtime_minutes: 6
covers_card: null
---

# Maintenance cron jobs fire and produce their side-effects

System sentinel for the four maintenance crons that lacked a journey. Each is
scheduled via `Cron_Scheduler` (Action Scheduler, WP-Cron fallback) and is
driven here with `wp action-scheduler run` (or `wp cron event run`).

## Steps

### 1. All six recurring jobs are scheduled
- **Action**: `wp eval` over `Cron_Scheduler::has_scheduled()` for each hook, or `wp action-scheduler list --group=...`.
- **Expect**: `wb_listora_check_expirations`, `wb_listora_draft_reminder_cron`, `wb_listora_daily_cleanup`, `wb_listora_expire_featured`, `wb_listora_cleanup_unverified_listings`, `wb_listora_search_reindex` are each scheduled exactly once (no duplicates — the request-static + `has_scheduled` dedup guards).

### 2. Draft reminder
- **Setup**: a `draft` listing older than the reminder threshold owned by a user.
- **Action**: run `wb_listora_draft_reminder_cron`.
- **Expect**: the owner receives the draft-reminder email exactly once (reminder flag set so a second run does not re-send).

### 3. Daily cleanup
- **Action**: run `wb_listora_daily_cleanup`.
- **Expect**: stale transients / expired tokens / orphaned rows are pruned per the cleanup routine; no fatal; idempotent on a second run.

### 4. Cleanup unverified
- **Setup**: a `pending_verification` listing whose verification token is older than the grace window.
- **Action**: run `wb_listora_cleanup_unverified_listings`.
- **Expect**: the stale unverified listing is removed/trashed; a still-fresh unverified listing is left intact.

### 5. Search reindex
- **Action**: run `wb_listora_search_reindex` (or `wp listora reindex`).
- **Expect**: the `search_index` table is rebuilt; counts match published+indexable listings; a newly-created listing becomes searchable after the run.

### 6. No notice spam
- **Expect**: across all runs, `debug.log` has zero `_doing_it_wrong` notices for `as_*()` (AS data store readiness is gated by `did_action('action_scheduler_init')`).
