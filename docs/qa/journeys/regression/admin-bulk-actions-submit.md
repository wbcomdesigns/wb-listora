---
journey: admin-bulk-actions-submit
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [reviews-admin, claims-admin, submit-lock]
prerequisites:
  - "At least one pending review and one claim"
estimated_runtime_minutes: 4
covers_card: null
---

# Bulk Apply and Filter buttons actually submit

Regression sentinel for `assets/js/shared/submit-lock.js`.

## Background

`submit-lock.js` disabled the button inside its own click handler. A submit button's activation behaviour returns early when the button is disabled, so the form never submitted. Bulk Apply on Reviews and Claims, and the Claims Filter button, turned into "Processing..." and posted nothing. This had been true since the April 2026 move from inline onclick to delegation. The lock now engages on the form's `submit` event.

## Steps

### 1. Reviews bulk
- **Action**: Listora → Reviews → Pending, tick one row, Bulk Actions → Reject, Apply.
- **Expect**: a POST to the page, "Bulk action applied.", and the row gone from Pending. The button shows "Processing..." only while the page is leaving.

### 2. Claims Filter
- **Action**: Listora → Claims, click Filter.
- **Expect**: the page reloads with the filter query in the URL.

### 3. Claims bulk
- **Action**: tick a claim, choose a bulk action, Apply.
- **Expect**: the success notice, and the claim's status changed.

### 4. Double-click guard still works
- **Action**: double-click Apply.
- **Expect**: exactly one POST.

### 5. Restore anything you changed.

## Fail diagnostics
- "Processing..." with no request → the lock is back in a `click` handler on a `type="submit"` button.
