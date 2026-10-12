---
journey: type-editor-selected-only-saves
group: settings-setup
actor: admin
mode: browser
plugin: wb-listora
priority: high
site_profile: required
roles: [admin, editor, owner, guest]
covers: [type-editor, admin, card-10355147854]
prerequisites:
  - "Any {type} with categories and features selected"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820]
themes: [wp-admin default]
supersedes: []
fails_on: "pre-fix: collectFormData() counted the [data-listora-picker-only] checkbox (sent NaN term id; save failed 'Invalid parameter(s): features')"
---

# The picker's "Selected only" filter does not change what is saved

## Why

`collectFormData()` in `assets/js/admin/type-editor.js:883-896` collects checked boxes in `#listora-type-categories` and `#listora-type-features` with `:not([data-listora-picker-only])`. The "Selected only" view filter (`includes/admin/class-type-editor.php:202`, wired at `type-editor.js:27-30`) sits in the same container; counting it sent `NaN` as a term id and the save failed. An admin editing a type must never lose or corrupt the assignment because a view filter was on.

## Setup

- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Sign in as {admin}. `$TYPE` = a {type} slug with at least 3 categories and 3 features selected. Record `GET /listora/v1/listing-types/$TYPE` -> `categories` and `features` ids (`$CATS0`, `$FEATS0`).

## Steps

### 1. Save with the filter on
- **Action**: wp-admin > Listora > Listing Types > edit `$TYPE`. Tick **Selected only** under Features and under Categories. Click **Save Type** (`#listora-save-type`).
- **Expect**: toast "Type saved successfully."; the `PUT /listora/v1/listing-types/$TYPE` body's `features` and `categories` hold only integer term ids (no `null`/`NaN`); after reload the selection is unchanged.
- **Read-back**: `GET /listora/v1/listing-types/$TYPE` equals `$CATS0` / `$FEATS0`.

### 2. Save with the filter off (positive control)
- **Action**: untick both, Save Type.
- **Expect**: same result, same ids.

### 3. Filter does not hide the saved state
- **Action**: with "Selected only" ticked, untick one visible category and Save.
- **Expect**: exactly that id is dropped from `categories`; all others stay.
- **Read-back**: set difference of `$CATS0` and the new list is that one id.

### 4. Boundary: nothing selected
- **Action**: untick every feature and category with the filter on, Save.
- **Expect**: saved as empty lists, not `[NaN]`, and the toast is a success. Not verified live (added by consolidation).

### 5. Large data and search
- **Action**: on a site with hundreds of terms (check `wp term list listora_listing_cat --format=count`) type in the picker search box.
- **Expect**: the list filters, the count badge updates, and saving sends only the checked ids.

### 6. Retry
- **Action**: double-click **Save Type**.
- **Expect**: one request outcome, no duplicate toast error. Not verified live.

## Role ladder

| Actor | Expect |
|---|---|
| {admin} | steps 1-6 |
| {editor}, {owner} | Listing Types screen not available (no `manage_options`); `PUT /listora/v1/listing-types/$TYPE` 403 |
| {guest} | 401/403 |

## Pass criteria

1. Saving with "Selected only" on or off sends identical integer id lists.
2. Unticking one id drops exactly that id.
3. An empty selection saves as empty lists.
4. Non-admins are refused.

## Fail diagnostics

| Symptom | File |
|---|---|
| "Invalid parameter(s): features" | collectors in `assets/js/admin/type-editor.js:886` and `:892` lost `:not([data-listora-picker-only])` |
| Filter not rendered | `includes/admin/class-type-editor.php:185-202` |

## Not verified

Not run in a browser on 2026-10-12: selectors and line numbers were confirmed against the current files only; the REST authorisation rows of the ladder and steps 4-6 are unrun.

## Cleanup

Restore `$CATS0` and `$FEATS0` on `$TYPE` if any step changed them.
