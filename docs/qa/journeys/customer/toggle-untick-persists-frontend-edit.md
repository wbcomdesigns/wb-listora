---
journey: toggle-untick-persists-frontend-edit
plugin: wb-listora
priority: high
site_profile: required
roles: [owner, other, guest, admin]
covers: [listing-submission, meta-fields, checkbox, toggle, card-10331996916]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing owned by {owner} of a {type} that has a toggle field (`$TOGGLE_KEY`, e.g. Job > Position Filled) and a multi-checkbox field (`$GROUP_KEY`)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged default theme]
supersedes: []
fails_on: "pre-fix: buildSubmissionData() omitted unticked checkboxes (see src/blocks/listing-submission/view.js:599-611 history)"
---

# Unticking a toggle on the frontend edit form saves

## Why

A browser leaves an unchecked box out of the request and `/submit` skips an absent field so a partial update cannot wipe it; together a member could never switch a toggle off. `buildSubmissionData()` (`src/blocks/listing-submission/view.js:599-611`) now sends `0` for an unticked single `input[type=checkbox][name^="meta_"]` and an empty value for a checkbox group with nothing ticked. A hidden `0` input was rejected because conditional-field logic finds its trigger by name.

## Setup

- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Sign in as {owner} (`?autologin={owner}`); `$LID` = the owner's listing of {type}; `$TOGGLE_KEY` and `$GROUP_KEY` are the meta keys (no `_listora_` prefix) of the toggle and the multi-checkbox field, taken from the type's field definition.
- Record the starting values: `wp post meta get $LID _listora_$TOGGLE_KEY` and `_listora_$GROUP_KEY`.

## Steps

### 1. Untick and submit
- **Action**: as {owner} open Dashboard > Listings > **Edit** on `$LID` with the toggle ticked, untick it, click **Update Listing**.
- **Expect**: the request payload carries `meta_$TOGGLE_KEY=0`; after reload the box is unticked.
- **Read-back**: `wp post meta get $LID _listora_$TOGGLE_KEY` prints `0` (REST-level verified 2026-10-12: HTTP 200, meta `'0'`).

### 2. Tick it again
- **Action**: tick the same box, Update Listing.
- **Expect**: the box renders ticked.
- **Read-back**: meta `1` (REST-level verified).

### 3. API partial update is non-destructive (positive control for steps 1-2)
- **Action**: `POST /listora/v1/submit` as {owner} with `listing_id` and only `title`.
- **Expect**: 200; the toggle keeps its value.
- **Read-back**: meta unchanged (REST-level verified: stayed `1`).

### 4. Conditional fields still react
- **Action**: on a type with a field conditional on a checkbox, toggle the trigger.
- **Expect**: the dependent field shows and hides (the visible checkbox is found by name, not a hidden input).

### 5. Checkbox group
- **Action**: on the multi-checkbox field untick every box, Update.
- **Expect**: the stored value is an empty array, not the previous selection. Boundary: with all boxes ticked the stored array holds every option exactly once.
- **Read-back**: `wp post meta get $LID _listora_$GROUP_KEY --format=json` prints `[]`.

### 6. Retry and parallel
- **Action**: double-click **Update Listing** with the toggle unticked; then submit the same edit from two tabs.
- **Expect**: one saved result, meta `0`, no duplicate listing. Not verified live (added by consolidation).

### 7. Mobile and dark
- **Action**: repeat step 1's form at 390px in light and dark.
- **Expect**: the toggle row is >= 40px tall, contrast holds, no horizontal scroll.

## Role ladder

| Actor | Expect |
|---|---|
| {owner} | steps 1-7 |
| {other} (another member, same role) | `POST /submit` with the owner's `listing_id` refused 403, toggle unchanged |
| {guest} | 401/403 |
| {admin} | can edit (control); an admin edit does not trigger a terms prompt (2.0.0) |

## Pass criteria

1. Unticking a single toggle saves `0`; ticking saves `1`.
2. A partial API update leaves the toggle alone.
3. An all-unticked checkbox group stores an empty array.
4. Conditional fields still react.
5. Another member and a guest cannot change the owner's toggle.

## Fail diagnostics

| Symptom | File |
|---|---|
| Untick does not persist | `src/blocks/listing-submission/view.js:602-609` (payload missing `meta_<key>=0`) |
| Partial update wipes the toggle | `includes/rest/class-submission-controller.php` meta save loop (absent field must be skipped) |
| Conditional field stuck | the trigger lookup by name in `view.js` (a hidden input shadowing the checkbox) |

## Not verified

Steps 1, 2 and 3 ran at the REST layer (`rest_do_request` as the owner), not through the form or a browser. Steps 4, 5 (form), 6, 7 and the {other}/{guest} rows were not run.

## Cleanup

Restore the starting values of `$TOGGLE_KEY` and `$GROUP_KEY` on `$LID`.
