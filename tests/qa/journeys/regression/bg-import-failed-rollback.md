---
journey: bg-import-failed-rollback
plugin: wb-listora
priority: high
roles: [admin]
covers: ["#9977212594", "background import stuck RUNNING", "FAILED not clobbered by finalize"]
prerequisites:
  - "WP-CLI available ($WP_CLI)"
  - "Action Scheduler loaded (combo or Free with bundled AS)"
estimated_runtime_minutes: 3
---

# Background import reaches a terminal FAILED state (never stuck RUNNING)

Card #9977212594: `run_batch()` set the run to `STATUS_RUNNING` before its try
block; on a `\Throwable` it logged + re-threw for "AS retry" — but Action
Scheduler does NOT retry failed actions, so the run stayed `running` forever
and the admin Import Status UI polled an eternal spinner.

A second clobber bug rode along: when `process_file_chunk()` hit a missing
source file it called `mark_failed()`, returned `false` ("no more rows"), and
`run_batch()`/`run_synchronously()` then handed off to finalize — overwriting
`failed` with `finalizing` → `done`.

Fix contract (`includes/import-export/class-background-import.php`):
1. The catch block self-requeues the chunk (cursor-resumable) and counts
   `chunk_retries`; at `MAX_CHUNK_RETRIES` (3) consecutive failures the run is
   marked `failed` with a final log message.
2. A committed chunk resets `chunk_retries` to 0.
3. Both drain paths check for `failed` status after chunk processing and bail
   before finalize — `failed` is terminal, never overwritten by `done`.
4. `get_progress()` reports `done: true` for `failed` runs so the UI stops
   polling.

## Steps

### 1. Missing-source run terminates FAILED (not DONE)
- **Action**: via `wp eval-file`, create a CSV run whose stashed source is then
  deleted, and drain it:
  ```php
  $csv = wp_tempnam( 'listora-journey.csv' );
  file_put_contents( $csv, "title\n" . str_repeat( "Row X\n", 30 ) );
  $run_id = \WBListora\ImportExport\Background_Import::queue_file( 'csv', $csv, 'business', array( 0 => 'title' ), 30 );
  // total 30 > SYNC_THRESHOLD → async path; now break the source.
  $state = get_option( 'wb_listora_bg_import_' . $run_id );
  unlink( $state['source'] );
  try { \WBListora\ImportExport\Background_Import::run_batch( $run_id ); } catch ( \Throwable $e ) {}
  $p = \WBListora\ImportExport\Background_Import::get_progress( $run_id );
  echo $p['status'] . '|' . ( $p['done'] ? '1' : '0' );
  ```
- **Expect**: output `failed|1`. NOT `finalizing`, NOT `done`, NOT `running`.

### 2. Thrown chunk self-requeues, then fails at the retry cap
- **Action**: via `wp eval-file`, register a listener that throws during row
  processing (any hook on the row-create path, e.g. `wp_insert_post`), queue a
  CSV run as in step 1 (with the source intact), then call `run_batch()` three
  times, swallowing each re-throw. After calls 1 and 2, read the state option.
- **Expect**:
  - After call 1 and 2: `status = queued`, `chunk_retries` = 1 then 2, and a
    PENDING `wb_listora_bg_import_batch` action exists for the run
    (`as_get_scheduled_actions` status=pending returns 1 row).
  - After call 3: `status = failed`, messages include
    `Import failed after 3 attempts at the same chunk.`, `done: true` in
    `get_progress()`.

### 3. Success resets the failure counter
- **Action**: remove the throwing listener, write `chunk_retries = 2` into a
  fresh small run's state, run one clean batch.
- **Expect**: state's `chunk_retries` back to `0`; run proceeds (queued next
  chunk or finalizing/done).

## Cleanup
- `delete_option` any `wb_listora_bg_import_*` rows created; delete temp CSVs;
  unschedule leftover `wb_listora_bg_import_*` actions in group `wb-listora`;
  trash listings created by step 2/3 runs.
