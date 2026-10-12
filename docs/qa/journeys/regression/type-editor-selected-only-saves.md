---
journey: type-editor-selected-only-saves
plugin: wb-listora
priority: high
roles: [admin, editor, member]
covers: [type-editor, admin, card-10355147854]
prerequisites:
  - "Any listing type with categories and features selected"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820]
themes: [wp-admin default]
changed: true
---

# The picker's "Selected only" filter does not change what is saved

`collectFormData()` in `assets/js/admin/type-editor.js:883-896` collects checked boxes in `#listora-type-categories` and `#listora-type-features` with `:not([data-listora-picker-only])`. The "Selected only" view filter (`<input type="checkbox" data-listora-picker-only>`, rendered by `includes/admin/class-type-editor.php:202` and wired at `type-editor.js:27-30`) sits in the same container; counting it sent `NaN` as a term id and the save failed with "Invalid parameter(s): features" (or categories).

## Steps

### 1. Save with the filter on
- **Action**: wp-admin > Listora > Listing Types > edit a type. Note the selected categories and features. Tick **Selected only** under Features and under Categories. Click **Save Type** (`#listora-save-type`).
- **Expect**: toast "Type saved successfully."; the `PUT /listora/v1/listing-types/<slug>` body's `features` and `categories` hold only integer term ids (no `null`/`NaN`); after reload the selection is unchanged.

### 2. Save with the filter off (positive control)
- **Action**: untick both, Save Type.
- **Expect**: same result, same ids.

### 3. Filter does not hide the saved state
- **Action**: with "Selected only" ticked, untick one visible category and Save.
- **Expect**: exactly that id is dropped from `categories`; all others stay.

### 4. Role ladder
| Actor | Expect |
|---|---|
| Admin | steps 1-3 |
| Editor / member | Listing Types screen not available (no `manage_options`); `PUT /listora/v1/listing-types/<slug>` 403 |
| Logged out | 401/403 |

### 5. Large data
Pickers hold every category/feature (2809 listings, hundreds of terms): search box filters the list, the count badge updates, and saving sends only the checked ids.

## Fail diagnostics
- "Invalid parameter(s): features" -> the collectors in `assets/js/admin/type-editor.js:886` and `:892` lost `:not([data-listora-picker-only])`.
- Filter not rendered -> `includes/admin/class-type-editor.php:185-202`.

## Not verified
Not run in a browser on 2026-10-12. Selectors and line numbers were confirmed against the current files only; the REST authorisation rows of the ladder are unrun.

Verified via: source.
