---
journey: demo-import-background-progress
plugin: wb-listora
priority: high
roles: [administrator]
covers: [background-import, import-progress-rest, bg-import-resume-idempotent, bg-import-finalize-reindex, /import/progress/{run_id}]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free active"
  - "Action Scheduler available (bundled in Free at vendor/woocommerce/action-scheduler)"
  - "wp-content/debug.log writable; WP_DEBUG + WP_DEBUG_LOG on"
estimated_runtime_minutes: 12
covers_card: null
covers_commit: HEAD
---

# Demo/data import runs in the background, polls progress over REST, and resumes without duplicates

The 1.2.0 Free `Background_Import` service moves demo-pack and file imports off
the wizard request onto Action Scheduler. The wizard kicks a run and returns a
`run_id`; the UI polls `GET /listora/v1/import/progress/{run_id}` while AS chews
through chunks. The run is resumable (cursor persisted per chunk) and
idempotent (a row whose hash is already mapped this run is skipped), so killing
and restarting the AS runner mid-import must NOT create duplicate listings or
re-sideload images. When the source is exhausted, a finalize job rebuilds the
search index. This journey locks the responsiveness, progress, resume, and
final-state contracts.

## Setup

- Site: `$SITE_URL`, administrator session (route is `manage_options`-gated)
- The Setup Wizard demo-import step (`admin.php?page=listora-setup`) OR a direct
  `Background_Import::queue_demo([...])` invocation
- Baseline the listings count:
  ```sql
  SELECT COUNT(*) FROM wp_posts WHERE post_type='listora_listing';
  ```
- Baseline `wp-content/debug.log` byte count.

## Steps

### 1. Start a demo import from the wizard - request returns fast
- **Action**: `playwright_navigate admin.php?page=listora-setup` → demo-import
  step → start the import (or `wp eval` `Background_Import::queue_demo(...)`).
- **Expect**: the start request returns a `run_id` (`[A-Za-z0-9]+`) and the
  wizard stays interactive. NO single request blocks >10s - chunks run inside
  AS, not the request.
- **Capture**: `RUN` ← the returned run_id
- **On fail**: `includes/import-export/class-background-import.php::queue_demo` /
  `dispatch` / `Cron_Scheduler::enqueue_async`; wizard handoff at
  `includes/admin/class-setup-wizard.php:783-794`

### 2. Poll progress over REST - shape + permission
- **Action**: authenticated (admin) `curl -s "$SITE_URL/wp-json/listora/v1/import/progress/<RUN>"`
- **Expect**: HTTP 200 with `{ run_id, kind, status, total, processed,
  imported, skipped, errors, percent, messages[], done }`. `status` is one of
  `queued|running|finalizing|done|failed`. `percent` climbs across polls.
- **On fail**: route `register_rest_routes` (`/import/progress/(?P<run_id>[A-Za-z0-9]+)`)
  + `rest_progress` + `get_progress` shape

### 3. Progress endpoint is admin-only
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/import/progress/<RUN>"` with
  NO auth, then as a logged-in subscriber.
- **Expect**: anon → 401 `listora_unauthorized`; subscriber → 403
  `listora_forbidden`. Never 200 for non-admins.
- **On fail**: `progress_permissions` (logged-in + `manage_options`)

### 4. Wizard stays responsive while the run drains
- **Action**: while `status` is `queued`/`running`, navigate around the wizard /
  reload the progress UI a few times.
- **Expect**: every page/request responds < 10s; the UI reflects the polled
  `percent`/`processed` without freezing.
- **On fail**: a chunk is running inline instead of async - check
  `wb_listora_bg_import_use_async` filter + `enqueue_batch`

### 5. Kill the AS runner mid-import, then restart it
- **Action**: while `status` is `running` (percent between 1 and 99), stop the
  Action Scheduler runner (e.g. interrupt `wp action-scheduler run`), then
  restart it: `wp action-scheduler run --group=wb-listora`.
- **Expect**: the run continues from the persisted cursor - it does NOT restart
  from row 0. `processed` only moves forward.
- **On fail**: cursor not persisted before return - `process_file_chunk` /
  `process_demo_unit` (state `cursor` written via `put_state` BEFORE returning)

### 6. No duplicate listings or images after the resume
- **Action**: on `status=done`, recount listings and compare to the run's
  `imported`:
  ```sql
  SELECT COUNT(*) FROM wp_posts WHERE post_type='listora_listing';
  ```
- **Expect**: final count == baseline + `imported`. A retried chunk created NO
  duplicate rows (the `seen` hash→post_id map skips already-created rows;
  `skipped` reflects them). Images are not re-sideloaded (importers' existing
  dedupe path).
- **On fail**: idempotency map - `process_file_chunk` `$seen[$hash]` guard;
  demo dedupe - `process_demo_unit` (seeders re-detect already-seeded content)

### 7. Final state: listings + images + search index all present
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/import/progress/<RUN>"`
  one last time; spot-check a few imported listings on the frontend grid and
  run a search query that should hit imported content.
- **Expect**: `status=done`, `done=true`, `percent=100`. Imported listings
  render with their images; a `GET /listora/v1/search?q=<imported title>`
  returns the imported listing (the finalize job rebuilt the search index).
- **On fail**: `run_finalize` (`Search_Indexer::index_listing` loop) /
  `HOOK_FINALIZE` (`wb_listora_bg_import_finalize`) not enqueued

### 8. debug.log clean
- **Action**: diff `wp-content/debug.log` against the baseline.
- **Expect**: zero new fatals/warnings; specifically no `_doing_it_wrong`
  Action-Scheduler-before-init spam (the run defers to AS correctly).

## Pass criteria

ALL of the following hold:
1. Start returns a `run_id` and never blocks the wizard (>10s) - chunks run on AS.
2. `GET /import/progress/{run_id}` returns the documented shape and is admin-only
   (401 anon, 403 subscriber).
3. Killing + restarting the AS runner mid-import resumes from the cursor.
4. Final listing count == baseline + `imported`; no duplicate listings/images.
5. `status=done`, `percent=100`, imported content searchable (index rebuilt).
6. debug.log clean (no fatals, no AS-before-init notices).

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Wizard hangs / request >10s | chunk ran inline, not async | `class-background-import.php::enqueue_batch` + `wb_listora_bg_import_use_async` |
| Progress 404 | run state expired/cleaned OR bad run_id | `rest_progress` 404 branch / `get_state` |
| Progress 200 for anon/subscriber | permission gate dropped | `progress_permissions` |
| Run restarts from 0 after kill | cursor not persisted pre-return | `process_file_chunk` / `process_demo_unit` `put_state` ordering |
| Duplicate listings after resume | idempotency hash map not consulted | `process_file_chunk` `$seen[$hash]` skip |
| Imported listings not searchable | finalize/reindex never ran | `run_finalize` + `HOOK_FINALIZE` enqueue |
| `_doing_it_wrong` AS spam in log | AS called before data store init | `Cron_Scheduler::has_action_scheduler` guard (D.cron-as-init-timing) |
