---
journey: media-step-field-prompt
plugin: wb-listora
priority: normal
roles: [member]
covers: ["field-aware validation copy", "media step abandonment friction"]
prerequisites:
  - "Listing type with a required featured image (default)"
estimated_runtime_minutes: 2
---

# Media step shows an action prompt, not generic "required" copy

Custom-required submission fields (hidden inputs with
`data-listora-required`) showed the generic "This field is required." — on
the Media step, exactly where casual submitters bail. `listoraI18n` now
ships a `requiredFieldMessages` map (filterable via
`wb_listora_required_field_messages`) consulted before the generic fallback,
and `requiredFieldError` itself is now actually localized (it previously
existed only as a hardcoded JS fallback).

## Steps

### 1. Featured image prompt
- **Action**: walk the wizard to the Media step (type, title/category/
  description, address) and click Continue without choosing a featured
  image.
- **Expect**: the field error reads "Add a featured photo to continue."
  (NOT "This field is required."), the field wrapper gets `is-invalid`, and
  focus moves to the upload trigger.

### 2. Recovery
- **Action**: set a featured image.
- **Expect**: error clears on change; Continue advances.

### 3. Filter override
- **Action**: `add_filter( 'wb_listora_required_field_messages', ... )`
  changing the featured_image copy via eval.
- **Expect**: the custom copy renders instead.
