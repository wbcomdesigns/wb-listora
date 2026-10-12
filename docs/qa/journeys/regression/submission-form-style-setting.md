---
journey: submission-form-style-setting
plugin: wb-listora
priority: normal
roles: [admin, member, logged-out]
covers: ["submission form style setting", "layoutMode default resolution", "single-form stepper leak"]
prerequisites:
  - "Submission page with a listora/listing-submission block that has NO explicit layoutMode attribute (this site's /add-listing/ qualifies)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Site setting controls the submission form layout (wizard vs single page)

Settings > Submissions > "Submission form style" (`wb_listora_settings[submission_form_style]`, values `wizard` | `single_form`, radios in `includes/admin/class-settings-page.php:1049-1054`). The block's `layoutMode` attribute defaults to `default`, which defers to the setting (`blocks/listing-submission/render.php:28-52`); an explicit block `wizard`/`single-form` wins. The `wb_listora_submission_layout_mode` filter runs after resolution.

## Setup
Record the current value (2026-10-12: `wizard`) and restore it at the end.

## Steps

### 1. Default = wizard
- **Action**: with the setting `wizard` (or unset) load `/add-listing/` as a member.
- **Expect**: root element classes include `listora-page--booking` and `listora-submission--wizard`; stepper `.listora-submission__progress` visible with six `.listora-submission__step-dot` (Pro) ; one `.listora-submission__step` visible at a time.

### 2. Setting flips to single page form
- **Action**: set `submission_form_style` to `single_form` (Settings UI radio or `wp eval`), reload.
- **Expect**: root has `listora-submission--single-form` and `listora-page--list` (not `--booking`); every step section is stacked and visible; `.listora-submission__progress` computes `display: none` (the stepper-leak regression). Verified 2026-10-12 server-side: classes `listora-page listora-page--list listora-submission listora-block listora-submission--single-form`, and the progress element is present in the DOM (hidden by CSS). Computed `display` was not measured.

### 3. Explicit block attribute wins
- **Action**: a test page whose block is saved with `{"layoutMode":"wizard"}`, setting still `single_form`.
- **Expect**: wizard renders on that page; and the reverse (`single-form` block with setting `wizard`).

### 4. Edit mode
- **Action**: Dashboard > Edit on a listing (setting `wizard`).
- **Expect**: single-form layout (edit auto-single-form, filter `wb_listora_edit_auto_single_form`, `render.php:443`) with no stepper.

### 5. Settings UI
- **Action**: wp-admin > Listora > Settings > Submissions.
- **Expect**: "Submission form style" row with radios "Step-by-step wizard" / "Single page form"; saves and round-trips (reload shows the saved radio).

### 6. Role ladder / mobile
Member sees both layouts (steps 1-3); logged-out visitors see the sign-in prompt in both; admin sees both (control). At 390 the single form has no horizontal scroll and Submit is >= 40px tall.

## Cleanup
Restore `submission_form_style` and any test page.

## Fail diagnostics
| Symptom | File |
|---|---|
| Setting ignored | `blocks/listing-submission/render.php:33-40` |
| Stepper shows in single form | `blocks/listing-submission/style.css:180-181` (`.listora-submission--single-form .listora-submission__progress`) |
| Wrong shell class | `blocks/listing-submission/render.php:461` |

## Not verified
Step 2 was verified from the curl-rendered HTML as QA-J1-member; computed styles, steps 3-6 and the Settings UI radios (read from source) were not run in a browser. Setting restored to `wizard`.

Verified via: curl/DOM.
