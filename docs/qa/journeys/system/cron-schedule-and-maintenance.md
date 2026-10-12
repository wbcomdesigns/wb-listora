---
journey: cron-schedule-and-maintenance
plugin: wb-listora
priority: high
roles: [system, administrator, anonymous]
covers: [cron, cron-scheduler, action-scheduler-init-timing, debug-log-cleanliness, draft-reminder, daily-cleanup, cleanup-unverified, search-reindex, wp-cli-cleanup, bc-10393575329]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH; WP_DEBUG + WP_DEBUG_LOG on"
  - "Action Scheduler available (bundled in Free); Free and Pro both active for the Pro group checks"
  - "Steps 4-6 on a SCRATCH site: a site with many old drafts would have the draft-reminder job flag and email up to a batch of real drafts"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:system/cron-maintenance.md
  - ora:regression/cron-scheduler-deferred-init.md
fails_on: step 9 fails on 5b224ee6; steps 2-3 pre-fix sha unknown
site_profile: required
---

# Maintenance cron jobs are scheduled once through Cron_Scheduler, never spam the log, and produce their side-effects

## Why
Every `as_*()` call must go through `Cron_Scheduler` (`Cron_Scheduler::has_action_scheduler()` guards on `did_action( 'action_scheduler_init' )`, one place) or `_doing_it_wrong` notices spam `debug.log` on every WP-CLI call and bootstrap; `schedule_recurring()` also returns early unless the request is a scheduling request (cron, wp-admin non-AJAX, WP-CLI) so a visitor never queries the schedule. The jobs must exist exactly once and do their work. The duplicate-pending self-heal sweep (`Plugin::dedupe_recurring_cron()`) is `system/cron-recurring-dedupe.md`; step 1 here owns the exactly-once assertion and that file refers back to it.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Note `wc -l wp-content/debug.log` and count only NEW lines.
- Subcommand is `wp action-scheduler action list` (there is no `wp action-scheduler list`).

## Steps

### 1. Recurring jobs are scheduled exactly once
- **Action**: `wp action-scheduler action list --status=pending --group=wb-listora --fields=hook --format=csv` and the same for `--group=wb-listora-pro`.
- **Expect**: 7 Free hooks each with exactly ONE pending action: `wb_listora_check_expirations` (twice daily), `wb_listora_draft_reminder_cron` (twice daily), `wb_listora_review_reminder_cron` (daily), `wb_listora_daily_cleanup` (daily), `wb_listora_expire_featured`, `wb_listora_cleanup_unverified_listings`, `wb_listora_prune_email_log` (one may be momentarily `in-progress`); Pro adds `wb_listora_pro_analytics_cleanup`, `_audit_cleanup`, `_license_check`, `_saved_search_alerts`, `_webhook_log_cleanup`, `_expire_needs` (and `_send_digest` with digest mode on); `wb_listora_pro_deliver_webhook` one-shot rows are not recurring. WP-Cron holds none of them.
- **Positive control**: a duplicate shows as COUNT 2; visit any front page twice, re-run, counts stay 1.
- **Note**: `wb_listora_search_reindex` is a transient on-demand single event, never a standing job (the reindex button and cursor: `admin/health-check-and-reindex.md`).

### 2. No Action Scheduler init-timing notices
- **Action**: `wp plugin list --status=active --format=csv`, then `grep -c "as_next_scheduled_action\|as_schedule_recurring_action" wp-content/debug.log` (new lines only).
- **Expect**: `0`. On fail the `did_action( 'action_scheduler_init' )` guard was removed, or a call site (Pro `class-analytics.php`, `class-advanced-search.php`, `class-audit-log.php`, `class-notification-digest.php`) bypasses `Cron_Scheduler`.

### 3. Request types (front end, wp-admin, REST)
- **Action**: `curl` `$SITE_URL/listings/`, `$SITE_URL/wp-admin/index.php` (admin jar), `$SITE_URL/wp-json/listora/v1/listings`.
- **Expect**: zero new notices; the jobs of step 1 are still present (the deferral does not break scheduling: a plugin that stopped scheduling would trivially have no notices, so step 1 is the control).

### 4. Draft reminder (scratch)
- **Setup**: a `draft` listing with `post_modified_gmt` older than 48 h, owned by {owner}, no `_listora_draft_reminded` meta.
- **Action**: `wp action-scheduler run --hooks=wb_listora_draft_reminder_cron` (or `do_action`).
- **Expect**: `wb_listora_draft_reminder` fires, {owner} gets the email once, `_listora_draft_reminded` is set so a second run does not re-send (bounded by `wb_listora_draft_reminder_max_per_run`). Negative control: a draft modified 1 hour ago is left alone.

### 5. Daily cleanup
- **Action**: `wp listora cleanup` (same as `do_action( 'wb_listora_daily_cleanup' )` plus email-log retention).
- **Expect**: exit 0 and output `Running WB Listora cleanup tasks...`, `  Email log retention: N row(s) pruned.`, `  Analytics retention prune: done.`, `  Stale unverified listings: cleaned up.`, `  Orphaned listing rows purged: N.`, `Success: Cleanup complete.`; `Leads::prune()` removes leads older than the retention months, `prune_analytics` and `purge_orphans_cron` run; a second run changes nothing (idempotent). Positive control: seed a lead with `created_at` 400 days ago and assert it is gone while today's stays. `cleanup` appears under `wp help listora`.

### 6. Cleanup unverified
- **Setup**: a `pending_verification` listing older than `unverified_listings_max_days` (default 7) and a fresh one.
- **Action**: run `wb_listora_cleanup_unverified_listings`.
- **Expect**: the stale one is trashed (`unverified_listings_action`, default `trash`) and `wb_listora_unverified_listing_cleaned` fires; the fresh one is untouched.

### 7. Search reindex on demand
- **Action**: `wp listora reindex` (`--type=<slug>`, `--dry-run`) or let the single event run after a data change.
- **Expect**: `listora_search_index` rebuilt, count equals published listings, a newly created listing appears in `GET /listora/v1/search`. The cron path works in chunks and re-queues itself (`wb_listora_reindex_offset` deleted when the chain ends); at 2000+ listings expect several chunks.

### 8. A recurring hook fires cleanly
- **Action**: force one: `wp eval 'as_enqueue_async_action("wb_listora_check_expirations",[],"wb-listora");'` then `wp action-scheduler run --hooks=wb_listora_check_expirations`.
- **Expect**: exit 0, no fatal in `debug.log` ("0 scheduled tasks completed." when not due).

### 9. A visitor page load runs no schedule work (card 10393575329)
- **Setup**: mu-plugin `wp-content/mu-plugins/qa-as-probe.php` (remove afterwards) with `SAVEQUERIES` on: `add_action( 'shutdown', function () { global $wpdb; foreach ( (array) $wpdb->queries as $q ) { if ( false !== strpos( $q[0], 'actionscheduler_actions' ) && false !== strpos( $q[0], 'wb_listora_' ) ) { error_log( 'LISTORA_AS_QUERY ' . $q[0] ); } } } );`; `as_unschedule_all_actions( 'wb_listora_daily_cleanup' )`.
- **Action**: empty `debug.log`; `curl` `$SITE_URL/` and `/listings/` as {guest}; `grep -c LISTORA_AS_QUERY wp-content/debug.log`.
- **Expect**: `0`, and `wb_listora_daily_cleanup` is still unscheduled. Positive control: load `/wp-admin/` as {admin} once: the job is scheduled again and the grep count is >= 1. Not verified live.

### 10. Timezone and scale (added)
- **Action**: run steps 1 and 4 with site timezone UTC+14 then UTC-12 (restore UTC); repeat step 5 with 2000+ leads/analytics rows.
- **Expect**: schedules are independent of the site timezone; cleanup works in bounded batches. Not verified live.

## Role ladder
System journey: the actor is the request type. {guest} front end (step 9), {admin} wp-admin (step 9 control), WP-CLI (all other steps), REST (step 3). No owner object exists.

## Pass criteria
1. Each recurring job has exactly one pending action in its group.
2. Zero Action Scheduler init-timing notices after CLI + admin + front-end + REST.
3. Draft reminder, cleanup, unverified cleanup and reindex produce their documented side-effects and are idempotent.
4. A visitor request never schedules or queries schedule state.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Hook scheduled twice | dedup guard | `includes/workflow/class-cron-scheduler.php` `schedule_recurring` |
| Hook not scheduled | scheduling-request gate | `class-cron-scheduler.php`, `includes/workflow/class-expiration-cron.php` |
| `_doing_it_wrong` for `as_*()` | guard removed / caller bypasses scheduler | `class-cron-scheduler.php` `has_action_scheduler()` |
| Draft reminder re-sent | flag not set | `class-expiration-cron.php` `send_draft_reminders` |
| Leads never pruned | cleanup listener | `includes/core/class-leads.php` (`init`, `prune`) |
| Unverified listing not trashed | cron handler | `includes/workflow/class-email-verification.php` |
| Reindex stalls | cursor | `includes/search/class-search-indexer.php` `process_scheduled_reindex` |
| `cleanup` fatals | wiring drift | `Notifications::prune_log()`, `wb_listora_daily_cleanup` listeners, `Email_Verification::CRON_HOOK`, `Listing_Data_Eraser::purge_orphans()` |
| `cleanup` not a subcommand | method dropped | `includes/class-cli-commands.php` |

## Not verified
Steps 4-7 and 9 were not executed (the reference site holds thousands of old drafts); steps 1-3 and 8 ran live on 2026-10-12 (step 8 reported "0 scheduled tasks completed.").

## Cleanup
Remove `mu-plugins/qa-as-probe.php`; re-arm any job you unscheduled by loading wp-admin once; delete seeded drafts, leads and the pending_verification listings.
