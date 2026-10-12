---
group: import-migration
plugin: wb-listora
scope: Demo-content import, background import engine, CSV and settings import/export, and competitor-plugin migration (Free pipeline and Free Migration tab)
journeys: 7
site_profile: required
---
# Import and migration: what an agent checks here

## Scope
Covers: the setup-wizard demo step and its progress widget, Settings > Advanced "Re-run Demo Import" / "Delete Demo Data", Settings > Import / Export (settings JSON, listings CSV, basic CSV importer), Settings > Migration (Free competitor cards), the shared `Background_Import` engine and its progress REST route, hours mapping during migration, the submit-hook context for migrated listings, and demo-account security.

Does NOT cover: the wizard steps before "Demo Content" (type, location, maps, pages: `settings-setup`); how an imported listing looks on its own page (`listing-detail`), in the grid (`browse-blocks`) or on the map (`map`); search index quality beyond "imported title is searchable" (`directory-search`); the email templates themselves (`emails-alerts`); the Pro Visual Import wizard, Google Places and the Pro competitor UI (Pro folder, run after this one).

When Pro is active two Free surfaces change: the Migration tab is removed from the nav and the basic CSV "Import Listings" card is not rendered (filter `wb_listora_show_basic_csv_import` is forced false). Run the Free group once with Pro deactivated to reach both.

## Surfaces

| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| S1 | Setup wizard, Demo Content step | `$SITE_URL/wp-admin/admin.php?page=listora-setup&rerun=1&step=demo` | {admin} | `includes/admin/class-setup-wizard.php` (`render_step_demo`) |
| S2 | Setup wizard, Done step with live import progress | `admin.php?page=listora-setup&step=done` (after S1 submits with a pack) | {admin} | `class-setup-wizard.php` (`render_step_done`, `render_import_progress`), `assets/css/admin/setup-wizard.css` |
| S3 | Settings > Import / Export | `admin.php?page=listora-settings&tab=import-export` | {admin} (manage_listora_settings); {editor} gets 403 | `includes/admin/class-import-export-tabs.php` (`render_import_export`), `assets/js/admin/admin-pages.js` |
| S4 | Settings > Migration (Free only; absent while Pro active) | `admin.php?page=listora-settings&tab=migration` | {admin} | `class-import-export-tabs.php` (`render_migration`), `admin-pages.js` (`initMigration`) |
| S5 | Settings > Advanced > Maintenance: Demo import and Delete demo data rows | `admin.php?page=listora-settings&tab=advanced` | {admin} (`manage_options` for the AJAX) | `includes/admin/class-demo-import-admin.php` (`render_rows`), `class-advanced-settings-tab.php` |
| S6 | Re-run confirm dialog | click `#listora-demo-import-btn` while `data-has-demo="1"` | {admin} | `admin-pages.js` (`initDemoImport`, `window.listoraConfirm`) |
| S7 | Import progress widget (shared, three hosts: S2, S3 CSV card, S5) | `.listora-import-progress` | {admin} | `assets/css/admin/import-progress.css`, `admin-pages.js` (`updateProgressWidget`, `finishImport`) |
| S8 | Directory after import | `$SITE_URL{directory}` and `$SITE_URL{directory}?listora_type={type}` | {guest} | block output (owned by `directory-search`); checked here only for imported content |
| S9 | Events Calendar page after the Events pack | the calendar page, month and next month | {guest} | `.listora-calendar__*` (owned by `browse-blocks`) |
| S10 | Login form | `$SITE_URL/wp-login.php` | {guest} | WordPress core; demo accounts only |
| S11 | REST progress route | `GET $SITE_URL/wp-json/listora/v1/import/progress/{run_id}` | {admin} 200, {other} 403, {guest} 401 | `includes/import-export/class-background-import.php` (`progress_permissions`) |
| S12 | REST import/export | `GET /listora/v1/export/csv`, `POST /import/csv`, `/import/json`, `/import/geojson` | {admin} only | `includes/rest/class-import-export-controller.php` |
| S13 | WP-CLI | `wp listora demo seed|remove`, `wp listora migrate --from=<key> [--dry-run]`, `wp listora import`, `wp listora export` | shell | `includes/class-cli-commands.php` |

## Components on each surface

**S1 Demo step** (page order): wizard step strip (label "Demo Content"); heading "Want some sample listings?"; intro paragraph; option cards `.listora-wizard__option-card` with `input[name=import_demo]` value 1 (checked) and 0 ("Skip demo content - I will add my own listings"); pack grid `.listora-demo-packs` of `label.listora-demo-pack` each with `input[name=demo_pack]`, icon, `strong` title and a `span` count line, one per pack in `wb_listora_get_demo_packs()` (10) plus "All packs" (value `all`) = 11 radios; Back and "Finish Setup" buttons (label differs from "Continue"); `.listora-wizard__skip` "Skip setup" link; admin notice slot (`render_demo_import_notice` transient, must be empty on a clean load).

**S2 Done step**: `.listora-wizard__success` check icon; `h2[data-listora-done-heading]` and `p[data-listora-done-subhead]` (three texts: importing, ready, failed, each with `data-ready-text` / `data-failed-text`); `.listora-import-progress[data-run-id]` with `__label` + `__text`, `__track[role=progressbar]` + `__bar`, count line; action links (View directory, Add listing, Settings: `.listora-wizard__actions`); "Go to Dashboard" button in `.listora-wizard-done-form`.

**S3 Import / Export**: pane `.listora-impex`; block "Plugin Settings" with cards "Export Settings" (`[data-listora-action=export-settings]`) and "Import Settings" (`#listora-import-file` accept .json, `[data-listora-action=import-settings]`, `#listora-import-status`); block "Listings Data" with cards "Export Listings" (`#listora-csv-export-type` select with "All types" first, `#listora-csv-export-btn`, `#listora-csv-export-status`) and "Import Listings" `#listora-csv-import-card` (`#listora-csv-import-type` required, `#listora-csv-import-file` accept .csv, `#listora-csv-import-mapping.is-hidden[aria-live]`, `#listora-csv-import-btn`, `#listora-csv-import-status`, `#listora-csv-import-progress` widget). Pro active: the Import Listings card is absent and the Pro sections follow (Pro `_group.md`).

**S4 Migration**: `.listora-settings-block` "Migration Sources" with description; `.listora-migration-grid` of `.listora-migration-card[data-source]` per registered migrator (5: directorist, listingpro, geodirectory, hivepress, bdp). Each card: `__header` (icon, `h3.__title`, `__desc`, badge `.listora-badge--success` "Detected" or `--muted` "Not Detected"), `__body`: detected = `__count` ("N listings available for migration." with `strong`), `__controls` (`label` + `input.listora-migration-dryrun[data-source]` "Dry run (validate without importing)", `button.listora-migration-start[data-source][data-count]` "Start Migration"), `#listora-progress-{slug}` (`#listora-fill-{slug}`, `#listora-stats-{slug}`, `#listora-pct-{slug}`), `#listora-result-{slug}.listora-migration-result`; not detected = `__notice` "<Source> data not found. ...". Zero migrators: paragraph "No migration sources are available...".

**S5 Advanced rows**: row "Demo import": `#listora-demo-import-btn.button` (icon + "Re-run Demo Import", `data-has-demo`, `data-packs` JSON), `#listora-demo-import-status[aria-live]`, description, hidden `#listora-demo-import-progress` widget. Row "Delete demo data": `#listora-demo-delete-btn.button-link-delete` (`data-demo-count`, disabled at 0), `#listora-demo-delete-status`, description whose wording and plural follow the count. Neighbouring rows in the same section: Setup Wizard re-run link (owned by `settings-setup`).

**S6 Confirm**: `listoraConfirm` modal with the text "Demo listings already exist. Re-running will add duplicate demo content. Continue?", Continue and Cancel buttons, Escape and backdrop close.

**S7 Progress widget**: `__label` (icon + `__text`), `__track[role=progressbar][aria-valuenow][aria-label]`, `__bar` (inline-size), `__stats` with `__count`; end states add `.is-done` or `.is-failed`; failed adds `ul.__log` (last ~10 messages) and `a.__as-link` ("View Action Scheduler"); done adds `a.__view-link` ("View Listings") after the status span.

**S8 Directory**: result count heading, card grid with image, title, type badge, address; newest sort; Features filter list per type (owned by `directory-search`; here: values must come from the import).
**S9 Calendar**: month header, `.listora-calendar__cell` day cells with events, `.listora-calendar__empty[role=status]` ("No events in <month year>.") must be absent.
**S10 Login**: `#login_error` message for a bad demo password.
**S11-S13**: no UI; run by the API and CLI journeys.

## States to force

| Surface | State | How |
|---|---|---|
| S1 | fresh run vs `rerun=1` | open without `rerun=1` after setup completed: the wizard must not reopen (`step=done` typed with no data redirects) |
| S1 | each pack selected / All packs / Skip | click each radio; submit |
| S2 | importing (not done) / done / failed | start a 30+ row run and load immediately; wait; the failed fixture of `background-import-lifecycle.md` step 10 |
| S3 | Pro active vs inactive | deactivate Pro; the CSV card appears |
| S3 | zero types / many types in the select | delete types in a scratch site; seed 12 types |
| S3 | bad settings file / other-version file / CSV with wrong column count | upload each |
| S4 | detected, not detected, zero migrators, huge count (4+ digits) | seed source posts of a source type (`at_biz_dir`, `hp_listing`, `wpbdp_listing`, `gd_place_detail` rows) or none |
| S4 | dry run vs real, errors > 0, network abort | tick/untick dry run; block admin-ajax after click; a source row that throws |
| S5 | `data-has-demo` 0 vs 1; delete count 0 / 1 / many | empty site vs after demo import; `wp listora demo remove` |
| S7 | 0%, mid, 100%, failed, two runs without reload | poll fixtures; run the button twice |
| all | long content, RTL | 120-char demo title via CSV; switch site language to an RTL locale |
| S8/S9 | N=0, 1, many | before import, after Events pack, after re-import |

## Settings that change this group

| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| Wb-listora-pro active | on / off | S3: Import Listings card hidden when on; S4: Migration tab and nav item removed when on |
| Settings > Submissions > moderation (`wb_listora_settings[moderation]`) | `manual` / `auto_approve` | status of CSV and migrated listings in S3/S4 results and `{directory}` visibility |
| Site timezone (Settings > General) | UTC, UTC+14, UTC-12 | S9 event dates, hours mapping (F-17) |
| Filter `wb_listora_bg_import_use_async` | true / false | S7: async chunks vs a single synchronous run (10-row `SYNC_THRESHOLD`) |
| Filter `wb_listora_should_send_notification` | applied around seeder inserts | email log gains 0 rows during demo import |
| Site language | LTR / RTL | S1, S3, S4, S7 mirroring |

## Fixtures and setup
- Placeholders from `docs/qa/sites/<site-host>.json`. Fixtures script: `docs/qa/fixtures/2.0-fixtures.php` (run as documented in `docs/qa/fixtures/`); hours probe: `docs/qa/fixtures/migrated-hours-probe.php`.
- Run destructive journeys on a SCRATCH site only (demo import, CSV import and migration write listings, users and attachments).
- Capture baselines before each journey: `wp post list --post_type=listora_listing --post_status=any --format=count`, `SELECT COALESCE(MAX(id),0) FROM ${PREFIX}listora_email_log`, byte size of `wp-content/debug.log`.
- Competitor source data: create posts of the source type (`at_biz_dir`, `hp_listing`, `wpbdp_listing`) or GeoDirectory rows; the migrators read the DB, the source plugin need not be active.
- Restore afterwards: `wp listora demo remove`; delete `QA Roundtrip*` and `QA-mig-*` listings; `delete_option( 'wb_listora_bg_import_<run_id>' )` and unlink `uploads/wb-listora-import/src-*.csv`; reactivate Pro; reset timezone to UTC.

## Run order
1. `import-migration/demo-import-quality.md` (mixed, high): wizard demo step, pack list, Re-run button, dates, features, events. Browser steps 1, 2, 8 first.
2. `import-migration/background-import-lifecycle.md` (mixed, high): progress contract, resume, failed terminal state; the engine under step 1.
3. `import-migration/import-export-roundtrip.md` (browser, normal): Import / Export tab, CSV and settings round trip (Pro deactivated for the Free importer).
4. `import-migration/migrate-from-competitor-plugin.md` (mixed, high): the migration pipeline per source; step 9 in a browser.
5. `import-migration/competitor-hours-are-mapped-on-import.md` (mixed, high): hours shapes, raw preservation, listing page check.
6. `import-migration/migration-listener-context.md` (cli, high): quiet listeners during migration.
7. `import-migration/demo-accounts-not-loginable.md` (cli, critical): demo passwords; run last, after the fresh import of step 1.

Then run: `wb-listora-pro/docs/qa/journeys/import-migration/_group.md`.

## Group-specific micro checks

| id | Check |
|---|---|
| G-01 | S1: radio count with `name=demo_pack` equals `count(wb_listora_get_demo_packs())` + 1 (11), exactly one is checked, and every pack card shows a count line; after importing each pack the number of `_listora_demo_content` listings equals the number in that card ("20 restaurants" etc.) |
| G-02 | S1: the primary button reads "Finish Setup →" on the demo step and "Continue →" on earlier steps; the "Skip setup" link is present on every step except Done |
| G-03 | S7: while polling, `__track[aria-valuenow]` equals the integer in `__bar` inline-size equals the REST `percent`, and `__count` equals REST `imported`; none ever decreases |
| G-04 | S2 vs S5: the S2 bar starts with `style="width:0%"` while S3/S5 use `inline-size`; in an RTL locale both bars must fill from the right edge |
| G-05 | S7: run Re-run Demo Import twice without reloading; exactly one "View Listings" link and no duplicate `__log` or "View Action Scheduler" link remain next to `#listora-demo-import-status` |
| G-06 | S7 done line: "Imported: N" always; ", Skipped: n" and ", Errors: n" appear only when non-zero; N equals `GET .../progress` `imported`; error styling (`is-error`) only when errors > 0 |
| G-07 | S5: after clicking Re-run Demo Import the button text becomes "Queueing…" and is disabled; once finished it shows "Re-run Demo Import" AND still has its refresh icon (the script writes `textContent`, which removes the `<i data-lucide>`); compare icon presence before and after |
| G-08 | S6: dialog appears iff `data-has-demo="1"`; Cancel, Escape and backdrop each send no `listora_run_demo_import` request and return focus to `#listora-demo-import-btn` |
| G-09 | S5: `#listora-demo-delete-btn` is disabled iff `data-demo-count` is 0; the description says "the 1 demo listing" vs "the N demo listings" correctly and N equals `Demo_Seeder::count_demo_content()['listings']`; after a Delete click the count and disabled state update without reload |
| G-10 | S3: export filename matches `listora-export-YYYY-MM-DD-HHMMSS.csv`; the type select lists "All types" first and then exactly the Listing Types admin list; data row count equals `COUNT(*)` of published listings of that type |
| G-11 | S3: `#listora-import-status` after a good settings file reads success and after a CSV/other-version file reads an error with the error colour class; file input rejects other extensions at the picker |
| G-12 | S4: for every card, badge, count and controls agree: "Detected" implies a count line, dry-run checkbox and Start button; "Not Detected" implies the notice and none of those; the `strong` count equals the button `data-count` equals the dry-run `Total` and uses thousands separators at 1,000+ |
| G-13 | S4: clicking Start Migration on one card disables every card's button; labels run "Start Migration" -> "Migrating..." -> "Complete" (success) or back to "Start Migration" (failure); result box class is `--dryrun` for dry runs, `--success` otherwise, `--error` whenever errors > 0 even if some imported |
| G-14 | S4: the progress fill jumps 0 -> 100% (synchronous AJAX, no intermediate value); a source of 2000+ rows hits the 60 s abort and shows the "Network is slow" text while the server may still be running: re-run must import 0 new (idempotent), never duplicate |
| G-15 | S2: heading and sub-heading swap together with the run: importing text while `done=false`, "Your directory is ready!" on completion without reload, failed text on `status=failed`; the progress widget stays visible in the failed state |
| G-16 | S8: after the demo import the "newest" sort differs from the default order, no listing card shows an empty author/owner name, and the count heading reads "1 listing" / "N listings" correctly after a one-listing CSV import |
| G-17 | S9: every `.listora-calendar__cell` for a date with an event carries it, the weekly comedy night appears on each of its weekdays this month and next, and no event has a date before the import day |
| G-18 | S10: a flagged demo user posting `password` gets `#login_error` and no auth cookie; the message must not differ from the one for a real non-demo user with a wrong password |
| G-19 | S3 at 390: the two cards in each block stack, the mapping table scrolls inside `#listora-csv-import-mapping` not the page, every button and file input is at least 40 px tall |
| G-20 | S3 with Pro active vs inactive: exactly one CSV importer exists on the screen in each state (Free card XOR Pro Visual Import), never both and never neither |

## Known defects (expected-fail)
- `import-migration/migrate-from-competitor-plugin.md` step 1: ListingPro has no schema doc under `audit/architecture/competitor-schemas/` (`listingpro.md` missing).

No other step in this folder is marked `expected-fail` today.

## Not covered yet
- The Free Migration tab UI (S4: badges, dry-run checkbox, Start button, result box, AJAX `listora_run_migration`, 60 s abort) has no browser step; `migrate-from-competitor-plugin.md` drives the CLI instead.
- Delete Demo Data (S5: `#listora-demo-delete-btn`, AJAX `listora_delete_demo`, confirm, count refresh, ownership of imported attachments) is only used as cleanup, never asserted.
- The wizard Done-step progress UI (S2: heading swap, failed state, link to directory/add listing) has no step.
- CSV import behaviour on the Free card itself with Pro off: mapping step (`#listora-csv-import-mapping`), required type, empty file, and failed-run UI (`import-export-roundtrip.md` step 7, 8 are unrun); roundtrip steps 1-4 do not run at all while Pro is active.
- Settings JSON export contents: whether secrets (`captcha_secret_key`, `google_maps_key`) are exported is asked, not asserted.
- `POST /import/csv`, `/import/json` permission ladder and boundary input (huge body, wrong content type); only `/import/geojson` has a step.
- RTL and dark mode for all admin surfaces in this group (no step runs them).
