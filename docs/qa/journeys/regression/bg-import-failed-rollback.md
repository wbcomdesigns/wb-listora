---
journey: bg-import-failed-rollback
plugin: wb-listora
priority: high
roles: [admin]
covers: ["#9977212594", "background import stuck RUNNING", "FAILED not clobbered by finalize"]
prerequisites:
  - "WP-CLI available ($WP_CLI)"
  - "Action Scheduler loaded (combo or Free with bundled AS)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [CLI only]
changed: true
---

# Background import reaches a terminal FAILED state (never stuck RUNNING)

Card #9977212594: `run_batch()` set the run to `STATUS_RUNNING` before its try block; on a `\Throwable` it logged and re-threw for "AS retry", but Action Scheduler does NOT retry failed actions, so the run stayed `running` forever and the admin Import Status UI polled an eternal spinner. A second clobber rode along: a missing source file made `process_file_chunk()` call `mark_failed()` and return `false` ("no more rows"), and the drain paths then handed off to finalize, overwriting `failed` with `finalizing` then `done`.

Contract (`includes/import-export/class-background-import.php`, class `WBListora\ImportExport\Background_Import`):
1. The catch block self-requeues the chunk (cursor-resumable) and counts `chunk_retries`; at `MAX_CHUNK_RETRIES` (3, `:84`) consecutive failures the run is `failed` with a final log message (`:298-313`).
2. A committed chunk resets `chunk_retries` to 0 (`:332-333`).
3. Both drain paths check for `failed` after chunk processing and bail before finalize (`:326`, and `:1124` in the synchronous path); `failed` is terminal.
4. `get_progress()` reports `done: true` for `failed` runs so the UI stops polling (`:248`).

State lives in the autoload-off option `wb_listora_bg_import_{run_id}`; batches run on the Action Scheduler hook `wb_listora_bg_import_batch`, group `wb-listora`; `SYNC_THRESHOLD` is 10 rows (more rows use the async path when Action Scheduler is present).

Single-role (system/CLI path; an administrator starts imports from Settings > Re-run Demo Import or Tools > Import). Control: a clean run (step 3) proves the same code path completes normally.

## Steps (1 and 2 executed 2026-10-12)

### 1. Missing-source run terminates FAILED (not DONE)
- **Action**: via `wp eval-file`:
  ```php
  $csv = wp_tempnam( 'listora-journey.csv' );
  file_put_contents( $csv, "title\n" . str_repeat( "Row X\n", 30 ) );
  $run_id = \WBListora\ImportExport\Background_Import::queue_file( 'csv', $csv, 'business', array( 0 => 'title' ), 30 );
  // 30 > SYNC_THRESHOLD, so the async path; now break the source.
  $state = get_option( 'wb_listora_bg_import_' . $run_id );
  unlink( $state['source'] );
  try { \WBListora\ImportExport\Background_Import::run_batch( $run_id ); } catch ( \Throwable $e ) {}
  $p = \WBListora\ImportExport\Background_Import::get_progress( $run_id );
  echo $p['status'] . '|' . ( $p['done'] ? '1' : '0' );
  ```
- **Expect**: `failed|1`. NOT `finalizing`, `done` or `running`. (The run's state keys: `run_id, status, processed, imported, skipped, errors, created, seen, messages, started_at, kind, source, type_slug, mapping, cursor, total`.)

### 2. A thrown chunk self-requeues, then fails at the retry cap
- **Action**: via `wp eval-file`, register a listener that throws on the row-create path (`add_action( 'wp_insert_post', $thrower )`), queue a CSV run of 30 rows as above with the source intact, then call `run_batch()` three times swallowing each re-throw; read the state option and `get_progress()` after each call. REMOVE every listener you registered before step 3 (including any filter), or step 3 throws too.
- **Expect**:
  - After calls 1 and 2: `status = queued`, `chunk_retries` = 1 then 2, `done` false, and one PENDING `wb_listora_bg_import_batch` action for the run (`as_get_scheduled_actions` with `status=pending`, `args=[run_id]`).
  - After call 3: `status = failed`, `chunk_retries` = 3, last message `Import failed after 3 attempts at the same chunk.`, `get_progress()['done']` true.
  - No row was created (the thrower fires before insert).

### 3. Success resets the failure counter (POSITIVE CONTROL for steps 1-2)
- **Action**: no throwing listener; queue a fresh small run (an in-range 3-row CSV, or the same 30 rows), set `chunk_retries = 2` in its state option, run one clean `run_batch()`.
- **Expect**: `chunk_retries` back to `0` (`:332`), and the run proceeds (`queued` for the next chunk, or `finalizing` / `done`). This proves `failed` in steps 1-2 comes from the fault and not from a broken runner.
- **Not executed**: my attempt reused a script that still had a filter attached and the run failed instead; re-run with the listener fully removed.

### 4. Admin visibility
- **Expect**: the Import Status UI stops polling a `failed` run and shows its last message (`done: true` from the progress REST route served by `rest_progress()`, `:987`); permissions come from `progress_permissions()` (`:1009`). Not executed in a browser.

## Cleanup (do all of this, a failed run leaves its source file behind)
- `delete_option( 'wb_listora_bg_import_<run_id>' )` for every run you created, and `unlink( $state['source'] )` (under `wp-content/uploads/wb-listora-import/src-*.csv`).
- `as_unschedule_all_actions( 'wb_listora_bg_import_batch', array( $run_id ), 'wb-listora' )`: pass the run's args; never unschedule the hook without args on a shared site.
- Delete listings created by a step 3 run (`post_title` of your fixture rows).

## Fail diagnostics
| Symptom | File |
|---|---|
| Run stays `running` | catch block not terminal: `class-background-import.php:298-313` |
| `failed` becomes `done` | drain path missing the failed check: `:326`, `:1124` |
| UI spins forever | `get_progress()` `done` for failed: `:248` |

Verified via: wp eval-file executions of steps 1 and 2 against http://wb-listora.local (queued, forced-failure, cleaned up).
