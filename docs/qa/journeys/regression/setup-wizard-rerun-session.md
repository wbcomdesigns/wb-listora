---
journey: setup-wizard-rerun-session
plugin: wb-listora
priority: high
roles: [administrator, editor]
covers: [setup-wizard, card-10294691503]
prerequisites:
  - "Site reachable at $SITE_URL with setup already complete (wb_listora_setup_complete = 1)"
  - "Back up wb_listora_settings and wb_listora_setup_data first - a re-run legitimately rewrites them"
estimated_runtime_minutes: 5
---

# A completed site can re-run the wizard on purpose, and nothing can replay it by accident

Card 10294691503 asked for a completion guard. The first fix rendered a "Setup is already complete"
landing, but carried the re-run intent as `rerun=1` on the URL, and the step form and Back link
dropped it - Continue on a deliberate re-run landed straight back on the landing. The done step was
exempt from the guard, so a bookmarked `step=done` (or any unknown step, which normalises to done)
plus "Go to Dashboard" re-ran `finalize_setup()` against leftover `wb_listora_setup_data`.

A run is now a per-user session (`wb_listora_wizard_session_<user>` transient). Opening a run -
first run, or the `rerun=1` link - opens it; the done step finalizes, deletes the step data and
closes it. A URL flag could not work: the seeded-site check flips setup to complete partway through
a first run, which is also why "is setup complete?" alone cannot gate the steps.

## Steps

### 1. Landing, not a silent restart
- **Action**: as administrator open `$SITE_URL/wp-admin/admin.php?page=listora-setup`
- **Expect**: "Setup is already complete" with "Go to Listora" and "Run the wizard again". No progress bar.

### 2. A deliberate re-run walks forward and back
- **Action**: click "Run the wizard again", then "Continue →" without changing anything
- **Expect**: URL `step=location`, heading "Where is your directory based?" - NOT the landing (this is the bounce)
- **Action**: click "← Back"
- **Expect**: step 1 "What type of directory are you building?"

### 3. Done closes the run
- **Action**: open `step=done` while the run is open
- **Expect**: completion screen renders. `wp option get wb_listora_setup_data` → does not exist.
- **Action**: reload the same URL, then open `step=finish`
- **Expect**: both show "Setup is already complete"

### 4. Replayed POSTs write nothing
- **Action**: with the nonce copied from the done screen, POST `listora_wizard_step=location&city=QA-REPLAY` to `admin.php?page=listora-setup&step=maps`, then POST `listora_wizard_step=done`
- **Expect**: no `wb_listora_setup_data` option appears (no QA-REPLAY city); the done POST only redirects to `admin.php?page=listora&listora-welcome=1`; `wb_listora_settings` byte-identical to before step 4

### 4b. Edge doors into the run
- **Action**: set `map_provider=google` in `wb_listora_settings`, delete `wb_listora_setup_data`, open `?page=listora-setup&rerun=1&step=done`
- **Expect**: `map_provider` is still `google` - a done screen with no step data sets no provider
- **Action**: seed `wb_listora_setup_data.demo_run_id` pointing at an import state with `status=failed`, open the done screen
- **Expect**: heading "Setup is saved, but the demo import did not finish", not "Your directory is ready!"
- **Action**: open `?rerun=1&step=type`, then click **Skip setup**
- **Expect**: lands on the Listora dashboard and the `wb_listora_wizard_session_<user>` transient is gone

### 5. Role row
- **Action**: as editor open `admin.php?page=listora-setup`
- **Expect**: "Sorry, you are not allowed to access this page." (no `manage_listora_settings`)

## Teardown
Restore `wb_listora_settings` / `wb_listora_setup_data` from the backup; `wp transient delete wb_listora_wizard_session_<admin id>`.
