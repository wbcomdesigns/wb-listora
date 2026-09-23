---
journey: setup-wizard-done-heading-follows-import
plugin: wb-listora
priority: high
roles: [administrator]
covers: [setup-wizard, background-import, card-10290534093]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Back up wb_listora_settings and wb_listora_setup_data first"
estimated_runtime_minutes: 4
---

# The done screen heading tells the truth about the demo import

The done step said "Your directory is ready!" above "Importing demo content… 0 items imported". The
first fix swapped the heading to "Almost there - your demo content is importing" whenever a
`demo_run_id` existed, and claimed the progress poller would swap it back. The poller was never
changed, and the run id outlives the run, so a finished import read "importing" forever - directly
above "Demo content imported.".

The heading now follows `Background_Import::get_progress()` status server-side, and
`build/admin/import-progress.js` swaps both lines to their `data-ready-text` when the poll reports
`done`. **Build note:** the fix lives in `src/admin/import-progress.js`; a source edit alone ships nothing.

## Steps

### 1. A live import reads "importing", then flips without a reload
- **Setup**: fake a running run and point the wizard at it:
  `wp eval 'update_option("wb_listora_bg_import_qarun1", ["kind"=>"demo","status"=>"running","total"=>20,"processed"=>5,"imported"=>5], false); update_option("wb_listora_setup_data", ["demo_run_id"=>"qarun1"]);'`
- **Action**: open `admin.php?page=listora-setup&step=type&rerun=1`, then `…&step=done`
- **Expect**: heading "Almost there - your demo content is importing"; progress text "Importing demo content in the background…"
- **Action**: `wp eval '$s=get_option("wb_listora_bg_import_qarun1"); $s["status"]="done"; $s["imported"]=20; update_option("wb_listora_bg_import_qarun1",$s,false);'` and wait ~6s (poll is 2.5s)
- **Expect**: WITHOUT reloading - heading "Your directory is ready!", subhead "Everything is set up. Here's what you can do next:", progress "Demo content imported.", count 20

### 2. A finished run never reads "importing"
- **Setup**: point `wb_listora_setup_data.demo_run_id` at a run whose status is already `done`
- **Action**: open the done step inside a run (`rerun=1` first)
- **Expect**: heading "Your directory is ready!" on first paint - never "Almost there" above "Demo content imported."

## Teardown
`wp option delete wb_listora_bg_import_qarun1`; restore both options from backup.
