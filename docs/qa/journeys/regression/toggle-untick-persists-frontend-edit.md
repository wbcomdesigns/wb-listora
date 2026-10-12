---
journey: toggle-untick-persists-frontend-edit
plugin: wb-listora
priority: high
roles: [subscriber, other-member, logged-out, administrator]
covers: [listing-submission, meta-fields, checkbox, toggle]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member-owned listing of a type with a toggle: Job > Position Filled (`_listora_position_filled`) or Restaurant > Delivery"
estimated_runtime_minutes: 6
covers_card: 10331996916
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Unticking a toggle on the frontend edit form saves

A browser leaves an unchecked box out of the request and `/submit` skips an absent field so a partial update cannot wipe it; together the member could never switch a toggle off. `buildSubmissionData()` (`src/blocks/listing-submission/view.js:599-611`) now sends `0` for an unticked single `input[type=checkbox][name^="meta_"]` and an empty value for a checkbox group with nothing ticked. A hidden `0` input was rejected because conditional-field logic finds its trigger by name and would find the hidden input first.

## Steps

### 1. Untick and submit
- **Action**: as the owner open Dashboard (`/my-listings/`) > **Edit** on your own listing with a ticked toggle (Job > Position Filled). Untick it, click **Update Listing**.
- **Expect**: request payload carries `meta_position_filled=0`; `wp post meta get <id> _listora_position_filled` is `0`; reload the edit form: the box is unticked. (REST-level, 2026-10-12: untick sent `meta_position_filled=0` -> meta `'0'`, HTTP 200.)

### 2. Tick it again
- **Action**: tick the same box, Update Listing.
- **Expect**: meta `1`, box renders ticked (REST-level verified).

### 3. Conditional fields still react
- **Action**: on a type with a field conditional on a checkbox, toggle the trigger.
- **Expect**: the dependent field shows/hides as before (the visible checkbox is found by name, not a hidden input).

### 4. API partial update is non-destructive (positive control for steps 1-2)
- **Action**: `POST /listora/v1/submit` with `listing_id` and only `title`.
- **Expect**: the toggle keeps its value (REST-level verified: meta stayed `1`).

### 5. Checkbox group
- **Action**: a multi-checkbox field (`name="meta_<key>[]"`) with the last box unticked, Update.
- **Expect**: the stored value is an empty array, not the previous selection.

### 6. Role ladder
| Actor | Expect |
|---|---|
| Owner | steps 1-5 |
| Another member | `POST /submit` with the owner's `listing_id` refused 403, toggle unchanged |
| Logged out | 401/403 |
| Admin | can edit (control) and an admin edit does not trigger a terms prompt (2.0.0) |

### 7. Mobile
At 390 the toggle row is >= 40px tall and the form has no horizontal scroll.

## Fail diagnostics
| Symptom | File |
|---|---|
| Untick does not persist | `src/blocks/listing-submission/view.js:602-609` (payload missing `meta_<key>=0`) |
| Partial update wipes the toggle | `includes/rest/class-submission-controller.php` meta save loop (absent field must be skipped) |
| Conditional field stuck | the trigger lookup by name in `view.js` (a hidden input shadowing the checkbox) |

## Not verified
Steps 1, 2 and 4 were run at the REST layer (`rest_do_request` as the owner, Job type, `meta_position_filled`), not through the form and not in a browser. Steps 3, 5, 6 (other-member/logged-out rows) and 7 were not run.

Verified via: wp-cli `rest_do_request`, source.
