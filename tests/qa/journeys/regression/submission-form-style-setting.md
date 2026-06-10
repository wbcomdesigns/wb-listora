---
journey: submission-form-style-setting
plugin: wb-listora
priority: normal
roles: [admin, member]
covers: ["submission form style setting", "layoutMode default resolution", "single-form stepper leak"]
prerequisites:
  - "Submission page with a listora/listing-submission block that has NO explicit layoutMode attribute"
estimated_runtime_minutes: 2
---

# Site setting controls the submission form layout (wizard vs single page)

1.2.0 adds Settings > Submissions > "Submission form style". The block's
`layoutMode` attribute default changed from `wizard` to `default`, which
defers to the setting; a block whose author explicitly chose
wizard/single-form in the editor keeps that choice. New
`wb_listora_submission_layout_mode` filter runs after resolution.

Also fixes a latent leak found while building this: the single-form CSS hid
`.listora-submission__stepper` but the stepper template's real root class is
`.listora-submission__progress` — the wizard progress bar rendered in every
single-form context (including dashboard inline edit).

## Steps

### 1. Default = wizard
- **Action**: with `submission_form_style` unset (or `wizard`), load the
  submission page.
- **Expect**: `.listora-page--booking` shell, stepper visible, one step shown
  at a time.

### 2. Setting flips to single page form
- **Action**: set the option key to `single_form` (Settings UI radio or
  `wp eval`), reload.
- **Expect**: `.listora-submission--single-form` on the wrapper,
  `.listora-page--list` shell, ALL step sections stacked and visible, and
  `.listora-submission__progress` computes `display: none` (no stepper).

### 3. Explicit block attribute wins
- **Action**: a block saved with `{"layoutMode":"wizard"}` on a test page,
  setting still `single_form`.
- **Expect**: wizard renders on that page.

### 4. Settings UI
- **Action**: Settings > Submissions.
- **Expect**: "Submission form style" radio row (Step-by-step wizard /
  Single page form) saves and round-trips.
