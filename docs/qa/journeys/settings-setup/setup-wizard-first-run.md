---
journey: setup-wizard-first-run
group: settings-setup
actor: admin
mode: browser
plugin: wb-listora
priority: critical
roles: [administrator, editor, guest]
covers: [setup-wizard, headers-already-sent, wizard-quiet-notices, wizard-skip, demo-seed, rerun-landing, wizard-session, replay-protection, unknown-step-normalization, done-heading-follows-import, background-import, 2.0.0-wizard, "#9867159785", card-10294691503, card-9910738227, card-10290534093]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} autologin"
  - "Back up `wb_listora_settings` and `wb_listora_setup_data`; `wp transient get wb_listora_wizard_session_<admin id>` must be empty (an open session shows step 1 instead of the landing)"
  - "`debug.log` byte count baseline; a scratch state is fine because the demo step imports content"
estimated_runtime_minutes: 16
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
site_profile: required
supersedes:
  - "ora:regression/setup-wizard-rerun-session.md"
  - "ora:regression/setup-wizard-unknown-step.md"
  - "ora:regression/setup-wizard-done-heading-follows-import.md"
fails_on: ["#9867159785"]
---

# Setup wizard: redirect, quiet screen, Skip, demo, Done, re-run, replay protection

## Why
Finishing the wizard used to print `Cannot modify header information - headers already sent` on a blank page; another plugin's notices cluttered it; a stale bookmark or a replayed POST rewrote a finished site's settings. The owner must land on the Dashboard with a truthful completion message.

The wizard (`admin.php?page=listora-setup`) has six steps `type`, `location`, `maps`, `pages`, `demo`, `done`. A run is a per-user session (transient `wb_listora_wizard_session_<user id>`, `includes/admin/class-setup-wizard.php:172`): opening a run (first run, or the `rerun=1` link) opens it; reaching the done step finalizes, deletes the step data and closes it. POSTs on a completed site without an open session write nothing (`handle_post_submission()` `:152-163`). An unknown step normalizes to `done` (`render()` `:386-392`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
  ```bash
  wp option delete wb_listora_setup_complete
  wp transient set wb_listora_show_wizard_redirect 1 3600     # what activation sets
  wc -c wp-content/debug.log                                  # baseline
  ```
  (A real activation also works: `wp plugin deactivate wb-listora && wp plugin activate wb-listora`.)
- Page creation/adoption/showcase behaviour is `settings-setup/setup-pages-created-adopted-once.md`.
- Teardown: `wp option delete wb_listora_bg_import_<run id>`; restore both options from the backup; `wp transient delete wb_listora_wizard_session_<admin id>`.

## Steps

### 1. First admin page load redirects once
- **Action**: `$SITE_URL/wp-admin/index.php` as {admin}.
- **Expect**: one redirect to `admin.php?page=listora-setup`; the one-shot transient is consumed (a second `index.php` load is NOT redirected). With Pro active the admin is never bounced between two wizards.
- **On fail**: `Activation_Redirect::maybe_redirect` (`includes/admin/class-activation-redirect.php` ~49).

### 2. The wizard is quiet and the progress bar is right (1440 and 390)
- **Expect**: title "WB Listora Setup"; `.listora-wizard__progress-step` x6 with the current one marked; ZERO `.notice` elements even when another plugin or mu-plugin queues one (`Setup_Wizard::quiet_wizard_notices` removes every `admin_notices`/`all_admin_notices` action except its own demo-import warning); a "Skip setup" link under the card on type, location, maps, pages and demo.
- **Read-back**: positive control: the same mu-plugin notice DOES show on `admin.php?page=listora`.
- **On fail**: `class-setup-wizard.php` (~63-75), `render` skip block (~484).

### 3. Walk the steps
- **Action**: `type`: pick a directory type and Continue; `location`: set country/city and Continue; `maps`: keep OpenStreetMap and Continue; `pages`: leave showcase pages ticked and Continue.
- **Expect**: each submit advances without a white page; "Back" returns without losing choices; no PHP notice in `debug.log`.
- **On fail**: `Setup_Wizard::handle_post_submission` runs on `admin_init` priority 1 before output.

### 4. Demo step lists every pack, honours Skip
- **Action**: open `step=demo`.
- **Expect**: one `input[name=demo_pack]` radio per shipped pack (Restaurant Directory, Job Board, Real Estate, Hotel Directory, General Directory, Classifieds, Education, Healthcare, Places & Attractions, Events, All packs) plus "Skip demo content - I will add my own listings"; button "Finish Setup"; Events line "8 upcoming concerts, conferences and workshops, dated from the day you import".
- **Read-back**: radio count equals the number of folders under `demo/` plus "All packs" and "Skip".
- **Action**: choose "Restaurant Directory", Finish Setup.
- **Expect**: import queued in the background (see `import-migration/background-import-lifecycle.md`), browser lands on `step=done`; at least 3 listings with `_listora_demo_content`; no approval emails (Email Log unchanged).

### 5. Done heading follows the import, and flips without a reload
- **Setup**: `wp eval 'update_option("wb_listora_bg_import_qarun1", ["kind"=>"demo","status"=>"running","total"=>20,"processed"=>5,"imported"=>5], false); update_option("wb_listora_setup_data", ["demo_run_id"=>"qarun1"]);'` (option prefix `wb_listora_bg_import_`, `class-background-import.php:58`; alphanumeric run ids).
- **Action**: open `step=type&rerun=1`, then `step=done`.
- **Expect**: `h2[data-listora-done-heading]` "Almost there - your demo content is importing"; `.listora-import-progress__text` "Importing demo content in the background...".
- **Action**: `wp eval '$s=get_option("wb_listora_bg_import_qarun1"); $s["status"]="done"; $s["imported"]=20; update_option("wb_listora_bg_import_qarun1",$s,false);'`, wait ~6 s.
- **Expect** WITHOUT reload (poll every 2500 ms, `build/admin/import-progress.js`): heading "Your directory is ready!", subhead "Everything is set up. Here's what you can do next:", progress "Demo content imported.", count 20.
- **On fail**: `$importing = is_array( $progress ) && empty( $progress['done'] )` branch in `render_step_done()` (`:901-985`); a source edit alone ships nothing, rebuild `src/admin/import-progress.js`.

### 6. A finished run never reads "importing"; a failed run says so
- **Setup**: run option `status` `done`, `wb_listora_setup_data.demo_run_id` pointing at it.
- **Action**: `rerun=1&step=type`, then `step=done`.
- **Expect**: "Your directory is ready!" on first paint, never "Almost there" above "Demo content imported.". The done step deletes `wb_listora_setup_data` as it renders, so a reload shows the "already complete" landing.
- **Setup**: run option `status` `failed`.
- **Expect**: "Setup is saved, but the demo import did not finish" and subhead "Some demo content could not be created. Your settings are saved - run the wizard again to retry the import, or add your own listings."

### 7. Done lands on the Dashboard with zero headers errors (CRITICAL)
- **Action**: on `step=done` observe, then click "Go to Dashboard".
- **Expect**: completion screen with "View Your Directory", "Add Your First Listing", "Configure Settings" then "Go to Dashboard"; NO "Skip setup" link on this step; the button lands on `admin.php?page=listora&listora-welcome=1`, never a blank page; `debug.log` diff has zero `headers already sent`; `wb_listora_setup_complete` = 1; `wb_listora_setup_data` does not exist.
- **On fail**: regression of #9867159785: `Setup_Wizard::init` hook priority, `render_step_done` (~960).

### 8. Unknown and stale steps render the completion summary; real steps keep their content
- **Action**: open `rerun=1`, then `step=finish`; re-open `rerun=1`, then `step=done`; re-open `rerun=1` and visit `step=type`, `step=pages`.
- **Expect**: `step=finish` and `step=done` both render `.listora-wizard__success` with "Your directory is ready!", NO `.listora-wizard__nav` and no "Continue"; `type` shows "What type of directory are you building?" with Continue; `pages` shows the page list plus "Optional pages", each with the `.listora-wizard__nav` row (Back, Continue).
- **Read-back**: positive control: `step=finish` INSIDE an open run shows the completion screen; outside a run (after done) it shows the "already complete" landing.

### 9. Skip setup closes the run
- **Action**: reset (`wp option delete wb_listora_setup_complete`), open `admin.php?page=listora-setup&rerun=1&step=type`, click "Skip setup".
- **Expect**: redirect to `admin.php?page=listora`; the session transient is gone; the wizard no longer accepts step POSTs for that session; the menu keeps no "Setup Wizard" entry once complete.

### 10. Re-run is explicit and idempotent
- **Action**: open `admin.php?page=listora-setup` with no params on a completed site.
- **Expect**: `h2` "Setup is already complete" with "Your directory is set up. Running the wizard again will take you back through directory type, location, maps and pages, and can import demo content a second time.", links "Go to Listora" and "Run the wizard again"; no `.listora-wizard__progress` bar. The latter opens `step=type&rerun=1`; "Continue" unchanged goes to `step=location` (not the landing); "Back" returns to type; "Skip setup" is present on every non-final step. Walking it again does not duplicate the Directory, Add Listing or Dashboard pages (`wp post list --post_type=page --name=add-listing --format=count` stays 1) or seeded listings.
- **Also**: deep link `rerun=1&step=done` with no session shows the done step without importing anything.

### 11. Replayed POSTs and edge doors write nothing
- **Action**: with a nonce copied from an earlier done screen POST `listora_wizard_step=location&city=QA-REPLAY` to `admin.php?page=listora-setup&step=maps`, then `listora_wizard_step=done`.
- **Expect**: no `wb_listora_setup_data` (no QA-REPLAY); the done POST only redirects to `admin.php?page=listora&listora-welcome=1`; `wb_listora_settings` byte-identical.
- **Action**: set `map_provider=google`, delete `wb_listora_setup_data`, open `?page=listora-setup&rerun=1&step=done`.
- **Expect**: `map_provider` still `google` (`finalize_setup()` writes a provider only when step data has one).
- **Read-back**: `md5` of `wb_listora_settings` before and after the replay.

### 12. 390px and theme
- **Expect**: progress bar and card wrap, no horizontal scroll, buttons >= 40px; wp-admin dark scheme readable. Not verified live.

## Role ladder
| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-11 | pass |
| {editor} | `admin.php?page=listora-setup` (every step) | "Sorry, you are not allowed to access this page." (needs `manage_listora_settings`) |
| any non-admin POSTing a step | replay with a valid-looking nonce | refused (capability + `listora_wizard` nonce) |
| {guest} | URL | login redirect |

## Pass criteria
1. First load redirects once; the wizard shows no foreign notices.
2. Every step advances without white pages; Skip appears on all but the last step and closes the run.
3. The done heading tells the truth (importing / ready / failed) and flips without reload; Done lands on the Dashboard with zero `headers already sent`.
4. Re-run is explicit, shows every demo pack, creates no duplicate pages; stale steps render the summary; replays write nothing.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Wizard never redirects | transient not set | `class-activator.php` (~65), `class-activation-redirect.php` |
| Blank page on step submit/Done | output before the POST handler | `Setup_Wizard::init` priority, `handle_post_submission` |
| Other plugin notices visible | `quiet_wizard_notices` not on `in_admin_header` | `class-setup-wizard.php` ~63 |
| Landing shows step 1 | stale session transient | `wb_listora_wizard_session_<id>` |
| Heading stuck on "importing" / never flips | heading branch / unbuilt JS | `class-setup-wizard.php` heading branch, `src/admin/import-progress.js` + build |
| Pages duplicated on re-run | page registry not idempotent | `Page_Registry`, activator essential-pages step |

## Not verified
- From the merged files: landing, rerun, `step=finish`, `step=done` and the editor refusal were observed live via curl; the POST-replay and edge-door steps (11) were not executed (they rewrite settings); the faked-run flip in a live browser (5) was not executed; step 12 not run; steps 1-4, 7, 9 carried over from the first-run journey.

## Cleanup
- Delete the option and transient state the run created: `wp option delete wb_listora_bg_import_qarun1`, `wp option delete wb_listora_bg_import_<run id>`, `wp transient delete wb_listora_wizard_session_<admin id>`, `wp transient delete wb_listora_show_wizard_redirect`.
- Restore `wb_listora_settings` and `wb_listora_setup_data` from the backup taken in the prerequisites, then `wp option update wb_listora_setup_complete 1` if the site was complete before the run.
- Delete the Restaurant demo listings step 4 imported: `wp post list --post_type=listora_listing --meta_key=_listora_demo_content --format=ids | xargs wp post delete --force`.
- Remove the mu-plugin notice used as the positive control in step 2.
