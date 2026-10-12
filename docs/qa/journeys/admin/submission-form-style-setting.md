---
journey: submission-form-style-setting
plugin: wb-listora
priority: normal
site_profile: required
roles: [admin, owner, guest]
covers: ["submission form style setting", "layoutMode default resolution", "single-form stepper leak"]
prerequisites:
  - "A submission page with a listora/listing-submission block that has NO explicit layoutMode attribute (the page behind `submit_url` in the app-config payload usually qualifies)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged default theme]
supersedes: []
fails_on: "pre-fix: .listora-submission--single-form did not hide .listora-submission__progress (stepper leaked into single-page form)"
---

# Site setting controls the submission form layout (wizard vs single page)

## Why

Settings > Submissions > "Submission form style" (`wb_listora_settings[submission_form_style]`: `wizard` | `single_form`, radios in `includes/admin/class-settings-page.php:1049-1054`) decides the form layout for a block whose `layoutMode` is `default` (`blocks/listing-submission/render.php:28-52`). If it is ignored, owners cannot switch layouts; if the stepper leaks into the single form, members see a progress bar with nothing to step through. An explicit block `wizard`/`single-form` wins; the `wb_listora_submission_layout_mode` filter runs after resolution.

## Setup

- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Record the current value (`wp --path="$WP_PATH" eval 'echo wb_listora_get_setting("submission_form_style","wizard");'`) and restore it at the end.
- `$SUBMIT_URL` = the submission page (`submit_url` from `GET /listora/v1/settings/app-config`). Sign in as {owner}.

## Steps

### 1. Default is the wizard
- **Action**: with the setting `wizard` (or unset) load `$SUBMIT_URL` as {owner}.
- **Expect**: root classes include `listora-page--booking` and `listora-submission--wizard`; the stepper `.listora-submission__progress` is visible with one `.listora-submission__step-dot` per step; one `.listora-submission__step` visible at a time.
- **Read-back**: step-dot count equals the number of `.listora-submission__step` sections.

### 2. Setting flips to the single page form
- **Action**: set `submission_form_style` to `single_form` (Settings UI radio or `wp eval`), reload.
- **Expect**: root has `listora-submission--single-form` and `listora-page--list` (not `--booking`); every step section is stacked and visible; `getComputedStyle(.listora-submission__progress).display` is `none` (the stepper-leak regression).
- **Read-back**: server HTML classes verified 2026-10-12 (`listora-page listora-page--list listora-submission listora-block listora-submission--single-form`); computed `display` not measured.

### 3. Explicit block attribute wins (both directions)
- **Action**: a test page whose block is saved with `{"layoutMode":"wizard"}` while the setting is `single_form`; then a `single-form` block with the setting `wizard`.
- **Expect**: the block value wins in each case.

### 4. Edit mode
- **Action**: Dashboard > Edit on a listing (setting `wizard`).
- **Expect**: single-form layout (edit auto-single-form, filter `wb_listora_edit_auto_single_form`, `render.php:443`) with no stepper.

### 5. Settings UI round trip
- **Action**: wp-admin > Listora > Settings > Submissions as {admin}; pick each radio, Save, reload.
- **Expect**: row "Submission form style" with radios "Step-by-step wizard" / "Single page form"; the saved radio is selected after reload; an invalid posted value (`hacked`) leaves the previous value.
- **Read-back**: the option value after each save.

### 6. Filter runs last
- **Action**: mu-plugin `add_filter('wb_listora_submission_layout_mode', fn()=>'single-form');` with the setting `wizard`.
- **Expect**: single form renders; remove the mu-plugin afterwards. Not verified live (added by consolidation).

### 7. Viewport and theme matrix
- **Action**: load both layouts at 1440 and 390, light and dark, in BuddyX Pro and one unbridged theme.
- **Expect**: no horizontal scroll at 390; Submit and Next buttons >= 40px tall; stepper hidden in single form in every cell. Not verified live.

## Role ladder

| Actor | Expect |
|---|---|
| {owner} | both layouts (steps 1-4, 6) |
| {guest} | the sign-in prompt in both layouts |
| {admin} (control) | both layouts and the Settings UI (step 5) |
| {editor} | Settings > Submissions refused (no `manage_options`). Not verified live |

## Pass criteria

1. `wizard` setting renders the booking/wizard shell with the stepper; `single_form` renders the list shell with the stepper hidden.
2. An explicit block `layoutMode` beats the setting; the filter beats both.
3. Edit mode is single-form with no stepper.
4. The Settings radios round-trip and reject invalid values.

## Fail diagnostics

| Symptom | File |
|---|---|
| Setting ignored | `blocks/listing-submission/render.php:33-40` |
| Stepper shows in single form | `blocks/listing-submission/style.css:180-181` (`.listora-submission--single-form .listora-submission__progress`) |
| Wrong shell class | `blocks/listing-submission/render.php:461` |

## Not verified

Step 2 was verified from the curl-rendered HTML as a member; computed styles, steps 3-7 and the Settings UI radios (read from source) were not run in a browser.

## Cleanup

Restore `submission_form_style` and delete any test page or mu-plugin.
