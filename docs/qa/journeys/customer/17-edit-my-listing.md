---
journey: edit-my-listing
plugin: wb-listora
priority: high
roles: [subscriber, contributor, other-member, editor, admin, guest]
covers: [submission, edit-listing, submit-update, edit-permission-gate, media-kept-on-edit, ownership-rule, bc-10351400338, bc-10393550151, bc-10331918084, bc-10331922348]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "{owner} owns a published listing {listing} with a description and one draft; type {type}. Seed an admin-uploaded featured image and gallery on {listing} (QA-J1- prefix fixtures)"
  - "{other}, {editor} and {admin} exist"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:regression/listing-media-kept-on-others-save.md (steps 2-3 only; steps 1 and 5 live in admin/listing-media-fields-and-retention.md)"
fails_on: [5d1e013d, 771baeeb]
---

# An owner edits an existing listing (PUT /submit/{id}) and nothing they did not touch is lost

## Why
If the edit form opens blank, saves a different listing, drops photos an admin added, or wipes a title, the owner loses live data. Submit-new is `customer/02-submit-a-listing-wizard-end-to-end.md`; this journey covers edit-existing.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `$DASH` = `wb_listora_get_dashboard_url()`. Edit URL: `$DASH?tab=listings&action=edit&id=<id>` or `<submission page>?edit=<id>`.
- Per-role REST: `rest_do_request` or cookie + `X-WP-Nonce` from `admin-ajax.php?action=rest-nonce`.

## Steps

### 1. Open the edit form prefilled (owner)
- **Action**: as {owner} `$DASH?tab=listings`, click "Edit" on the {listing} row (or open the URL).
- **Expect**: heading "Edit Listing" with a "Back to listings" button; the form (`data-wp-on--submit="actions.handleSubmission"`) is prefilled: `input#listora-title[name="title"]` has the listing title, type, category, fields, gallery and location filled; NOT a blank new-listing form. A published or pending listing ends with "Update Listing"; a DRAFT ends with "Submit Listing" (2.0.0) with success heading "Listing Submitted!" (a published edit shows "Listing Updated!"). The standalone `<submission page>?edit=<id>` behaves the same.
- **Read-back**: positive control for step 2.

### 2. Someone who cannot edit it gets no data and no edit form
- **Action**: as {other} open `$DASH?tab=listings&action=edit&id=<{owner}'s id>`; then as {guest}.
- **Expect ({other})**: no title value and no listing data anywhere in the HTML, AND a clear "cannot edit this listing" message instead of any form. `expected-fail: PRODUCT-BUGS "non-owner sees Edit Listing over a blank new-listing wizard"` (today the panel reads "Edit Listing" above a blank NEW wizard whose submit would create a different listing).
- **Expect ({guest})**: "My Dashboard" with "Please log in to view your dashboard." and a Log In link.
- **Expect ({editor}, {admin})**: the form prefills exactly as for the owner and shows no Terms checkbox.
- **On fail**: `blocks/listing-submission/render.php:116`, `templates/blocks/user-dashboard/tab-listings.php:77`.

### 3. Staff reach another member's listing through the detail-page Edit button (card 10351400338)
- **Action**: as {admin} (then {editor}) open {listing} on the frontend, click owner-toolbar "Edit" in `.listora-detail__actions` (follow the link, do not type the URL).
- **Expect**: lands on the edit form with `input#listora-title` = {listing}'s title, heading "Edit Listing", button "Update Listing"; not a blank "Add Listing".
- **Read-back**: save a changed title; `post_author` still {owner}, `post_title` new, `COUNT(*)` of `listora_listing` unchanged. Negative control: as {other} (no `edit_others_posts`) the toolbar has no Edit button.
- **fails_on**: 5d1e013d (parent of bdfe96b5). Not verified live.

### 4. A demo or imported listing with only a city saves (card 10393550151)
- **Action**: pick a demo/importer listing whose street address meta is empty and city set (`wp post meta get <id> _listora_address`); open its edit form, change only the title, click "Update Listing".
- **Expect**: `200`, "Listing Updated!"; no street-address required error; no Terms of Service checkbox or prompt.
- **Read-back**: `post_title` is new; city meta unchanged.
- **fails_on**: 771baeeb. Not verified live.

### 5. Change a field and save
- **Action**: as {owner} change the title to `QA-J1 edited` and a custom field, click "Update Listing".
- **Expect**: `PUT /listora/v1/submit/<id>` (sent as POST with `X-HTTP-Method-Override: PUT`) returns `200 {id, status:"publish", url, message:"Your listing has been updated.", submitted:false, ...}`; the permalink shows the new title; `wb_listora_after_update_listing` fires; a published listing stays `publish` (edits do not re-queue it under manual moderation; per-type "manual" moderation is a separate case).
- **Read-back**: `post_title` and the field meta equal what was sent.

### 6. Edit keeps media the editor did not upload
- **Action**: as {owner} open `$DASH?tab=listings`, Edit {listing} (admin-uploaded featured image and gallery), change only the description, click "Update Listing".
- **Expect**: all admin photos still present in the gallery and as featured image.
- **Read-back**: `_listora_gallery` and `_thumbnail_id` byte-identical before/after (`wp post meta get`).
- **On fail**: `includes/rest/class-submission-controller.php:1072-1088` (`update_listing()` lost `wb_listora_keep_listing_media()`).

### 7. The ownership rule still holds (negative control for step 6)
- **Action**: as {owner} `PUT /listora/v1/submit/<id>` with `gallery` / `featured_image` set to an admin attachment NOT on this listing.
- **Expect**: `200`; the foreign id is dropped: gallery and thumbnail unchanged.
- **On fail**: `includes/media-helpers.php:31` (`wb_listora_user_can_attach()`).

### 8. Submitting a draft from the dashboard
- **Action**: {owner} edits the draft fixture and submits.
- **Expect**: on a directory with plans and no plan chosen: `400 listora_plan_required` ("Please select a listing plan to continue."); on a free directory the outcome heading is "Listing Submitted!" with the real result (live, waiting for review, waiting for credits), never "Listing Updated!".

### 9. Validation: browser and REST
- **Action (browser)**: clear the required Listing Title and a required custom field, click Continue/Update.
- **Expect**: each field shows "This field is required." (`requiredFieldError`) and the step does not advance.
- **Action (REST)**: `PUT /submit/<id>` with `title: ""`.
- **Expect**: `400` with `field: "title"` and the stored title unchanged. `expected-fail: PRODUCT-BUGS "PUT /submit with empty title saves an empty title"` (today `200` "Your listing has been updated." and, when the listing has a description, the title is saved EMPTY). A title that is a URL is refused `400 listora_title_is_url` (works today).
- **On fail**: `class-submission-controller.php:1143`.

### 10. Role ladder (REST)
| Actor | `PUT /submit/<id>` |
|---|---|
| {owner} | `200` |
| {other} | `403 listora_forbidden` ("You do not have permission to perform this action.") |
| {guest} | `401 listora_unauthorized` |
| {editor}, {admin} | `200` (control); rule is `wb_listora_user_can_edit_listing()` |
- **Lifecycle**: repeat step 5 on a pending, expired and trashed listing and on one whose author was deleted; trashed and deleted-author must not open an edit form for {owner}. Not verified live.

### 11. 390 viewport
- **Expect**: no horizontal page scroll; Back to listings, Continue and Update Listing >= 40px tall.

## Role ladder
See step 10.

## Pass criteria
1. The owner's edit form is prefilled; a draft says "Submit Listing", a live listing "Update Listing".
2. Save returns 200 and the change is visible on the permalink; {other} 403, {guest} 401; staff edit the owner's listing without creating a second one.
3. No listing data leaks to someone who cannot edit it, and they get an explicit refusal (expected-fail until fixed).
4. Photos the editor did not upload survive a save; foreign attachments are dropped.
5. An empty title is refused with 400 (expected-fail until fixed).

## Fail diagnostics
| Symptom | Likely cause | File:line |
|---|---|---|
| Blank form for the owner | edit detection | `blocks/listing-submission/render.php:83` |
| Non-owner sees "Edit Listing" over a blank wizard | param silently ignored | `render.php:116`, `tab-listings.php:77` |
| Wrong button label | draft check | `templates/blocks/listing-submission/navigation.php:38` |
| 403 for the owner | permission rule | `class-submission-controller.php:205` |
| Empty title saved | no required check on update | `class-submission-controller.php:1143` |
| Terms asked of admin | terms default | `class-submission-controller.php:1120` |
| Member edit drops photos | keep-media call lost | `class-submission-controller.php:1072-1088` |
| Foreign file accepted | ownership rule | `media-helpers.php:31` |

## Not verified
- A real browser save and the "Listing Updated!" screen; dark mode and the unknown theme; steps 3, 4, 6, 7, 10 lifecycle rows. The in-browser save click was not driven; REST per role was.
- Moderation re-queue under per-type "manual" moderation.

## Cleanup
Delete `QA-J1` edits (restore the title), remove seeded media fixtures.
