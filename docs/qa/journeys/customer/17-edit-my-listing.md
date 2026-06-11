---
journey: edit-my-listing
plugin: wb-listora
priority: normal
roles: [subscriber, contributor]
covers: [submission, edit-listing, submit-update]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A logged-in user who owns >=1 listing (capture LISTING_ID + edit URL)"
estimated_runtime_minutes: 5
covers_card: null
---

# Owner edits an existing listing (PUT /submit/{id})

Submit-new is covered by `02-submit-a-listing-wizard-end-to-end.md`; the
edit-existing path (`PUT /listora/v1/submit/{id}`) was not. The dashboard sends
`?action=edit&id=N` and the submission block prefills from the listing.

## Steps

### 1. Open the edit form prefilled
- **Action**: from the dashboard Listings tab click Edit on LISTING_ID → submission page with `?action=edit&id=LISTING_ID`.
- **Expect**: the wizard prefills title, type, fields, gallery, location from the existing listing (not a blank form). This is the regression guarded against the blank-edit bug (5a4d0f9) — re-assert it.

### 2. Change a field + save
- **Action**: edit the title + a custom field, submit → `PUT /submit/{id}`.
- **Expect**: 200; `wb_listora_after_update_listing` fires; the listing reflects the change on its public page and in `GET /listings/{id}/detail`. Status handling matches the site's moderation setting (re-pending on edit if configured).

### 3. Ownership gate
- **Action**: as a non-owner, `PUT /submit/LISTING_ID`.
- **Expect**: 403 — only the owner (or a cap-holder) may edit.

### 4. Validation
- **Action**: submit with a required field cleared.
- **Expect**: 400 with a field-level error; the listing is not corrupted.
