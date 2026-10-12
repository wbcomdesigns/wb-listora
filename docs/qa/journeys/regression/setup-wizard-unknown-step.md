---
journey: setup-wizard-unknown-step
plugin: wb-listora
priority: normal
roles: [administrator, editor]
covers: [setup-wizard, wizard-step-normalization]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "An open wizard session (open `admin.php?page=listora-setup&rerun=1` first) - on a completed site without one every step URL shows 'Setup is already complete'"
estimated_runtime_minutes: 3
covers_card: 9910738227
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# Setup wizard renders the completion summary for unknown/stale steps

`Setup_Wizard::render()` normalizes any step outside `type/location/maps/pages/demo/done` to `done` (`includes/admin/class-setup-wizard.php:386-392`), so a stale `step=finish` bookmark renders the completion summary, not a blank card with a stray Continue button. NOTE: reaching the done screen finalizes and CLOSES the session (see `setup-wizard-rerun-session.md`), so re-open `rerun=1` before each step below.

Verified via: curl as admin inside an open session (observed for `step=finish`: `.listora-wizard__success` present, no `.listora-wizard__nav`, heading "Your directory is ready!"). Steps 2-3 source-verified only. Editor row observed.

## Steps

### 1. Unknown step renders the completion summary
- **Action**: open `admin.php?page=listora-setup&rerun=1`, then `admin.php?page=listora-setup&step=finish`.
- **Expect**: `.listora-wizard__success` with `h2[data-listora-done-heading]` "Your directory is ready!"; actions "View Your Directory →", "Add Your First Listing", "Configure Settings"; a "Go to Dashboard" button; NO `.listora-wizard__nav` and no "Continue →".

### 2. Canonical done step is identical
- **Action**: re-open `rerun=1`, then `step=done`. **Expect**: the same summary.

### 3. Real steps still render their own content (positive control)
- **Action**: `rerun=1&step=type` and `rerun=1&step=pages`.
- **Expect**: `type`: "What type of directory are you building?" with Continue; `pages`: the page list plus "Optional pages", each with the `.listora-wizard__nav` row ("← Back", "Continue →").

### 4. Role
- qa_editor: "Sorry, you are not allowed to access this page." for every step.
