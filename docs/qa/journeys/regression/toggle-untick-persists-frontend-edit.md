---
journey: toggle-untick-persists-frontend-edit
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [listing-submission, meta-fields, checkbox, toggle]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10331996916
---

# Unticking a toggle on the frontend edit form saves

Regression sentinel for the 1.8.0 QA bounce.

## Background

A browser leaves an unchecked box out of the request, and `/submit` skips an absent field so a partial update cannot wipe it. Together the member could never switch a toggle off: untick Position Filled, Submit, and it came back ticked. `buildSubmissionData()` in `src/blocks/listing-submission/view.js` now sends `0` for an unticked single box and an empty value for a checkbox group with nothing ticked. A hidden `0` input was rejected because conditional-field logic finds its trigger by name and would find the hidden input first.

## Steps

### 1. Untick and submit
- **Action**: as a member, open Dashboard > Edit on your own listing with a ticked toggle (Job > Position Filled, or Restaurant > Delivery). Untick it, click Update Listing.
- **Expect**: `wp post meta get <id> _listora_<key>` is empty/false. Reload the edit form: the box is unticked.
- **On fail**: check the request payload carries `meta_<key>=0`.

### 2. Tick it again
- **Action**: tick the same box, Update Listing.
- **Expect**: meta is `1`, the box renders ticked.

### 3. Conditional fields still react
- **Action**: on a type with a field conditional on a checkbox, toggle the trigger.
- **Expect**: the dependent field shows/hides as before.

### 4. API partial update is still non-destructive
- **Action**: `POST /listora/v1/submit` with `listing_id` and only `title`.
- **Expect**: the toggle keeps its value.
