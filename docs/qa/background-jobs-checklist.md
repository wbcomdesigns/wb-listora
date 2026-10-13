# Background jobs: surface checklist (Free + Pro)

Run this whenever scheduling code changes (the job list, `Cron_Scheduler`, a
deactivator, a feature that owns a job). It answers one question: does every
job still run, once, and does every surface it feeds still change?

Standard: `docs/standards/background-jobs.md`. All recurring jobs are declared in
`Cron_Scheduler::jobs()` (Free) or added through the `wb_listora_cron_jobs`
filter (Pro features, only while the feature is on). One-off jobs go through
`Cron_Scheduler::schedule_single()` / `enqueue_async()`.

## 1. Job inventory

Recurring. Group `wb-listora` = Free, `wb-listora-pro` = Pro. AS = Action Scheduler.

| Hook | Owner | Every | Runs only when | What it does | Surface that shows it ran |
|---|---|---|---|---|---|
| `wb_listora_check_expirations` | Free `Expiration_Cron` | 12h | always | Expires listings past their date; sends expiring-soon warnings | A listing with a past `_listora_expiration_date` becomes Expired; "expires in 7 days" mail in Mailpit / Email Log |
| `wb_listora_draft_reminder_cron` | Free `Expiration_Cron` | 12h | always | Reminds owners of old drafts | "Finish your listing" mail / Email Log row |
| `wb_listora_review_reminder_cron` | Free `Expiration_Cron` | daily | always | Asks visitors who contacted a listing to review it | Review-reminder Email Log row |
| `wb_listora_daily_cleanup` | Free `Expiration_Cron`, `Search_Indexer`, `Listing_Data_Eraser`, `Leads` | daily | always | Analytics prune (retention), search-index orphan sweep, orphan contact messages, lead retention | Old analytics rows gone; no index row for a deleted listing; leads older than the retention setting gone |
| `wb_listora_expire_featured` | Free `Featured` | daily | always | Unfeatures listings past `_listora_featured_until` | Featured badge / Featured block drops the listing |
| `wb_listora_cleanup_unverified_listings` | Free `Email_Verification` | daily | always | Deletes or trashes listings never email-verified | Unverified test listing past the window gone |
| `wb_listora_prune_email_log` | Free `Notifications` | daily | always | Email Log retention | Rows older than the retention gone from Listora > Email Log |
| `wb_listora_pro_expire_needs` | Pro `Need_Expiration` | daily | Needs on | Closes needs past their deadline, closes waiting quotes | Need with a past deadline shows Expired |
| `wb_listora_pro_saved_search_alerts` | Pro `Advanced_Search` | daily | Advanced Search on | Emails new matches for saved searches (batched via `..._batch`) | Alert mail for a saved search with a new matching listing |
| `wb_listora_pro_send_digest` | Pro `Notification_Digest` | daily 09:00 | Digest on | Sends the daily digest | Digest mail |
| `wb_listora_pro_analytics_cleanup` | Pro `Analytics` | daily | Analytics on | Prunes search_terms | Old search_terms rows gone |
| `wb_listora_pro_webhook_log_cleanup` | Pro `Outgoing_Webhooks` | daily | Webhooks on | Trims each webhook's log to 50, deletes orphans | Webhook log count per webhook <= 50 |
| `wb_listora_pro_audit_cleanup` | Pro `Audit_Log` | hourly | Audit Log on | Audit retention, 10k rows per run | Old audit rows gone |
| `wb_listora_pro_license_check` | Pro `License` | weekly | always | Re-validates the licence | Settings > License "Last verified" |

One-off (scheduled on an event, never recurring):

| Hook | Owner | Scheduled when | Surface |
|---|---|---|---|
| `wb_listora_search_reindex` | Free `Search_Indexer` | A migration changes the index shape; chains itself in chunks | Search finds every listing after an update |
| `wb_listora_bg_import_batch` / `_finalize` | Free `Background_Import` | A background import starts / each batch ends | Import progress reaches 100%, listings owned by the importer |
| `listora_import_batch` | Pro `Visual_Importer` | Each visual-import batch | Visual import completes |
| `wb_listora_pro_deliver_webhook` | Pro `Outgoing_Webhooks` | A webhook event, a retry, a manual Retry / Test | Webhook log row moves to delivered / failed |
| `wb_listora_pro_saved_search_alerts_batch` | Pro `Advanced_Search` | The alert job has more saved searches than one batch | All saved searches receive their alert |

Not ours (left as is): `wbcom_credits_*` (Credits SDK), `edd_sl_sdk_weekly_license_check_*` (licence SDK).

## 2. Checks (run before the change for a baseline, then after)

Record each result as PASS / FAIL with the number seen.

### A. Inventory
1. Pending AS actions: exactly one per recurring hook above that should run (feature on), in its group. Query: `actionscheduler_actions` grouped by hook and status.
2. No Listora hook pending in BOTH Action Scheduler and WP-Cron (`_get_cron_array()`).
3. No duplicate pending action for any hook.

### B. Request cost (temporary `pre_get_scheduled_event` + SAVEQUERIES probe, removed after)
4. Visitor page (home, directory, a listing) and a REST call: 0 Listora scheduling lookups, 0 AS queries.
5. wp-admin page: scheduling queries only on the first admin page of the day (or after an update / feature toggle); 0 on the next.
6. A job deleted by hand comes back: within an hour on cron requests, within a day on wp-admin, at once after `Cron_Scheduler::forget_armed()` (activation does this).

### C. Lifecycle
7. Deactivate Free: no Listora action pending in any group (Pro's included: Pro cannot run without Free); no Listora WP-Cron event.
8. Reactivate Free (+ Pro): every job in section 1 comes back on the first admin page.
9. Deactivate Pro only: Pro's jobs gone, Free's untouched.
10. Feature off (e.g. Analytics): its job gone; feature on: back on the next admin page.
11. Update with jobs already queued (hooks keep their names): the existing actions keep running, nothing duplicated.

### D. Behaviour (each job still does its work)
12. `wp action-scheduler run --hooks=<hook>` (or `wp cron event run <hook>`) for each recurring hook: the surface in section 1 changes; no PHP notice in debug.log; the action ends `complete`, not `failed`.
13. One-off chains: a full reindex finishes (`wb_listora_reindex_offset` option removed); a webhook test delivers; a small background import completes.

### E. Without Action Scheduler
14. With AS unavailable (WP-Cron fallback): recurring jobs are WP-Cron events, one-off jobs are single events, and each runs.

### F. Gates
15. `bin/coding-rules-check.sh` / `bin/architecture-checks.sh`: no `wp_schedule_event`, `wp_schedule_single_event`, `as_schedule_*_action` or `as_enqueue_async_action` call outside `class-cron-scheduler.php`.
16. PHPUnit `--group cron` passes in both repos.
