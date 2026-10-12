---
journey: background-import-lifecycle
group: import-migration
actor: admin
mode: mixed
plugin: wb-listora
priority: high
roles: [administrator, member-other, anonymous]
covers: [background-import, import-progress-rest, bg-import-resume-idempotent, bg-import-finalize-reindex, "GET /import/progress/{run_id}", "#9977212594", bg-import-failed-terminal, FAILED-not-clobbered-by-finalize]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH; SCRATCH site (the run creates listings)"
  - "Free active; Action Scheduler available (bundled at vendor/woocommerce/action-scheduler, or loaded by WooCommerce/Pro)"
  - "wp-content/debug.log writable; WP_DEBUG + WP_DEBUG_LOG on"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
supersedes:
  - ora:admin/demo-import-background-progress.md
  - ora:regression/bg-import-failed-rollback.md
fails_on: unknown for steps 1-9 (no pre-fix sha recorded); steps 10-12 fail on the build before card #9977212594 (pre-fix sha not recorded)
site_profile: required
---

# A demo or file import runs in the background, reports progress over REST, resumes without duplicates and always reaches a terminal state

## Why
`Background_Import` moves demo-pack and file imports onto Action Scheduler so the wizard stays responsive. If a chunk is not resumable the owner gets duplicates; if a failure is not terminal the Import Status UI spins forever (card #9977212594: AS does not retry failed actions, so a thrown chunk stayed `running`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Start paths (all share one engine): Setup Wizard demo step (`admin.php?page=listora-setup&rerun=1&step=demo`), Settings > Advanced > "Re-run Demo Import" (`#listora-demo-import-btn`, progress box `#listora-demo-import-progress`), `wp listora demo seed`, `Background_Import::queue_demo([...])`, or `Background_Import::queue_file(...)` for files.
- Baselines: `LISTINGS_BEFORE` = `wp post list --post_type=listora_listing --post_status=any --format=count`; byte size of `debug.log`.
- Constants: `SYNC_THRESHOLD` 10 rows (more uses the async path), `MAX_CHUNK_RETRIES` 3, state option `wb_listora_bg_import_{run_id}` (autoload off), AS hook `wb_listora_bg_import_batch`, group `wb-listora`.

## Steps

### 1. Start from the wizard: the request returns fast
- **Action**: Setup Wizard demo step, start the import (or `wp eval` `Background_Import::queue_demo(...)`).
- **Expect**: the start request returns a `run_id` (`[A-Za-z0-9]+`); no single request blocks over 10 s.
- **Capture**: `RUN` = run_id.

### 2. Poll progress over REST: shape
- **Action**: as {admin} `GET $SITE_URL/wp-json/listora/v1/import/progress/<RUN>`.
- **Expect**: 200 with `{ run_id, kind, status, total, processed, imported, skipped, errors, percent, messages[], done }`; `status` is one of `queued|running|finalizing|done|failed`; `percent` climbs across polls. An unknown run id: 404 `listora_import_run_not_found`.

### 3. Progress is admin-only
- **Expect**: {guest} 401 `listora_unauthorized`; {other} (Subscriber) 403 `listora_forbidden`; never 200 for non-admins. Also `POST admin-ajax.php?action=listora_run_demo_import` as {other} is 403 (needs `manage_options`).

### 4. The wizard stays responsive while the run drains
- **Action**: while `status` is `queued`/`running`, navigate and reload the progress UI.
- **Expect**: every request under 10 s; the UI follows the polled `percent`/`processed`.

### 5. Kill the Action Scheduler runner mid-import, then restart it
- **Action**: at `percent` between 1 and 99 stop the runner, then `wp action-scheduler run --group=wb-listora`.
- **Expect**: continues from the persisted cursor (never from row 0); `processed` only moves forward.

### 6. No duplicates or re-sideloaded images after the resume
- **Action**: on `status=done` recount listings.
- **Expect**: count = `LISTINGS_BEFORE` + `imported`; a retried chunk created no duplicates (`seen` hash map; `skipped` reflects them); images are not re-sideloaded.

### 7. Final state: listings, images and search index
- **Action**: last poll; spot-check imported listings on the frontend grid; `GET /listora/v1/search?q=<imported title>`.
- **Expect**: `status=done`, `done=true`, `percent=100`; listings render with images; search returns the imported listing (finalize rebuilt the index).

### 8. debug.log clean
- **Expect**: zero new fatals or warnings, and no `_doing_it_wrong` Action-Scheduler-before-init spam.

### 9. Scale (added)
- **Action**: queue a CSV of 2000+ rows via `queue_file`.
- **Expect**: many chunks, each request short, `percent` monotone, final count correct, memory flat. Not verified live.

### 10. A missing source terminates FAILED, not DONE (#9977212594)
- **Action**: via `wp eval-file`:
  ```php
  $csv = wp_tempnam( 'listora-journey.csv' );
  file_put_contents( $csv, "title\n" . str_repeat( "Row X\n", 30 ) );
  $run_id = \WBListora\ImportExport\Background_Import::queue_file( 'csv', $csv, '{type}', array( 0 => 'title' ), 30 );
  $state = get_option( 'wb_listora_bg_import_' . $run_id );
  unlink( $state['source'] );
  try { \WBListora\ImportExport\Background_Import::run_batch( $run_id ); } catch ( \Throwable $e ) {}
  $p = \WBListora\ImportExport\Background_Import::get_progress( $run_id );
  echo $p['status'] . '|' . ( $p['done'] ? '1' : '0' );
  ```
- **Expect**: `failed|1`. NOT `finalizing`, `done` or `running` (a drain path must check `failed` before finalize).

### 11. A thrown chunk self-requeues, then fails at the retry cap
- **Action**: register a listener that throws on the row-create path (`add_action( 'wp_insert_post', $thrower )`), queue a 30-row CSV with the source intact, call `run_batch()` three times swallowing each re-throw, reading state and `get_progress()` after each. Remove every listener you registered before step 12.
- **Expect**: after calls 1 and 2 `status=queued`, `chunk_retries` 1 then 2, `done` false, one PENDING `wb_listora_bg_import_batch` action for the run; after call 3 `status=failed`, `chunk_retries=3`, last message `Import failed after 3 attempts at the same chunk.`, `get_progress()['done']` true; no row created.

### 12. Success resets the failure counter (positive control for 10-11)
- **Action**: no throwing listener; fresh small run; set `chunk_retries = 2` in its state; one clean `run_batch()`.
- **Expect**: `chunk_retries` back to `0` and the run proceeds. This proves `failed` in 10-11 comes from the fault.

### 13. Admin visibility of a failed run
- **Expect**: the Import Status UI stops polling a `failed` run (`done: true`) and shows its last message. Not verified in a browser.

## Role ladder

| Rung | Call | Expect |
|---|---|---|
| {admin} | `GET /listora/v1/import/progress/<RUN>` | 200 |
| {other} (Subscriber) | same | 403 `listora_forbidden` |
| {guest} | same | 401 `listora_unauthorized` |
| {other} | `POST admin-ajax.php?action=listora_run_demo_import` | 403 |

## Pass criteria
1. Start returns a `run_id` fast; shape and permissions hold.
2. Kill and restart resumes from the cursor; no duplicates; index rebuilt.
3. A missing source or repeated chunk failure ends `failed` with `done: true`, never `running`, never overwritten by finalize.
4. debug.log clean.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Wizard hangs / request over 10 s | chunk ran inline | `includes/import-export/class-background-import.php` `enqueue_batch`, `wb_listora_bg_import_use_async` |
| Progress 200 for non-admin | gate dropped | `progress_permissions` |
| Run restarts from 0 | cursor not persisted before return | `process_file_chunk` / `process_demo_unit` `put_state` order |
| Duplicates after resume | `$seen[$hash]` guard | `process_file_chunk` |
| Not searchable | finalize never enqueued | `run_finalize`, `HOOK_FINALIZE` |
| Run stays `running` | catch block not terminal | `class-background-import.php` catch block (retry cap) |
| `failed` becomes `done` | drain path lacks the failed check | both drain paths |
| `_doing_it_wrong` spam | AS called before init | `Cron_Scheduler::has_action_scheduler` |

## Not verified
Steps 9, 12 (the 2026-10-12 attempt reused a script with a filter still attached) and 13. Steps 10 and 11 ran on 2026-10-12 against the reference site; the rest of the progress contract was verified with `rest_do_request` for the three rungs.

## Cleanup
`delete_option( 'wb_listora_bg_import_<run_id>' )` and `unlink( $state['source'] )` (under `wp-content/uploads/wb-listora-import/src-*.csv`) for every run created; `as_unschedule_all_actions( 'wb_listora_bg_import_batch', array( $run_id ), 'wb-listora' )` with the run's args (never without args on a shared site); delete listings created by a clean run.
