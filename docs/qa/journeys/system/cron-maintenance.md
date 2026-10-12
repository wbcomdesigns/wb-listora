---
journey: cron-maintenance
plugin: wb-listora
priority: normal
roles: [system]
covers: [cron, draft-reminder, daily-cleanup, cleanup-unverified, search-reindex, bc-10393575329]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI available"
  - "Action Scheduler available (bundled in Free)"
estimated_runtime_minutes: 6
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---
<!-- System journey (cron/CLI): no frontend, no owner object, no viewport or theme, so no role ladder. Step 1 verified live against the Action Scheduler table on the QA site; steps 2-6 verified from source and NOT executed (see "Not verified"): this site holds 2,533 admin drafts older than 48h, so running the draft-reminder job here would flag and email up to a batch of real drafts. -->

# Maintenance cron jobs fire and produce their side-effects

System sentinel for the four maintenance crons that lacked a journey. Each is
scheduled via `Cron_Scheduler` (Action Scheduler, WP-Cron fallback) and is
driven here with `wp action-scheduler run` (or `wp cron event run`).

## Steps

### 1. All seven recurring jobs are scheduled
- **Action**: `wp action-scheduler list --status=pending --group=wb-listora --format=csv` or `wp eval 'global $wpdb; print_r($wpdb->get_results("SELECT hook,COUNT(*) c FROM {$wpdb->prefix}actionscheduler_actions WHERE hook LIKE \"wb_listora%\" AND status=\"pending\" GROUP BY hook"));'` (Action Scheduler is the scheduler here; WP-Cron holds none of them).
- **Expect (observed 2026-10-12)**: `wb_listora_check_expirations` (twice daily), `wb_listora_draft_reminder_cron` (twice daily), `wb_listora_review_reminder_cron` (daily), `wb_listora_daily_cleanup` (daily), `wb_listora_expire_featured`, `wb_listora_cleanup_unverified_listings` and `wb_listora_prune_email_log` each have exactly ONE pending action (no duplicates — the request-static + `has_scheduled` dedup guards). Pro adds its own (`wb_listora_pro_analytics_cleanup`, `_audit_cleanup`, `_license_check`, `_saved_search_alerts`, `_webhook_log_cleanup`, `_expire_needs`); they are not part of this journey.
- **POSITIVE CONTROL / negative**: a duplicate would show as COUNT 2; none is present. Visit any front page twice, re-run, and the counts stay 1.
- **Note**: `wb_listora_search_reindex` is NOT a recurring job — it is an on-demand WP-Cron single-event queued ~30-60s after a data change (see step 4), so it only appears in the schedule transiently. Do not assert it as a standing recurring action.

### 2. Draft reminder
- **Setup (scratch site only)**: a `draft` listing whose `post_modified_gmt` is older than 48 hours, owned by a member, with no `_listora_draft_reminded` meta. Do NOT run this on a site that already holds many old drafts: the job processes them all up to `wb_listora_draft_reminder_max_per_run`.
- **Action**: `wp action-scheduler run --hooks=wb_listora_draft_reminder_cron` (or `do_action( 'wb_listora_draft_reminder_cron' )`).
- **Expect**: `wb_listora_draft_reminder` fires for the draft, the owner receives the draft-reminder email once, and `_listora_draft_reminded` is set so a second run does not re-send. NEGATIVE CONTROL: a draft modified 1 hour ago is left alone.

### 3. Daily cleanup
- **Action**: `wp listora cleanup` (same as `do_action( 'wb_listora_daily_cleanup' )` plus email-log retention) or run the hook.
- **Expect**: the listeners on `wb_listora_daily_cleanup` run: `Leads::prune()` (leads older than the retention months), analytics retention (`prune_analytics`), orphaned search-index and listing-data rows (`purge_orphans_cron`); no fatal; a second run changes nothing (idempotent). POSITIVE CONTROL: seed a lead with `created_at` 400 days ago and assert it is gone; a lead from today stays.

### 4. Cleanup unverified
- **Setup**: a `pending_verification` listing older than the grace window (`unverified_listings_max_days`, default 7) and a fresh one.
- **Action**: run `wb_listora_cleanup_unverified_listings`.
- **Expect**: the stale one is trashed (`unverified_listings_action`, default `trash`) and `wb_listora_unverified_listing_cleaned` fires; the fresh one is untouched.

### 5. Search reindex
- **Action**: `wp listora reindex` (`--type=<slug>`, `--dry-run` available), or let the single event `wb_listora_search_reindex` run after a data change.
- **Expect**: `listora_search_index` is rebuilt; its count matches the published listings; a newly created listing appears in `GET /listora/v1/search`. The cron path works in chunks and re-queues itself (option `wb_listora_reindex_offset` is deleted when the chain ends). At 2,809 listings expect several chunks, not one request.

### 6. No notice spam
- **Expect**: across all runs, `debug.log` has zero `_doing_it_wrong` notices for `as_*()` (AS data store readiness is gated by `did_action('action_scheduler_init')`).

### 7. A visitor page load runs no schedule work (card 10393575329)
- **Setup**: mu-plugin `wp-content/mu-plugins/qa-as-probe.php` (remove afterwards), with `SAVEQUERIES` on: `add_action( 'shutdown', function () { global $wpdb; foreach ( (array) $wpdb->queries as $q ) { if ( false !== strpos( $q[0], 'actionscheduler_actions' ) && false !== strpos( $q[0], 'wb_listora_' ) ) { error_log( 'LISTORA_AS_QUERY ' . $q[0] ); } } } );`. Make a recurring job due: `as_unschedule_all_actions( 'wb_listora_daily_cleanup' )`.
- **Action** (anonymous): empty `debug.log`; `curl -s -o /dev/null $SITE_URL/` and `$SITE_URL/listings/`; `grep -c LISTORA_AS_QUERY wp-content/debug.log`.
- **Expect**: `0`, and `wb_listora_daily_cleanup` is still unscheduled afterwards (a visitor never re-arms it).
- **POSITIVE CONTROL**: load `/wp-admin/` as admin once: the job is scheduled again and the grep count is >= 1.
- **fails_on**: 5b224ee6. Not verified live.

## Fail diagnostics

| Symptom | Likely cause | File:line |
|---|---|---|
| A hook is scheduled twice | dedup guard | `includes/workflow/class-cron-scheduler.php:124` (`schedule_recurring`) |
| A hook is not scheduled | scheduling request gate | `includes/workflow/class-cron-scheduler.php:73`, `includes/workflow/class-expiration-cron.php:31` |
| Draft reminder re-sent | flag not set | `includes/workflow/class-expiration-cron.php:295` (`send_draft_reminders`) |
| Leads never pruned | cleanup listener | `includes/core/class-leads.php:38` (`init`), `:275` (`prune`) |
| Unverified listing not trashed | cron handler | `includes/workflow/class-email-verification.php:518` |
| Reindex stalls | cursor | `includes/search/class-search-indexer.php:92` (`process_scheduled_reindex`) |
| `_doing_it_wrong` for `as_*()` | called before AS init | `includes/workflow/class-cron-scheduler.php:88` |

## Not verified

- Steps 2 to 5 were not executed (reasons in the header comment); only the schedule (step 1) was checked live.
- Step 6 (no `_doing_it_wrong` notices) was not diffed against debug.log.
