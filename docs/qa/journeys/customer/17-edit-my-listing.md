---
journey: edit-my-listing
plugin: wb-listora
priority: normal
roles: [subscriber, contributor]
covers: [submission, edit-listing, submit-update, edit-permission-gate]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member (OWNER) who owns a published listing with a description, and one draft: QA-J1-<n> fixtures, type business"
  - "A second member (OTHER), the editor qa_editor and the admin"
estimated_runtime_minutes: 6
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Owner edits an existing listing (PUT /submit/{id})

Submit-new is `02-submit-a-listing-wizard-end-to-end.md`. This journey covers the edit-existing path: the dashboard sends `?tab=listings&action=edit&id=N` (or the submission page takes `?edit=N`), the form prefills, and the save is `PUT /listora/v1/submit/{id}`.

Verified via: curl/DOM for the prefilled form and the labels, REST (`rest_do_request`) per role for the save. The in-browser save click was not driven.

## Steps

### 1. Open the edit form prefilled
- **Action**: as OWNER, `$DASH/?tab=listings` and click "Edit" on the row, or open `$DASH/?tab=listings&action=edit&id=<id>`.
- **Expect**: heading "Edit Listing" with a "Back to listings" button; the form (`data-wp-on--submit="actions.handleSubmission"`) is prefilled: `input#listora-title[name="title"]` has the listing title as its value, type, category, fields, gallery and location are filled; it is NOT a blank new-listing form. The standalone page `<submission page>?edit=<id>` behaves the same.
- **Expect (button label)**: a published or pending listing ends with "Update Listing"; a DRAFT ends with "Submit Listing" (2.0.0), and its success heading is "Listing Submitted!" (a published edit shows "Listing Updated!").
- **POSITIVE CONTROL** for step 1b below: this step as OWNER shows the title value, so an absent value for another user is meaningful.

### 1b. Someone who cannot edit it gets no data and no edit form
- **Action**: as OTHER, open `$DASH/?tab=listings&action=edit&id=<OWNER's id>`; then logged out.
- **Expect (OTHER)**: no title value and no listing data anywhere in the HTML (verified). KNOWN GAP: the panel still reads "Edit Listing" above a blank NEW-listing wizard (steps Type, Basic Info, Details, Media, Plan, Preview), and submitting it would create a different listing. See PRODUCT-BUGS in the refresh report; once fixed, expect a clear "cannot edit this listing" message instead.
- **Expect (logged out)**: "My Dashboard" with "Please log in to view your dashboard." and a Log In link.
- **Editor / admin (control)**: the form prefills exactly as for the owner and, for them, shows no Terms checkbox.

### 2. Change a field and save
- **Action**: as OWNER change the title to `QA-J1 edited` and a custom field, click "Update Listing".
- **Expect**: `PUT /listora/v1/submit/<id>` (sent as POST with `X-HTTP-Method-Override: PUT`) returns `200 {id, status:"publish", url, message:"Your listing has been updated.", submitted:false, ...}`; the permalink shows the new title; `wb_listora_after_update_listing` fires. A published listing stays `publish` (observed on a site with manual moderation: edits do not re-queue it).
- **Role ladder (REST)**: OWNER 200; OTHER `403 listora_forbidden`; logged out `401 listora_unauthorized` (both with "You do not have permission to perform this action."); editor 200 and admin 200 (control). The edit-permission rule is `wb_listora_user_can_edit_listing()`.

### 3. Submitting a draft from the dashboard
- **Action**: OWNER edits the draft fixture and submits.
- **Expect**: with no plan or credits configured the server answers `400 listora_plan_required` ("Please select a listing plan to continue.") on the QA site, which has plans; on a free directory the outcome heading is "Listing Submitted!" with the real result (live, waiting for review, waiting for credits), never "Listing Updated!".

### 4. Validation
- **Action (browser)**: clear the Listing Title (`required`) and a required custom field, click Continue/Update.
- **Expect**: the field shows "This field is required." (`requiredFieldError`) and the step does not advance.
- **Action (REST)**: `PUT /submit/<id>` with `title: ""`.
- **Observed gap (PRODUCT-BUG)**: `200 "Your listing has been updated."` and, on a listing that has a description, the title is saved EMPTY. A title that is a URL is refused (`400 listora_title_is_url`). Once fixed, expect a 400 with `field: "title"` and an unchanged title.

### 5. 390 viewport
- **Expect**: no horizontal page scroll; Back to listings, Continue and Update Listing buttons >= 40px tall.

## Pass criteria

1. The owner's edit form is prefilled; a draft says "Submit Listing", a live listing "Update Listing".
2. Save returns 200 and the change is visible on the permalink; OTHER 403, logged out 401.
3. No listing data leaks to someone who cannot edit it.

## Fail diagnostics

| Symptom | Likely cause | File:line |
|---|---|---|
| Blank form for the owner | edit detection | `blocks/listing-submission/render.php:83` |
| Non-owner sees "Edit Listing" over a blank wizard | param silently ignored | `blocks/listing-submission/render.php:116`, `templates/blocks/user-dashboard/tab-listings.php:77` |
| Wrong button label | draft check | `templates/blocks/listing-submission/navigation.php:38` |
| 403 for the owner | permission rule | `includes/rest/class-submission-controller.php:205` |
| Empty title saved | no required check on update | `includes/rest/class-submission-controller.php:1143` |
| Terms asked of admin | terms default | `includes/rest/class-submission-controller.php:1120` |

## Not verified

- A real browser save and the "Listing Updated!" screen; dark mode and the unknown theme.
- Moderation re-queue when the type's moderation is set to manual: the QA site edit kept `publish`; test a per-type "manual" setting separately.
