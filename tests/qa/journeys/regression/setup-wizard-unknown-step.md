---
journey: setup-wizard-unknown-step
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [setup-wizard, wizard-step-normalization]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 3
covers_card: 9910738227
---

# Setup wizard renders the completion summary for unknown/stale steps

Regression sentinel: the wizard's render switch had no `default` case, so any
step value outside the known set (`type`/`location`/`maps`/`pages`/`demo`/
`done`) — e.g. a stale `step=finish` bookmark or a hand-edited URL — fell
through with no output, leaving a blank card with a stray "Continue" button.

Fix: `Setup_Wizard::render()` normalizes any unrecognized step to `done` before
computing indices, so the completion summary renders and the Continue/nav row
is suppressed.

## Steps

### 1. Unknown step renders the completion summary
- **Action**: `/wp-admin/admin.php?page=listora-setup&step=finish`.
- **Expect**: `.listora-wizard__success` block present; heading "Your directory
  is ready!"; action buttons "View Your Directory", "Add Your First Listing",
  "Configure Settings"; a "Go to Dashboard" button. NO stray "Continue →"
  button in a `.listora-wizard__nav`.

### 2. Canonical done step is unchanged
- **Action**: `step=done`.
- **Expect**: identical completion summary (the normalization only affects
  out-of-set values).

### 3. Real steps still render their own content
- **Action**: `step=type`, `step=pages`.
- **Expect**: each renders its own form fields + the Continue/Back nav (not the
  completion summary).
