---
journey: admin-setup-wizard-first-run
plugin: wb-listora
priority: critical
roles: [administrator, qa_editor]
covers: [setup-wizard, headers-already-sent, wizard-quiet-notices, wizard-skip, demo-seed, essential-pages-idempotent, rerun-landing, 2.0.0-wizard]
prerequisites:
  - "Site reachable at $SITE_URL; admin ?autologin=1"
  - "A scratch state: wizard not completed (`wp option delete wb_listora_setup_complete`), because the demo step imports content"
  - "debug.log byte count baseline"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
---

# Setup wizard first run: redirect, quiet screen, Skip, demo, Done, re-run

The wizard (`admin.php?page=listora-setup`) has six steps: `type`, `location`, `maps`, `pages`, `demo`, `done`. Sentinel for #9867159785: finishing used to print `Cannot modify header information - headers already sent` and show a blank page. 2.0.0: other plugins' notices are hidden on the wizard (only its own demo-import warning shows), "Skip setup" is offered on every step except the last, and the demo step lists every pack. Re-running is deliberate (`rerun=1` or the "Run the wizard again" button).

## Setup

```bash
wp option delete wb_listora_setup_complete
wp transient set wb_listora_show_wizard_redirect 1 3600     # what activation sets
wc -c wp-content/debug.log                                  # baseline
```
(A real activation also works: `wp plugin deactivate wb-listora && wp plugin activate wb-listora`.)

## Steps

### 1. First admin page load redirects to the wizard (administrator)
- **Action**: `$SITE_URL/wp-admin/index.php?autologin=1`.
- **Expect**: one redirect to `admin.php?page=listora-setup` (the one-shot transient is consumed: a second load of `index.php` is NOT redirected again). With Pro active the admin is never bounced between two wizards.
- **On fail**: `Activation_Redirect::maybe_redirect` (`includes/admin/class-activation-redirect.php` ~49), transient `wb_listora_show_wizard_redirect`.

### 2. The wizard is quiet and the progress bar is right (1440 and 390)
- **Expect**: title "WB Listora Setup"; `.listora-wizard__progress-step` x6 with the current one marked; ZERO `.notice` elements even when another plugin or a mu-plugin queues a notice (2.0.0: `Setup_Wizard::quiet_wizard_notices` removes every `admin_notices`/`all_admin_notices` action except its own demo-import warning); a "Skip setup" link under the card on type, location, maps, pages and demo.
- **Positive control**: the same mu-plugin notice DOES show on `admin.php?page=listora` (the Dashboard), so its absence on the wizard is the wizard's rule.
- **On fail**: `class-setup-wizard.php` (~75), `render` skip block (~484).

### 3. Walk the steps
- **Action**: step `type`: pick a directory type and Continue; `location`: set country/city and Continue; `maps`: keep OpenStreetMap (Leaflet) and Continue; `pages`: leave the showcase pages ticked and Continue.
- **Expect**: each submit advances to the next step without a white page; "← Back" returns without losing choices; no PHP notice in `debug.log`.
- **On fail**: `Setup_Wizard::handle_post_submission` runs on `admin_init` priority 1 before any output.

### 4. Demo step lists every pack, honours Skip
- **Action**: `step=demo`.
- **Expect**: 11 radios `input[name=demo_pack]` (Restaurant Directory, Job Board, Real Estate, Hotel Directory, General Directory, Classifieds, Education, Healthcare, Places & Attractions, Events, All packs) and the "Skip demo content - I will add my own listings" option; the button reads "Finish Setup →". The Events line says "8 upcoming concerts, conferences and workshops, dated from the day you import".
- **Action**: choose "Restaurant Directory" and Finish Setup.
- **Expect**: the import is queued in the background (see `admin/demo-import-background-progress.md`), the browser lands on `step=done`; DB: at least 3 listings with `_listora_demo_content`; no approval emails (Email Log unchanged).

### 5. Done step and the headers regression (CRITICAL)
- **Action**: on `step=done` observe, then click "Go to Dashboard".
- **Expect**: heading "Your directory is ready!" (or "Setup is saved, but the demo import did not finish" when a pack failed, with "run the wizard again to retry the import"); NO "Skip setup" link on this step; the button lands on `admin.php?page=listora&listora-welcome=1` (the Dashboard), never a blank page; `debug.log` diff has zero `headers already sent`; `wb_listora_setup_complete` = 1.
- **On fail**: regression of #9867159785: `Setup_Wizard::init` hook priority, `render_step_done` (~960).

### 6. Skip setup closes the run
- **Action**: reset (`wp option delete wb_listora_setup_complete`), open `admin.php?page=listora-setup`, click "Skip setup".
- **Expect**: redirect to the Dashboard; the wizard no longer accepts step POSTs for that session (replaying a captured step POST does nothing); the menu keeps no "Setup Wizard" sidebar entry once complete.

### 7. Re-run is explicit and idempotent
- **Action**: open `admin.php?page=listora-setup` again (no params).
- **Expect**: the "Setup is already complete" card ("Your directory is set up. Running the wizard again will take you back through directory type, location, maps and pages, and can import demo content a second time.") with "Go to Listora" and "Run the wizard again"; the latter opens `step=type&rerun=1`. Walking it again does not duplicate the essential pages or seeded listings (`wp post list --post_type=page --name=add-listing --format=count` stays 1; same for the Directory and Dashboard pages).
- **Also**: deep links `rerun=1&step=done` with no session show the done step without importing anything.

### 8. Essential pages exist once
- **Action**: `wp eval 'foreach(["directory","submission","dashboard"] as $k){ echo $k,": ",\WBListora\Core\Page_Registry::get_id($k),"\n"; }'`.
- **Expect**: three distinct non-zero IDs; each maps to a published page (here Directory `/listings/`, Add Listing `/add-listing/`, My Listings `/my-listings/`).

### 9. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-8 | pass |
| qa_editor | `admin.php?page=listora-setup` | not allowed (needs `manage_listora_settings`) |
| any non-admin POSTing a step | replay a step POST with a valid-looking nonce | refused (capability + `listora_wizard` nonce) |
| anonymous | URL | login redirect |

## Pass criteria

1. First load redirects once; the wizard shows no foreign notices.
2. Every step advances without white pages; Skip appears on all but the last step and closes the run.
3. Done lands on the Dashboard with zero `headers already sent` entries.
4. Re-run is explicit, shows every demo pack, and creates no duplicate pages.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Wizard never redirects | transient not set | `class-activator.php` (~65), `class-activation-redirect.php` |
| Blank page on step submit/Done | output before the POST handler | `Setup_Wizard::init` priority, `handle_post_submission` |
| Other plugin notices visible | `quiet_wizard_notices` not on `in_admin_header` | `class-setup-wizard.php` ~63 |
| Pages duplicated on re-run | page registry not idempotent | `Page_Registry`, activator essential-pages step |
