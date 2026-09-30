---
journey: type-editor-selected-only-saves
plugin: wb-listora
priority: high
roles: [admin]
covers: [type-editor, admin, card-10355147854]
prerequisites:
  - "Any listing type"
estimated_runtime_minutes: 3
---

# The picker's "Selected only" filter does not change what is saved

Regression sentinel for `assets/js/admin/type-editor.js` `collectFormData()`.

## Background

Card 10355147854. The Features and Categories pickers (new in 1.9.0) render a "Selected only" view filter inside the same container as the term checkboxes. The save handler collected every checked box in the container, so the filter was sent as a term id (NaN) and the save failed with "Invalid parameter(s): features" (or categories).

## Steps

### 1. Save with the filter on
Listing Types > edit a type. Note the selected features and categories. Tick **Selected only** under Features and under Categories. Save.
- **Expect**: "Type saved successfully."; the PUT body's `features` and `categories` hold only term ids; the selection is unchanged after reload.

### 2. Save with the filter off
Untick both and save again.
- **Expect**: same result.

## Fail diagnostics
- "Invalid parameter(s): features" -> the collectors in `type-editor.js` lost `:not([data-listora-picker-only])`.
