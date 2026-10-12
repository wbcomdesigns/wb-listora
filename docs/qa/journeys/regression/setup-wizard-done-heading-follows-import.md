---
journey: setup-wizard-done-heading-follows-import
plugin: wb-listora
priority: high
roles: [administrator, editor]
covers: [setup-wizard, background-import, card-10290534093]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Back up wb_listora_settings and wb_listora_setup_data first; close any open wizard session (wp transient delete wb_listora_wizard_session_<admin id>)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# The done screen heading tells the truth about the demo import

`render_step_done()` (`includes/admin/class-setup-wizard.php:901-985`) sets the heading from `Background_Import::get_progress()` status: "Almost there - your demo content is importing" while the run is not done, "Your directory is ready!" when done, "Setup is saved, but the demo import did not finish" when failed. `build/admin/import-progress.js` (source `src/admin/import-progress.js`, poll every 2500ms) swaps both lines to their `data-ready-text` when the poll reports `done` and sets the progress text to "Demo content imported.". A source edit alone ships nothing.

Verified via: source read of the wizard and both JS files (strings and constants present), plus the landing/step rendering via curl as admin. The faked-run flip in a live browser was NOT executed here (the wizard rewrites settings; shared browser unavailable). Admin-only surface; ladder row in step 3.

## Steps

### 1. A live import reads "importing", then flips without a reload
- **Setup**: `wp eval 'update_option("wb_listora_bg_import_qarun1", ["kind"=>"demo","status"=>"running","total"=>20,"processed"=>5,"imported"=>5], false); update_option("wb_listora_setup_data", ["demo_run_id"=>"qarun1"]);'` (option prefix `wb_listora_bg_import_`, `class-background-import.php:58`; run ids are alphanumeric).
- **Action**: open `admin.php?page=listora-setup&step=type&rerun=1` (opens the session), then `...&step=done`.
- **Expect**: `h2[data-listora-done-heading]` "Almost there - your demo content is importing"; progress text `.listora-import-progress__text` "Importing demo content in the background…".
- **Action**: `wp eval '$s=get_option("wb_listora_bg_import_qarun1"); $s["status"]="done"; $s["imported"]=20; update_option("wb_listora_bg_import_qarun1",$s,false);'`, wait ~6s.
- **Expect** WITHOUT reload: heading "Your directory is ready!", subhead "Everything is set up. Here's what you can do next:", progress "Demo content imported.", count 20.

### 2. A finished run never reads "importing"
- **Setup**: run option with `status` `done`, `wb_listora_setup_data.demo_run_id` pointing at it.
- **Action**: `rerun=1&step=type`, then `step=done`.
- **Expect**: "Your directory is ready!" on first paint; never "Almost there" above "Demo content imported.". Note: the done step deletes `wb_listora_setup_data` as it renders, so a reload shows the "already complete" landing.

### 3. Failed run (negative path) and role row
- **Setup**: run option `status` `failed`. **Expect**: "Setup is saved, but the demo import did not finish" and the subhead "Some demo content could not be created. Your settings are saved - run the wizard again to retry the import, or add your own listings."
- **Role**: qa_editor opening the wizard: "Sorry, you are not allowed to access this page."

## Teardown
`wp option delete wb_listora_bg_import_qarun1`; restore both options from the backup.

## Fail diagnostics
| Symptom | File |
|---|---|
| Heading stuck on "importing" | `class-setup-wizard.php` heading branch (`$importing = is_array( $progress ) && empty( $progress['done'] )`) |
| Never flips | `src/admin/import-progress.js` + `build/admin/import-progress.js` (rebuild) |
