---
journey: setup-wizard-rerun-session
plugin: wb-listora
priority: high
roles: [administrator, editor]
covers: [setup-wizard, card-10294691503]
prerequisites:
  - "Site reachable at $SITE_URL with setup complete (wb_listora_setup_complete true; verified)"
  - "Back up wb_listora_settings and wb_listora_setup_data first - a re-run legitimately rewrites them"
  - "No wizard session open for the admin: `wp transient get wb_listora_wizard_session_<admin id>` must be empty, or step 1 shows step 1 of the wizard instead of the landing"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# A completed site can re-run the wizard on purpose, and nothing can replay it by accident

A run is a per-user session (transient `wb_listora_wizard_session_<user id>`, `includes/admin/class-setup-wizard.php:172`). Opening a run (first run, or the `rerun=1` link) opens it; reaching the done step finalizes, deletes the step data and closes it (`render_step_done()` and the `done` POST). POSTs on a completed site without an open session write nothing (`handle_post_submission()` `:152-163`). An unknown step (`finish`) normalizes to `done` (`render()` `:386-392`).

Verified via: curl as admin and qa_editor (landing, rerun, `step=finish`, `step=done`, editor refusal) and source read. NOTE a pitfall hit during verification: an earlier QA session had left the admin's session open, so a plain `listora-setup` showed "What type of directory are you building?" instead of the landing until `rerun=1&step=finish` closed it. The POST-replay steps 4/4b were NOT executed (they would rewrite settings).

## Steps

### 1. Landing, not a silent restart
- **Action**: as administrator open `$SITE_URL/wp-admin/admin.php?page=listora-setup`.
- **Expect** (observed): `h2` "Setup is already complete" with links "Go to Listora" and "Run the wizard again". No `.listora-wizard__progress` bar.

### 2. A deliberate re-run walks forward and back
- **Action**: click "Run the wizard again" (`rerun=1`), then "Continue →" unchanged.
- **Expect**: URL `step=location`, heading "Where is your directory based?" - NOT the landing.
- **Action**: click "← Back". **Expect**: "What type of directory are you building?". "Skip setup" link is present on every non-final step.

### 3. Done closes the run
- **Action**: open `step=done` while the run is open.
- **Expect**: completion screen ("Your directory is ready!", buttons "View Your Directory →", "Add Your First Listing", "Configure Settings", then "Go to Dashboard"); `wp option get wb_listora_setup_data` does not exist.
- **Action**: reload the same URL, then open `step=finish`. **Expect** (observed): both show "Setup is already complete".
- **Positive control**: opening `step=finish` INSIDE an open run shows the completion screen (observed: `.listora-wizard__success` present, no `.listora-wizard__nav`).

### 4. Replayed POSTs write nothing
- **Action**: with the nonce copied from an earlier done screen, POST `listora_wizard_step=location&city=QA-REPLAY` to `admin.php?page=listora-setup&step=maps`, then `listora_wizard_step=done`.
- **Expect**: no `wb_listora_setup_data` option (no QA-REPLAY); the done POST only redirects to `admin.php?page=listora&listora-welcome=1`; `wb_listora_settings` byte-identical.

### 4b. Edge doors into the run
- Set `map_provider=google`, delete `wb_listora_setup_data`, open `?page=listora-setup&rerun=1&step=done`: **Expect** `map_provider` still `google` (`finalize_setup()` only writes a provider when step data has one).
- Seed `wb_listora_setup_data.demo_run_id` at a run with `status=failed`, open done: heading "Setup is saved, but the demo import did not finish".
- Open `?rerun=1&step=type`, click **Skip setup**: lands on `admin.php?page=listora` and the session transient is gone.

### 5. Role row
- qa_editor `admin.php?page=listora-setup`: "Sorry, you are not allowed to access this page." (observed).

## Teardown
Restore `wb_listora_settings` / `wb_listora_setup_data`; `wp transient delete wb_listora_wizard_session_<admin id>`.
